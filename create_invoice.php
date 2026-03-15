<?php
session_start();
require_once "db.php";
require_once "log_helper.php";

/* =========================
   ✅ STAFF + ADMIN SAFE REDIRECT (shared invoice creation)
   - Staff page: services.php
   - Admin page: servicesadmin.php
   - Use return_to from form, fallback by role
========================= */
$allowedReturn = ['services.php', 'servicesadmin.php'];

$returnTo = $_POST['return_to'] ?? '';
$returnTo = basename((string)$returnTo);

if (!in_array($returnTo, $allowedReturn, true)) {
    $role = strtolower(trim($_SESSION['role'] ?? ''));
    $returnTo = ($role === 'admin') ? 'servicesadmin.php' : 'services.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$returnTo}?err=" . urlencode("Invalid request."));
    exit;
}

/* =========================
   SANITIZE INPUTS
========================= */
$client_id         = (int)($_POST['client_id'] ?? 0);
$appointment_id    = (int)($_POST['appointment_id'] ?? 0);
$vehicle_id        = (int)($_POST['vehicle_id'] ?? 0);

$serviceCategory     = trim($_POST['serviceCategory'] ?? '');
$serviceDescription  = trim($_POST['serviceDescription'] ?? '');
$partsUsed           = trim($_POST['partsUsed'] ?? '');

$laborFee  = round((float)($_POST['laborFee'] ?? 0), 2);
$partsCost = round((float)($_POST['partsCost'] ?? 0), 2);
$totalCost = round((float)($_POST['totalCost'] ?? 0), 2);

$serviceDate      = $_POST['serviceDate'] ?? date("Y-m-d");
$mechanicAssigned = trim($_POST['mechanicAssigned'] ?? '');
$status           = trim($_POST['status'] ?? 'Ready for payment');

/* =========================
   BASIC VALIDATION
========================= */
if ($client_id <= 0 || $appointment_id <= 0 || $vehicle_id <= 0 || $serviceCategory === '') {
    $_SESSION['invoice_error'] = "Invalid invoice request. Please fill in required fields.";
    header("Location: {$returnTo}?err=" . urlencode("Invalid invoice request."));
    exit;
}

if ($laborFee < 0 || $partsCost < 0 || $totalCost < 0) {
    $_SESSION['invoice_error'] = "Costs cannot be negative.";
    header("Location: {$returnTo}?err=" . urlencode("Costs cannot be negative."));
    exit;
}

// (Optional) Ensure totalCost matches labor + parts (avoid tampering)
$calcTotal = round($laborFee + $partsCost, 2);
if (abs($totalCost - $calcTotal) > 0.009) {
    $totalCost = $calcTotal; // trust server-side calc
}

/* =========================
   TRANSACTION (insert service + update appointment)
========================= */
try {
    $conn->begin_transaction();

    // ✅ Insert invoice into services
    $stmt = $conn->prepare("
        INSERT INTO services
        (client_id, appointment_id, vehicle_id, serviceCategory, serviceDescription, partsUsed, laborFee, partsCost, totalCost, serviceDate, mechanicAssigned, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // IMPORTANT: correct type string = 12 params:
    // i i i s s s d d d s s s  => "iiisssdddsss"
    $stmt->bind_param(
        "iiisssdddsss",
        $client_id,
        $appointment_id,
        $vehicle_id,
        $serviceCategory,
        $serviceDescription,
        $partsUsed,
        $laborFee,
        $partsCost,
        $totalCost,
        $serviceDate,
        $mechanicAssigned,
        $status
    );

    if (!$stmt->execute()) {
        throw new Exception("Error creating invoice: " . $stmt->error);
    }

    $service_id = (int)$stmt->insert_id;
    $stmt->close();

    // ✅ Update appointment status to 'Invoiced'
    $update = $conn->prepare("UPDATE appointment SET status='Invoiced' WHERE appointment_id=?");
    $update->bind_param("i", $appointment_id);

    if (!$update->execute()) {
        throw new Exception("Failed to update appointment status.");
    }
    $update->close();

    /* =========================
       ✅ SYSTEM LOGS
    ========================= */
    log_event(
        $conn,
        "Create Invoice",
        "services",
        $service_id,
        "Invoice created for appointment #{$appointment_id} | vehicle #{$vehicle_id} | client #{$client_id} | category: {$serviceCategory} | total: ₱" . number_format($totalCost, 2)
    );

    log_event(
        $conn,
        "Update Appointment Status",
        "appointment",
        $appointment_id,
        "Appointment marked as Invoiced after invoice creation (service #{$service_id})"
    );

    $conn->commit();

    $_SESSION['invoice_success'] = "Invoice created successfully.";
    header("Location: {$returnTo}?success=" . urlencode("Invoice Created"));
    exit;

} catch (Throwable $e) {
    $conn->rollback();

    $_SESSION['invoice_error'] = $e->getMessage();
    header("Location: {$returnTo}?err=" . urlencode($e->getMessage()));
    exit;
}
