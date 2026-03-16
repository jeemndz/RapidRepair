<?php
// Start output buffering
ob_start();

// Show all errors (for debugging)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Allow Android app requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Include DB
include "db.php";

// Clear buffer to prevent stray whitespace
ob_clean();

// Get POST data safely
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Check if fields are empty
if (empty($email) || empty($password)) {
    echo json_encode([
        "status" => "error",
        "message" => "Email and password are required"
    ]);
    exit;
}

// Prepare SQL statement
$query = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// Check user and password
if ($row = $result->fetch_assoc()) {
    if ($password === $row['password']) { // plain password check
        echo json_encode([
            "status" => "success",
            "name" => $row['name']
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid password"
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "User not found"
    ]);
}

$stmt->close();
$conn->close();
?>