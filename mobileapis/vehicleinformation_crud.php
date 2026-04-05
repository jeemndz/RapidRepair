<?php
/**
 * Vehicle Information CRUD API
 * Handles list, create, update, and delete operations for vehicles
 * Database Table: vehicleinformation
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * IMPORTANT:
 * Deploy db.php on Azure in the SAME folder as this file,
 * then use a local include path like this.
 */
require_once __DIR__ . '/db.php';

function responseJson($status, $message = '', $data = null, $httpCode = 200)
{
    http_response_code($httpCode);

    $response = [
        'status' => $status,
        'message' => $message,
    ];

    if ($data !== null) {
        if (is_array($data)) {
            $isList = array_keys($data) === range(0, count($data) - 1);

            if ($isList) {
                $response['vehicles'] = $data;
            } elseif (isset($data['vehicle_id'])) {
                $response['vehicle'] = $data;
            } else {
                $response['data'] = $data;
            }
        } else {
            $response['data'] = $data;
        }
    }

    echo json_encode($response);
    exit;
}

function successResponse($message = '', $data = null, $httpCode = 200)
{
    responseJson('success', $message, $data, $httpCode);
}

function errorResponse($message, $httpCode = 400)
{
    responseJson('error', $message, null, $httpCode);
}

function getConnection()
{
    global $conn;

    if (!isset($conn) || !$conn) {
        errorResponse('Database connection is not available.', 500);
    }

    if (!$conn->ping()) {
        errorResponse('Database connection lost.', 500);
    }

    return $conn;
}

function sanitize($value)
{
    return trim((string)$value);
}

function getRequestData()
{
    $data = [];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $data = $_GET;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = $_POST;

        if (empty($data)) {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $data = $json;
            }
        }
    }

    return is_array($data) ? $data : [];
}

function validateRequired($requiredFields, $data)
{
    $missing = [];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            $missing[] = $field;
        }
    }

    return $missing;
}

function normalizeVehicle($row)
{
    return [
        'vehicle_id' => (int)$row['vehicle_id'],
        'tenantID' => (int)$row['tenantID'],
        'user_id' => (int)$row['user_id'],
        'brand' => (string)$row['brand'],
        'model' => (string)$row['model'],
        'year' => (string)$row['year_model'],
        'year_model' => (string)$row['year_model'],
        'fuel_type' => (string)$row['fuel_type'],
        'transmission_type' => (string)$row['transmission_type'],
        'engine_number' => $row['engine_number'] !== null ? (string)$row['engine_number'] : '',
        'mileage_km' => (int)$row['mileage_km'],
        'vin_number' => $row['vin_number'] !== null ? (string)$row['vin_number'] : '',
        'plate_number' => (string)$row['plate_number'],
        'color' => $row['color'] !== null ? (string)$row['color'] : '',
        'status' => (string)$row['status'],
        'date_added' => (string)$row['date_added'],
    ];
}

function fetchVehicleById($conn, $vehicle_id)
{
    $query = "SELECT * FROM vehicleinformation WHERE vehicle_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $stmt->bind_param('i', $vehicle_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $vehicle = $result->fetch_assoc();

    $stmt->close();

    if (!$vehicle) {
        errorResponse('Vehicle not found.', 404);
    }

    return normalizeVehicle($vehicle);
}

