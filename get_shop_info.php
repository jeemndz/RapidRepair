<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

function respond($statusCode, array $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$tenantID = isset($_GET['tenantID']) ? (int) $_GET['tenantID'] : 0;

if ($tenantID <= 0) {
    respond(400, [
        'success' => false,
        'message' => 'Missing tenantID.',
    ]);
}

$stmt = $conn->prepare("
    SELECT tenantID, shopName, shopAddress, contactNumber
    FROM owners
    WHERE tenantID = ?
    LIMIT 1
");

if (!$stmt) {
    respond(500, [
        'success' => false,
        'message' => 'Failed to prepare query.',
    ]);
}

$stmt->bind_param('i', $tenantID);
$stmt->execute();
$result = $stmt->get_result();
$shop = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$shop) {
    respond(404, [
        'success' => false,
        'message' => 'Shop not found.',
    ]);
}

respond(200, [
    'success' => true,
    'tenantID' => (int) $shop['tenantID'],
    'shopName' => $shop['shopName'] ?? '',
    'shopAddress' => $shop['shopAddress'] ?? '',
    'contactNumber' => $shop['contactNumber'] ?? '',
]);
?>