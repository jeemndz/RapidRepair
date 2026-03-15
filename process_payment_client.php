<?php
session_start();
require_once "db.php";
require_once "log_helper.php";

header("Content-Type: application/json");

function respond($ok, $message, $extra = [])
{
    echo json_encode(array_merge(["ok" => $ok, "message" => $message], $extra));
    exit;
}

if (!isset($_SESSION["user_id"])) {
    respond(false, "Unauthorized.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Invalid request.");
}

$user_id = (int) ($_SESSION["user_id"] ?? 0);

$service_id = (int) ($_POST["service_id"] ?? 0);
$amountPaid_raw = $_POST["amountPaid"] ?? "";
$paymentMethod = trim($_POST["paymentMethod"] ?? "");
$gcashRef = trim($_POST["gcashReferenceNumber"] ?? "");
$remarks = trim($_POST["remarks"] ?? "");

if ($service_id <= 0) {
    respond(false, "Invalid service id.");
}

if ($amountPaid_raw === "" || !is_numeric($amountPaid_raw)) {
    respond(false, "Please enter a valid Amount Paid.");
}

$amountPaid = round((float) $amountPaid_raw, 2);
if ($amountPaid < 0) {
    respond(false, "Amount paid cannot be negative.");
}

$allowedMethods = ['Cash', 'GCash'];
if (!in_array($paymentMethod, $allowedMethods, true)) {
    respond(false, "Invalid payment method.");
}

if ($paymentMethod === 'GCash' && $gcashRef === '') {
    respond(false, "GCash reference number is required.");
}

if ($paymentMethod === 'Cash') {
    $gcashRef = null;
}

/* =========================
   GET CLIENT_ID of THIS USER
========================= */
$cq = $conn->prepare("SELECT client_id FROM client_information WHERE user_id = ? LIMIT 1");
$cq->bind_param("i", $user_id);
$cq->execute();
$client = $cq->get_result()->fetch_assoc();
$cq->close();

if (!$client) {
    respond(false, "Client record not found.");
}

$client_id = (int) $client["client_id"];

/* =========================
   FETCH SERVICE (SECURE)
========================= */
$stmt = $conn->prepare("
    SELECT service_id, client_id, appointment_id, totalCost, status
    FROM services
    WHERE service_id = ? AND client_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $service_id, $client_id);
$stmt->execute();
$svc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$svc) {
    respond(false, "Invoice not found for this user.");
}

$totalCost = round((float) $svc["totalCost"], 2);
$appointment_id = (int) $svc["appointment_id"];
$currentStatus = strtolower(trim($svc["status"] ?? ""));

if ($currentStatus === "paid") {
    respond(false, "This service is already marked as Paid.");
}

if ($amountPaid > $totalCost) {
    respond(false, "Amount paid cannot exceed ₱" . number_format($totalCost, 2));
}

$balance = round($totalCost - $amountPaid, 2);

/* =========================
   STATUS (Paid / Partial / Unpaid)
========================= */
if ($balance <= 0.00 && $amountPaid > 0) {
    $paymentStatus = "Paid";
    $balance = 0.00;
} elseif ($amountPaid > 0) {
    $paymentStatus = "Partial";
} else {
    $paymentStatus = "Unpaid";
}

/* =========================
   INVOICE REFERENCE (SERVER GENERATED)
   Stored in payments.referenceNumber
========================= */
$invoiceReference = "RR-" . str_pad($service_id, 5, "0", STR_PAD_LEFT);

/* =========================
   DUPLICATE GCASH REF CHECK
========================= */
if ($gcashRef !== null && $gcashRef !== "") {
    $chk = $conn->prepare("SELECT payment_id FROM payments WHERE gcashReferenceNumber = ? LIMIT 1");
    $chk->bind_param("s", $gcashRef);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $chk->close();
        respond(false, "GCash reference number already exists.");
    }
    $chk->close();
}

try {
    $conn->begin_transaction();

    // Insert Payment
    $insert = $conn->prepare("
        INSERT INTO payments
        (client_id, appointment_id, service_id,
         paymentAmount, amountPaid, balance,
         paymentMethod, paymentDate, paymentStatus,
         remarks, referenceNumber, gcashReferenceNumber)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)
    ");

    $insert->bind_param(
        "iiidddsssss",
        $client_id,
        $appointment_id,
        $service_id,
        $totalCost,
        $amountPaid,
        $balance,
        $paymentMethod,
        $paymentStatus,
        $remarks,
        $invoiceReference,
        $gcashRef
    );

    if (!$insert->execute()) {
        throw new Exception("Failed to insert payment.");
    }

    $payment_id = (int) $insert->insert_id;
    $insert->close();

    // Update services status only when fully paid
    if ($paymentStatus === "Paid") {
        $updService = $conn->prepare("UPDATE services SET status='Paid' WHERE service_id=? AND client_id=?");
        $updService->bind_param("ii", $service_id, $client_id);
        if (!$updService->execute()) {
            throw new Exception("Failed to update service status.");
        }
        $updService->close();

        // Update appointment status
        $updAppt = $conn->prepare("UPDATE appointment SET status='Completed' WHERE appointment_id=?");
        $updAppt->bind_param("i", $appointment_id);
        if (!$updAppt->execute()) {
            throw new Exception("Failed to update appointment status.");
        }
        $updAppt->close();
    }

    // Logs
    log_event(
        $conn,
        "Client Payment",
        "payments",
        $payment_id,
        "Invoice {$invoiceReference} | ₱" . number_format($amountPaid, 2) .
        " via {$paymentMethod}" . ($gcashRef ? " | GCashRef: {$gcashRef}" : "")
    );

    if ($paymentStatus === "Paid") {
        log_event($conn, "Update Service Status", "services", $service_id, "Service marked as Paid");
        log_event($conn, "Update Appointment Status", "appointment", $appointment_id, "Appointment marked as Completed");
    }

    $conn->commit();

    respond(true, "Payment recorded successfully.", [
        "payment_id" => $payment_id,
        "invoiceReference" => $invoiceReference,
        "paymentStatus" => $paymentStatus
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    respond(false, "Payment failed: " . $e->getMessage());
}
