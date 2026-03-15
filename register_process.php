<?php
session_start();
require_once "db.php";

function redirectWithMessage($message, $type, $redirect, $old = [])
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type; // success | error

    // remember inputs (exclude passwords)
    if (!empty($old)) {
        $_SESSION['old'] = $old;
    }

    header("Location: $redirect");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectWithMessage("Invalid request.", "error", "register.php");
}

/* GET DATA */
$fullname  = trim($_POST['fullname'] ?? '');
$username  = trim($_POST['username'] ?? '');
$email     = trim($_POST['email'] ?? '');
$password  = $_POST['password'] ?? '';
$confirm   = $_POST['confirm_password'] ?? '';

/* SAVE INPUTS FOR RE-FILL */
$old = [
    'fullname' => $fullname,
    'username' => $username,
    'email'    => $email
];

/* VALIDATION */
if ($fullname === '' || $username === '' || $email === '' || $password === '' || $confirm === '') {
    redirectWithMessage("Please fill in all fields.", "error", "register.php", $old);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectWithMessage("Please enter a valid email address.", "error", "register.php", $old);
}

if ($password !== $confirm) {
    redirectWithMessage("Passwords do not match.", "error", "register.php", $old);
}

if (strlen($password) < 6) {
    redirectWithMessage("Password must be at least 6 characters.", "error", "register.php", $old);
}

/* CHECK USERNAME */
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    redirectWithMessage("Username already exists.", "error", "register.php", $old);
}
$stmt->close();

/* CHECK EMAIL */
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    redirectWithMessage("Email already exists.", "error", "register.php", $old);
}
$stmt->close();

/* INSERT USER */
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$role = 'client';

$stmt = $conn->prepare(
    "INSERT INTO users (fullName, username, email, password, role)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param("sssss", $fullname, $username, $email, $hashedPassword, $role);

if (!$stmt->execute()) {
    $stmt->close();
    redirectWithMessage("Registration failed. Please try again.", "error", "register.php", $old);
}

$user_id = $conn->insert_id;
$stmt->close();

/* CREATE CLIENT RECORD */
$names = preg_split('/\s+/', $fullname, 2);
$firstName = $names[0] ?? '';
$lastName  = $names[1] ?? '';

/* ✅ CHECK IF dateRegistered COLUMN EXISTS (prevents fatal error) */
$hasDateRegistered = false;
$hasCreatedAt = false;

$colRes = $conn->query("SHOW COLUMNS FROM client_information");
if ($colRes) {
    while ($col = $colRes->fetch_assoc()) {
        if ($col['Field'] === 'dateRegistered') $hasDateRegistered = true;
        if ($col['Field'] === 'created_at') $hasCreatedAt = true;
    }
}

/* ✅ Insert depending on available column */
if ($hasDateRegistered) {
    $stmt = $conn->prepare(
        "INSERT INTO client_information (user_id, firstName, lastName, dateRegistered)
         VALUES (?, ?, ?, CURDATE())"
    );
    $stmt->bind_param("iss", $user_id, $firstName, $lastName);

} elseif ($hasCreatedAt) {
    $stmt = $conn->prepare(
        "INSERT INTO client_information (user_id, firstName, lastName, created_at)
         VALUES (?, ?, ?, NOW())"
    );
    $stmt->bind_param("iss", $user_id, $firstName, $lastName);

} else {
    // fallback: no date column exists
    $stmt = $conn->prepare(
        "INSERT INTO client_information (user_id, firstName, lastName)
         VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iss", $user_id, $firstName, $lastName);
}

if (!$stmt->execute()) {
    $stmt->close();
    redirectWithMessage("Client record creation failed.", "error", "register.php", $old);
}
$stmt->close();

/* CLEAR OLD INPUTS ON SUCCESS */
unset($_SESSION['old']);

/* ✅ SUCCESS -> show message on login.php (Option B) */
redirectWithMessage(
    "Account registered successfully! Welcome, $firstName 🎉 Please log in.",
    "success",
    "login.php"
);
