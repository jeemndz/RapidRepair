<?php
session_start();
require_once "db.php";

/* =========================
   HARD-CODED ADMIN CONFIRM PASSWORD
   (same one you use in manage_users.php)
========================= */
define("ADMIN_CONFIRM_PASSWORD", "RREDMS123");

// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage_users.php");
    exit();
}

/* =========================
   INPUTS
========================= */
$userId = (int)($_POST['user_id'] ?? 0);
$newRole = trim($_POST['role'] ?? '');
$adminConfirmPassword = (string)($_POST['admin_confirm_password'] ?? '');

if ($userId <= 0 || $newRole === '' || $adminConfirmPassword === '') {
    header("Location: manage_users.php?err=" . urlencode("Missing required fields."));
    exit();
}

$allowedRoles = ['client', 'staff', 'admin'];
if (!in_array($newRole, $allowedRoles, true)) {
    header("Location: manage_users.php?err=" . urlencode("Invalid role selected."));
    exit();
}

/* =========================
   CONFIRM PASSWORD (hardcoded)
========================= */
if (!hash_equals(ADMIN_CONFIRM_PASSWORD, $adminConfirmPassword)) {
    header("Location: manage_users.php?err=" . urlencode("Admin confirmation password is incorrect."));
    exit();
}

/* =========================
   SAFETY: prevent updating own role
========================= */
$currentAdminId = (int)($_SESSION['user_id'] ?? 0);
if ($currentAdminId > 0 && $userId === $currentAdminId) {
    header("Location: manage_users.php?err=" . urlencode("You cannot change your own role."));
    exit();
}

/* =========================
   FETCH CURRENT USER ROLE
========================= */
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    header("Location: manage_users.php?err=" . urlencode("User not found."));
    exit();
}

/* =========================
   OPTIONAL: protect admin accounts from role changes
   - keep this if you want ALL admins protected
   - remove this block if you want to allow changing admin -> staff/client
========================= */
if (strtolower($row['role']) === 'admin') {
    header("Location: manage_users.php?err=" . urlencode("Admin accounts are protected."));
    exit();
}

/* =========================
   UPDATE ROLE
========================= */
$stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ? LIMIT 1");
$stmt->bind_param("si", $newRole, $userId);

if ($stmt->execute()) {
    $stmt->close();
    header("Location: manage_users.php?ok=" . urlencode("Role updated successfully."));
    exit();
} else {
    $err = $stmt->error;
    $stmt->close();
    header("Location: manage_users.php?err=" . urlencode("Update failed: " . $err));
    exit();
}
