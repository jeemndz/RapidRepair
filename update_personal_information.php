<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

function json_response($statusCode, $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function read_input()
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, [
        'status' => 'error',
        'success' => false,
        'message' => 'POST method is required.',
    ]);
}

$input = read_input();

$tenantID = isset($input['tenantID']) ? (int) $input['tenantID'] : 0;
$userID = isset($input['user_id']) ? (int) $input['user_id'] : 0;
$fullName = isset($input['fullName']) ? trim((string) $input['fullName']) : '';
$username = isset($input['username']) ? trim((string) $input['username']) : '';
$email = isset($input['email']) ? trim((string) $input['email']) : '';
$contactNumber = isset($input['contactNumber']) ? trim((string) $input['contactNumber']) : '';
$address = isset($input['address']) ? trim((string) $input['address']) : '';

if ($tenantID <= 0 || $userID <= 0) {
    json_response(400, [
        'status' => 'error',
        'success' => false,
        'message' => 'tenantID and user_id are required.',
    ]);
}

if ($fullName === '') {
    json_response(400, [
        'status' => 'error',
        'success' => false,
        'message' => 'Full name is required.',
    ]);
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(400, [
        'status' => 'error',
        'success' => false,
        'message' => 'A valid email address is required.',
    ]);
}

$checkSql = "
    SELECT user_id
    FROM users
    WHERE tenantID = ?
      AND email = ?
      AND user_id <> ?
    LIMIT 1
";

$checkStmt = mysqli_prepare($conn, $checkSql);

if (!$checkStmt) {
    json_response(500, [
        'status' => 'error',
        'success' => false,
        'message' => 'Failed to prepare duplicate email check: ' . mysqli_error($conn),
    ]);
}

mysqli_stmt_bind_param($checkStmt, 'isi', $tenantID, $email, $userID);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);
$existing = mysqli_fetch_assoc($checkResult);
mysqli_stmt_close($checkStmt);

if ($existing) {
    json_response(409, [
        'status' => 'error',
        'success' => false,
        'message' => 'This email address is already used by another account.',
    ]);
}

$updateSql = "
    UPDATE users
    SET
        fullName = ?,
        username = ?,
        email = ?,
        contactNumber = ?,
        address = ?
    WHERE user_id = ?
      AND tenantID = ?
      AND role = 'client'
";

$stmt = mysqli_prepare($conn, $updateSql);

if (!$stmt) {
    json_response(500, [
        'status' => 'error',
        'success' => false,
        'message' => 'Failed to prepare user update: ' . mysqli_error($conn),
    ]);
}

mysqli_stmt_bind_param($stmt, 'sssssii', $fullName, $username, $email, $contactNumber, $address, $userID, $tenantID);

if (!mysqli_stmt_execute($stmt)) {
    json_response(500, [
        'status' => 'error',
        'success' => false,
        'message' => 'Failed to update user information: ' . mysqli_stmt_error($stmt),
    ]);
}

mysqli_stmt_close($stmt);

json_response(200, [
    'status' => 'success',
    'success' => true,
    'message' => 'Personal information updated successfully.',
    'user' => [
        'user_id' => $userID,
        'tenantID' => $tenantID,
        'fullName' => $fullName,
        'username' => $username,
        'address' => $address,
        'email' => $email,
        'contactNumber' => $contactNumber,
        'role' => 'client',
    ],
]);
