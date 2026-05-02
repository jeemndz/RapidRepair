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
$inviteCode = normalizeInviteCode($data['invite_code'] ?? $data['inviteCode'] ?? '');

if (
    $firstName === '' ||
    $lastName === '' ||
    $usernameInput === '' ||
    $email === '' ||
    $address === '' ||
    $phone === '' ||
    $password === '' ||
    $inviteCode === ''
) {
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
    $stmt->execute();
    $emailResult = $stmt->get_result();
    $stmt->close();

    if ($emailResult && $emailResult->num_rows > 0) {
        $conn->rollback();
        respond(409, [
            'status' => 'error',
            'message' => 'Email already registered.',
        ]);
    }

    $stmt = $conn->prepare("
        SELECT tenantID, ownerName, shopName
        FROM owners
        WHERE invite_code = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare invite code lookup: ' . $conn->error);
    }

    $stmt->bind_param('s', $inviteCode);
    $stmt->execute();
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
    $username = strtolower(preg_replace('/[^a-z0-9._-]+/', '.', $usernameInput));
    $username = trim($username, '.');

    if ($username === '') {
        $username = strtolower(preg_replace('/\s+/', '.', $fullName));
        $username = trim($username, '.');
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO users (
            tenantID,
            fullName,
            username,
            address,
            email,
            password,
            contactNumber,
            role
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'client')
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare user insert: ' . $conn->error);
    }

    $stmt->bind_param(
        'issssss',
        $tenantID,
        $fullName,
        $username,
        $address,
        $email,
        $hashedPassword,
        $phone
    );

    if (!$stmt->execute()) {
        throw new Exception('Registration failed: ' . $stmt->error);
    }

    $newUserId = (int) $conn->insert_id;
    $stmt->close();

    $conn->commit();

    respond(200, [
        'status' => 'success',
        'message' => 'User registered successfully.',
        'user_id' => $newUserId,
        'tenantID' => $tenantID,
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