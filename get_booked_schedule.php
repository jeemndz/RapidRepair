<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dbPath = __DIR__ . '/db.php';
$configPath = __DIR__ . '/config.php';

if (file_exists($dbPath)) {
    require_once $dbPath;
} elseif (file_exists($configPath)) {
    require_once $configPath;
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database configuration file not found'
    ]);
    exit;
}

if (!isset($conn) || !$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

$tenantID = (int)($_GET['tenantID'] ?? 0);

if ($tenantID <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid tenantID'
    ]);
    exit;
}

$stmt = $conn->prepare(" 
    SELECT appointment_date, appointment_time
    FROM appointments
    WHERE tenantID = ?
      AND appointment_date >= CURDATE()
      AND status IN (
          'Pending',
          'Confirmed',
          'For Diagnosis',
          'Diagnosing',
          'For Approval',
          'In Progress'
      )
    ORDER BY appointment_date ASC, appointment_time ASC
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to prepare schedule query',
        'error' => $conn->error
    ]);
    exit;
}

$stmt->bind_param('i', $tenantID);
$stmt->execute();
$result = $stmt->get_result();

$schedule = [];

while ($row = $result->fetch_assoc()) {
    $date = (string)$row['appointment_date'];
    $time = substr((string)$row['appointment_time'], 0, 5);

    if (!isset($schedule[$date])) {
        $schedule[$date] = [];
    }

    if ($time !== '' && !in_array($time, $schedule[$date], true)) {
        $schedule[$date][] = $time;
    }
}

$stmt->close();

foreach ($schedule as $date => $times) {
    sort($times);
    $schedule[$date] = $times;
}

echo json_encode([
    'status' => 'success',
    'data' => $schedule
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
