<?php
header("Content-Type: application/json");
include "db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if(empty($email) || empty($password)){
    echo json_encode([
        "status" => "error",
        "message" => "Email and password required"
    ]);
    exit;
}

// Resolve tenantID from user record and use it for session scoping.
$query = "SELECT user_id, tenantID, fullName, password FROM users WHERE email=? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if($row = $result->fetch_assoc()){
    // Compare password with hash
    if(password_verify($password, $row['password'])){
        $_SESSION['user_id'] = (int) $row['user_id'];
        $_SESSION['tenantID'] = isset($row['tenantID']) ? (int) $row['tenantID'] : null;
        $_SESSION['fullName'] = $row['fullName'];

        echo json_encode([
            "status" => "success",
            "user_id" => $row['user_id'],
            "tenantID" => $row['tenantID'],
            "name" => $row['fullName']
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid password"
        ]);
    }
}else{
    echo json_encode([
        "status" => "error",
        "message" => "User not found"
    ]);
}

$stmt->close();
$conn->close();
?>