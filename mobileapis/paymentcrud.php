<?php
ob_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

if (!file_exists(__DIR__ . '/../db.php')) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database configuration file not found',
    ]);
    exit;
}

require_once __DIR__ . '/../db.php';

function sendResponse($statusCode, $data)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);

    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'JSON encoding failed: ' . json_last_error_msg()
        ]);
    } else {
        echo $json;
    }
    exit;
}

function normalizeId($value, $fallback = 0)
{
    $num = (int) $value;
    return ($num > 0) ? $num : $fallback;
}

function normalizeMoney($value, $fallback = 0)
{
    $num = (float) $value;
    return ($num >= 0) ? round($num, 2) : $fallback;
}

function normalizeMethod($value)
{
    $valid = ['Cash', 'GCash', 'Card', 'Bank Transfer'];
    $value = trim((string) $value);
    return in_array($value, $valid, true) ? $value : 'GCash';
}

function sanitizeString($str)
{
    return trim(strip_tags((string) ($str ?? '')));
}

function normalizePayment($row)
{
    return [
        'payment_id' => (int) ($row['payment_id'] ?? 0),
        'tenantID' => (int) ($row['tenantID'] ?? 0),
        'user_id' => (int) ($row['user_id'] ?? 0),
        'appointment_id' => (int) ($row['appointment_id'] ?? 0),
        'paymentAmount' => (float) ($row['paymentAmount'] ?? 0),
        'amountPaid' => (float) ($row['amountPaid'] ?? 0),
        'balance' => (float) ($row['balance'] ?? 0),
        'paymentMethod' => (string) ($row['paymentMethod'] ?? 'Cash'),
        'paymentDate' => $row['paymentDate'] ?? null,
        'paymentStatus' => (string) ($row['paymentStatus'] ?? 'Pending'),
        'referenceNumber' => $row['referenceNumber'] ?? null,
        'gcashReferenceNumber' => $row['gcashReferenceNumber'] ?? null,
        'remarks' => $row['remarks'] ?? null,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
        'appointment_date' => $row['appointment_date'] ?? '',
        'appointment_time' => $row['appointment_time'] ?? '',
        'appointment_status' => (string)($row['appointment_status'] ?? ''),
        'job_status' => (string)($row['job_status'] ?? 'Pending'),
    ];
}

if (!isset($conn) || $conn->connect_error) {
    sendResponse(500, [
        'status' => 'error',
        'message' => 'Database connection failed',
    ]);
}

$rawInput = file_get_contents('php://input');
$jsonInput = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== false && !empty($rawInput)) {
    $jsonInput = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(400, [
            'status' => 'error',
            'message' => 'Invalid JSON format',
            'json_error' => json_last_error_msg(),
        ]);
    }
}

if (!is_array($jsonInput)) {
    $jsonInput = [];
}

$action = '';
if (!empty($jsonInput['action'])) {
    $action = sanitizeString($jsonInput['action']);
} elseif (!empty($_POST['action'])) {
    $action = sanitizeString($_POST['action']);
} elseif (!empty($_GET['action'])) {
    $action = sanitizeString($_GET['action']);
}
$action = strtolower($action);

if ($action === '') {
    sendResponse(400, [
        'status' => 'error',
        'message' => 'Missing action parameter',
        'available_actions' => ['list', 'pay'],
    ]);
}

try {
    switch ($action) {
        case 'list':
            handleList($conn, $jsonInput);
            break;

        case 'pay':
            handlePay($conn, $jsonInput);
            break;

        default:
            sendResponse(403, [
                'status' => 'error',
                'message' => 'Only list and pay actions are allowed for this endpoint',
                'available_actions' => ['list', 'pay'],
            ]);
    }
} catch (Throwable $e) {
    sendResponse(500, [
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
} finally {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (isset($conn) && $conn) {
        $conn->close();
    }
}

function handleList($conn, $jsonInput = [])
{
    $data = !empty($jsonInput) ? $jsonInput : array_merge($_GET, $_POST);

    $tenantID = normalizeId($data['tenantID'] ?? 0);
    if ($tenantID <= 0) {
        sendResponse(400, [
            'status' => 'error',
            'message' => 'Invalid or missing tenantID'
        ]);
    }

    // Auto-populate repair_jobs for appointments without them
    $appointmentQuery = "
        SELECT DISTINCT p.appointment_id, a.tenantID, a.user_id, a.vehicle_id
        FROM payments p
        LEFT JOIN appointments a ON p.appointment_id = a.appointment_id
        WHERE p.tenantID = ?
        AND NOT EXISTS (
            SELECT 1 FROM repair_jobs rj WHERE rj.appointment_id = p.appointment_id
        )
    ";
    
    $stmt = $conn->prepare($appointmentQuery);
    if ($stmt) {
        $stmt->bind_param('i', $tenantID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $appointmentId = $row['appointment_id'];
            $appointTenantID = $row['tenantID'] ?? $tenantID;
            $userId = $row['user_id'] ?? 0;
            $vehicleId = $row['vehicle_id'] ?? 0;
            
            $createStmt = $conn->prepare("
                INSERT INTO repair_jobs (appointment_id, tenantID, user_id, vehicle_id, job_status, priority, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'Queued', 'Normal', NOW(), NOW())
                ON DUPLICATE KEY UPDATE updated_at = NOW()
            ");
            
            if ($createStmt) {
                $createStmt->bind_param('iiii', $appointmentId, $appointTenantID, $userId, $vehicleId);
                $createStmt->execute();
                $createStmt->close();
            }
        }
        $stmt->close();
    }

    $user_id = normalizeId($data['user_id'] ?? 0);
    $limit = min(max((int) ($data['limit'] ?? 50), 1), 100);
    $offset = max((int) ($data['offset'] ?? 0), 0);
    $paymentStatus = sanitizeString($data['paymentStatus'] ?? '');

    $query = "SELECT 
    p.payment_id,
    p.tenantID,
    p.user_id,
    p.appointment_id,
    p.paymentAmount,
    p.amountPaid,
    p.balance,
    p.paymentMethod,
    p.paymentDate,
    p.paymentStatus,
    p.referenceNumber,
    p.gcashReferenceNumber,
    p.remarks,
    p.created_at,
    p.updated_at,
    a.appointment_date,
    a.appointment_time,
    a.status AS appointment_status,
    COALESCE((SELECT job_status FROM repair_jobs WHERE appointment_id = p.appointment_id ORDER BY created_at DESC LIMIT 1), 'Pending') as job_status
FROM payments p
LEFT JOIN appointments a ON p.appointment_id = a.appointment_id
WHERE p.tenantID = ?";

    $types = 'i';
    $params = [$tenantID];

    if ($user_id > 0) {
        $query .= " AND p.user_id = ?";
        $types .= 'i';
        $params[] = $user_id;
    }

    if ($paymentStatus !== '') {
        $query .= " AND p.paymentStatus = ?";
        $types .= 's';
        $params[] = $paymentStatus;
    }

    $query .= " ORDER BY p.paymentDate DESC, p.created_at DESC LIMIT ? OFFSET ?";
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to prepare statement',
            'error' => $conn->error
        ]);
    }

    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Query execution failed',
            'error' => $stmt->error
        ]);
    }

    $result = $stmt->get_result();
    $payments = [];

    while ($row = $result->fetch_assoc()) {
        $payments[] = normalizePayment($row);
    }

    $stmt->close();

    sendResponse(200, [
        'status' => 'success',
        'message' => 'Payments fetched successfully',
        'data' => $payments,
        'paymentCount' => count($payments),
    ]);
}

