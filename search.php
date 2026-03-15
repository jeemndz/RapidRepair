<?php
header('Content-Type: application/json');
session_start();
require_once "db.php";

$q = trim($_GET['q'] ?? '');
if ($q === '' || strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$results = [];
$like = "%{$q}%";

// -------------------- CLIENTS --------------------
$stmt = $conn->prepare("
    SELECT client_id, firstName, lastName, contactNumber, email
    FROM client_information
    WHERE firstName LIKE ? OR lastName LIKE ? OR contactNumber LIKE ? OR email LIKE ?
    LIMIT 5
");
$stmt->bind_param("ssss", $like, $like, $like, $like);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $results[] = [
        "title" => "Client: {$row['firstName']} {$row['lastName']}",
        "subtitle" => "{$row['contactNumber']} • {$row['email']}",
        "url" => "create_client.php?client_id=" . (int)$row['client_id']
    ];
}
$stmt->close();

// -------------------- APPOINTMENTS / BOOKINGS --------------------
$stmt = $conn->prepare("
    SELECT appointment_id, serviceType, appointmentDate, status
    FROM appointment
    WHERE appointment_id LIKE ? OR serviceType LIKE ? OR status LIKE ?
    ORDER BY appointmentDate DESC
    LIMIT 5
");
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $results[] = [
        "title" => "Booking #{$row['appointment_id']} • {$row['serviceType']}",
        "subtitle" => "{$row['appointmentDate']} • {$row['status']}",
        "url" => "bookings.php?appointment_id=" . (int)$row['appointment_id']
    ];
}
$stmt->close();

// -------------------- VEHICLES --------------------
$stmt = $conn->prepare("
    SELECT vehicle_id, plateNumber, vehicleBrand, vehicleModel
    FROM vehicleinfo
    WHERE plateNumber LIKE ? OR vehicleBrand LIKE ? OR vehicleModel LIKE ?
    LIMIT 5
");
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $results[] = [
        "title" => "Vehicle: {$row['plateNumber']}",
        "subtitle" => "{$row['vehicleBrand']} {$row['vehicleModel']}",
        "url" => "staffvehicle.php?vehicle_id=" . (int)$row['vehicle_id']
    ];
}
$stmt->close();

echo json_encode($results);
