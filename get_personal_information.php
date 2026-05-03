<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

$tenantID = isset($_GET['tenantID']) ? (int) $_GET['tenantID'] : 0;
$userID = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($tenantID <= 0 || $userID <= 0) {
    json_response(400, [
        'status' => 'error',
        'success' => false,
        'message' => 'tenantID and user_id are required.',
    ]);
}

$sql = "
    SELECT
        user_id,
        tenantID,
        fullName,
        username,
        address,
        email,
        contactNumber,
        role
    FROM users
    WHERE user_id = ?
      AND tenantID = ?
      AND role = 'client'
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    json_response(500, [
        'status' => 'error',
        'success' => false,
        'message' => 'Failed to prepare user query: ' . mysqli_error($conn),
    ]);
}

mysqli_stmt_bind_param($stmt, 'ii', $userID, $tenantID);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    json_response(404, [
        'status' => 'error',
        'success' => false,
        'message' => 'User information not found.',
    ]);
}

json_response(200, [
    'status' => 'success',
    'success' => true,
    'user' => [
        'user_id' => (int) $user['user_id'],
        'tenantID' => (int) $user['tenantID'],
        'fullName' => $user['fullName'],
        'username' => $user['username'],
        'address' => $user['address'],
        'email' => $user['email'],
        'contactNumber' => $user['contactNumber'],
        'role' => $user['role'],
    ],
]);
