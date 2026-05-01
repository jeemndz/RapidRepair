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

require_once __DIR__ . '/../db.php';

function sendResponse($statusCode, $data)
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeId($value, $fallback = 0)
{
    $num = (int)$value;
    return $num > 0 ? $num : $fallback;
}

function normalizeMoney($value, $fallback = 0)
{
    $num = (float)$value;
    return $num >= 0 ? round($num, 2) : $fallback;
}

function normalizeMethod($value)
{
    $valid = ['Cash', 'GCash', 'Card', 'Bank Transfer'];
    $value = trim((string)$value);
    return in_array($value, $valid, true) ? $value : 'GCash';
}

function sanitizeString($str)
{
    return trim(strip_tags((string)($str ?? '')));
}

function normalizePayment($row)
{
    return [
        'payment_id' => (int)($row['payment_id'] ?? 0),
        'tenantID' => (int)($row['tenantID'] ?? 0),
        'user_id' => (int)($row['user_id'] ?? 0),
        'appointment_id' => (int)($row['appointment_id'] ?? 0),
        'repair_job_id' => (int)($row['repair_job_id'] ?? 0),

        'paymentAmount' => (float)($row['paymentAmount'] ?? 0),
        'amountPaid' => (float)($row['amountPaid'] ?? 0),
        'balance' => (float)($row['balance'] ?? 0),

        'labor_total' => (float)($row['labor_total'] ?? 0),
        'parts_total' => (float)($row['parts_total'] ?? 0),
        'grand_total' => (float)($row['grand_total'] ?? 0),

        'paymentMethod' => (string)($row['paymentMethod'] ?? 'Cash'),
        'paymentDate' => $row['paymentDate'] ?? null,
        'paymentStatus' => (string)($row['paymentStatus'] ?? 'Pending'),
        'referenceNumber' => $row['referenceNumber'] ?? null,
        'gcashReferenceNumber' => $row['gcashReferenceNumber'] ?? null,
        'remarks' => $row['remarks'] ?? null,
        'invoice_items' => $row['invoice_items'] ?? null,

        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),

        'appointment_date' => $row['appointment_date'] ?? '',
        'appointment_time' => $row['appointment_time'] ?? '',
        'appointment_status' => (string)($row['appointment_status'] ?? ''),

        'job_order_no' => $row['job_order_no'] ?? null,
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

if (!is_array($jsonInput)) $jsonInput = [];

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
    while (ob_get_level() > 0) ob_end_clean();
    if (isset($conn) && $conn) $conn->close();
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

    $user_id = normalizeId($data['user_id'] ?? 0);
    $limit = min(max((int)($data['limit'] ?? 50), 1), 100);
    $offset = max((int)($data['offset'] ?? 0), 0);
    $paymentStatus = sanitizeString($data['paymentStatus'] ?? '');

    $query = "
        SELECT 
            p.payment_id,
            p.tenantID,
            p.user_id,
            p.appointment_id,
            p.repair_job_id,
            p.paymentAmount,
            p.amountPaid,
            p.balance,
            p.paymentMethod,
            p.paymentDate,
            p.paymentStatus,
            p.referenceNumber,
            p.gcashReferenceNumber,
            p.remarks,
            p.invoice_items,
            p.created_at,
            p.updated_at,
            p.labor_total,
            p.parts_total,
            p.grand_total,

            a.appointment_date,
            a.appointment_time,
            a.status AS appointment_status,

            rj.job_order_no,
            rj.job_status

        FROM payments p
        LEFT JOIN appointments a 
            ON a.appointment_id = p.appointment_id
        LEFT JOIN repair_jobs rj
            ON rj.repair_job_id = p.repair_job_id
        WHERE p.tenantID = ?
    ";

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

    $query .= "
        ORDER BY COALESCE(p.paymentDate, p.created_at) DESC, p.payment_id DESC
        LIMIT ? OFFSET ?
    ";

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

    if ($payment_id <= 0) sendResponse(400, ['status' => 'error', 'message' => 'Invalid payment_id']);
    if ($tenantID <= 0) sendResponse(400, ['status' => 'error', 'message' => 'Invalid tenantID']);
    if ($user_id <= 0) sendResponse(400, ['status' => 'error', 'message' => 'Invalid user_id']);
    if ($amountPaid <= 0) sendResponse(400, ['status' => 'error', 'message' => 'Amount paid must be greater than 0']);

    $stmt = $conn->prepare("
        SELECT 
            payment_id,
            tenantID,
            user_id,
            grand_total,
            paymentAmount,
            amountPaid,
            paymentStatus
        FROM payments
        WHERE payment_id = ?
        AND tenantID = ?
        AND user_id = ?
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

    if (strtolower((string)$payment['paymentStatus']) === 'paid') {
        sendResponse(400, [
            'status' => 'error',
            'message' => 'This payment is already fully paid'
        ]);
    }

    $grandTotal = round((float)($payment['grand_total'] ?? 0), 2);
    $paymentAmount = round((float)($payment['paymentAmount'] ?? 0), 2);
    $totalToPay = $grandTotal > 0 ? $grandTotal : $paymentAmount;

    $previousAmountPaid = round((float)$payment['amountPaid'], 2);
    $paymentThisTime = round((float)$amountPaid, 2);
    $newAmountPaid = round($previousAmountPaid + $paymentThisTime, 2);

    if ($newAmountPaid > $totalToPay) {
        $newAmountPaid = $totalToPay;
    }

    $balance = round($totalToPay - $newAmountPaid, 2);

    if ($newAmountPaid <= 0) {
        $status = 'Pending';
    } elseif ($newAmountPaid < $totalToPay) {
        $status = 'Partial';
    } else {
        $status = 'Paid';
        $balance = 0.00;
    }

    $stmt = $conn->prepare("
        UPDATE payments
        SET 
            amountPaid = ?,
            balance = ?,
            paymentStatus = ?,
            paymentMethod = ?,
            gcashReferenceNumber = ?,
            paymentDate = NOW(),
            updated_at = NOW()
        WHERE payment_id = ?
        AND tenantID = ?
        AND user_id = ?
    ");

    if (!$stmt) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to prepare statement',
            'error' => $conn->error
        ]);
    }

    $stmt->bind_param(
        'ddsssiii',
        $newAmountPaid,
        $balance,
        $status,
        $paymentMethod,
        $gcashReferenceNumber,
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
            'paymentAmount' => $paymentAmount,
            'grand_total' => $totalToPay,
            'amountPaid' => $newAmountPaid,
            'balance' => $balance,
            'paymentStatus' => $status,
            'paymentMethod' => $paymentMethod,
            'gcashReferenceNumber' => $gcashReferenceNumber ?: null,
        ]
    ]);
}
?>