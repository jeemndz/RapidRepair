<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}

$client_id = $_SESSION['client_id'];

// Get POST data from Edit Modal
$vehicle_id = $_POST['vehicle_id'] ?? 0;
$plateNumber = $_POST['plateNumber'] ?? '';
$vehicleBrand = $_POST['vehicleBrand'] ?? '';
$vehicleModel = $_POST['vehicleModel'] ?? '';
$vehicleYear = $_POST['vehicleYear'] ?? null;
$engineNumber = $_POST['engineNumber'] ?? '';
$fuelType = $_POST['fuelType'] ?? '';
$transmissiontype = $_POST['transmissiontype'] ?? '';
$color = $_POST['color'] ?? '';
$mileage = $_POST['mileage'] ?? '';

if (!$vehicle_id) {
    die("Vehicle ID is missing.");
}

if (empty($plateNumber)) {
    die("Plate Number is required.");
}

// Ensure vehicleYear is numeric
$vehicleYear = is_numeric($vehicleYear) ? (int)$vehicleYear : null;

// Update the vehicle in database
$stmt = $conn->prepare("
    UPDATE vehicleinfo
    SET plateNumber=?, vehicleBrand=?, vehicleModel=?, vehicleYear=?, engineNumber=?, fuelType=?, transmissiontype=?, color=?, mileage=?
    WHERE vehicle_id=? AND client_id=?
");

$stmt->bind_param(
    "ssssssssiii",
    $plateNumber,
    $vehicleBrand,
    $vehicleModel,
    $vehicleYear,
    $engineNumber,
    $fuelType,
    $transmissiontype,
    $color,
    $mileage,
    $vehicle_id,
    $client_id
);

if ($stmt->execute()) {
    // Redirect back with success message
    header("Location: vehicle.php?success=1");
    exit();
} else {
    echo "Error updating vehicle: " . $stmt->error;
}

$stmt->close();
$conn->close();
