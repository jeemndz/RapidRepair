<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    exit;
}

require_once __DIR__ . '/../db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection not available.']);
    exit;
}

$tenantID = isset($_GET['tenantID']) && is_numeric($_GET['tenantID']) ? (int)$_GET['tenantID'] : 1;

$sql = "SELECT service_id, tenantID, service_name, description, price, duration_minutes, category, status, created_at, updated_at FROM services WHERE tenantID = ? AND status = 'Active' ORDER BY service_name ASC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to prepare query.']);
    exit;
}
$stmt->bind_param('i', $tenantID);
$stmt->execute();
$result = $stmt->get_result();
$services = [];
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode([
    'status' => 'success',
    'services' => $services,
]);
