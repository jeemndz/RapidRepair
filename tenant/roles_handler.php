<?php
/**
 * Role Management Handler
 * Handles tenant-scoped CRUD operations for roles table
 */

ob_start();
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($exception) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $exception->getMessage()
    ]);
    exit;
});

function jsonResponse(int $statusCode, array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : null);
$tenantID = isset($_SESSION['tenantID']) ? (int) $_SESSION['tenantID'] : 0;

if ($tenantID <= 0) {
    jsonResponse(401, ['success' => false, 'message' => 'Unauthorized']);
}

switch ($action) {
    case 'get_all':
        getRoles($conn, $tenantID);
        break;
    case 'get_single':
        getRoleById($conn, $tenantID);
        break;
    case 'add':
        addRole($conn, $tenantID);
        break;
    case 'update':
        updateRole($conn, $tenantID);
        break;
    case 'delete':
        deleteRole($conn, $tenantID);
        break;
    default:
        jsonResponse(400, ['success' => false, 'message' => 'Invalid action']);
}

function getRoles(mysqli $conn, int $tenantID): void
{
    $query = "SELECT role_id, role_name, username, email, access_scope, is_active, status, tenantID, created_at, updated_at
              FROM roles
              WHERE tenantID = ?
              ORDER BY created_at DESC, role_id DESC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $stmt->bind_param('i', $tenantID);
    $stmt->execute();
    $result = $stmt->get_result();

    $roles = [];
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }

    $stmt->close();

    jsonResponse(200, [
        'success' => true,
        'roles' => $roles,
        'count' => count($roles)
    ]);
}

function getRoleById(mysqli $conn, int $tenantID): void
{
    $roleId = isset($_GET['role_id']) ? (int) $_GET['role_id'] : 0;
    if ($roleId <= 0) {
        jsonResponse(400, ['success' => false, 'message' => 'Role ID is required']);
    }

    $query = "SELECT role_id, role_name, username, email, access_scope, is_active, status, tenantID, created_at, updated_at
              FROM roles
              WHERE role_id = ? AND tenantID = ?
              LIMIT 1";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $stmt->bind_param('ii', $roleId, $tenantID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        jsonResponse(404, ['success' => false, 'message' => 'Role not found']);
    }

    $role = $result->fetch_assoc();
    $stmt->close();

    jsonResponse(200, [
        'success' => true,
        'role' => $role
    ]);
}

