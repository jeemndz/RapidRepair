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

function respond($statusCode, array $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeInviteCode($value)
{
    $digits = preg_replace('/\D+/', '', (string) $value);

    return str_pad(substr($digits, -6), 6, '0', STR_PAD_LEFT);
}

if (!isset($conn) || !$conn || $conn->connect_error) {
    respond(500, [
        'status' => 'error',
        'message' => 'Database connection failed.',
    ]);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$firstName = trim((string) ($data['firstName'] ?? ''));
$lastName = trim((string) ($data['lastName'] ?? ''));
$usernameInput = trim((string) ($data['username'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$address = trim((string) ($data['address'] ?? ''));
$phone = trim((string) ($data['phone'] ?? ''));
$password = (string) ($data['password'] ?? '');
$inviteCode = normalizeInviteCode($data['invite_code'] ?? $data['inviteCode'] ?? $data['code'] ?? '');

if ($firstName === '' || $lastName === '' || $email === '' || $phone === '' || $password === '' || $inviteCode === '' || $address === '') {
    respond(400, [
        'status' => 'error',
        'message' => 'Please fill all fields.',
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, [
        'status' => 'error',
        'message' => 'Please enter a valid email address.',
    ]);
}

if (strlen($password) < 6) {
    respond(400, [
        'status' => 'error',
        'message' => 'Password must be at least 6 characters long.',
    ]);
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Failed to prepare duplicate email check: ' . $conn->error);
    }

    $stmt->bind_param('s', $email);
    if (!$stmt->execute()) {
        throw new Exception('Failed to check existing user: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $stmt->close();

    if ($result && $result->num_rows > 0) {
        $conn->rollback();
        respond(409, [
            'status' => 'error',
            'message' => 'Email already registered.',
        ]);
    }

    $stmt = $conn->prepare('SELECT tenantID, ownerName, shopName FROM owners WHERE invite_code = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Failed to prepare invite code lookup: ' . $conn->error);
    }

    $stmt->bind_param('s', $inviteCode);
    if (!$stmt->execute()) {
        throw new Exception('Failed to validate invite code: ' . $stmt->error);
    }

    $ownerResult = $stmt->get_result();
    $owner = $ownerResult ? $ownerResult->fetch_assoc() : null;
    $stmt->close();

    if (!$owner) {
        $conn->rollback();
        respond(400, [
            'status' => 'error',
            'message' => 'Invalid invite code.',
        ]);
    }

    $tenantID = (int) ($owner['tenantID'] ?? 0);
    if ($tenantID <= 0) {
        throw new Exception('Invalid tenant linked to invite code.');
    }

    $fullName = trim($firstName . ' ' . $lastName);
    $usernameBase = $usernameInput !== '' ? $usernameInput : (strstr($email, '@', true) ?: $fullName);
    $username = strtolower(preg_replace('/[^a-z0-9._-]+/', '.', $usernameBase));
    $username = trim($username, '.');
    if ($username === '') {
        $username = strtolower(preg_replace('/\s+/', '.', $fullName));
        $username = trim($username, '.');
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (tenantID, fullName, username, address, email, password, contactNumber, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'client')");
    if (!$stmt) {
        throw new Exception('Failed to prepare user insert: ' . $conn->error);
    }

    $stmt->bind_param('issssss', $tenantID, $fullName, $username, $address, $email, $hashedPassword, $phone);
    if (!$stmt->execute()) {
        throw new Exception('Registration failed: ' . $stmt->error);
    }
    $newUserId = (int) $conn->insert_id;
    $stmt->close();

    // Safety check: ensure the inserted row is tied to the tenant from invite_code.
    $stmt = $conn->prepare('SELECT tenantID FROM users WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Failed to prepare tenant verification query: ' . $conn->error);
    }

    $stmt->bind_param('i', $newUserId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to verify inserted tenantID: ' . $stmt->error);
    }

    $verifyResult = $stmt->get_result();
    $insertedRow = $verifyResult ? $verifyResult->fetch_assoc() : null;
    $stmt->close();

    $insertedTenantId = (int) ($insertedRow['tenantID'] ?? 0);
    if ($insertedTenantId !== $tenantID) {
        throw new Exception('tenantID mismatch after insert. Registration was cancelled.');
    }

    $conn->commit();

    respond(200, [
        'status' => 'success',
        'message' => 'User registered successfully.',
        'user_id' => $newUserId,
        'tenantID' => $tenantID,
        'invite_code' => $inviteCode,
        'shopName' => $owner['shopName'] ?? '',
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }

    respond(500, [
        'status' => 'error',
        'message' => 'Registration failed.',
        'details' => $e->getMessage(),
    ]);
}

?>