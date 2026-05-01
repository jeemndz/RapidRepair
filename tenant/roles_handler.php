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
require_once '../log_helper.php';

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
    case 'get_count':
        getRolesCount($conn, $tenantID);
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
    $query = "SELECT role_id, first_name, last_name, role_name, username, email, access_scope, is_active, status, tenantID, created_at, updated_at
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

function getRolesCount(mysqli $conn, int $tenantID): void
{
    $query = "SELECT COUNT(*) as total FROM roles WHERE tenantID = ? AND status = 'Active'";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    $stmt->bind_param('i', $tenantID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    jsonResponse(200, [
        'success' => true,
        'count' => (int)$row['total']
    ]);
}

function getRoleById(mysqli $conn, int $tenantID): void
{
    $roleId = isset($_GET['role_id']) ? (int) $_GET['role_id'] : 0;
    if ($roleId <= 0) {
        jsonResponse(400, ['success' => false, 'message' => 'Role ID is required']);
    }

    $query = "SELECT role_id, first_name, last_name, role_name, username, email, access_scope, is_active, status, tenantID, created_at, updated_at
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

    error_log('getRoleById - roleId: ' . $roleId . ', access_scope: "' . $role['access_scope'] . '"');

    jsonResponse(200, [
        'success' => true,
        'role' => $role
    ]);
}

function addRole(mysqli $conn, int $tenantID): void
{
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $roleName = trim((string) ($_POST['role_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $accessScope = trim((string) ($_POST['access_scope'] ?? ''));
    $status = (string) ($_POST['status'] ?? 'Active');

    if ($firstName === '' || $lastName === '' || $username === '' || $email === '' || $password === '') {
        jsonResponse(400, ['success' => false, 'message' => 'First name, last name, username, email, and password are required']);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, ['success' => false, 'message' => 'Invalid email format']);
    }

    if (!in_array($status, ['Active', 'Inactive'], true)) {
        $status = 'Active';
    }

    if ($accessScope === '') {
        jsonResponse(400, ['success' => false, 'message' => 'At least one module access scope must be selected']);
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

    $query = "INSERT INTO roles (first_name, last_name, role_name, username, email, password, access_scope, is_active, status, tenantID)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $stmt->bind_param('sssssssisi', $firstName, $lastName, $roleName, $username, $email, $hashedPassword, $accessScope, $isActive, $status, $tenantID);

    if ($stmt->execute()) {
        $newRoleId = $stmt->insert_id;
        $stmt->close();

        log_event(
            $conn,
            'CREATE User Role',
            'role',
            (int) $newRoleId,
            'Created user role for ' . $firstName . ' ' . $lastName . ' (username: ' . $username . ', status: ' . $status . ')'
        );

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
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $roleName = trim((string) ($_POST['role_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $accessScope = trim((string) ($_POST['access_scope'] ?? ''));
    $status = (string) ($_POST['status'] ?? 'Active');

    // Log what we received
    error_log('updateRole received - roleId: ' . $roleId . ', accessScope: "' . $accessScope . '" (type: ' . gettype($accessScope) . ')');
    error_log('updateRole POST data: ' . json_encode($_POST));

    if ($roleId <= 0 || $firstName === '' || $lastName === '' || $username === '' || $email === '') {
        jsonResponse(400, ['success' => false, 'message' => 'Role ID, first name, last name, username, and email are required']);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, ['success' => false, 'message' => 'Invalid email format']);
    }

    if (!in_array($status, ['Active', 'Inactive'], true)) {
        $status = 'Active';
    }

    if ($accessScope === '') {
        jsonResponse(400, ['success' => false, 'message' => 'At least one module access scope must be selected']);
    }

    $verifyQuery = "SELECT role_id, first_name, last_name, username, status FROM roles WHERE role_id = ? AND tenantID = ? LIMIT 1";
    $verifyStmt = $conn->prepare($verifyQuery);
    if (!$verifyStmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $verifyStmt->bind_param('ii', $roleId, $tenantID);
    $verifyStmt->execute();

    $verifyResult = $verifyStmt->get_result();
    if ($verifyResult->num_rows === 0) {
        $verifyStmt->close();
        jsonResponse(403, ['success' => false, 'message' => 'Role not found or unauthorized']);
    }
    $verifyResult->fetch_assoc();
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
                  SET first_name = ?, last_name = ?, role_name = ?, username = ?, email = ?, password = ?, access_scope = ?, is_active = ?, status = ?
                  WHERE role_id = ? AND tenantID = ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }

        $stmt->bind_param('sssssssisii', $firstName, $lastName, $roleName, $username, $email, $hashedPassword, $accessScope, $isActive, $status, $roleId, $tenantID);
    } else {
        $query = "UPDATE roles
                  SET first_name = ?, last_name = ?, role_name = ?, username = ?, email = ?, access_scope = ?, is_active = ?, status = ?
                  WHERE role_id = ? AND tenantID = ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }

        $stmt->bind_param('sssssissii', $firstName, $lastName, $roleName, $username, $email, $accessScope, $isActive, $status, $roleId, $tenantID);
    }

    if ($stmt->execute()) {
        $stmt->close();

        log_event(
            $conn,
            'UPDATE User Role',
            'role',
            $roleId,
            'Updated role for ' . $firstName . ' ' . $lastName . ' (username: ' . $username . ', status: ' . $status . ')'
        );

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

    $verifyQuery = "SELECT role_id, first_name, last_name, username, status FROM roles WHERE role_id = ? AND tenantID = ? LIMIT 1";
    $verifyStmt = $conn->prepare($verifyQuery);
    if (!$verifyStmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $verifyStmt->bind_param('ii', $roleId, $tenantID);
    $verifyStmt->execute();

    $verifyResult = $verifyStmt->get_result();
    if ($verifyResult->num_rows === 0) {
        $verifyStmt->close();
        jsonResponse(403, ['success' => false, 'message' => 'Role not found or unauthorized']);
    }
    $roleRow = $verifyResult->fetch_assoc();
    $verifyStmt->close();

    $query = "DELETE FROM roles WHERE role_id = ? AND tenantID = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $stmt->bind_param('ii', $roleId, $tenantID);

    if ($stmt->execute()) {
        $stmt->close();

        log_event(
            $conn,
            'DELETE User Role',
            'role',
            $roleId,
            'Deleted role for ' . ($roleRow['first_name'] ?? '') . ' ' . ($roleRow['last_name'] ?? '') . ' (username: ' . ($roleRow['username'] ?? 'N/A') . ')'
        );

        jsonResponse(200, [
            'success' => true,
            'message' => 'User role deleted successfully'
        ]);
    }

    $error = $conn->error;
    $stmt->close();
    jsonResponse(500, ['success' => false, 'message' => 'Error deleting role: ' . $error]);
}
