<?php
session_start();
require_once "db.php";
require_once "log_helper.php";

/* =========================
   ✅ STAFF + ADMIN SAFE REDIRECT
   - staff -> services.php
   - admin -> servicesadmin.php
   - also supports return_to hidden field from the form
========================= */
$allowedReturn = ['services.php', 'servicesadmin.php'];

$returnTo = $_POST['return_to'] ?? '';
$returnTo = basename((string)$returnTo);

if (!in_array($returnTo, $allowedReturn, true)) {
    $role = strtolower(trim($_SESSION['role'] ?? 'staff'));
    $returnTo = ($role === 'admin') ? 'servicesadmin.php' : 'services.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$returnTo}?err=" . urlencode("Invalid request."));
    exit;
}

/* =========================
   SANITIZE INPUTS
========================= */
$service_id      = (int)($_POST['service_id'] ?? 0);
$client_id       = (int)($_POST['client_id'] ?? 0);
$appointment_id  = (int)($_POST['appointment_id'] ?? 0);
$amountPaid_raw  = $_POST['amountPaid'] ?? '';
$paymentMethod   = trim($_POST['paymentMethod'] ?? '');
$remarks         = trim($_POST['remarks'] ?? '');
$referenceNumber = trim($_POST['referenceNumber'] ?? '');

/* =========================
   BASIC VALIDATION
========================= */
if ($service_id <= 0 || $client_id <= 0 || $appointment_id <= 0) {
    $_SESSION['payment_error'] = "Invalid payment request.";
    header("Location: {$returnTo}?err=" . urlencode("Invalid payment request."));
    exit;
}

if ($amountPaid_raw === '' || !is_numeric($amountPaid_raw)) {
    $_SESSION['payment_error'] = "Please enter a valid amount paid.";
    header("Location: {$returnTo}?err=" . urlencode("Please enter a valid amount paid."));
    exit;
}

$amountPaid = (float)$amountPaid_raw;

if ($amountPaid < 0) {
    $_SESSION['payment_error'] = "Amount paid cannot be negative.";
    header("Location: {$returnTo}?err=" . urlencode("Amount paid cannot be negative."));
    exit;
}

$allowedMethods = ['Cash', 'GCash'];
if (!in_array($paymentMethod, $allowedMethods, true)) {
    $_SESSION['payment_error'] = "Invalid payment method selected.";
    header("Location: {$returnTo}?err=" . urlencode("Invalid payment method selected."));
    exit;
}

/* =========================
   FETCH TRUSTED SERVICE DATA
========================= */
$stmt = $conn->prepare("SELECT totalCost, status FROM services WHERE service_id = ? LIMIT 1");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    $stmt->close();
    $_SESSION['payment_error'] = "Service not found.";
    header("Location: {$returnTo}?err=" . urlencode("Service not found."));
    exit;
}

$service       = $res->fetch_assoc();
$totalCost     = round((float)$service['totalCost'], 2);
$currentStatus = strtolower(trim($service['status'] ?? ''));
$stmt->close();

if ($currentStatus === 'paid') {
    $_SESSION['payment_error'] = "This service is already marked as Paid.";
    header("Location: {$returnTo}?err=" . urlencode("This service is already marked as Paid."));
    exit;
}

$amountPaid = round($amountPaid, 2);

if ($amountPaid > $totalCost) {
    $_SESSION['payment_error'] = "Amount paid cannot exceed ₱" . number_format($totalCost, 2);
    header("Location: {$returnTo}?err=" . urlencode("Amount paid cannot exceed ₱" . number_format($totalCost, 2)));
    exit;
}

if ($amountPaid !== $totalCost) {
    $_SESSION['payment_error'] = "Exact payment required. Please pay ₱" . number_format($totalCost, 2);
    header("Location: {$returnTo}?err=" . urlencode("Exact payment required."));
    exit;
}

/* =========================
   PREVENT DUPLICATE REFERENCE
========================= */
if ($referenceNumber !== '') {
    $chk = $conn->prepare("SELECT payment_id FROM payments WHERE referenceNumber = ? LIMIT 1");
    $chk->bind_param("s", $referenceNumber);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows > 0) {
        $chk->close();
        $_SESSION['payment_error'] = "Reference number already exists.";
        header("Location: {$returnTo}?err=" . urlencode("Reference number already exists."));
        exit;
    }
    $chk->close();
}

/* =========================
   TRANSACTION
========================= */
try {
    $conn->begin_transaction();

    /* =========================
       INSERT PAYMENT
    ========================= */
    $paymentStatus = 'Paid';
    $balance = 0.00;

    $insert = $conn->prepare("
        INSERT INTO payments
        (client_id, appointment_id, service_id, paymentAmount, amountPaid, balance, paymentMethod,
         paymentDate, paymentStatus, remarks, referenceNumber)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
    ");

    $insert->bind_param(
        "iiidddssss",
        $client_id,
        $appointment_id,
        $service_id,
        $totalCost,
        $amountPaid,
        $balance,
        $paymentMethod,
        $paymentStatus,
        $remarks,
        $referenceNumber
    );

    if (!$insert->execute()) {
        throw new Exception("Failed to insert payment.");
    }

    $payment_id = (int)$insert->insert_id;
    $insert->close();

    /* =========================
       UPDATE SERVICE STATUS
    ========================= */
    $updService = $conn->prepare("UPDATE services SET status='Paid' WHERE service_id=?");
    $updService->bind_param("i", $service_id);

    if (!$updService->execute()) {
        throw new Exception("Failed to update service status.");
    }
    $updService->close();

    /* =========================
       UPDATE APPOINTMENT STATUS
    ========================= */
    $updAppt = $conn->prepare("UPDATE appointment SET status='Completed' WHERE appointment_id=?");
    $updAppt->bind_param("i", $appointment_id);

    if (!$updAppt->execute()) {
        throw new Exception("Failed to update appointment status.");
    }
    $updAppt->close();

    /* =========================
       ✅ SYSTEM LOGS
    ========================= */
    log_event(
        $conn,
        "Record Payment",
        "payments",
        $payment_id,
        "Payment recorded for service #{$service_id} | ₱" . number_format($amountPaid, 2) . " via {$paymentMethod}"
    );

    log_event(
        $conn,
        "Update Service Status",
        "services",
        $service_id,
        "Service marked as Paid"
    );

    log_event(
        $conn,
        "Update Appointment Status",
        "appointment",
        $appointment_id,
        "Appointment marked as Completed after payment"
    );

    $conn->commit();

    $_SESSION['payment_success'] = "Payment recorded successfully.";
    header("Location: {$returnTo}?success=" . urlencode("Payment recorded successfully."));
    exit;

} catch (Throwable $e) {

    $conn->rollback();

    $_SESSION['payment_error'] = "Payment failed: " . $e->getMessage();
    header("Location: {$returnTo}?err=" . urlencode("Payment failed: " . $e->getMessage()));
    exit;
}
