<?php
/**
 * RapidRepair Booking Creation API
 * Dedicated endpoint for creating appointment bookings
 * 
 * Supports both GET and POST methods
 * Returns JSON response with appointment details
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
        'message' => 'Database connection failed: ' . ($conn ? $conn->connect_error : 'No connection')
    ]);
    exit;
}

$conn->set_charset('utf8mb4');

/**
 * Send JSON response and exit
 */
function respond($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

/**
 * Get input from POST body or return empty array
 */
function getInput() {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST ?? [];
}

/**
 * Normalize tenant ID (default to 1)
 */
function normalizeTenantID($value) {
    $tenantID = (int)($value ?? 0);
    return $tenantID > 0 ? $tenantID : 1;
}

/**
 * Normalize service IDs array
 */
function normalizeServiceIds($value) {
    if (is_array($value)) {
        $ids = $value;
    } elseif (is_string($value) && trim($value) !== '') {
        $ids = json_decode($value, true);
        if (!is_array($ids)) {
            $ids = explode(',', $value);
        }
    } else {
        $ids = [];
    }

    $ids = array_map('intval', $ids);
    $ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));

    return $ids;
}

/**
 * Validate date format (YYYY-MM-DD)
 */
function validateDateValue($date) {
    return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
}

/**
 * Validate time format (HH:MM or HH:MM:SS)
 */
function validateTimeValue($time) {
    return is_string($time) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time);
}

/**
 * Check if a row exists in database
 */
function rowExists(mysqli $conn, string $sql, string $types, ...$params): bool {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new Exception('Execute failed: ' . $err);
    }

    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Get input from GET or POST
    if ($method === 'GET') {
        $input = $_GET;
    } elseif ($method === 'POST') {
        $input = getInput();
    } else {
        respond(405, ['status' => 'error', 'message' => 'Method not allowed']);
    }

    // Extract and normalize input
    $tenantID = normalizeTenantID($input['tenantID'] ?? null);
    $user_id = (int)($input['user_id'] ?? 0);
    $vehicle_id = (int)($input['vehicle_id'] ?? 0);
    $appointment_date = trim((string)($input['appointment_date'] ?? ''));
    $appointment_time = trim((string)($input['appointment_time'] ?? ''));
    $notes = trim((string)($input['notes'] ?? ''));
    $service_ids = normalizeServiceIds($input['service_ids'] ?? []);
    $total_amount = (float)($input['total_amount'] ?? 0);

    // Validate required fields
    if ($user_id <= 0) {
        respond(400, ['status' => 'error', 'message' => 'Invalid or missing user_id']);
    }

    if ($vehicle_id <= 0) {
        respond(400, ['status' => 'error', 'message' => 'Invalid or missing vehicle_id']);
    }

    if (!validateDateValue($appointment_date)) {
        respond(400, ['status' => 'error', 'message' => 'Invalid appointment_date format. Use YYYY-MM-DD']);
    }

    if (!validateTimeValue($appointment_time)) {
        respond(400, ['status' => 'error', 'message' => 'Invalid appointment_time format. Use HH:MM or HH:MM:SS']);
    }

    if (empty($service_ids)) {
        respond(400, ['status' => 'error', 'message' => 'At least one service must be selected']);
    }

    if ($total_amount <= 0) {
        respond(400, ['status' => 'error', 'message' => 'Invalid total_amount']);
    }

    // Validate tenant/user/vehicle relations
    $userExists = rowExists(
        $conn,
        "SELECT id FROM users WHERE id = ? AND tenantID = ? LIMIT 1",
        'ii',
        $user_id,
        $tenantID
    );

    if (!$userExists) {
        respond(400, ['status' => 'error', 'message' => 'User does not belong to this tenant']);
    }

    $vehicleExists = rowExists(
        $conn,
        "SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND tenantID = ? AND user_id = ? LIMIT 1",
        'iii',
        $vehicle_id,
        $tenantID,
        $user_id
    );

    if (!$vehicleExists) {
        respond(400, ['status' => 'error', 'message' => 'Vehicle does not belong to this tenant/user']);
    }

    // Start transaction
    $conn->begin_transaction();

    // Load and validate services
    $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
    $types = str_repeat('i', count($service_ids)) . 'i';

    $sql = "SELECT id, price FROM services WHERE id IN ($placeholders) AND tenantID = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Service prepare failed: ' . $conn->error);
    }

    $params = array_merge($service_ids, [$tenantID]);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        throw new Exception('Service query failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $services = [];
    $computedTotal = 0;

    while ($row = $result->fetch_assoc()) {
        $serviceId = (int)$row['id'];
        $price = (float)$row['price'];
        $services[$serviceId] = $price;
        $computedTotal += $price;
    }
    $stmt->close();

    // Verify all services are valid
    if (count($services) !== count($service_ids)) {
        throw new Exception('One or more selected services are invalid for this tenant');
    }

    $finalTotal = $computedTotal;
    $status = 'Pending';

    // Insert appointment record
    $stmt = $conn->prepare("
        INSERT INTO appointments
        (tenantID, user_id, vehicle_id, appointment_date, appointment_time, status, notes, total_amount, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    if (!$stmt) {
        throw new Exception('Appointment prepare failed: ' . $conn->error);
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
        $finalTotal
    );

    if (!$stmt->execute()) {
        throw new Exception('Appointment insert failed: ' . $stmt->error);
    }

    $appointment_id = $conn->insert_id;
    $stmt->close();

    // Insert appointment services
    $stmt = $conn->prepare("
        INSERT INTO appointment_services
        (appointment_id, tenantID, service_id, service_price, duration_minutes, notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    if (!$stmt) {
        throw new Exception('Appointment services prepare failed: ' . $conn->error);
    }

    $duration_minutes = 0;
    $service_notes = '';

    foreach ($service_ids as $service_id) {
        $service_price = (float)$services[$service_id];
        
        $stmt->bind_param(
            'iiidis',
            $appointment_id,
            $tenantID,
            $service_id,
            $service_price,
            $duration_minutes,
            $service_notes
        );

        if (!$stmt->execute()) {
            throw new Exception('Appointment service insert failed: ' . $stmt->error);
        }
    }
    $stmt->close();

    // Insert payment record
    $paymentMethod = 'Pending';
    $paymentStatus = 'Pending';
    $referenceNumber = 'RR-' . str_pad((string)$appointment_id, 5, '0', STR_PAD_LEFT);
    $amountPaid = 0.00;
    $balance = $finalTotal;

    $stmt = $conn->prepare("
        INSERT INTO payments
        (tenantID, user_id, appointment_id, paymentAmount, amountPaid, balance, paymentMethod, paymentStatus, referenceNumber, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    if (!$stmt) {
        throw new Exception('Payment prepare failed: ' . $conn->error);
    }

    $stmt->bind_param(
        'iiidddsss',
        $tenantID,
        $user_id,
        $appointment_id,
        $finalTotal,
        $amountPaid,
        $balance,
        $paymentMethod,
        $paymentStatus,
        $referenceNumber
    );

    if (!$stmt->execute()) {
        throw new Exception('Payment insert failed: ' . $stmt->error);
    }
    $stmt->close();

    // Commit transaction
    $conn->commit();

    // Return success response
    respond(201, [
        'status' => 'success',
        'message' => 'Appointment created successfully',
        'data' => [
            'appointment_id' => $appointment_id,
            'reference_number' => $referenceNumber,
            'status' => $status,
            'total_amount' => $finalTotal
        ]
    ]);

} catch (Throwable $e) {
    // Rollback transaction on error
    if ($conn && $conn->errno === 0) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {}
    }

    respond(500, [
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
