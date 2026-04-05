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

// Try to include db.php from the same folder or parent (robust for API use)
$dbFileFound = false;
if (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
    $dbFileFound = true;
} elseif (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
    $dbFileFound = true;
}

if (!$dbFileFound) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'db.php not found.', 'dir' => __DIR__]);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection not available.']);
    exit;
}

// Health check for root GET
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    echo json_encode(['status' => 'ok', 'message' => 'Vehicle API is running.']);
    exit;
}

$tenantID = isset($_GET['tenantID']) && is_numeric($_GET['tenantID']) ? (int) $_GET['tenantID'] : 0;
$includeAllOnEmpty = isset($_GET['includeAllOnEmpty']) && $_GET['includeAllOnEmpty'] == '1';

$vehicles = [];

// Query for tenant-specific vehicles
if ($tenantID > 0) {
    $sql = "SELECT vehicle_id, tenantID, user_id, brand, model, year_model, plate_number, color, status, created_at, updated_at FROM vehicleinformation WHERE tenantID = ? AND status = 'Active' ORDER BY brand ASC, model ASC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to prepare statement: ' . $conn->error]);
        $conn->close();
        exit;
    }
    
    $stmt->bind_param('i', $tenantID);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Query execution failed: ' . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
    $stmt->close();
}

// Fallback: if tenant vehicles are empty (or tenant is not provided) and includeAllOnEmpty requested, get all active vehicles
if (($tenantID <= 0 || empty($vehicles)) && $includeAllOnEmpty) {
    $sql = "SELECT vehicle_id, tenantID, user_id, brand, model, year_model, plate_number, color, status, created_at, updated_at FROM vehicleinformation WHERE status = 'Active' ORDER BY brand ASC, model ASC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to prepare fallback statement: ' . $conn->error]);
        $conn->close();
        exit;
    }
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Fallback query execution failed: ' . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $result = $stmt->get_result();
    $vehicles = [];
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
    $stmt->close();
}

$conn->close();

echo json_encode([
    'status' => 'success',
    'tenantID' => $tenantID,
    'fallbackUsed' => ($includeAllOnEmpty && !empty($vehicles)),
    'vehicles' => $vehicles,
]);