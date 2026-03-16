<?php
header("Content-Type: application/json");
include "db.php";

$tenantID = $_POST['tenantID'];
$fullName = $_POST['fullName'];
$email = $_POST['email'];
$username = $_POST['username'];
$password = $_POST['password'];
$contactNumber = $_POST['contactNumber'];

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Check if email or username already exists
$stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR username=?");
$stmt->bind_param("ss", $email, $username);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0){
    echo json_encode(["status"=>"error","message"=>"Email or username already exists"]);
    exit;
}

// Insert new user
$stmt = $conn->prepare("INSERT INTO users (tenantID, fullName, email, username, password, contactNumber, role) VALUES (?, ?, ?, ?, ?, ?, 'client')");
$stmt->bind_param("isssss", $tenantID, $fullName, $email, $username, $hashedPassword, $contactNumber);

if($stmt->execute()){
    echo json_encode(["status"=>"success","message"=>"User registered successfully"]);
}else{
    echo json_encode(["status"=>"error","message"=>"Registration failed"]);
}
?>