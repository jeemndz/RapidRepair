<?php
session_start();
include __DIR__ . "/../db.php";
include __DIR__ . "/paymongo/paymongo_helper.php";

header('Content-Type: application/json');

$response = ['success' => false, 'error' => 'Invalid request'];

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid request data');
    }

    $tenantID = (int)($input['tenantID'] ?? 0);
    $paymentIntentID = trim($input['paymentIntentID'] ?? '');
    $cardNumber = preg_replace('/\s+/', '', trim($input['cardNumber'] ?? ''));
    $expiryDate = trim($input['expiryDate'] ?? '');
    $cvv = trim($input['cvv'] ?? '');
    $cardholderName = trim($input['cardholderName'] ?? '');

    if (!$tenantID || !$paymentIntentID || !$cardNumber || !$expiryDate || !$cvv) {
        throw new Exception('Missing required fields');
    }

    // Validate expiry date format
    if (!preg_match('/^\d{2}\/\d{2}$/', $expiryDate)) {
        throw new Exception('Invalid expiry date format');
    }

    list($expMonth, $expYear) = explode('/', $expiryDate);
    $expYear = '20' . $expYear;

    // Validate card number length
    if (strlen($cardNumber) < 13) {
        throw new Exception('Invalid card number');
    }

    // Initialize Paymongo gateway
    $gateway = initializePaymongoGateway();
    if (!$gateway) {
        throw new Exception('Failed to initialize payment gateway');
    }

    // Create payment method from card
    $paymentMethodResult = $gateway->createPaymentMethod($cardNumber, $expMonth, $expYear, $cvv);

    if (!$paymentMethodResult['success'] || !isset($paymentMethodResult['data']['data']['id'])) {
        $errorDetail = '';
        if (isset($paymentMethodResult['data']['errors'])) {
            $errors = $paymentMethodResult['data']['errors'];
            if (is_array($errors) && count($errors) > 0) {
                $errorDetail = $errors[0]['detail'] ?? 'Card processing failed';
            }
        }
        throw new Exception($errorDetail ?: 'Failed to process card');
    }

    $paymentMethodId = $paymentMethodResult['data']['data']['id'];

    // Attach payment method to payment intent
    $confirmResult = confirmPaymongoPayment($conn, $tenantID, $paymentIntentID, $paymentMethodId, $cardholderName, 'monthly');

    if ($confirmResult['success']) {
        $response = [
            'success' => true,
            'paymentIntentID' => $paymentIntentID,
            'message' => 'Payment processed successfully'
        ];
    } else {
        throw new Exception($confirmResult['error'] ?? 'Payment confirmation failed');
    }
} catch (Exception $e) {
    error_log('Payment processing error: ' . $e->getMessage());
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);
?>
