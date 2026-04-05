<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

require_once 'config.php';

$conn = new mysqli(
  'rapidrepairs.mysql.database.azure.com',
  'rradmin1',
  'RapidRepair@2024',
  'rapidrepairs',
  3306
);

if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'Database connection failed: ' . $conn->connect_error
  ]);
  exit;
}

$conn->set_charset('utf8mb4');

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

function toPositiveInt($value) {
  $num = (int)$value;
  return ($num > 0) ? $num : null;
}

function toDecimal($value) {
  $num = (float)$value;
  return is_finite($num) && $num >= 0 ? $num : 0;
}

$rawBody = file_get_contents('php://input');
$jsonInput = json_decode($rawBody, true);
$input = is_array($jsonInput) ? $jsonInput : $_POST;

$action = isset($_GET['action']) ? strtolower(trim($_GET['action'])) :
          (isset($input['action']) ? strtolower(trim($input['action'])) : null);

if (!$action) {
  respond('error', 'Missing action parameter', null, 400);
}

if ($action === 'create') {
  $tenantID = toPositiveInt($input['tenantID'] ?? null);
  $user_id = toPositiveInt($input['user_id'] ?? null);
  $vehicle_id = toPositiveInt($input['vehicle_id'] ?? null);
  $appointment_date = isset($input['appointment_date']) ? trim($input['appointment_date']) : null;
  $appointment_time = isset($input['appointment_time']) ? trim($input['appointment_time']) : null;
  $notes = isset($input['notes']) ? trim($input['notes']) : '';
  $service_ids = isset($input['service_ids'])
    ? (is_array($input['service_ids']) ? $input['service_ids'] : explode(',', $input['service_ids']))
    : [];
  $total_amount = toDecimal($input['total_amount'] ?? 0);

  if (!$tenantID || !$user_id || !$vehicle_id || !$appointment_date || !$appointment_time) {
    respond('error', 'Missing required fields: tenantID, user_id, vehicle_id, appointment_date, appointment_time', null, 400);
  }

  if (empty($service_ids)) {
    respond('error', 'At least one service must be selected', null, 400);
  }

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    respond('error', 'Invalid appointment_date format. Use YYYY-MM-DD', null, 400);
  }

  if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $appointment_time)) {
    respond('error', 'Invalid appointment_time format. Use HH:MM:SS or HH:MM', null, 400);
  }

  $normalizedServiceIds = [];
  foreach ($service_ids as $service_id) {
    $service_id = (int)$service_id;
    if ($service_id > 0) {
      $normalizedServiceIds[] = $service_id;
    }
  }

  if (empty($normalizedServiceIds)) {
    respond('error', 'Invalid service_ids', null, 400);
  }

  $conn->begin_transaction();

  try {
    $status = 'Pending';

    $query = "INSERT INTO appointments
      (tenantID, user_id, vehicle_id, appointment_date, appointment_time, status, notes, total_amount, created_at, updated_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $conn->error);
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
      throw new Exception('Execute failed: ' . $stmt->error);
    }

    $appointment_id = $conn->insert_id;
    $stmt->close();

    $placeholders = implode(',', array_fill(0, count($normalizedServiceIds), '?'));
    $serviceQuery = "SELECT service_id, price FROM services WHERE service_id IN ($placeholders) AND tenantID = ?";

    $stmt = $conn->prepare($serviceQuery);
    if (!$stmt) {
      throw new Exception('Service prepare failed: ' . $conn->error);
    }

    $types = str_repeat('i', count($normalizedServiceIds)) . 'i';
    $params = array_merge($normalizedServiceIds, [$tenantID]);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
      throw new Exception('Service query failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $services = [];
    while ($row = $result->fetch_assoc()) {
      $services[(int)$row['service_id']] = (float)$row['price'];
    }
    $stmt->close();

    if (count($services) !== count($normalizedServiceIds)) {
      throw new Exception('One or more selected services were not found for this tenant.');
    }

    $serviceInsertQuery = "INSERT INTO appointment_services
      (appointment_id, tenantID, service_id, service_price, duration_minutes, notes, created_at)
      VALUES (?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($serviceInsertQuery);
    if (!$stmt) {
      throw new Exception('Service insert prepare failed: ' . $conn->error);
    }

    $duration_minutes = 0;
    $service_notes = '';

    foreach ($normalizedServiceIds as $service_id) {
      $service_price = $services[$service_id] ?? 0;

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
        throw new Exception('Service insert failed: ' . $stmt->error);
      }
    }
    $stmt->close();

    $paymentQuery = "INSERT INTO payments
      (tenantID, user_id, appointment_id, paymentAmount, amountPaid, balance, paymentMethod, paymentStatus, referenceNumber, created_at, updated_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

    $stmt = $conn->prepare($paymentQuery);
    if (!$stmt) {
      throw new Exception('Payment prepare failed: ' . $conn->error);
    }

    $paymentMethod = 'Pending';
    $paymentStatus = 'Pending';
    $referenceNumber = 'RR-' . str_pad((string)$appointment_id, 5, '0', STR_PAD_LEFT);
    $amountPaid = 0.00;
    $balance = $total_amount;

    $stmt->bind_param(
      'iiidddsss',
      $tenantID,
      $user_id,
      $appointment_id,
      $total_amount,
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

    $conn->commit();

    respond('success', 'Appointment created successfully', [
      'appointment_id' => $appointment_id,
      'reference_number' => $referenceNumber,
      'status' => $status,
      'total_amount' => $total_amount
    ]);
  } catch (Exception $e) {
    $conn->rollback();
    respond('error', 'Failed to create appointment: ' . $e->getMessage(), null, 500);
  }
}

else if ($action === 'list') {
  $tenantID = toPositiveInt($_GET['tenantID'] ?? $_POST['tenantID'] ?? null);
  $user_id = toPositiveInt($_GET['user_id'] ?? $_POST['user_id'] ?? null);
  $limit = min((int)($_GET['limit'] ?? $_POST['limit'] ?? 50), 100);
  $offset = (int)($_GET['offset'] ?? $_POST['offset'] ?? 0);

  if (!$tenantID) {
    respond('error', 'Missing tenantID', null, 400);
  }

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

else if ($action === 'update') {
  $appointment_id = toPositiveInt($input['appointment_id'] ?? null);
  $tenantID = toPositiveInt($input['tenantID'] ?? null);
  $status = isset($input['status']) ? trim($input['status']) : null;

  if (!$appointment_id || !$tenantID) {
    respond('error', 'Missing appointment_id or tenantID', null, 400);
  }

  $valid_statuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];
  if ($status && !in_array($status, $valid_statuses, true)) {
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

else if ($action === 'delete') {
  $appointment_id = toPositiveInt($_GET['appointment_id'] ?? $_POST['appointment_id'] ?? null);
  $tenantID = toPositiveInt($_GET['tenantID'] ?? $_POST['tenantID'] ?? null);

  if (!$appointment_id || !$tenantID) {
    respond('error', 'Missing appointment_id or tenantID', null, 400);
  }

  $conn->begin_transaction();

  try {
    $stmt = $conn->prepare("DELETE FROM appointment_services WHERE appointment_id = ? AND tenantID = ?");
    $stmt->bind_param('ii', $appointment_id, $tenantID);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM payments WHERE appointment_id = ? AND tenantID = ?");
    $stmt->bind_param('ii', $appointment_id, $tenantID);
    $stmt->execute();
    $stmt->close();

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

else {
  respond('error', 'Invalid action: ' . htmlspecialchars($action), null, 400);
}

$conn->close();
?>