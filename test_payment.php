<?php
// Diagnostic test to check if payment API is running
header('Content-Type: application/json');
ob_start();

$action = $_GET['action'] ?? 'check';
$response = [
    'timestamp' => time(),
    'test_endpoint' => 'test_payment.php',
    'status' => 'unknown'
];

if ($action === 'check') {
    // Test if paymentcrud.php is accessible
    $api_url = 'https://rapidrepair-gygpcbczgyg0czek.southeastasia-01.azurewebsites.net/paymentcrud.php?action=test';
    
    $response['checking'] = $api_url;
    $response['step_1'] = 'Attempting to reach paymentcrud.php...';
    
    try {
        // Try using file_get_contents
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET'
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        $result = @file_get_contents($api_url, false, $context);
        
        if ($result === false) {
            $response['status'] = 'error';
            $response['message'] = 'paymentcrud.php is NOT accessible (connection failed)';
            $response['debug'] = 'file_get_contents returned false';
        } else {
            $response['status'] = 'success';
            $response['message'] = 'paymentcrud.php is RUNNING';
            $response['api_response_raw'] = $result;
            
            // Try to parse as JSON
            $json_decoded = json_decode($result, true);
            if ($json_decoded !== null) {
                $response['api_response_parsed'] = $json_decoded;
            }
        }
    } catch (Exception $e) {
        $response['status'] = 'error';
        $response['message'] = 'Exception occurred: ' . $e->getMessage();
        $response['error_type'] = get_class($e);
    }
} else {
    $response['status'] = 'success';
    $response['message'] = 'This is a diagnostic test endpoint';
    $response['instructions'] = 'Add ?action=check to test if paymentcrud.php is running';
}

ob_end_clean();
echo json_encode($response, JSON_PRETTY_PRINT);
?>
