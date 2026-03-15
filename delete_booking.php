<?php
require_once "db.php";
session_start();

if (!isset($_GET['id'])) {
    header("Location: bookings.php");
    exit;
}

$id = intval($_GET['id']);

$sql = "DELETE FROM appointment WHERE appointment_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: bookings.php");
exit;
?>
