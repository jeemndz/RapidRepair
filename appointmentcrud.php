<?php
/**
 * Appointment Booking API
 * Creates new appointment bookings with services
 * 
 * POST Request with JSON payload:
 * {
 *   "tenantID": 1,
 *   "user_id": 10,
 *   "vehicle_id": 5,
 *   "appointment_date": "2026-04-15",
 *   "appointment_time": "14:30:00",
 *   "service_ids": [1, 2],
 *   "total_amount": 1500,
 *   "notes": "Regular service"
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Require POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
  exit;
}

// Get connection from db.php (from GitHub)
require_once 'https://raw.githubusercontent.com/jeemnndz/RapidRepair/main/db.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'Database connection failed'
  ]);
  exit;
}

$conn->set_charset('utf8mb4');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Validate and sanitize inputs
$tenantID = isset($input['tenantID']) ? (int)$input['tenantID'] : null;
$user_id = isset($input['user_id']) ? (int)$input['user_id'] : null;
$vehicle_id = isset($input['vehicle_id']) ? (int)$input['vehicle_id'] : null;
$appointment_date = isset($input['appointment_date']) ? trim($input['appointment_date']) : null;
$appointment_time = isset($input['appointment_time']) ? trim($input['appointment_time']) : null;
$notes = isset($input['notes']) ? trim($input['notes']) : '';
$service_ids = isset($input['service_ids']) ? (is_array($input['service_ids']) ? $input['service_ids'] : explode(',', $input['service_ids'])) : [];
$total_amount = isset($input['total_amount']) ? (float)$input['total_amount'] : 0;

// Validate required fields
if (!$tenantID || $tenantID <= 0) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Invalid or missing tenantID']);
  exit;
}

if (!$user_id || $user_id <= 0) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Invalid or missing user_id']);
  exit;
}

if (!$vehicle_id || $vehicle_id <= 0) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Invalid or missing vehicle_id']);
  exit;
}

if (!$appointment_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Invalid appointment_date format. Use YYYY-MM-DD']);
  exit;
}

if (!$appointment_time || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $appointment_time)) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Invalid appointment_time format. Use HH:MM:SS or HH:MM']);
  exit;
}

if (empty($service_ids)) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'At least one service must be selected']);
  exit;
}

if ($total_amount <= 0) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Invalid total_amount']);
  exit;
}

// Begin transaction
$conn->begin_transaction();

try {
  // Insert appointment
  $query = "INSERT INTO appointments (tenantID, user_id, vehicle_id, appointment_date, appointment_time, status, notes, total_amount, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
  
  $stmt = $conn->prepare($query);
  if (!$stmt) {
    throw new Exception('Prepare failed: ' . $conn->error);
  }

  $status = 'Pending';
  $stmt->bind_param('iiisssd', $tenantID, $user_id, $vehicle_id, $appointment_date, $appointment_time, $status, $notes, $total_amount);
  
  if (!$stmt->execute()) {
    throw new Exception('Execute failed: ' . $stmt->error);
  }

  $appointment_id = $conn->insert_id;
  $stmt->close();

  // Get service details
  $service_ids_placeholders = implode(',', array_map('intval', $service_ids));
  $service_query = "SELECT id, price FROM services WHERE id IN ($service_ids_placeholders) AND tenantID = ?";
  
  $stmt = $conn->prepare($service_query);
  if (!$stmt) {
    throw new Exception('Service prepare failed: ' . $conn->error);
  }

  $stmt->bind_param('i', $tenantID);
  if (!$stmt->execute()) {
    throw new Exception('Service query failed: ' . $stmt->error);
  }

  $result = $stmt->get_result();
  $services = [];
  while ($row = $result->fetch_assoc()) {
    $services[$row['id']] = $row['price'];
  }
  $stmt->close();

  // Insert appointment services
  $service_insert_query = "INSERT INTO appointment_services (appointment_id, tenantID, service_id, service_price, duration_minutes, notes, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, NOW())";
  
  $stmt = $conn->prepare($service_insert_query);
  if (!$stmt) {
    throw new Exception('Service insert prepare failed: ' . $conn->error);
  }

  $duration_minutes = 0;
  foreach ($service_ids as $service_id) {
    $service_id = (int)$service_id;
    $service_price = $services[$service_id] ?? 0;
    $service_notes = '';

    $stmt->bind_param('iiidis', $appointment_id, $tenantID, $service_id, $service_price, $duration_minutes, $service_notes);
    if (!$stmt->execute()) {
      throw new Exception('Service insert failed: ' . $stmt->error);
    }
  }
  $stmt->close();

  // Insert payment record
  $payment_query = "INSERT INTO payments (tenantID, user_id, appointment_id, paymentAmount, amountPaid, balance, paymentMethod, paymentStatus, referenceNumber, created_at, updated_at) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
  
  $stmt = $conn->prepare($payment_query);
  if (!$stmt) {
    throw new Exception('Payment prepare failed: ' . $conn->error);
  }

  $paymentMethod = 'Pending';
  $paymentStatus = 'Pending';
  $referenceNumber = 'RR-' . str_pad($appointment_id, 5, '0', STR_PAD_LEFT);
  $amountPaid = 0;
  $balance = $total_amount;

  $stmt->bind_param('iiiddsss', $tenantID, $user_id, $appointment_id, $total_amount, $amountPaid, $balance, $paymentMethod, $paymentStatus, $referenceNumber);
  if (!$stmt->execute()) {
    throw new Exception('Payment insert failed: ' . $stmt->error);
  }
  $stmt->close();

  // Commit transaction
  $conn->commit();
  $conn->close();

  // Success response
  http_response_code(201);
  echo json_encode([
    'status' => 'success',
    'message' => 'Appointment created successfully',
    'data' => [
      'appointment_id' => $appointment_id,
      'reference_number' => $referenceNumber,
      'status' => $status,
      'total_amount' => $total_amount
    ]
  ]);

} catch (Exception $e) {
  // Rollback on error
  $conn->rollback();
  $conn->close();
  
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'Failed to create appointment: ' . $e->getMessage()
  ]);
}
?>
