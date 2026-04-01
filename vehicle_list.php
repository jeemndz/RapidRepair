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

// Check if vehicleinformation table exists
$tableCheckSql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicleinformation' LIMIT 1";
$tableExists = $conn->query($tableCheckSql) && $conn->query($tableCheckSql)->num_rows > 0;

if (!$tableExists) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'vehicleinformation table not found in database.']);
    $conn->close();
    exit;
}


// Health check for root GET
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    echo json_encode(['status' => 'ok', 'message' => 'Vehicle API is running.', 'db' => 'connected']);
    $conn->close();
    exit;
}

$tenantID = isset($_GET['tenantID']) && is_numeric($_GET['tenantID']) ? (int) $_GET['tenantID'] : 0;
$includeAllOnEmpty = isset($_GET['includeAllOnEmpty']) && $_GET['includeAllOnEmpty'] == '1';

$vehicles = [];
$fallbackUsed = false;

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

// Fallback: if no tenant-specific vehicles and includeAllOnEmpty requested, get all active vehicles
if ((empty($vehicles) && $includeAllOnEmpty) || $tenantID <= 0) {
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
    $fallbackUsed = true;
}

$conn->close();

echo json_encode([
    'status' => 'success',
    'tenantID' => $tenantID,
    'fallbackUsed' => $fallbackUsed,
    'count' => count($vehicles),
    'vehicles' => $vehicles,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
