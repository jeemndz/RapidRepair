<?php
/**
 * Payment CRUD API
 * Handles payment operations: list, create, update, delete
 * Database: rapidrepairs (Azure MySQL)
 */

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-API-Version: 1.0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Helper Functions
function sendResponse($statusCode, $data)
{
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
    $valid_statuses = ['Pending', 'Partial', 'Paid', 'Failed', 'Refunded'];
    $status = trim($value);
    return in_array($status, $valid_statuses) ? $status : 'Pending';
}

function normalizeMethod($value) {
    $valid_methods = ['Cash', 'GCash', 'Card', 'Bank Transfer'];
    $method = trim($value);
    return in_array($method, $valid_methods) ? $method : 'Cash';
}

function sanitizeString($str) {
    return trim(strip_tags($str ?? ''));
}

function normalizePayment($row)
{
    return [
        'payment_id' => (int)($row['payment_id'] ?? 0),
        'tenantID' => (int)($row['tenantID'] ?? 0),
        'user_id' => (int)($row['user_id'] ?? 0),
        'appointment_id' => (int)($row['appointment_id'] ?? 0),
        'paymentAmount' => (float)($row['paymentAmount'] ?? 0),
        'amountPaid' => (float)($row['amountPaid'] ?? 0),
        'balance' => (float)($row['balance'] ?? 0),
        'paymentMethod' => (string)($row['paymentMethod'] ?? 'Cash'),
        'paymentDate' => $row['paymentDate'] !== null ? (string)$row['paymentDate'] : null,
        'paymentStatus' => (string)($row['paymentStatus'] ?? 'Pending'),
        'referenceNumber' => $row['referenceNumber'] !== null ? (string)$row['referenceNumber'] : null,
        'gcashReferenceNumber' => $row['gcashReferenceNumber'] !== null ? (string)$row['gcashReferenceNumber'] : null,
        'remarks' => $row['remarks'] !== null ? (string)$row['remarks'] : null,
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'appointment_date' => $row['appointment_date'] !== null ? (string)$row['appointment_date'] : '',
        'appointment_time' => $row['appointment_time'] !== null ? (string)$row['appointment_time'] : '',
    ];
}

// Database connection check
if (!isset($conn) || $conn->connect_error) {
    sendResponse(500, [
        'status' => 'error',
        'message' => 'Database connection failed',
        'error' => 'Unable to establish database connection',
    ]);
}

// Handle empty GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    sendResponse(200, [
        'status' => 'ok',
        'message' => 'Payment CRUD API is running',
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
}

// Get action parameter
$action = '';
if (isset($_GET['action'])) {
    $action = sanitizeString($_GET['action']);
} elseif (isset($_POST['action'])) {
    $action = sanitizeString($_POST['action']);
}

if (empty($action)) {
    sendResponse(400, [
        'status' => 'error',
        'message' => 'Missing action parameter',
        'available_actions' => ['list', 'create', 'update', 'delete'],
    ]);
}

try {
    switch ($action) {
        case 'list':
            handleList($conn);
            break;
        
        case 'create':
            handleCreate($conn);
            break;
        
        case 'update':
            handleUpdate($conn);
            break;
        
        case 'delete':
            handleDelete($conn);
            break;
        
        default:
            sendResponse(400, [
                'status' => 'error',
                'message' => 'Invalid action',
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

/**
 * LIST Payments
 * Params: action, tenantID, user_id (optional), limit, offset, paymentStatus (optional)
 */
function handleList($conn) {
    $tenantID = normalizeId($_GET['tenantID'] ?? $_POST['tenantID'] ?? 0);
    
    if ($tenantID <= 0) {
        sendResponse(400, [
            'status' => 'error',
            'message' => 'Invalid or missing tenantID',
        ]);
    }

    $user_id = isset($_GET['user_id']) ? normalizeId($_GET['user_id']) : (isset($_POST['user_id']) ? normalizeId($_POST['user_id']) : 0);
    $limit = min(max((int)($_GET['limit'] ?? $_POST['limit'] ?? 50), 1), 100);
    $offset = max((int)($_GET['offset'] ?? $_POST['offset'] ?? 0), 0);
    $paymentStatus = isset($_GET['paymentStatus']) ? sanitizeString($_GET['paymentStatus']) : (isset($_POST['paymentStatus']) ? sanitizeString($_POST['paymentStatus']) : '');

    // Base query
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
        a.status as appointment_status,
        a.total_amount
    FROM payments p
    LEFT JOIN appointments a ON p.appointment_id = a.appointment_id
    WHERE p.tenantID = ?";

    $types = 'i';
    $params = [$tenantID];

    // Filter by user_id if provided
    if ($user_id > 0) {
        $query .= " AND p.user_id = ?";
        $types .= 'i';
        $params[] = $user_id;
    }

    // Filter by payment status if provided
    if (!empty($paymentStatus)) {
        $query .= " AND p.paymentStatus = ?";
        $types .= 's';
        $params[] = $paymentStatus;
    }

    // Order and limit
    $query .= " ORDER BY p.paymentDate DESC, p.created_at DESC LIMIT ? OFFSET ?";
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;

    // Execute query
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to prepare statement',
            'error' => $conn->error,
        ]);
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Query execution failed',
            'error' => $stmt->error,
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
        'tenantID' => $tenantID,
        'user_id' => $user_id,
        'paymentCount' => count($payments),
        'payments' => $payments,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
}

/**
 * CREATE Payment
 * Params: action, tenantID, user_id, appointment_id, paymentAmount (required)
 */
function handleCreate($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;

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

    // Validation
    if ($user_id <= 0) {
        sendResponse(400, [
            'status' => 'error',
            'message' => 'Invalid user_id',
        ]);
    }
    if ($appointment_id <= 0) {
        sendResponse(400, [
            'status' => 'error',
            'message' => 'Invalid appointment_id',
        ]);
    }
    if ($paymentAmount <= 0) {
        sendResponse(400, [
            'status' => 'error',
            'message' => 'Invalid paymentAmount',
        ]);
    }

    // Calculate balance
    $balance = round($paymentAmount - $amountPaid, 2);

    // Generate reference number if not provided
    if (empty($referenceNumber)) {
        $referenceNumber = 'RR-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    // Insert payment record
    $query = "INSERT INTO payments (
        tenantID, user_id, appointment_id, paymentAmount, 
        amountPaid, balance, paymentMethod, paymentStatus,
        referenceNumber, gcashReferenceNumber, remarks
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to prepare statement',
            'error' => $conn->error,
        ]);
    }

    $stmt->bind_param(
        'iiidddsssss',
        $tenantID, $user_id, $appointment_id, $paymentAmount,
        $amountPaid, $balance, $paymentMethod, $paymentStatus,
        $referenceNumber, $gcashReferenceNumber, $remarks
    );

    if (!$stmt->execute()) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to create payment',
            'error' => $stmt->error,
        ]);
    }

    $payment_id = $stmt->insert_id;
    $stmt->close();

    sendResponse(201, [
        'status' => 'success',
        'message' => 'Payment created successfully',
        'data' => [
            'payment_id' => $payment_id,
            'referenceNumber' => $referenceNumber,
            'paymentStatus' => $paymentStatus,
        ],
    ]);
}

