<?php
/**
 * Payment CRUD API
 * Handles payment operations: list, create, update, delete
 * Database: rapidrepairs (Azure MySQL)
 */

// CRITICAL: Set JSON headers and error handling FIRST
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
ob_start();

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    die('{}');
}

// Database Configuration - Direct credentials as fallback
$db_host = 'rapidrepairs.mysql.database.azure.com';
$db_name = 'rapidrepairs';
$db_user = 'rradmin1@rapidrepairs';
$db_pass = 'rradmin123!';  // Your MySQL password

// Try environment variables first (if set)
if (!empty(getenv('DB_PASS'))) {
    $db_pass = getenv('DB_PASS');
}

// Try to load db.php from parent or current directory
if (file_exists(__DIR__ . '/../db.php')) {
    include __DIR__ . '/../db.php';
} elseif (file_exists(__DIR__ . '/db.php')) {
    include __DIR__ . '/db.php';
}

// Database Connection
$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    ob_end_clean();
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error,
        'debug' => [
            'host' => $db_host,
            'user' => $db_user,
            'database' => $db_name
        ]
    ]));
}

$conn->set_charset('utf8mb4');

// Helper Functions
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

function sendJson($status, $message, $data = null, $httpCode = 200) {
    ob_end_clean();
    http_response_code($httpCode);
    die(json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// Main Request Handler
$action = isset($_REQUEST['action']) ? sanitizeString($_REQUEST['action']) : '';

try {
    switch ($action) {
        case 'test':
            sendJson('success', 'Connection successful', [
                'host' => $db_host,
                'database' => $db_name,
                'user' => $db_user,
                'charset' => $conn->character_set_name(),
            ], 200);
            break;
        
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
            sendJson('error', 'Invalid action. Use: list, create, update, delete, or test', null, 400);
    }
} catch (Throwable $e) {
    sendJson('error', $e->getMessage(), null, 500);
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
    $tenantID = normalizeId($_REQUEST['tenantID'] ?? 0, 1);
    $user_id = isset($_REQUEST['user_id']) ? normalizeId($_REQUEST['user_id']) : 0;
    $limit = min(max((int)($_REQUEST['limit'] ?? 50), 1), 100);
    $offset = max((int)($_REQUEST['offset'] ?? 0), 0);
    $paymentStatus = isset($_REQUEST['paymentStatus']) ? sanitizeString($_REQUEST['paymentStatus']) : '';

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
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $payments = [];

    while ($row = $result->fetch_assoc()) {
        $payments[] = [
            'payment_id' => normalizeId($row['payment_id']),
            'tenantID' => normalizeId($row['tenantID']),
            'user_id' => normalizeId($row['user_id']),
            'appointment_id' => normalizeId($row['appointment_id']),
            'paymentAmount' => normalizeMoney($row['paymentAmount']),
            'amountPaid' => normalizeMoney($row['amountPaid']),
            'balance' => normalizeMoney($row['balance']),
            'paymentMethod' => $row['paymentMethod'] ?: 'Cash',
            'paymentDate' => $row['paymentDate'] ?: null,
            'paymentStatus' => $row['paymentStatus'] ?: 'Pending',
            'referenceNumber' => $row['referenceNumber'] ?: null,
            'gcashReferenceNumber' => $row['gcashReferenceNumber'] ?: null,
            'remarks' => $row['remarks'] ?: null,
            'created_at' => $row['created_at'] ?: '',
            'updated_at' => $row['updated_at'] ?: '',
            'appointment_date' => $row['appointment_date'] ?: '',
            'appointment_time' => $row['appointment_time'] ?: '',
        ];
    }

    $stmt->close();

    sendJson('success', 'Payments retrieved successfully', $payments, 200);
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
    if ($user_id <= 0) throw new Exception('Invalid user_id');
    if ($appointment_id <= 0) throw new Exception('Invalid appointment_id');
    if ($paymentAmount <= 0) throw new Exception('Invalid paymentAmount');

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
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param(
        'iiidddsssss',
        $tenantID, $user_id, $appointment_id, $paymentAmount,
        $amountPaid, $balance, $paymentMethod, $paymentStatus,
        $referenceNumber, $gcashReferenceNumber, $remarks
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $payment_id = $stmt->insert_id;
    $stmt->close();

    sendJson('success', 'Payment created successfully', [
        'payment_id' => $payment_id,
        'referenceNumber' => $referenceNumber,
        'paymentStatus' => $paymentStatus,
    ], 201);
}

/**
 * UPDATE Payment
 * Params: action, payment_id, amountPaid (optional), paymentStatus (optional), paymentMethod (optional)
 */
function handleUpdate($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;

    $payment_id = normalizeId($data['payment_id'] ?? 0);
    if ($payment_id <= 0) throw new Exception('Invalid payment_id');

    // Fetch current payment record
    $stmt = $conn->prepare("SELECT paymentAmount FROM payments WHERE payment_id = ?");
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        throw new Exception('Payment not found');
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
        throw new Exception('No fields to update');
    }

    // Add payment_id to params
    $types .= 'i';
    $params[] = $payment_id;

    // Execute update
    $query = "UPDATE payments SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE payment_id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $stmt->close();

    sendJson('success', 'Payment updated successfully', null, 200);
}

/**
 * DELETE Payment
 * Params: action, payment_id
 */
function handleDelete($conn) {
    $payment_id = normalizeId($_REQUEST['payment_id'] ?? 0);
    if ($payment_id <= 0) throw new Exception('Invalid payment_id');

    // Delete payment record
    $stmt = $conn->prepare("DELETE FROM payments WHERE payment_id = ?");
    $stmt->bind_param('i', $payment_id);

    if (!$stmt->execute()) {
        throw new Exception('Delete failed: ' . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception('Payment not found');
    }

    $stmt->close();

    sendJson('success', 'Payment deleted successfully', null, 200);
}
?>
