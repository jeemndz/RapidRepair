<?php
header("Content-Type: application/json");
include "db.php";

$tenantID = $_POST['tenantID'];
$fullName = $_POST['fullName'];
$email = $_POST['email'];
$username = $_POST['username'];
$password = $_POST['password'];
$contactNumber = $_POST['contactNumber'];

// Generate 6-digit code
$verification_code = rand(100000, 999999);
$expires_at = date("Y-m-d H:i:s", strtotime("+15 minutes"));

// Store in email_verifications table
$stmt = $conn->prepare("INSERT INTO email_verifications (tenantID, fullName, email, username, password, contactNumber, verification_code, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isssssss", $tenantID, $fullName, $email, $username, $password, $contactNumber, $verification_code, $expires_at);
$stmt->execute();

// Send email (using PHP mail or PHPMailer)
$subject = "Verify your Rapid Repair Account";
$message = "Hello $fullName,\n\nYour verification code is: $verification_code\nYour shop tenant ID is: $tenantID\nThis code will expire in 15 minutes.\n\nThank you!";
$headers = "From: noreply@rapidrepair.com\r\n";

// Using mail() for simplicity
if(mail($email, $subject, $message, $headers)){
    echo json_encode(["status"=>"success","message"=>"Verification code sent"]);
} else {
    echo json_encode(["status"=>"error","message"=>"Failed to send email"]);
}
?>