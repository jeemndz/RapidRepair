<?php
/**
 * Vehicle List API Endpoint
 * Returns vehicles for a given tenantID and optional user_id
 */

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-API-Version: 1.1');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendResponse($statusCode, $data)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeVehicle($row)
{
    return [
        'vehicle_id' => (int)($row['vehicle_id'] ?? 0),
        'tenantID' => (int)($row['tenantID'] ?? 0),
        'user_id' => (int)($row['user_id'] ?? 0),
        'brand' => (string)($row['brand'] ?? ''),
        'model' => (string)($row['model'] ?? ''),
        'year' => (string)($row['year_model'] ?? ''),
        'year_model' => (string)($row['year_model'] ?? ''),
        'fuel_type' => (string)($row['fuel_type'] ?? 'Gasoline'),
        'transmission_type' => (string)($row['transmission_type'] ?? 'Manual'),
        'engine_number' => $row['engine_number'] !== null ? (string)$row['engine_number'] : '',
        'mileage_km' => isset($row['mileage_km']) ? (int)$row['mileage_km'] : 0,
        'vin_number' => $row['vin_number'] !== null ? (string)$row['vin_number'] : '',
        'plate_number' => (string)($row['plate_number'] ?? ''),
        'color' => $row['color'] !== null ? (string)$row['color'] : '',
        'status' => (string)($row['status'] ?? 'Active'),
        'date_added' => (string)($row['date_added'] ?? ($row['created_at'] ?? '')),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

if (!isset($conn) || $conn->connect_error) {
    sendResponse(500, [
        'status' => 'error',
        'message' => 'Database connection failed',
        'error' => 'Unable to establish database connection',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    sendResponse(200, [
        'status' => 'ok',
        'message' => 'Vehicle List API is running',
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
}

$tenantID = 0;

if (isset($_GET['tenantID'])) {
    $tenantID = (int)$_GET['tenantID'];
} elseif (isset($_GET['tenantid'])) {
    $tenantID = (int)$_GET['tenantid'];
} elseif (isset($_GET['tenant_id'])) {
    $tenantID = (int)$_GET['tenant_id'];
} elseif (isset($_POST['tenantID'])) {
    $tenantID = (int)$_POST['tenantID'];
} elseif (isset($_POST['tenantid'])) {
    $tenantID = (int)$_POST['tenantid'];
} elseif (isset($_POST['tenant_id'])) {
    $tenantID = (int)$_POST['tenant_id'];
}

$user_id = 0;
if (isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
} elseif (isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
}

$includeAllOnEmpty = false;
if (isset($_GET['includeAllOnEmpty'])) {
    $includeAllOnEmpty = $_GET['includeAllOnEmpty'] === '1';
} elseif (isset($_POST['includeAllOnEmpty'])) {
    $includeAllOnEmpty = $_POST['includeAllOnEmpty'] === '1';
}

if ($tenantID <= 0) {
    sendResponse(400, [
        'status' => 'error',
        'message' => 'Invalid or missing tenantID',
    ]);
}

$vehicles = [];

$sql = "
    SELECT
        vehicle_id,
        tenantID,
        user_id,
        brand,
        model,
        year_model,
        fuel_type,
        transmission_type,
        engine_number,
        mileage_km,
        vin_number,
        plate_number,
        color,
        status,
        date_added,
        created_at,
        updated_at
    FROM vehicleinformation
    WHERE tenantID = ?
";

if ($user_id > 0) {
    $sql .= " AND user_id = ?";
}

$sql .= " AND status = 'Active'
          ORDER BY COALESCE(date_added, created_at) DESC, vehicle_id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    sendResponse(500, [
        'status' => 'error',
        'message' => 'Failed to prepare statement',
        'error' => $conn->error,
    ]);
}

if ($user_id > 0) {
    $stmt->bind_param('ii', $tenantID, $user_id);
} else {
    $stmt->bind_param('i', $tenantID);
}

if (!$stmt->execute()) {
    sendResponse(500, [
        'status' => 'error',
        'message' => 'Query execution failed',
        'error' => $stmt->error,
    ]);
}

$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $vehicles[] = normalizeVehicle($row);
}
$stmt->close();

if (empty($vehicles) && $includeAllOnEmpty) {
    $fallbackSql = "
        SELECT
            vehicle_id,
            tenantID,
            user_id,
            brand,
            model,
            year_model,
            fuel_type,
            transmission_type,
            engine_number,
            mileage_km,
            vin_number,
            plate_number,
            color,
            status,
            date_added,
            created_at,
            updated_at
        FROM vehicleinformation
        WHERE status = 'Active'
        ORDER BY COALESCE(date_added, created_at) DESC, vehicle_id DESC
        LIMIT 100
    ";

    $fallbackStmt = $conn->prepare($fallbackSql);

    if ($fallbackStmt) {
        if ($fallbackStmt->execute()) {
            $fallbackResult = $fallbackStmt->get_result();
            while ($row = $fallbackResult->fetch_assoc()) {
                $vehicles[] = normalizeVehicle($row);
            }
        }
        $fallbackStmt->close();
    }
}

$conn->close();

sendResponse(200, [
    'status' => 'success',
    'tenantID' => $tenantID,
    'user_id' => $user_id,
    'vehicleCount' => count($vehicles),
    'vehicles' => $vehicles,
    'timestamp' => date('Y-m-d H:i:s'),
]);
?>