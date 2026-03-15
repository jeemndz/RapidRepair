<?php
session_start();
require_once "db.php";
require_once "log_helper.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: vehicle.php");
    exit();
}

// You used client_id session (client side)
if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}

$client_id = (int)($_SESSION['client_id'] ?? 0);
if ($client_id <= 0) {
    header("Location: login.php");
    exit();
}

/* =========================
   SANITIZE INPUTS
========================= */
$plateNumber       = strtoupper(trim($_POST['plateNumber'] ?? ''));
$vehicleBrand      = trim($_POST['vehicleBrand'] ?? '');
$vehicleModel      = trim($_POST['vehicleModel'] ?? '');
$vehicleYear_raw   = trim($_POST['vehicleYear'] ?? '');
$engineNumber      = trim($_POST['engineNumber'] ?? '');
$fuelType          = trim($_POST['fuelType'] ?? '');
$transmissiontype  = trim($_POST['transmissiontype'] ?? '');
$color             = trim($_POST['color'] ?? '');
$mileage_raw       = trim($_POST['mileage'] ?? '');

/* =========================
   VALIDATION
========================= */
if ($plateNumber === '' || $vehicleBrand === '' || $vehicleModel === '' || $fuelType === '' || $transmissiontype === '') {
    $_SESSION['vehicle_error'] = "Please fill in all required fields.";
    header("Location: vehicle.php");
    exit();
}

/* ✅ Plate format validation */
if (!preg_match('/^[A-Z]{2,3}\s\d{3,5}$/', $plateNumber)) {
    $_SESSION['vehicle_error'] = "Invalid plate format. Use: ABC 1234 / AB 1234 / ABC 12345";
    header("Location: vehicle.php");
    exit();
}

/* ✅ Validate dropdown values */
$allowedFuel  = ['Gasoline','Diesel','Electric','Hybrid'];
$allowedTrans = ['Manual','Automatic','Hybrid','IMT','CVT','DCT'];

if (!in_array($fuelType, $allowedFuel, true)) {
    $_SESSION['vehicle_error'] = "Invalid Fuel Type.";
    header("Location: vehicle.php");
    exit();
}

if (!in_array($transmissiontype, $allowedTrans, true)) {
    $_SESSION['vehicle_error'] = "Invalid Transmission Type.";
    header("Location: vehicle.php");
    exit();
}

/* ✅ Year (NULL if blank) */
$vehicleYear = null;
if ($vehicleYear_raw !== '') {
    $y = (int)$vehicleYear_raw;
    if ($y < 1900 || $y > 2099) {
        $_SESSION['vehicle_error'] = "Invalid vehicle year.";
        header("Location: vehicle.php");
        exit();
    }
    $vehicleYear = $y;
}

/* ✅ Mileage (0 if blank) */
$mileage = 0;
if ($mileage_raw !== '') {
    if (!ctype_digit($mileage_raw)) {
        $_SESSION['vehicle_error'] = "Mileage must be a whole number.";
        header("Location: vehicle.php");
        exit();
    }
    $mileage = (int)$mileage_raw;
}

/* =========================
   PREVENT DUPLICATE PLATE
   (choose one: per client OR globally)
========================= */

/* Option A: duplicate plate for same client only */
$chk = $conn->prepare("SELECT vehicle_id FROM vehicleinfo WHERE client_id = ? AND plateNumber = ? LIMIT 1");
$chk->bind_param("is", $client_id, $plateNumber);
$chk->execute();
$chk->store_result();

if ($chk->num_rows > 0) {
    $chk->close();
    $_SESSION['vehicle_error'] = "This plate number is already registered in your account.";
    header("Location: vehicle.php");
    exit();
}
$chk->close();

/* =========================
   INSERT VEHICLE
========================= */
$stmt = $conn->prepare("
    INSERT INTO vehicleinfo
        (client_id, plateNumber, vehicleBrand, vehicleModel, vehicleYear, engineNumber, fuelType, transmissiontype, color, mileage)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

/*
Types:
client_id (i)
plateNumber (s)
vehicleBrand (s)
vehicleModel (s)
vehicleYear (i) -> can be NULL
engineNumber (s)
fuelType (s)
transmissiontype (s)
color (s)
mileage (i)
*/
$stmt->bind_param(
    "isssissssi",
    $client_id,
    $plateNumber,
    $vehicleBrand,
    $vehicleModel,
    $vehicleYear,
    $engineNumber,
    $fuelType,
    $transmissiontype,
    $color,
    $mileage
);

if (!$stmt->execute()) {
    $_SESSION['vehicle_error'] = "Error: " . $stmt->error;
    $stmt->close();
    header("Location: vehicle.php");
    exit();
}

$new_vehicle_id = (int)$stmt->insert_id;
$stmt->close();

/* =========================
   ✅ SYSTEM LOGS
========================= */
log_event(
    $conn,
    "Register Vehicle",
    "vehicleinfo",
    $new_vehicle_id,
    "Client registered vehicle: {$plateNumber} ({$vehicleBrand} {$vehicleModel})"
);

/* =========================
   REDIRECT
========================= */
$_SESSION['vehicle_success'] = "Vehicle registered successfully!";
header("Location: vehicle.php?success=1");
exit();
