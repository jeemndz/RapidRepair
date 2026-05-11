<?php
header("Content-Type: application/json");
include "db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Support JSON and form-data
$data = json_decode(file_get_contents("php://input"), true);

$identifier = trim(
    $data['identifier']
    ?? $data['email']
    ?? $data['username']
    ?? $_POST['identifier']
    ?? $_POST['email']
    ?? $_POST['username']
    ?? ''
);

$password = trim(
    $data['password']
    ?? $_POST['password']
    ?? ''
);

if (empty($identifier) || empty($password)) {
    echo json_encode([
        "status" => "error",
        "message" => "Email/Username and password required"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| LOGIN USING EMAIL OR USERNAME
|--------------------------------------------------------------------------
*/
$query = "
    SELECT 
        user_id,
        tenantID,
        fullName,
        username,
        email,
        password
    FROM users
    WHERE email = ?
       OR username = ?
    LIMIT 1
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $identifier, $identifier);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    if (password_verify($password, $row['password'])) {

        $_SESSION['user_id'] = (int)$row['user_id'];
        $_SESSION['tenantID'] = (int)$row['tenantID'];
        $_SESSION['fullName'] = $row['fullName'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['email'] = $row['email'];

        echo json_encode([
            "status" => "success",
            "message" => "Login successful",
            "user_id" => $row['user_id'],
            "tenantID" => $row['tenantID'],
            "fullName" => $row['fullName'],
            "username" => $row['username'],
            "email" => $row['email']
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