<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status'=>'error','message'=>'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$service_id = $_POST['service_id'] ?? null;

if (!$service_id) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Invalid request']);
    exit;
}

// Update invoice status to Paid only if it belongs to logged-in client
$stmt = $conn->prepare("UPDATE services SET status='Paid' WHERE service_id = ? AND client_id = ?");
$stmt->bind_param("ii", $service_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['status'=>'success','message'=>'Payment successful']);
} else {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Failed to update invoice']);
}
?>
