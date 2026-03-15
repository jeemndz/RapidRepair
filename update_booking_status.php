<?php
session_start();
require_once "db.php";
require_once "log_helper.php";


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Invalid request");
}

$appointment_id   = (int)($_POST['appointment_id'] ?? 0);
$status           = trim($_POST['status'] ?? '');
$serviceType      = trim($_POST['serviceType'] ?? '');
$appointmentDate  = $_POST['appointmentDate'] ?? '';
$appointmentTime  = $_POST['appointmentTime'] ?? '';
$mechanicAssigned = trim($_POST['mechanicAssigned'] ?? '');
$notes            = trim($_POST['notes'] ?? '');

$allowedStatus = ['Pending','Approved','Scheduled','Ongoing','Completed','Cancelled','Rejected'];

if ($appointment_id <= 0) {
    http_response_code(400);
    exit("Invalid booking ID.");
}
if (!in_array($status, $allowedStatus, true)) {
    http_response_code(400);
    exit("Invalid status.");
}
if ($serviceType === '' || $appointmentDate === '' || $appointmentTime === '' || $mechanicAssigned === '') {
    http_response_code(400);
    exit("Please fill in required fields.");
}

// Date/time: block past date/time
$today = date("Y-m-d");
$nowDateTime = date("Y-m-d H:i");
$requestedDateTime = $appointmentDate . " " . $appointmentTime;

if ($appointmentDate < $today) {
    http_response_code(400);
    exit("Appointment date cannot be in the past.");
}
if ($requestedDateTime < $nowDateTime) {
    http_response_code(400);
    exit("Appointment time cannot be earlier than the current time.");
}

// Business hours
if ($appointmentTime < "08:00" || $appointmentTime > "19:59") {
    http_response_code(400);
    exit("Booking time must be within business hours (08:00 AM to 08:00 PM).");
}

// Get vehicle_id for this booking (for double-booking check)
$stmtV = $conn->prepare("SELECT vehicle_id FROM appointment WHERE appointment_id = ? LIMIT 1");
$stmtV->bind_param("i", $appointment_id);
$stmtV->execute();
$vehicleRow = $stmtV->get_result()->fetch_assoc();
$stmtV->close();

$vehicle_id = (int)($vehicleRow['vehicle_id'] ?? 0);
if ($vehicle_id <= 0) {
    http_response_code(400);
    exit("Vehicle not found for this booking.");
}

// Prevent double booking (same vehicle + date + time) except itself
$check = $conn->prepare("
    SELECT appointment_id
    FROM appointment
    WHERE vehicle_id = ?
      AND appointmentDate = ?
      AND appointmentTime = ?
      AND status IN ('Pending','Approved','Scheduled','Ongoing')
      AND appointment_id <> ?
    LIMIT 1
");
$check->bind_param("issi", $vehicle_id, $appointmentDate, $appointmentTime, $appointment_id);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $check->close();
    http_response_code(409);
    exit("This vehicle already has a booking on the same date and time.");
}
$check->close();

// Update booking
$upd = $conn->prepare("
    UPDATE appointment
    SET status = ?, serviceType = ?, appointmentDate = ?, appointmentTime = ?,
        mechanicAssigned = ?, notes = ?
    WHERE appointment_id = ?
    LIMIT 1
");
$upd->bind_param(
    "ssssssi",
    $status,
    $serviceType,
    $appointmentDate,
    $appointmentTime,
    $mechanicAssigned,
    $notes,
    $appointment_id
);

if (!$upd->execute()) {
    http_response_code(500);
    exit("Update failed: " . $upd->error);
}
$upd->close();

/* ================= ✅ LOG AFTER SUCCESS ================= */

log_event(
    $conn,
    "Update Booking Status",
    "appointment",
    $appointment_id,
    "Status updated to '{$status}'"
);


echo "OK";
