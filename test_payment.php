<?php
// Simple test to verify API endpoint works
header('Content-Type: application/json');

$response = [
    'status' => 'success',
    'message' => 'Payment API test endpoint',
    'timestamp' => time(),
    'action' => $_GET['action'] ?? 'default'
];

echo json_encode($response);
?>
