<?php
/**
 * RapidRepair Appointments API - Create Only
 * Simple endpoint for appointment creation
 * 
 * Supports: POST (create)
 * Returns JSON responses
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load database configuration
require_once 'config.php';

// Verify database connection
if (!isset($conn) || !$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

$conn->set_charset('utf8mb4');

/**
 * Send JSON response
 */
function respond($statusCode, $status, $message = '', $data = null) {
    http_response_code($statusCode);
    $response = ['status' => $status];
    
    if ($message) {
        $response['message'] = $message;
    }
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

/**
 * Get input from POST body
 */
function getInput() {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    return is_array($json) ? $json : $_POST;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Only allow POST method
    if ($method !== 'POST') {
        respond(405, 'error', 'Method not allowed. Use POST.');
    }
    
    $input = getInput();
    
    $tenantID = (int)($input['tenantID'] ?? 1);
    $user_id = (int)($input['user_id'] ?? 0);
    $vehicle_id = (int)($input['vehicle_id'] ?? 0);
    $appointment_date = trim((string)($input['appointment_date'] ?? ''));
    $appointment_time = trim((string)($input['appointment_time'] ?? ''));
    $status = trim((string)($input['status'] ?? 'Pending'));
    $notes = trim((string)($input['notes'] ?? ''));
    $total_amount = (float)($input['total_amount'] ?? 0);
    $service_ids = isset($input['service_ids']) && is_array($input['service_ids']) ? array_map('intval', $input['service_ids']) : [];
    
    // Validate required fields
    if (!$user_id || $user_id <= 0) {
        respond(400, 'error', 'Invalid user_id');
    }
    
    if (!$vehicle_id || $vehicle_id <= 0) {
        respond(400, 'error', 'Invalid vehicle_id');
    }
    
    if (!$appointment_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
        respond(400, 'error', 'Invalid appointment_date. Use YYYY-MM-DD');
    }
    
    if (!$appointment_time || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $appointment_time)) {
        respond(400, 'error', 'Invalid appointment_time. Use HH:MM or HH:MM:SS');
    }
    
    if (!$total_amount || $total_amount <= 0) {
        respond(400, 'error', 'Invalid total_amount');
    }
    
    // Validate user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND tenantID = ? LIMIT 1");
    if (!$stmt) {
        respond(500, 'error', 'Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('ii', $user_id, $tenantID);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $stmt->close();
        respond(400, 'error', 'User does not belong to this tenant');
    }
    $stmt->close();
    
    // Validate vehicle exists
    $stmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND tenantID = ? AND user_id = ? LIMIT 1");
    if (!$stmt) {
        respond(500, 'error', 'Prepare failed');
    }
    $stmt->bind_param('iii', $vehicle_id, $tenantID, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $stmt->close();
        respond(400, 'error', 'Vehicle does not belong to this user');
    }
    $stmt->close();
    
    // Insert appointment
    $stmt = $conn->prepare("
        INSERT INTO appointments
        (tenantID, user_id, vehicle_id, appointment_date, appointment_time, status, notes, total_amount, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    if (!$stmt) {
        respond(500, 'error', 'Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param(
        'iiissssd',
        $tenantID,
        $user_id,
        $vehicle_id,
        $appointment_date,
        $appointment_time,
        $status,
        $notes,
        $total_amount
    );
    
    if (!$stmt->execute()) {
        respond(500, 'error', 'Failed to create appointment: ' . $stmt->error);
    }
    
    $appointment_id = $conn->insert_id;
    $stmt->close();
    
    // Insert appointment services if provided
    if (!empty($service_ids)) {
        $service_stmt = $conn->prepare("
            SELECT service_id, service_name, service_price, estimated_duration 
            FROM services 
            WHERE service_id = ? AND tenantID = ? LIMIT 1
        ");
        
        $insert_service_stmt = $conn->prepare("
            INSERT INTO appointment_services 
            (appointment_id, tenantID, service_id, service_price, duration_minutes, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$service_stmt || !$insert_service_stmt) {
            respond(500, 'error', 'Failed to prepare service statements');
        }
        
        $service_notes = '';
        foreach ($service_ids as $service_id) {
            $service_id = (int)$service_id;
            if ($service_id <= 0) continue;
            
            // Get service details
            $service_stmt->bind_param('ii', $service_id, $tenantID);
            $service_stmt->execute();
            $service_result = $service_stmt->get_result();
            
            if ($service_result->num_rows > 0) {
                $service = $service_result->fetch_assoc();
                $service_price = (float)$service['service_price'];
                $duration_minutes = (int)$service['estimated_duration'];
                
                // Insert appointment service
                $insert_service_stmt->bind_param(
                    'iiidis',
                    $appointment_id,
                    $tenantID,
                    $service_id,
                    $service_price,
                    $duration_minutes,
                    $service_notes
                );
                
                if (!$insert_service_stmt->execute()) {
                    // Log error but don't fail - appointment was created
                    error_log('Failed to insert service ' . $service_id . ': ' . $insert_service_stmt->error);
                }
            }
        }
        
        $service_stmt->close();
        $insert_service_stmt->close();
    }
    
    respond(201, 'success', 'Appointment created successfully', [
        'appointment_id' => $appointment_id,
        'status' => $status,
        'total_amount' => $total_amount
    ]);

} catch (Throwable $e) {
    respond(500, 'error', 'Server error: ' . $e->getMessage());
}
?>
