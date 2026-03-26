<?php
header("Content-Type: application/json");
include "db.php"; // your MySQL connection

$data = json_decode(file_get_contents("php://input"), true);

$firstName = trim($data['firstName'] ?? '');
$lastName  = trim($data['lastName'] ?? '');
$email     = trim($data['email'] ?? '');
$phone     = trim($data['phone'] ?? '');
$password  = $data['password'] ?? '';

if (!$firstName || !$lastName || !$email || !$phone || !$password) {
    echo json_encode(["status" => "error", "message" => "Please fill all fields."]);
    exit;
}

// Check if email already exists
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email already registered."]);
    exit;
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$stmt = $conn->prepare("INSERT INTO users (fullName, email, contactNumber, password, role) VALUES (?, ?, ?, ?, 'client')");
$fullName = "$firstName $lastName";
$stmt->bind_param("ssss", $fullName, $email, $phone, $hashedPassword);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "User registered successfully!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Registration failed."]);
}

?>