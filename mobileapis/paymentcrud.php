<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendResponse($statusCode, $data) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeId($value, $fallback = 0) {
    $num = (int)$value;
    return ($num > 0) ? $num : $fallback;
}

function normalizeMoney($value, $fallback = 0) {
    $num = (float)$value;
    return ($num >= 0) ? round($num, 2) : $fallback;
}

function normalizeStatus($value) {
    $valid = ['Pending', 'Partial', 'Paid', 'Failed', 'Refunded'];
    $value = trim((string)$value);
    return in_array($value, $valid, true) ? $value : 'Pending';
}

function normalizeMethod($value) {
    $valid = ['Cash', 'GCash', 'Card', 'Bank Transfer'];
    $value = trim((string)$value);
    return in_array($value, $valid, true) ? $value : 'Cash';
}

function sanitizeString($str) {
    return trim(strip_tags((string)($str ?? '')));
}

function normalizePayment($row) {
    return [
        'payment_id' => (int)($row['payment_id'] ?? 0),
        'tenantID' => (int)($row['tenantID'] ?? 0),
        'user_id' => (int)($row['user_id'] ?? 0),
        'appointment_id' => (int)($row['appointment_id'] ?? 0),
        'paymentAmount' => (float)($row['paymentAmount'] ?? 0),
        'amountPaid' => (float)($row['amountPaid'] ?? 0),
        'balance' => (float)($row['balance'] ?? 0),
        'paymentMethod' => (string)($row['paymentMethod'] ?? 'Cash'),
        'paymentDate' => $row['paymentDate'] ?? null,
        'paymentStatus' => (string)($row['paymentStatus'] ?? 'Pending'),
        'referenceNumber' => $row['referenceNumber'] ?? null,
        'gcashReferenceNumber' => $row['gcashReferenceNumber'] ?? null,
        'remarks' => $row['remarks'] ?? null,
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'appointment_date' => $row['appointment_date'] ?? '',
        'appointment_time' => $row['appointment_time'] ?? '',
    ];
}

if (!isset($conn) || $conn->connect_error) {
    sendResponse(500, [
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
}

// Parse JSON input with better error handling
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
            'debug' => [
                'raw_input' => substr($rawInput, 0, 200),
                'content_type' => $contentType
            ]
        ]);
    }
}
if (!is_array($jsonInput)) {
    $jsonInput = [];
}

// Extract action from multiple sources with priority
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
        'available_actions' => ['list', 'create', 'update', 'delete'],
        'examples' => [
            'GET' => 'GET /paymentcrud.php?action=list&tenantID=1',
            'POST_JSON' => 'POST /paymentcrud.php with Content-Type: application/json and body: {"action":"list","tenantID":1}',
            'POST_FORM' => 'POST /paymentcrud.php with Content-Type: application/x-www-form-urlencoded and body: action=list&tenantID=1'
        ],
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
            'get_params' => !empty($_GET) ? array_keys($_GET) : [],
            'post_params' => !empty($_POST) ? array_keys($_POST) : [],
            'json_params' => !empty($jsonInput) ? array_keys($jsonInput) : [],
            'raw_input_length' => strlen($rawInput ?? ''),
        ]
    ]);
}

