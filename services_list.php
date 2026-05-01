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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    echo json_encode([
        'status' => 'ok',
        'message' => 'Service API is running.',
        'usage' => 'Add ?tenantID=1 to fetch services.'
    ]);
    exit;
}

$tenantID = isset($_GET['tenantID']) && is_numeric($_GET['tenantID']) ? (int) $_GET['tenantID'] : 0;
$includeAllOnEmpty = isset($_GET['includeAllOnEmpty']) && $_GET['includeAllOnEmpty'] === '1';

function getServices(mysqli $conn, ?int $tenantID = null): array
{
    $services = [];

    if ($tenantID !== null && $tenantID > 0) {
        $sql = "
            SELECT
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
            WHERE tenantID = ?
              AND status = 'Active'
            ORDER BY
                CASE service_type
                    WHEN 'Main' THEN 1
                    WHEN 'Sub' THEN 2
                    ELSE 3
                END,
                parent_service_id ASC,
                service_name ASC
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $tenantID);
    } else {
        $sql = "
            SELECT
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
            ORDER BY
                tenantID ASC,
                CASE service_type
                    WHEN 'Main' THEN 1
                    WHEN 'Sub' THEN 2
                    ELSE 3
                END,
                parent_service_id ASC,
                service_name ASC
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return [];
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row['service_id'] = (int) $row['service_id'];
        $row['tenantID'] = (int) $row['tenantID'];
        $row['parent_service_id'] = $row['parent_service_id'] !== null ? (int) $row['parent_service_id'] : null;
        $row['service_type'] = $row['service_type'] ?: 'Main';
        $row['service_name'] = $row['service_name'] ?: 'Unnamed Service';
        $row['description'] = $row['description'] ?: '';
        $row['price'] = (float) $row['price'];
        $row['duration_minutes'] = $row['duration_minutes'] !== null ? (int) $row['duration_minutes'] : 0;
        $row['category'] = $row['category'] ?: 'Other';
        $row['status'] = $row['status'] ?: 'Active';

        $services[] = $row;
    }

    $stmt->close();

    return $services;
}

$services = [];

if ($tenantID > 0) {
    $services = getServices($conn, $tenantID);
}

$fallbackUsed = false;

if (($tenantID <= 0 || empty($services)) && $includeAllOnEmpty) {
    $services = getServices($conn, null);
    $fallbackUsed = true;
}

$conn->close();

echo json_encode([
    'status' => 'success',
    'tenantID' => $tenantID,
    'fallbackUsed' => $fallbackUsed,
    'count' => count($services),
    'services' => $services
]);