function handlePay($conn, $jsonInput = [])
{
    $data = !empty($jsonInput) ? $jsonInput : $_REQUEST;

    $payment_id = normalizeId($data['payment_id'] ?? 0);
    $tenantID = normalizeId($data['tenantID'] ?? 0);
    $user_id = normalizeId($data['user_id'] ?? 0);
    $amountPaid = normalizeMoney($data['amountPaid'] ?? 0);
    $paymentMethod = normalizeMethod($data['paymentMethod'] ?? 'GCash');
    $gcashReferenceNumber = sanitizeString($data['gcashReferenceNumber'] ?? '');
    $remarks = sanitizeString($data['remarks'] ?? '');

    if ($payment_id <= 0) {
        sendResponse(400, ['status' => 'error', 'message' => 'Invalid payment_id']);
    }

    if ($tenantID <= 0) {
        sendResponse(400, ['status' => 'error', 'message' => 'Invalid tenantID']);
    }

    if ($user_id <= 0) {
        sendResponse(400, ['status' => 'error', 'message' => 'Invalid user_id']);
    }

    if ($amountPaid <= 0) {
        sendResponse(400, ['status' => 'error', 'message' => 'Amount paid must be greater than 0']);
    }

    $stmt = $conn->prepare("
        SELECT payment_id, tenantID, user_id, paymentAmount, amountPaid, paymentStatus
        FROM payments
        WHERE payment_id = ? AND tenantID = ? AND user_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to prepare statement',
            'error' => $conn->error
        ]);
    }

    $stmt->bind_param('iii', $payment_id, $tenantID, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        sendResponse(404, [
            'status' => 'error',
            'message' => 'Payment not found for this user'
        ]);
    }

    if (($payment['paymentStatus'] ?? '') === 'Paid') {
        sendResponse(400, [
            'status' => 'error',
            'message' => 'This payment is already fully paid'
        ]);
    }

    $paymentAmount = round((float) $payment['paymentAmount'], 2);
    $newAmountPaid = round((float) $amountPaid, 2);

    if ($newAmountPaid > $paymentAmount) {
        $newAmountPaid = $paymentAmount;
    }

    $balance = round($paymentAmount - $newAmountPaid, 2);

    if ($newAmountPaid <= 0) {
        $status = 'Pending';
    } elseif ($newAmountPaid < $paymentAmount) {
        $status = 'Partial';
    } else {
        $status = 'Paid';
        $balance = 0;
    }

    $query = "
        UPDATE payments
        SET amountPaid = ?,
            balance = ?,
            paymentStatus = ?,
            paymentMethod = ?,
            gcashReferenceNumber = ?,
            remarks = ?,
            paymentDate = NOW(),
            updated_at = NOW()
        WHERE payment_id = ? AND tenantID = ? AND user_id = ?
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to prepare statement',
            'error' => $conn->error
        ]);
    }

    $stmt->bind_param(
        'ddssssiii',
        $newAmountPaid,
        $balance,
        $status,
        $paymentMethod,
        $gcashReferenceNumber,
        $remarks,
        $payment_id,
        $tenantID,
        $user_id
    );

    if (!$stmt->execute()) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to record payment',
            'error' => $stmt->error
        ]);
    }

    $stmt->close();

    sendResponse(200, [
        'status' => 'success',
        'message' => 'Payment recorded successfully',
        'data' => [
            'payment_id' => $payment_id,
            'amountPaid' => $newAmountPaid,
            'balance' => $balance,
            'paymentStatus' => $status,
            'paymentMethod' => $paymentMethod,
            'gcashReferenceNumber' => $gcashReferenceNumber ?: null,
        ]
    ]);
}
?>