function handleListVehicles($conn, $data)
{
    $tenantID = isset($data['tenantID']) ? (int)$data['tenantID'] : 0;
    $user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;

    if ($tenantID <= 0 || $user_id <= 0) {
        errorResponse('Invalid tenantID or user_id.');
    }

    $query = "
        SELECT *
        FROM vehicleinformation
        WHERE tenantID = ? AND user_id = ?
        ORDER BY date_added DESC, vehicle_id DESC
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $stmt->bind_param('ii', $tenantID, $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $vehicles = [];

    while ($row = $result->fetch_assoc()) {
        $vehicles[] = normalizeVehicle($row);
    }

    $stmt->close();

    successResponse('Vehicles fetched successfully.', $vehicles);
}

function handleCreateVehicle($conn, $data)
{
    $required = ['tenantID', 'user_id', 'brand', 'model', 'year_model', 'plate_number'];
    $missing = validateRequired($required, $data);

    if (!empty($missing)) {
        errorResponse('Missing required fields: ' . implode(', ', $missing));
    }

    $tenantID = (int)$data['tenantID'];
    $user_id = (int)$data['user_id'];
    $brand = sanitize($data['brand']);
    $model = sanitize($data['model']);
    $year_model = sanitize($data['year_model']);
    $fuel_type = isset($data['fuel_type']) ? sanitize($data['fuel_type']) : 'Gasoline';
    $transmission_type = isset($data['transmission_type']) ? sanitize($data['transmission_type']) : 'Manual';
    $engine_number = isset($data['engine_number']) && $data['engine_number'] !== '' ? sanitize($data['engine_number']) : null;
    $mileage_km = isset($data['mileage_km']) && $data['mileage_km'] !== '' ? (int)$data['mileage_km'] : 0;
    $vin_number = isset($data['vin_number']) && $data['vin_number'] !== '' ? sanitize($data['vin_number']) : null;
    $plate_number = strtoupper(sanitize($data['plate_number']));
    $color = isset($data['color']) && $data['color'] !== '' ? sanitize($data['color']) : null;
    $status = isset($data['status']) && $data['status'] !== '' ? sanitize($data['status']) : 'Active';
    $date_added = isset($data['date_added']) && $data['date_added'] !== '' ? sanitize($data['date_added']) : date('Y-m-d H:i:s');

    if ($tenantID <= 0 || $user_id <= 0) {
        errorResponse('Invalid tenantID or user_id.');
    }

    if (!preg_match('/^\d{4}$/', $year_model)) {
        errorResponse('Year must be in YYYY format.');
    }

    $dupQuery = "SELECT vehicle_id FROM vehicleinformation WHERE plate_number = ? AND tenantID = ? AND user_id = ? LIMIT 1";
    $dupStmt = $conn->prepare($dupQuery);

    if (!$dupStmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $dupStmt->bind_param('sii', $plate_number, $tenantID, $user_id);
    $dupStmt->execute();

    $dupResult = $dupStmt->get_result();
    if ($dupResult->num_rows > 0) {
        $dupStmt->close();
        errorResponse('This plate number is already registered for this user.');
    }

    $dupStmt->close();

    $query = "
        INSERT INTO vehicleinformation
        (
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
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $stmt->bind_param(
        'iissssssisssss',
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

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        errorResponse('Failed to create vehicle: ' . $error, 500);
    }

    $vehicle_id = $conn->insert_id;
    $stmt->close();

    $vehicle = fetchVehicleById($conn, $vehicle_id);
    successResponse('Vehicle created successfully.', $vehicle, 201);
}

function handleUpdateVehicle($conn, $data)
{
    if (!isset($data['vehicle_id']) || !isset($data['tenantID']) || !isset($data['user_id'])) {
        errorResponse('Missing vehicle_id, tenantID, or user_id.');
    }

    $vehicle_id = (int)$data['vehicle_id'];
    $tenantID = (int)$data['tenantID'];
    $user_id = (int)$data['user_id'];

    if ($vehicle_id <= 0 || $tenantID <= 0 || $user_id <= 0) {
        errorResponse('Invalid vehicle_id, tenantID, or user_id.');
    }

    $verifyQuery = "
        SELECT vehicle_id
        FROM vehicleinformation
        WHERE vehicle_id = ? AND tenantID = ? AND user_id = ?
        LIMIT 1
    ";

    $verifyStmt = $conn->prepare($verifyQuery);

    if (!$verifyStmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $verifyStmt->bind_param('iii', $vehicle_id, $tenantID, $user_id);
    $verifyStmt->execute();

    $verifyResult = $verifyStmt->get_result();
    if ($verifyResult->num_rows === 0) {
        $verifyStmt->close();
        errorResponse('Vehicle not found or you do not have permission to update it.', 403);
    }

    $verifyStmt->close();

    $fieldTypes = [
        'brand' => 's',
        'model' => 's',
        'year_model' => 's',
        'fuel_type' => 's',
        'transmission_type' => 's',
        'engine_number' => 's',
        'mileage_km' => 'i',
        'vin_number' => 's',
        'plate_number' => 's',
        'color' => 's',
        'status' => 's',
    ];

    $updateFields = [];
    $updateValues = [];
    $types = '';

    foreach ($fieldTypes as $field => $type) {
        if (array_key_exists($field, $data) && $data[$field] !== '') {
            $value = $type === 'i' ? (int)$data[$field] : sanitize($data[$field]);

            if ($field === 'plate_number') {
                $value = strtoupper($value);
            }

            if ($field === 'year_model' && !preg_match('/^\d{4}$/', (string)$value)) {
                errorResponse('Year must be in YYYY format.');
            }

            $updateFields[] = "{$field} = ?";
            $updateValues[] = $value;
            $types .= $type;
        }
    }

    if (empty($updateFields)) {
        errorResponse('No updateable fields provided.');
    }

    $updateValues[] = $vehicle_id;
    $updateValues[] = $tenantID;
    $updateValues[] = $user_id;
    $types .= 'iii';

    $query = "
        UPDATE vehicleinformation
        SET " . implode(', ', $updateFields) . "
        WHERE vehicle_id = ? AND tenantID = ? AND user_id = ?
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $stmt->bind_param($types, ...$updateValues);

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        errorResponse('Failed to update vehicle: ' . $error, 500);
    }

    $stmt->close();

    $vehicle = fetchVehicleById($conn, $vehicle_id);
    successResponse('Vehicle updated successfully.', $vehicle);
}

function handleDeleteVehicle($conn, $data)
{
    if (!isset($data['vehicle_id']) || !isset($data['tenantID']) || !isset($data['user_id'])) {
        errorResponse('Missing vehicle_id, tenantID, or user_id.');
    }

    $vehicle_id = (int)$data['vehicle_id'];
    $tenantID = (int)$data['tenantID'];
    $user_id = (int)$data['user_id'];

    if ($vehicle_id <= 0 || $tenantID <= 0 || $user_id <= 0) {
        errorResponse('Invalid vehicle_id, tenantID, or user_id.');
    }

    $verifyQuery = "
        SELECT vehicle_id
        FROM vehicleinformation
        WHERE vehicle_id = ? AND tenantID = ? AND user_id = ?
        LIMIT 1
    ";

    $verifyStmt = $conn->prepare($verifyQuery);

    if (!$verifyStmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $verifyStmt->bind_param('iii', $vehicle_id, $tenantID, $user_id);
    $verifyStmt->execute();

    $verifyResult = $verifyStmt->get_result();
    if ($verifyResult->num_rows === 0) {
        $verifyStmt->close();
        errorResponse('Vehicle not found or you do not have permission to delete it.', 403);
    }

    $verifyStmt->close();

    $query = "DELETE FROM vehicleinformation WHERE vehicle_id = ? AND tenantID = ? AND user_id = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $stmt->bind_param('iii', $vehicle_id, $tenantID, $user_id);

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        errorResponse('Failed to delete vehicle: ' . $error, 500);
    }

    $stmt->close();

    successResponse('Vehicle deleted successfully.');
}

try {
    $conn = getConnection();
    $data = getRequestData();

    $action = isset($data['action']) ? sanitize($data['action']) : '';

    if ($action === '') {
        errorResponse('Missing action parameter.');
    }

    switch ($action) {
        case 'list':
            handleListVehicles($conn, $data);
            break;

        case 'create':
            handleCreateVehicle($conn, $data);
            break;

        case 'update':
            handleUpdateVehicle($conn, $data);
            break;

        case 'delete':
            handleDeleteVehicle($conn, $data);
            break;

        default:
            errorResponse('Unknown action: ' . $action);
    }

    $conn->close();
} catch (Throwable $e) {
    errorResponse('Server error: ' . $e->getMessage(), 500);
}
?>