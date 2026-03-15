<?php
require_once "db.php";
header('Content-Type: application/json; charset=utf-8');

$client_id = (int)($_GET['client_id'] ?? 0);

if ($client_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT vehicle_id, plateNumber
    FROM vehicleinfo
    WHERE client_id = ? AND status = 'Active'
    ORDER BY plateNumber ASC
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

$stmt->close();

echo json_encode($data);