/**
 * UPDATE Payment
 * Params: action, payment_id, amountPaid (optional), paymentStatus (optional), paymentMethod (optional)
 */
function handleUpdate($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;

    $payment_id = normalizeId($data['payment_id'] ?? 0);
    if ($payment_id <= 0) {
        sendResponse(400, [
            'status' => 'error',
            'message' => 'Invalid payment_id',
        ]);
    }

    // Fetch current payment record
    $stmt = $conn->prepare("SELECT paymentAmount FROM payments WHERE payment_id = ?");
    if (!$stmt) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to prepare statement',
        ]);
    }
    
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        sendResponse(404, [
            'status' => 'error',
            'message' => 'Payment not found',
        ]);
    }

    // Prepare update fields
    $updates = [];
    $types = '';
    $params = [];

    if (isset($data['amountPaid'])) {
        $amountPaid = normalizeMoney($data['amountPaid']);
        $updates[] = 'amountPaid = ?';
        $types .= 'd';
        $params[] = $amountPaid;

        // Update balance
        $balance = round($payment['paymentAmount'] - $amountPaid, 2);
        $updates[] = 'balance = ?';
        $types .= 'd';
        $params[] = $balance;

        // Auto-update paymentStatus based on amount paid
        if ($amountPaid <= 0) {
            $updates[] = 'paymentStatus = "Pending"';
        } elseif ($amountPaid < $payment['paymentAmount']) {
            $updates[] = 'paymentStatus = "Partial"';
        } elseif ($amountPaid >= $payment['paymentAmount']) {
            $updates[] = 'paymentStatus = "Paid"';
            $updates[] = 'paymentDate = NOW()';
        }
    }

    if (isset($data['paymentStatus'])) {
        $paymentStatus = normalizeStatus($data['paymentStatus']);
        $updates[] = 'paymentStatus = ?';
        $types .= 's';
        $params[] = $paymentStatus;

        if ($paymentStatus === 'Paid' && !isset($data['amountPaid'])) {
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
        sendResponse(400, [
            'status' => 'error',
            'message' => 'No fields to update',
        ]);
    }

    // Add payment_id to params
    $types .= 'i';
    $params[] = $payment_id;

    // Execute update
    $query = "UPDATE payments SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE payment_id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to prepare statement',
            'error' => $conn->error,
        ]);
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to update payment',
            'error' => $stmt->error,
        ]);
    }

    $stmt->close();

    sendResponse(200, [
        'status' => 'success',
        'message' => 'Payment updated successfully',
    ]);
}

/**
 * DELETE Payment
 * Params: action, payment_id
 */
function handleDelete($conn) {
    $payment_id = normalizeId($_GET['payment_id'] ?? $_POST['payment_id'] ?? 0);
    
    if ($payment_id <= 0) {
        sendResponse(400, [
            'status' => 'error',
            'message' => 'Invalid payment_id',
        ]);
    }

    // Delete payment record
    $stmt = $conn->prepare("DELETE FROM payments WHERE payment_id = ?");
    if (!$stmt) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to prepare statement',
        ]);
    }
    
    $stmt->bind_param('i', $payment_id);

    if (!$stmt->execute()) {
        sendResponse(500, [
            'status' => 'error',
            'message' => 'Failed to delete payment',
            'error' => $stmt->error,
        ]);
    }

    if ($stmt->affected_rows === 0) {
        sendResponse(404, [
            'status' => 'error',
            'message' => 'Payment not found',
        ]);
    }

    $stmt->close();

    sendResponse(200, [
        'status' => 'success',
        'message' => 'Payment deleted successfully',
    ]);
}
?>
