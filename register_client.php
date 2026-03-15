<?php
session_start();
require_once "db.php";
require_once "log_helper.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    header("Location: login.php");
    exit();
}

/* =========================
   SANITIZE INPUTS
========================= */
$contactNumber = trim($_POST['contactNumber'] ?? '');
$email         = trim($_POST['email'] ?? '');
$address       = trim($_POST['address'] ?? '');
$notes         = trim($_POST['notes'] ?? '');

/* =========================
   BASIC VALIDATION
========================= */
if ($contactNumber === '' || $email === '' || $address === '') {
    // optional: set session message
    $_SESSION['profile_error'] = "Please fill in Contact Number, Email, and Address.";
    header("Location: profile.php");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['profile_error'] = "Invalid email format.";
    header("Location: profile.php");
    exit();
}

/* (Optional) PH mobile format check (basic)
   Accept: 09xxxxxxxxx or +639xxxxxxxxx
*/
if (!preg_match('/^(09\d{9}|\+639\d{9})$/', $contactNumber)) {
    $_SESSION['profile_error'] = "Invalid contact number. Use 09xxxxxxxxx or +639xxxxxxxxx.";
    header("Location: profile.php");
    exit();
}

/* =========================
   GET CLIENT_ID FOR LOGS
========================= */
$client_id = null;
$fetch = $conn->prepare("SELECT client_id FROM client_information WHERE user_id = ? LIMIT 1");
$fetch->bind_param("i", $user_id);
$fetch->execute();
$row = $fetch->get_result()->fetch_assoc();
$fetch->close();

if (!$row || empty($row['client_id'])) {
    $_SESSION['profile_error'] = "Client record not found.";
    header("Location: profile.php");
    exit();
}

$client_id = (int)$row['client_id'];

/* =========================
   UPDATE PROFILE
========================= */
$stmt = $conn->prepare("
    UPDATE client_information
    SET
        contactNumber = ?,
        email = ?,
        address = ?,
        notes = ?
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "ssssi",
    $contactNumber,
    $email,
    $address,
    $notes,
    $user_id
);

if (!$stmt->execute()) {
    $stmt->close();
    $_SESSION['profile_error'] = "Update failed: " . $conn->error;
    header("Location: profile.php");
    exit();
}

$stmt->close();

/* =========================
   ✅ SYSTEM LOGS
========================= */
log_event(
    $conn,
    "Update Profile",
    "client_information",
    $client_id,
    "Client updated profile details (contact/email/address/notes)"
);

/* =========================
   REDIRECT
========================= */
$_SESSION['profile_success'] = "Profile updated successfully!";
header("Location: profile.php");
exit();
