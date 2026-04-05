<?php
/**
 * Simple API Test
 */

header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'message' => 'Mobile API test - File is working',
    'GET_params' => $_GET,
    'tenantID' => $_GET['tenantID'] ?? 'not set'
]);
?>
