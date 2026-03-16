<?php
// login.php

// Suppress PHP warnings/notices (for clean JSON output)
error_reporting(0);

// Allow Android app requests (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Include database connection
include "db.php";

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

// Prepare SQL statement to prevent SQL injection
$query = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($query);
if(!$stmt){
    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// Check if user exists
if ($row = $result->fetch_assoc()) {
    // Verify hashed password
    if (password_verify($password, $row['password'])) {
        echo json_encode([
            "status" => "success",
            "user_id" => (int)$row['user_id'],
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

// Close statement and connection
$stmt->close();
$conn->close();
?>