function addRole(mysqli $conn, int $tenantID): void
{
    $roleName = trim((string) ($_POST['role_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $accessScope = trim((string) ($_POST['access_scope'] ?? ''));
    $status = (string) ($_POST['status'] ?? 'Active');

    if ($roleName === '' || $username === '' || $email === '' || $password === '') {
        jsonResponse(400, ['success' => false, 'message' => 'Role name, username, email, and password are required']);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, ['success' => false, 'message' => 'Invalid email format']);
    }

    if (!in_array($status, ['Active', 'Inactive'], true)) {
        $status = 'Active';
    }

    if ($accessScope === '') {
        $accessScope = 'General';
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $isActive = $status === 'Active' ? 1 : 0;

    $checkQuery = "SELECT role_id FROM roles WHERE tenantID = ? AND (username = ? OR email = ?) LIMIT 1";
    $checkStmt = $conn->prepare($checkQuery);
    if (!$checkStmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $checkStmt->bind_param('iss', $tenantID, $username, $email);
    $checkStmt->execute();
    $exists = $checkStmt->get_result()->num_rows > 0;
    $checkStmt->close();

    if ($exists) {
        jsonResponse(409, ['success' => false, 'message' => 'Username or email already exists for this tenant']);
    }

    $query = "INSERT INTO roles (role_name, username, email, password, access_scope, is_active, status, tenantID)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $stmt->bind_param('sssssisi', $roleName, $username, $email, $hashedPassword, $accessScope, $isActive, $status, $tenantID);

    if ($stmt->execute()) {
        $newRoleId = $stmt->insert_id;
        $stmt->close();

        jsonResponse(200, [
            'success' => true,
            'message' => 'User role added successfully',
            'role_id' => $newRoleId
        ]);
    }

    $error = $conn->error;
    $stmt->close();

    if (strpos($error, 'Duplicate entry') !== false) {
        jsonResponse(409, ['success' => false, 'message' => 'Username or email already exists']);
    }

    jsonResponse(500, ['success' => false, 'message' => 'Error adding role: ' . $error]);
}

function updateRole(mysqli $conn, int $tenantID): void
{
    $roleId = isset($_POST['role_id']) ? (int) $_POST['role_id'] : 0;
    $roleName = trim((string) ($_POST['role_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $accessScope = trim((string) ($_POST['access_scope'] ?? ''));
    $status = (string) ($_POST['status'] ?? 'Active');

    if ($roleId <= 0 || $roleName === '' || $username === '' || $email === '') {
        jsonResponse(400, ['success' => false, 'message' => 'Role ID, role name, username, and email are required']);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, ['success' => false, 'message' => 'Invalid email format']);
    }

    if (!in_array($status, ['Active', 'Inactive'], true)) {
        $status = 'Active';
    }

    if ($accessScope === '') {
        $accessScope = 'General';
    }

    $verifyQuery = "SELECT role_id FROM roles WHERE role_id = ? AND tenantID = ? LIMIT 1";
    $verifyStmt = $conn->prepare($verifyQuery);
    if (!$verifyStmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $verifyStmt->bind_param('ii', $roleId, $tenantID);
    $verifyStmt->execute();

    if ($verifyStmt->get_result()->num_rows === 0) {
        $verifyStmt->close();
        jsonResponse(403, ['success' => false, 'message' => 'Role not found or unauthorized']);
    }
    $verifyStmt->close();

    $checkQuery = "SELECT role_id FROM roles WHERE tenantID = ? AND role_id <> ? AND (username = ? OR email = ?) LIMIT 1";
    $checkStmt = $conn->prepare($checkQuery);
    if (!$checkStmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $checkStmt->bind_param('iiss', $tenantID, $roleId, $username, $email);
    $checkStmt->execute();
    $exists = $checkStmt->get_result()->num_rows > 0;
    $checkStmt->close();

    if ($exists) {
        jsonResponse(409, ['success' => false, 'message' => 'Username or email already exists for this tenant']);
    }

    $isActive = $status === 'Active' ? 1 : 0;

    if ($password !== '') {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $query = "UPDATE roles
                  SET role_name = ?, username = ?, email = ?, password = ?, access_scope = ?, is_active = ?, status = ?
                  WHERE role_id = ? AND tenantID = ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }

        $stmt->bind_param('sssssisii', $roleName, $username, $email, $hashedPassword, $accessScope, $isActive, $status, $roleId, $tenantID);
    } else {
        $query = "UPDATE roles
                  SET role_name = ?, username = ?, email = ?, access_scope = ?, is_active = ?, status = ?
                  WHERE role_id = ? AND tenantID = ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }

        $stmt->bind_param('ssssisii', $roleName, $username, $email, $accessScope, $isActive, $status, $roleId, $tenantID);
    }

    if ($stmt->execute()) {
        $stmt->close();
        jsonResponse(200, [
            'success' => true,
            'message' => 'User role updated successfully'
        ]);
    }

    $error = $conn->error;
    $stmt->close();

    if (strpos($error, 'Duplicate entry') !== false) {
        jsonResponse(409, ['success' => false, 'message' => 'Username or email already exists']);
    }

    jsonResponse(500, ['success' => false, 'message' => 'Error updating role: ' . $error]);
}

function deleteRole(mysqli $conn, int $tenantID): void
{
    $roleId = isset($_POST['role_id']) ? (int) $_POST['role_id'] : 0;
    if ($roleId <= 0) {
        jsonResponse(400, ['success' => false, 'message' => 'Role ID is required']);
    }

    $verifyQuery = "SELECT role_id FROM roles WHERE role_id = ? AND tenantID = ? LIMIT 1";
    $verifyStmt = $conn->prepare($verifyQuery);
    if (!$verifyStmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $verifyStmt->bind_param('ii', $roleId, $tenantID);
    $verifyStmt->execute();

    if ($verifyStmt->get_result()->num_rows === 0) {
        $verifyStmt->close();
        jsonResponse(403, ['success' => false, 'message' => 'Role not found or unauthorized']);
    }
    $verifyStmt->close();

    $query = "DELETE FROM roles WHERE role_id = ? AND tenantID = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $stmt->bind_param('ii', $roleId, $tenantID);

    if ($stmt->execute()) {
        $stmt->close();
        jsonResponse(200, [
            'success' => true,
            'message' => 'User role deleted successfully'
        ]);
    }

    $error = $conn->error;
    $stmt->close();
    jsonResponse(500, ['success' => false, 'message' => 'Error deleting role: ' . $error]);
}
