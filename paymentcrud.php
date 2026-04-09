<?php
/**
 * Payment CRUD API
 * Handles payment operations: list, create, update, delete
 * Database: rapidrepairs (Azure MySQL)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database Configuration
// Azure MySQL credentials
$db_host = getenv('DB_HOST') ?: 'rapidrepairs.mysql.database.azure.com';
$db_name = getenv('DB_NAME') ?: 'rapidrepairs';
$db_user = getenv('DB_USER') ?: 'rradmin1';  // Correct Azure format: username@servername
$db_pass = getenv('DB_PASS') ?: getenv('rradmin123!');

// Fallback to db.php from GitHub if password not in environment
if (empty($db_pass)) {
    $db_config_path = __DIR__ . '/db.php';
    
    // Try local db.php first
    if (file_exists($db_config_path)) {
        include $db_config_path;
    } else {
        // Fallback to GitHub
        $db_config_url = 'https://raw.githubusercontent.com/jeemnndz/RapidRepair/main/db.php';
        $db_config_content = @file_get_contents($db_config_url);
        
        if ($db_config_content !== false) {
            // Safely extract variables using regex
            preg_match('/\$db_host\s*=\s*[\'"](.+?)[\'"]/i', $db_config_content, $m); if (!empty($m[1])) $db_host = $m[1];
            preg_match('/\$db_name\s*=\s*[\'"](.+?)[\'"]/i', $db_config_content, $m); if (!empty($m[1])) $db_name = $m[1];
            preg_match('/\$db_user\s*=\s*[\'"](.+?)[\'"]/i', $db_config_content, $m); if (!empty($m[1])) $db_user = $m[1];
            preg_match('/\$db_pass\s*=\s*[\'"](.+?)[\'"]/i', $db_config_content, $m); if (!empty($m[1])) $db_pass = $m[1];
        }
    }
}

// Database Connection
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }
    
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection error: ' . $e->getMessage()
    ]);
    exit;
}

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

function response($status, $message, $data = null) {
    header('Content-Type: application/json');
    return json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
}

// Main Request Handler
$action = isset($_REQUEST['action']) ? sanitizeString($_REQUEST['action']) : '';

try {
    switch ($action) {
        case 'test':
            // Diagnostic endpoint
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Connection successful',
                'database' => [
                    'host' => $db_host,
                    'name' => $db_name,
                    'user' => $db_user,
                    'charset' => $conn->character_set_name(),
                ]
            ]);
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
            http_response_code(400);
            echo response('error', 'Invalid action. Use: list, create, update, delete, or test');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo response('error', $e->getMessage());
} finally {
    if (isset($conn)) {
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

    http_response_code(200);
    echo response('success', 'Payments retrieved successfully', $payments);
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

    http_response_code(201);
    echo response('success', 'Payment created successfully', [
        'payment_id' => $payment_id,
        'referenceNumber' => $referenceNumber,
        'paymentStatus' => $paymentStatus,
    ]);
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

    http_response_code(200);
    echo response('success', 'Payment updated successfully');
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

    http_response_code(200);
    echo response('success', 'Payment deleted successfully');
}
?>
