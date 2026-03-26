<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    exit;
}

require_once __DIR__ . '/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection not available.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$data = $_POST;
$json = json_decode(file_get_contents('php://input'), true);
if (is_array($json)) {
    $data = array_merge($data, $json);
}

$tenantID = isset($data['tenantID']) && is_numeric($data['tenantID']) ? (int)$data['tenantID'] : 0;
$user_id = isset($data['user_id']) && is_numeric($data['user_id']) ? (int)$data['user_id'] : 0;
$brand = trim((string)($data['brand'] ?? ''));
$model = trim((string)($data['model'] ?? ''));
$year_model = preg_replace('/[^0-9]/', '', (string)($data['year_model'] ?? ''));
$fuel_type = trim((string)($data['fuel_type'] ?? 'Gasoline'));
$transmission_type = trim((string)($data['transmission_type'] ?? 'Manual'));
$engine_number = trim((string)($data['engine_number'] ?? ''));
$mileage_km = trim((string)($data['mileage_km'] ?? ''));
$vin_number = trim((string)($data['vin_number'] ?? ''));
$plate_number = strtoupper(trim((string)($data['plate_number'] ?? '')));
$color = trim((string)($data['color'] ?? ''));
$status = trim((string)($data['status'] ?? 'Active'));
$date_added = trim((string)($data['date_added'] ?? ''));

if (strlen($year_model) !== 4) {
    $year_model = '';
}

$validFuel = ['Gasoline', 'Diesel', 'Electric', 'Hybrid'];
$validTransmission = ['Manual', 'Automatic', 'CVT', 'DCT', 'AMT'];
$validStatus = ['Active', 'Inactive'];

if (!$tenantID || !$user_id || !$brand || !$model || !$plate_number) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'tenantID, user_id, brand, model, and plate_number are required.',
    ]);
    exit;
}

if (!in_array($fuel_type, $validFuel, true)) {
    $fuel_type = 'Gasoline';
}

if (!in_array($transmission_type, $validTransmission, true)) {
    $transmission_type = 'Manual';
}

if (!in_array($status, $validStatus, true)) {
    $status = 'Active';
}

$sql = "INSERT INTO vehicleinformation (
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
    date_added
) VALUES (
    ?,
    ?,
    ?,
    ?,
    NULLIF(?, ''),
    ?,
    ?,
    NULLIF(?, ''),
    NULLIF(?, ''),
    NULLIF(?, ''),
    ?,
    NULLIF(?, ''),
    ?,
    COALESCE(NULLIF(?, ''), NOW())
)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to prepare registration query.']);
    exit;
}

$stmt->bind_param(
    'iissssssssssss',
    $tenantID,
    $user_id,
    $brand,
    $model,
    $year_model,
    $fuel_type,
    $transmission_type,
    $engine_number,
    $mileage_km,
    $vin_number,
    $plate_number,
    $color,
    $status,
    $date_added
);

if ($stmt->execute()) {
    $vehicleId = $conn->insert_id;
    echo json_encode([
        'status' => 'success',
        'message' => 'Vehicle registered successfully!',
        'vehicle' => [
            'vehicle_id' => $vehicleId,
            'tenantID' => $tenantID,
            'user_id' => $user_id,
            'brand' => $brand,
            'model' => $model,
            'year_model' => $year_model,
            'fuel_type' => $fuel_type,
            'transmission_type' => $transmission_type,
            'engine_number' => $engine_number,
            'mileage_km' => $mileage_km,
            'vin_number' => $vin_number,
            'plate_number' => $plate_number,
            'color' => $color,
            'status' => $status,
            'date_added' => $date_added,
        ],
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Vehicle registration failed.',
    ]);
}

$stmt->close();
$conn->close();