try {
    switch ($action) {
        case 'list':
            handleList($conn);
            break;
        case 'create':
            handleCreate($conn, $jsonInput);
            break;
        case 'update':
            handleUpdate($conn, $jsonInput);
            break;
        case 'delete':
            handleDelete($conn);
            break;
        default:
            sendResponse(400, [
                'status' => 'error',
                'message' => 'Invalid action: ' . $action,
                'available_actions' => ['list', 'create', 'update', 'delete'],
            ]);
    }
} catch (Throwable $e) {
    sendResponse(500, [
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

function handleList($conn) {
    $tenantID = normalizeId($_GET['tenantID'] ?? $_POST['tenantID'] ?? 0);

    if ($tenantID <= 0) {
        sendResponse(400, ['status' => 'error', 'message' => 'Invalid or missing tenantID']);
    }

    $user_id = normalizeId($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
    $limit = min(max((int)($_GET['limit'] ?? $_POST['limit'] ?? 50), 1), 100);
    $offset = max((int)($_GET['offset'] ?? $_POST['offset'] ?? 0), 0);
    $paymentStatus = sanitizeString($_GET['paymentStatus'] ?? $_POST['paymentStatus'] ?? '');

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
        a.appointment_time
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
        sendResponse(500, ['status' => 'error', 'message' => 'Failed to prepare statement', 'error' => $conn->error]);
    }

    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        sendResponse(500, ['status' => 'error', 'message' => 'Query execution failed', 'error' => $stmt->error]);
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

function handleCreate($conn, $jsonInput = []) {
    $data = !empty($jsonInput) ? $jsonInput : $_REQUEST;

    $tenantID = normalizeId($data['tenantID'] ?? 0, 1);
    $user_id = normalizeId($data['user_id'] ?? 0);
    $appointment_id = normalizeId($data['appointment_id'] ?? 0);
    $paymentAmount = normalizeMoney($data['paymentAmount'] ?? 0);
    $amountPaid = normalizeMoney($data['amountPaid'] ?? 0);
    $paymentMethod = normalizeMethod($data['paymentMethod'] ?? 'Cash');
    $paymentStatus = normalizeStatus($data['paymentStatus'] ?? 'Pending');
    $referenceNumber = sanitizeString($data['referenceNumber'] ?? '');
    $gcashReferenceNumber = sanitizeString($data['gcashReferenceNumber'] ?? '');
    $remarks = sanitizeString($data['remarks'] ?? '');

    if ($user_id <= 0) sendResponse(400, ['status' => 'error', 'message' => 'Invalid user_id']);
    if ($appointment_id <= 0) sendResponse(400, ['status' => 'error', 'message' => 'Invalid appointment_id']);
    if ($paymentAmount <= 0) sendResponse(400, ['status' => 'error', 'message' => 'Invalid paymentAmount']);

    $balance = round($paymentAmount - $amountPaid, 2);

    if ($referenceNumber === '') {
        $referenceNumber = 'RR-' . date('YmdHis');
    }

    $query = "INSERT INTO payments (
        tenantID, user_id, appointment_id, paymentAmount,
        amountPaid, balance, paymentMethod, paymentStatus,
        referenceNumber, gcashReferenceNumber, remarks
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        sendResponse(500, ['status' => 'error', 'message' => 'Failed to prepare statement', 'error' => $conn->error]);
    }

    $stmt->bind_param(
        'iiidddsssss',
        $tenantID, $user_id, $appointment_id, $paymentAmount,
        $amountPaid, $balance, $paymentMethod, $paymentStatus,
        $referenceNumber, $gcashReferenceNumber, $remarks
    );

    if (!$stmt->execute()) {
        sendResponse(500, ['status' => 'error', 'message' => 'Failed to create payment', 'error' => $stmt->error]);
    }

    $payment_id = $stmt->insert_id;
    $stmt->close();

    sendResponse(201, [
        'status' => 'success',
        'message' => 'Payment created successfully',
        'data' => [
            'payment_id' => $payment_id,
            'referenceNumber' => $referenceNumber,
            'paymentStatus' => $paymentStatus
        ]
    ]);
}

function handleUpdate($conn, $jsonInput = []) {
    $data = !empty($jsonInput) ? $jsonInput : $_REQUEST;

    $payment_id = normalizeId($data['payment_id'] ?? 0);
    if ($payment_id <= 0) {
        sendResponse(400, ['status' => 'error', 'message' => 'Invalid payment_id']);
    }

    $stmt = $conn->prepare("SELECT paymentAmount FROM payments WHERE payment_id = ?");
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        sendResponse(404, ['status' => 'error', 'message' => 'Payment not found']);
    }

    $updates = [];
    $types = '';
    $params = [];

    if (isset($data['amountPaid'])) {
        $amountPaid = normalizeMoney($data['amountPaid']);
        $balance = round($payment['paymentAmount'] - $amountPaid, 2);

        $updates[] = 'amountPaid = ?';
        $types .= 'd';
        $params[] = $amountPaid;

        $updates[] = 'balance = ?';
        $types .= 'd';
        $params[] = $balance;

        if ($amountPaid <= 0) {
            $status = 'Pending';
        } elseif ($amountPaid < $payment['paymentAmount']) {
            $status = 'Partial';
        } else {
            $status = 'Paid';
        }

        $updates[] = 'paymentStatus = ?';
        $types .= 's';
        $params[] = $status;

        if ($status === 'Paid') {
            $updates[] = 'paymentDate = NOW()';
        }
    }

    if (isset($data['paymentMethod'])) {
        $paymentMethod = normalizeMethod($data['paymentMethod']);
        $updates[] = 'paymentMethod = ?';
        $types .= 's';
        $params[] = $paymentMethod;
    }

    if (isset($data['gcashReferenceNumber'])) {
        $gcashReferenceNumber = sanitizeString($data['gcashReferenceNumber']);
        $updates[] = 'gcashReferenceNumber = ?';
        $types .= 's';
        $params[] = $gcashReferenceNumber;
    }

    if (isset($data['remarks'])) {
        $remarks = sanitizeString($data['remarks']);
        $updates[] = 'remarks = ?';
        $types .= 's';
        $params[] = $remarks;
    }

    if (empty($updates)) {
        sendResponse(400, ['status' => 'error', 'message' => 'No fields to update']);
    }

    $types .= 'i';
    $params[] = $payment_id;

    $query = "UPDATE payments SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE payment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        sendResponse(500, ['status' => 'error', 'message' => 'Failed to update payment', 'error' => $stmt->error]);
    }

    $stmt->close();

    sendResponse(200, [
        'status' => 'success',
        'message' => 'Payment updated successfully',
        'data' => ['payment_id' => $payment_id]
    ]);
}

function handleDelete($conn) {
    $payment_id = normalizeId($_GET['payment_id'] ?? $_POST['payment_id'] ?? 0);

    if ($payment_id <= 0) {
        sendResponse(400, ['status' => 'error', 'message' => 'Invalid payment_id']);
    }

    $stmt = $conn->prepare("DELETE FROM payments WHERE payment_id = ?");
    $stmt->bind_param('i', $payment_id);

    if (!$stmt->execute()) {
        sendResponse(500, ['status' => 'error', 'message' => 'Failed to delete payment', 'error' => $stmt->error]);
    }

    if ($stmt->affected_rows === 0) {
        sendResponse(404, ['status' => 'error', 'message' => 'Payment not found']);
    }

    $stmt->close();

    sendResponse(200, ['status' => 'success', 'message' => 'Payment deleted successfully']);
}
?>