<?php
header("Content-Type: application/json");
include "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$tenantID = isset($data['tenantID']) && is_numeric($data['tenantID']) ? (int)$data['tenantID'] : 0;
$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if($tenantID <= 0 || empty($email) || empty($password)){
    echo json_encode([
        "status" => "error",
        "message" => "tenantID, email and password required"
    ]);
    exit;
}

// Query user within tenant scope
$query = "SELECT user_id, tenantID, fullName, password FROM users WHERE tenantID=? AND email=? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $tenantID, $email);
$stmt->execute();
$result = $stmt->get_result();

if($row = $result->fetch_assoc()){
    // Compare password with hash
    if(password_verify($password, $row['password'])){
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