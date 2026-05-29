<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

function responseJson($status, $message = '', $data = null, $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function getRequestData()
{
    $data = $_POST;

    if (empty($data)) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $data = $json;
        }
    }

    return $data;
}

function convertTo12Hour($time)
{
    return date('h:i A', strtotime($time));
}

$data = getRequestData();

$tenantID = (int)($data['tenantID'] ?? 0);
$appointment_date = trim($data['appointment_date'] ?? '');

if (!$tenantID || !$appointment_date) {
    responseJson('error', 'Missing tenantID or appointment_date', null, 400);
}

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_mechanics
    FROM roles
    WHERE tenantID = ?
    AND is_active = 1
    AND status = 'Active'
    AND (
        role_name LIKE '%Mechanic%'
        OR role_name LIKE '%Technician%'
    )
");

$stmt->bind_param('i', $tenantID);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$mechanics_count = (int)($row['total_mechanics'] ?? 0);

$defaultSlots = [
    '09:00:00',
    '10:30:00',
    '13:00:00',
    '14:30:00',
    '16:00:00',
    '17:30:00'
];

$slots = [];

foreach ($defaultSlots as $time) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS booked_count
        FROM appointments
        WHERE tenantID = ?
        AND appointment_date = ?
        AND appointment_time = ?
        AND status NOT IN ('Cancelled', 'No Show')
    ");

    $stmt->bind_param('iss', $tenantID, $appointment_date, $time);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookedRow = $result->fetch_assoc();
    $stmt->close();

    $booked_count = (int)($bookedRow['booked_count'] ?? 0);
    $remaining = max($mechanics_count - $booked_count, 0);

    $slots[] = [
        'time' => $time,
        'label' => convertTo12Hour($time),
        'booked' => $booked_count,
        'capacity' => $mechanics_count,
        'remaining' => $remaining,
        'available' => $mechanics_count > 0 && $booked_count < $mechanics_count
    ];
}

responseJson('success', 'Available slots loaded', [
    'mechanics_count' => $mechanics_count,
    'appointment_date' => $appointment_date,
    'slots' => $slots
]);