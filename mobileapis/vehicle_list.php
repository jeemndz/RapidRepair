<?php
/**
 * Vehicle List API Endpoint
 * Returns vehicles for a given tenantID
 * Uses centralized database connection from db.php
 */

// Include centralized database connection
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-API-Version: 1.0');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Response helper function
function sendResponse($statusCode, $data) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    sendResponse(500, [
        'status' => 'error',
        'message' => 'Database connection failed',
        'error' => 'Unable to establish database connection'
    ]);
}

// Health check endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    sendResponse(200, [
        'status' => 'ok',
        'message' => 'Vehicle List API is running',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Get parameters (supports aliases because some gateways block specific query keys)
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

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0);
$includeAllOnEmpty = isset($_GET['includeAllOnEmpty']) ? $_GET['includeAllOnEmpty'] === '1' : false;

$vehicles = [];

// Query vehicle information
if ($tenantID > 0) {
    // Get vehicles for specific tenant
    $sql = "SELECT 
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
                created_at,
                updated_at
            FROM vehicleinformation 
            WHERE tenantID = ?";
    
    // Add user filter if provided
    if ($user_id > 0) {
        $sql .= " AND user_id = ?";
    }
    
    $sql .= " AND status = 'Active' 
            ORDER BY created_at DESC, brand ASC, model ASC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to prepare statement',
            'error' => $conn->error
        ]);
    }
    
    // Bind parameters
    if ($user_id > 0) {
        $stmt->bind_param('ii', $tenantID, $user_id);
    } else {
        $stmt->bind_param('i', $tenantID);
    }
    
    // Execute query
    if (!$stmt->execute()) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Query execution failed',
            'error' => $stmt->error
        ]);
    }
    
    // Get results
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
    $stmt->close();
}

// Fallback: Get all active vehicles if no tenant vehicles found and flag is set
if (empty($vehicles) && $includeAllOnEmpty) {
    $sql = "SELECT 
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
                created_at,
                updated_at
            FROM vehicleinformation 
            WHERE status = 'Active'
            ORDER BY created_at DESC, brand ASC, model ASC
            LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $vehicles[] = $row;
            }
        }
        $stmt->close();
    }
}

$conn->close();

// Send successful response
sendResponse(200, [
    'status' => 'success',
    'tenantID' => $tenantID,
    'user_id' => $user_id,
    'vehicleCount' => count($vehicles),
    'vehicles' => $vehicles,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>