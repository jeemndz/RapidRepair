<?php
/**
 * Appointment CRUD API
 * Handles create, read, update, delete operations for appointments and services
 * 
 * Actions: create, list, update, delete, confirm
 * Database Tables: appointments, appointment_services, payments
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Get connection from db.php
require_once __DIR__ . '/../db.php';

if (!$conn || $conn->connect_error) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'Database connection failed'
  ]);
  exit;
}

$conn->set_charset('utf8mb4');

// Helper function for response
function respond($status, $message, $data = null, $httpCode = 200) {
  http_response_code($httpCode);
  $response = [
    'status' => $status,
    'message' => $message
  ];
  if ($data !== null) {
    $response['data'] = $data;
  }
  echo json_encode($response);
  exit;
}

// Helper function to validate positive integer
function toPositiveInt($value) {
  $num = (int)$value;
  return ($num > 0) ? $num : null;
}

// Helper function to validate decimal
function toDecimal($value) {
  $num = (float)$value;
  return is_finite($num) && $num > 0 ? $num : 0;
}

// Get action parameter
$action = isset($_GET['action']) ? strtolower(trim($_GET['action'])) : 
          (isset($_POST['action']) ? strtolower(trim($_POST['action'])) : null);

if (!$action) {
  respond('error', 'Missing action parameter', null, 400);
}

// Action: CREATE (Create new appointment with services)
if ($action === 'create') {
  // Get POST data
  $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

  // Validate required fields
  $tenantID = toPositiveInt($input['tenantID'] ?? null);
  $user_id = toPositiveInt($input['user_id'] ?? null);
  $vehicle_id = toPositiveInt($input['vehicle_id'] ?? null);
  $appointment_date = isset($input['appointment_date']) ? trim($input['appointment_date']) : null;
  $appointment_time = isset($input['appointment_time']) ? trim($input['appointment_time']) : null;
  $notes = isset($input['notes']) ? trim($input['notes']) : '';
  $service_ids = isset($input['service_ids']) ? (is_array($input['service_ids']) ? $input['service_ids'] : explode(',', $input['service_ids'])) : [];
  $total_amount = toDecimal($input['total_amount'] ?? 0);

  if (!$tenantID || !$user_id || !$vehicle_id || !$appointment_date || !$appointment_time) {
    respond('error', 'Missing required fields: tenantID, user_id, vehicle_id, appointment_date, appointment_time', null, 400);
  }

  if (empty($service_ids)) {
    respond('error', 'At least one service must be selected', null, 400);
  }

  // Validate appointment date format
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    respond('error', 'Invalid appointment_date format. Use YYYY-MM-DD', null, 400);
  }

  // Validate appointment time format
  if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $appointment_time)) {
    respond('error', 'Invalid appointment_time format. Use HH:MM:SS or HH:MM', null, 400);
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

    // Get service details and insert appointment_services
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

    // Insert payment record (initial state)
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

    respond('success', 'Appointment created successfully', [
      'appointment_id' => $appointment_id,
      'reference_number' => $referenceNumber,
      'status' => $status,
      'total_amount' => $total_amount
    ]);

  } catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    respond('error', 'Failed to create appointment: ' . $e->getMessage(), null, 500);
  }
}

// Action: LIST (Get appointments for a user or tenant)
else if ($action === 'list') {
  $tenantID = toPositiveInt($_GET['tenantID'] ?? $_POST['tenantID'] ?? null);
  $user_id = toPositiveInt($_GET['user_id'] ?? $_POST['user_id'] ?? null);
  $limit = min((int)($_GET['limit'] ?? $_POST['limit'] ?? 50), 100);
  $offset = (int)($_GET['offset'] ?? $_POST['offset'] ?? 0);

  if (!$tenantID) {
    respond('error', 'Missing tenantID', null, 400);
  }

  // Build query
  if ($user_id) {
    $query = "SELECT * FROM appointments WHERE tenantID = ? AND user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iiii', $tenantID, $user_id, $limit, $offset);
  } else {
    $query = "SELECT * FROM appointments WHERE tenantID = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iii', $tenantID, $limit, $offset);
  }

  if (!$stmt->execute()) {
    respond('error', 'Query failed: ' . $stmt->error, null, 500);
  }

  $result = $stmt->get_result();
  $appointments = [];
  while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
  }
  $stmt->close();

  respond('success', 'Appointments retrieved', $appointments);
}

// Action: UPDATE (Update appointment status or details)
else if ($action === 'update') {
  $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

  $appointment_id = toPositiveInt($input['appointment_id'] ?? null);
  $tenantID = toPositiveInt($input['tenantID'] ?? null);
  $status = isset($input['status']) ? trim($input['status']) : null;

  if (!$appointment_id || !$tenantID) {
    respond('error', 'Missing appointment_id or tenantID', null, 400);
  }

  // Validate status if provided
  $valid_statuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];
  if ($status && !in_array($status, $valid_statuses)) {
    respond('error', 'Invalid status. Must be one of: ' . implode(', ', $valid_statuses), null, 400);
  }

  $query = "UPDATE appointments SET status = ?, updated_at = NOW() WHERE appointment_id = ? AND tenantID = ?";
  $stmt = $conn->prepare($query);
  if (!$stmt) {
    respond('error', 'Prepare failed: ' . $conn->error, null, 500);
  }

  $stmt->bind_param('sii', $status, $appointment_id, $tenantID);
  if (!$stmt->execute()) {
    respond('error', 'Update failed: ' . $stmt->error, null, 500);
  }

  $stmt->close();

  respond('success', 'Appointment updated', ['appointment_id' => $appointment_id]);
}

// Action: DELETE
else if ($action === 'delete') {
  $appointment_id = toPositiveInt($_GET['appointment_id'] ?? $_POST['appointment_id'] ?? null);
  $tenantID = toPositiveInt($_GET['tenantID'] ?? $_POST['tenantID'] ?? null);

  if (!$appointment_id || !$tenantID) {
    respond('error', 'Missing appointment_id or tenantID', null, 400);
  }

  // Begin transaction in case we need to delete related records
  $conn->begin_transaction();

  try {
    // Delete appointment services
    $stmt = $conn->prepare("DELETE FROM appointment_services WHERE appointment_id = ? AND tenantID = ?");
    $stmt->bind_param('ii', $appointment_id, $tenantID);
    $stmt->execute();
    $stmt->close();

    // Delete payments
    $stmt = $conn->prepare("DELETE FROM payments WHERE appointment_id = ? AND tenantID = ?");
    $stmt->bind_param('ii', $appointment_id, $tenantID);
    $stmt->execute();
    $stmt->close();

    // Delete appointment
    $stmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id = ? AND tenantID = ?");
    $stmt->bind_param('ii', $appointment_id, $tenantID);
    if (!$stmt->execute()) {
      throw new Exception('Delete failed: ' . $stmt->error);
    }
    $stmt->close();

    $conn->commit();
    respond('success', 'Appointment deleted', ['appointment_id' => $appointment_id]);

  } catch (Exception $e) {
    $conn->rollback();
    respond('error', 'Delete failed: ' . $e->getMessage(), null, 500);
  }
}

// Invalid action
else {
  respond('error', 'Invalid action: ' . htmlspecialchars($action), null, 400);
}

$conn->close();
?>
