<?php
/**
 * Vehicle Information CRUD API
 * Handles list, create, update, and delete operations for vehicles
 * Database Table: vehicleinformation
 * Database Connection: GitHub RapidRepair Repository
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database connection from GitHub repository
require_once 'https://raw.githubusercontent.com/jeemnndz/RapidRepair/main/db.php';

// Response helper functions
function responseJson($status, $message = '', $data = null)
{
    $response = [
        'status' => $status,
        'message' => $message,
    ];

    if ($data !== null) {
        if ($status === 'success' && is_array($data) && !isset($data[0])) {
            // Single object response
            if (isset($data['vehicle_id'])) {
                $response['vehicle'] = $data;
            } else {
                $response['data'] = $data;
            }
        } else {
            // Multiple results
            $response['vehicles'] = is_array($data) ? $data : [$data];
        }
    }

    echo json_encode($response);
    exit;
}

function errorResponse($message, $code = 400)
{
    http_response_code($code);
    responseJson('error', $message);
}

// Get database connection (already established in db.php)
function getConnection()
{
    global $conn;

    if (!$conn || !$conn->ping()) {
        errorResponse('Database connection lost', 500);
    }

    return $conn;
}

// Sanitize input
function sanitize($value)
{
    return trim((string) $value);
}

// Validate required fields
function validateRequired($fields, $data)
{
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missing[] = $field;
        }
    }
    return $missing;
}

// Normalize vehicle data from database
function normalizeVehicle($row)
{
    return [
        'vehicle_id' => (int) $row['vehicle_id'],
        'tenantID' => (int) $row['tenantID'],
        'user_id' => (int) $row['user_id'],
        'brand' => $row['brand'],
        'model' => $row['model'],
        'year' => $row['year_model'],
        'year_model' => $row['year_model'],
        'fuel_type' => $row['fuel_type'],
        'transmission_type' => $row['transmission_type'],
        'engine_number' => $row['engine_number'],
        'mileage_km' => (int) $row['mileage_km'],
        'vin_number' => $row['vin_number'],
        'plate_number' => $row['plate_number'],
        'color' => $row['color'],
        'status' => $row['status'],
        'date_added' => $row['date_added'],
    ];
}

// GET: Fetch vehicles
function handleListVehicles($conn)
{
    $tenantID = isset($_GET['tenantID']) ? (int) $_GET['tenantID'] : 0;
    $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

    if ($tenantID <= 0 || $user_id <= 0) {
        errorResponse('Invalid tenantID or user_id');
    }

    $query = "
    SELECT * FROM vehicleinformation 
    WHERE tenantID = ? AND user_id = ?
    ORDER BY date_added DESC
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
    responseJson('success', 'Vehicles fetched successfully', $vehicles);
}

// POST: Create vehicle
function handleCreateVehicle($conn)
{
    $data = $_POST;

    $required = ['tenantID', 'user_id', 'brand', 'model', 'year_model', 'plate_number'];
    $missing = validateRequired($required, $data);

    if (!empty($missing)) {
        errorResponse('Missing required fields: ' . implode(', ', $missing));
    }

    $tenantID = (int) $data['tenantID'];
    $user_id = (int) $data['user_id'];
    $brand = sanitize($data['brand']);
    $model = sanitize($data['model']);
    $year_model = sanitize($data['year_model']);
    $fuel_type = isset($data['fuel_type']) ? sanitize($data['fuel_type']) : 'Gasoline';
    $transmission_type = isset($data['transmission_type']) ? sanitize($data['transmission_type']) : 'Manual';
    $engine_number = isset($data['engine_number']) ? sanitize($data['engine_number']) : null;
    $mileage_km = isset($data['mileage_km']) ? (int) $data['mileage_km'] : 0;
    $vin_number = isset($data['vin_number']) ? sanitize($data['vin_number']) : null;
    $plate_number = sanitize($data['plate_number']);
    $color = isset($data['color']) ? sanitize($data['color']) : null;
    $status = isset($data['status']) ? sanitize($data['status']) : 'Active';
    $date_added = isset($data['date_added']) ? $data['date_added'] : date('Y-m-d H:i:s');

    if ($tenantID <= 0 || $user_id <= 0) {
        errorResponse('Invalid tenantID or user_id');
    }

    if (empty($brand) || empty($model) || empty($year_model) || empty($plate_number)) {
        errorResponse('Brand, model, year, and plate number are required');
    }

    // Validate year format (should be 4 digits)
    if (!preg_match('/^\d{4}$/', $year_model)) {
        errorResponse('Year must be in YYYY format');
    }

    // Check for duplicate plate number
    $dupQuery = "SELECT vehicle_id FROM vehicleinformation WHERE plate_number = ? AND tenantID = ? AND user_id = ?";
    $dupStmt = $conn->prepare($dupQuery);
    if (!$dupStmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $dupStmt->bind_param('sii', $plate_number, $tenantID, $user_id);
    $dupStmt->execute();
    $dupResult = $dupStmt->get_result();

    if ($dupResult->num_rows > 0) {
        $dupStmt->close();
        errorResponse('This plate number is already registered for this user');
    }
    $dupStmt->close();

    $query = "
    INSERT INTO vehicleinformation 
    (tenantID, user_id, brand, model, year_model, fuel_type, transmission_type, 
     engine_number, mileage_km, vin_number, plate_number, color, status, date_added)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $stmt->bind_param(
        'iisssssiissis',
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
        errorResponse('Failed to create vehicle: ' . $stmt->error, 500);
    }

    $vehicle_id = $conn->insert_id;
    $stmt->close();

    // Fetch and return the created vehicle
    $fetchQuery = "SELECT * FROM vehicleinformation WHERE vehicle_id = ?";
    $fetchStmt = $conn->prepare($fetchQuery);
    $fetchStmt->bind_param('i', $vehicle_id);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $vehicle = $result->fetch_assoc();
    $fetchStmt->close();

    responseJson('success', 'Vehicle created successfully', normalizeVehicle($vehicle));
}

// POST: Update vehicle
function handleUpdateVehicle($conn)
{
    $data = $_POST;

    if (!isset($data['vehicle_id']) || !isset($data['tenantID']) || !isset($data['user_id'])) {
        errorResponse('Missing vehicle_id, tenantID, or user_id');
    }

    $vehicle_id = (int) $data['vehicle_id'];
    $tenantID = (int) $data['tenantID'];
    $user_id = (int) $data['user_id'];

    if ($vehicle_id <= 0 || $tenantID <= 0 || $user_id <= 0) {
        errorResponse('Invalid vehicle_id, tenantID, or user_id');
    }

    // Verify vehicle belongs to user
    $verifyQuery = "SELECT vehicle_id FROM vehicleinformation WHERE vehicle_id = ? AND tenantID = ? AND user_id = ?";
    $verifyStmt = $conn->prepare($verifyQuery);
    $verifyStmt->bind_param('iii', $vehicle_id, $tenantID, $user_id);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();

    if ($verifyResult->num_rows === 0) {
        $verifyStmt->close();
        errorResponse('Vehicle not found or you do not have permission to update it', 403);
    }
    $verifyStmt->close();

    // Build update query dynamically based on provided fields
    $allowedFields = [
        'brand',
        'model',
        'year_model',
        'fuel_type',
        'transmission_type',
        'engine_number',
        'mileage_km',
        'vin_number',
        'plate_number',
        'color',
        'status'
    ];

    $updateFields = [];
    $updateValues = [];
    $types = '';

    foreach ($allowedFields as $field) {
        if (isset($data[$field]) && $data[$field] !== '') {
            $updateFields[] = "$field = ?";
            $updateValues[] = sanitize($data[$field]);
            $types .= is_numeric($updateValues[count($updateValues) - 1]) ? 'i' : 's';
        }
    }

    if (empty($updateFields)) {
        errorResponse('No updateable fields provided');
    }

    $updateValues[] = $vehicle_id;
    $updateValues[] = $tenantID;
    $updateValues[] = $user_id;
    $types .= 'iii';

    $query = "UPDATE vehicleinformation SET " . implode(', ', $updateFields) . " WHERE vehicle_id = ? AND tenantID = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $stmt->bind_param($types, ...$updateValues);

    if (!$stmt->execute()) {
        errorResponse('Failed to update vehicle: ' . $stmt->error, 500);
    }

    $stmt->close();

    // Fetch and return the updated vehicle
    $fetchQuery = "SELECT * FROM vehicleinformation WHERE vehicle_id = ?";
    $fetchStmt = $conn->prepare($fetchQuery);
    $fetchStmt->bind_param('i', $vehicle_id);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $vehicle = $result->fetch_assoc();
    $fetchStmt->close();

    responseJson('success', 'Vehicle updated successfully', normalizeVehicle($vehicle));
}

// POST: Delete vehicle
function handleDeleteVehicle($conn)
{
    $data = $_POST;

    if (!isset($data['vehicle_id']) || !isset($data['tenantID']) || !isset($data['user_id'])) {
        errorResponse('Missing vehicle_id, tenantID, or user_id');
    }

    $vehicle_id = (int) $data['vehicle_id'];
    $tenantID = (int) $data['tenantID'];
    $user_id = (int) $data['user_id'];

    if ($vehicle_id <= 0 || $tenantID <= 0 || $user_id <= 0) {
        errorResponse('Invalid vehicle_id, tenantID, or user_id');
    }

    // Verify vehicle belongs to user before deleting
    $verifyQuery = "SELECT vehicle_id FROM vehicleinformation WHERE vehicle_id = ? AND tenantID = ? AND user_id = ?";
    $verifyStmt = $conn->prepare($verifyQuery);
    $verifyStmt->bind_param('iii', $vehicle_id, $tenantID, $user_id);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();

    if ($verifyResult->num_rows === 0) {
        $verifyStmt->close();
        errorResponse('Vehicle not found or you do not have permission to delete it', 403);
    }
    $verifyStmt->close();

    $query = "DELETE FROM vehicleinformation WHERE vehicle_id = ? AND tenantID = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $stmt->bind_param('iii', $vehicle_id, $tenantID, $user_id);

    if (!$stmt->execute()) {
        errorResponse('Failed to delete vehicle: ' . $stmt->error, 500);
    }

    $stmt->close();
    responseJson('success', 'Vehicle deleted successfully');
}

// Main router
try {
    $conn = getConnection();

    $action = isset($_GET['action']) ? sanitize($_GET['action']) : (isset($_POST['action']) ? sanitize($_POST['action']) : '');

    if (empty($action)) {
        errorResponse('Missing action parameter');
    }

    switch ($action) {
        case 'list':
            handleListVehicles($conn);
            break;

        case 'create':
            handleCreateVehicle($conn);
            break;

        case 'update':
            handleUpdateVehicle($conn);
            break;

        case 'delete':
            handleDeleteVehicle($conn);
            break;

        default:
            errorResponse('Unknown action: ' . $action);
    }

    $conn->close();
} catch (Exception $e) {
    errorResponse('Server error: ' . $e->getMessage(), 500);
}
?>