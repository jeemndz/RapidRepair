<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: vehicle.php");
    exit();
}

$vehicle_id = (int) $_GET['id'];
$client_id  = $_SESSION['client_id'];

$stmt = $conn->prepare("
    DELETE FROM vehicleinfo
    WHERE vehicle_id = ? AND client_id = ?
");
$stmt->bind_param("ii", $vehicle_id, $client_id);
$stmt->execute();

header("Location: vehicle.php?success=1");
exit();
