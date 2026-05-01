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

// DB include
if (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
} elseif (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'db.php not found.']);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection not available.']);
    exit;
}

// Health check
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    echo json_encode(['status' => 'ok', 'message' => 'Service API is running.']);
    exit;
}

$tenantID = isset($_GET['tenantID']) && is_numeric($_GET['tenantID']) ? (int) $_GET['tenantID'] : 0;
$includeAllOnEmpty = isset($_GET['includeAllOnEmpty']) && $_GET['includeAllOnEmpty'] == '1';

$services = [];

function fetchServices($conn, $tenantID = null) {
    $services = [];

    if ($tenantID !== null) {
        $sql = "SELECT 
                    service_id,
                    tenantID,
                    parent_service_id,
                    service_type,
                    service_name,
                    description,
                    price,
                    duration_minutes,
                    category,
                    status,
                    created_at,
                    updated_at
                FROM services 
                WHERE tenantID = ? AND status = 'Active'
                ORDER BY service_type DESC, service_name ASC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('i', $tenantID);
    } else {
        $sql = "SELECT 
                    service_id,
                    tenantID,
                    parent_service_id,
                    service_type,
                    service_name,
                    description,
                    price,
                    duration_minutes,
                    category,
                    status,
                    created_at,
                    updated_at
                FROM services 
                WHERE status = 'Active'
                ORDER BY service_type DESC, service_name ASC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Normalize values
        $row['service_type'] = $row['service_type'] ?? 'Main';
        $row['parent_service_id'] = $row['parent_service_id'] ?? null;

        $services[] = $row;
    }

    $stmt->close();
    return $services;
}

// Fetch by tenant
if ($tenantID > 0) {
    $services = fetchServices($conn, $tenantID);
}

// Fallback
if (($tenantID <= 0 || empty($services)) && $includeAllOnEmpty) {
    $services = fetchServices($conn, null);
}

$conn->close();

echo json_encode([
    'status' => 'success',
    'tenantID' => $tenantID,
    'fallbackUsed' => ($includeAllOnEmpty && !empty($services)),
    'services' => $services,
]);