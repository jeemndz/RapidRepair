<?php
/**
 * Paymongo Payment Gateway Helper - cURL Edition
 * Uses modern payment_intents endpoint with 3D Secure and multiple payment methods
 * No external dependencies - uses native PHP cURL
 */

class PaymongoPaymentGateway
{
    private $publicKey;
    private $secretKey;
    private $apiBaseUrl = 'https://api.paymongo.com/v1';
    private $authHeader;

    public function __construct($publicKey, $secretKey)
    {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
        $this->initializeAuth();
    }

    /**
     * Initialize authentication header
     */
    private function initializeAuth()
    {
        $this->authHeader = 'Basic ' . base64_encode($this->secretKey . ':');
    }

    /**
     * Make HTTP request to Paymongo API using cURL
     */
    private function makeRequest($method, $endpoint, $data = null)
    {
        try {
            $url = $this->apiBaseUrl . $endpoint;
            
            $curl = curl_init();
            
            $curlOptions = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => [
                    "accept: application/json",
                    "authorization: " . $this->authHeader,
                    "content-type: application/json"
                ],
            ];
            
            if ($data !== null) {
                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
            }
            
            curl_setopt_array($curl, $curlOptions);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            
            curl_close($curl);
            
            if ($error) {
                error_log('Paymongo cURL Error: ' . $error);
                return [
                    'success' => false,
                    'httpCode' => $httpCode,
                    'error' => $error,
                ];
            }
            
            $decoded = json_decode($response, true);
            
            return [
                'success' => $httpCode >= 200 && $httpCode < 300,
                'httpCode' => $httpCode,
                'data' => $decoded,
            ];
        } catch (Exception $e) {
            error_log('Paymongo Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create payment intent (modern endpoint)
     */
    public function createPaymentIntent($amount, $currency, $description, $paymentMethodAllowed = null, $returnUrl = null)
    {
        if (!$paymentMethodAllowed) {
            $paymentMethodAllowed = ['qrph', 'brankas', 'card', 'dob', 'billease', 'gcash', 'grab_pay', 'shopee_pay', 'paymaya'];
        }

        $attributes = [
            'amount' => (int)($amount * 100), // Convert to centavos
            'payment_method_allowed' => $paymentMethodAllowed,
            'payment_method_options' => [
                'card' => [
                    'request_three_d_secure' => 'any'
                ]
            ],
            'currency' => strtoupper($currency),
            'capture_type' => 'automatic',
            'description' => $description
        ];

        // Add return URL if provided (for hosted checkout)
        if ($returnUrl) {
            $attributes['return_url'] = $returnUrl;
        }

        $data = [
            'data' => [
                'attributes' => $attributes
            ]
        ];

        return $this->makeRequest('POST', '/payment_intents', $data);
    }

    /**
     * Attach payment method to intent
     */
    public function attachPaymentMethodToIntent($paymentIntentId, $paymentMethodId)
    {
        $data = [
            'data' => [
                'attributes' => [
                    'payment_method' => $paymentMethodId
                ]
            ]
        ];

        return $this->makeRequest('POST', '/payment_intents/' . $paymentIntentId . '/attach', $data);
    }

    /**
     * Create payment method from card details
     */
    public function createPaymentMethod($cardNumber, $expMonth, $expYear, $cvc)
    {
        $data = [
            'data' => [
                'attributes' => [
                    'type' => 'card',
                    'details' => [
                        'card_number' => $cardNumber,
                        'exp_month' => (int)$expMonth,
                        'exp_year' => (int)$expYear,
                        'cvc' => $cvc
                    ]
                ]
            ]
        ];

        return $this->makeRequest('POST', '/payment_methods', $data);
    }

    /**
     * Retrieve payment intent
     */
    public function retrievePaymentIntent($paymentIntentId)
    {
        return $this->makeRequest('GET', '/payment_intents/' . $paymentIntentId);
    }

    /**
     * Get public key
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * Convert PHP amount to centavos
     */
    public static function toCentavos($amount)
    {
        return (int)($amount * 100);
    }

    /**
     * Format amount for display
     */
    public static function formatAmount($amount)
    {
        return '₱' . number_format($amount, 2);
    }
}

/**
 * Initialize Paymongo Gateway with credentials from Azure App Service Configuration
 * Tries multiple methods to access environment variables for compatibility
 */
function initializePaymongoGateway()
{
    // Try getenv() first (standard PHP method)
    $publicKey = getenv('PAYMONGO_PUBLIC_KEY');
    $secretKey = getenv('PAYMONGO_SECRET_KEY');
    
    // Fallback to $_ENV if getenv() fails
    if (!$publicKey || !$secretKey) {
        $publicKey = $_ENV['PAYMONGO_PUBLIC_KEY'] ?? $publicKey;
        $secretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? $secretKey;
    }
    
    // Fallback to $_SERVER if $_ENV fails
    if (!$publicKey || !$secretKey) {
        $publicKey = $_SERVER['PAYMONGO_PUBLIC_KEY'] ?? $publicKey;
        $secretKey = $_SERVER['PAYMONGO_SECRET_KEY'] ?? $secretKey;
    }

    if (!$publicKey || !$secretKey) {
        error_log('Paymongo credentials not found in environment. Tried: getenv(), $_ENV, $_SERVER');
        error_log('Available $_SERVER keys: ' . implode(', ', array_filter(array_keys($_SERVER), function($key) {
            return strpos($key, 'PAYMONGO') !== false;
        })));
        return null;
    }

    return new PaymongoPaymentGateway($publicKey, $secretKey);
}

/**
 * Process payment intent creation
 * @param $planCode string Plan code to look up plan_id from application
 */
function processPaymongoPaymentIntent($conn, $tenantID, $amount, $currency, $description, $returnUrl = null, $planCode = null)
{
    try {
        $gateway = initializePaymongoGateway();
        
        if (!$gateway) {
            error_log('Payment intent creation failed: Could not initialize Paymongo gateway');
            error_log('Public Key available: ' . (getenv('PAYMONGO_PUBLIC_KEY') ? 'Yes' : 'No'));
            error_log('Secret Key available: ' . (getenv('PAYMONGO_SECRET_KEY') ? 'Yes' : 'No'));
            return [
                'success' => false,
                'error' => 'Failed to initialize Paymongo gateway. Please ensure API credentials are configured.'
            ];
        }

        // Create payment intent with return URL for hosted checkout
        $result = $gateway->createPaymentIntent(
            $amount,
            $currency,
            $description,
            ['qrph', 'brankas', 'card', 'dob', 'billease', 'gcash', 'grab_pay', 'shopee_pay', 'paymaya'],
            $returnUrl
        );

        if (!$result['success'] || !isset($result['data']['data']['id'])) {
            return [
                'success' => false,
                'error' => 'Failed to create payment intent',
                'details' => $result['data'] ?? []
            ];
        }

        $paymentIntentId = $result['data']['data']['id'];

        // Store initial payment record - don't require plan_id to have default
        $insertSql = "INSERT INTO subscription_payments 
                      (tenantID, amount, payment_method, payment_status, transaction_reference, created_at)
                      VALUES 
                      (" . (int)$tenantID . ", " . (float)$amount . ", 'card', 'pending', '" . 
                      mysqli_real_escape_string($conn, $paymentIntentId) . "', NOW())";

        if (!mysqli_query($conn, $insertSql)) {
            error_log('Database error: ' . mysqli_error($conn));
            return [
                'success' => false,
                'error' => 'Failed to store payment record: ' . mysqli_error($conn)
            ];
        }

        return [
            'success' => true,
            'paymentIntentId' => $paymentIntentId,
            'publicKey' => $gateway->getPublicKey()
        ];
    } catch (Exception $e) {
        error_log('Payment intent error: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Confirm payment by attaching payment method
 */
function confirmPaymongoPayment($conn, $tenantID, $paymentIntentId, $paymentMethodId, $cardholderName = '', $billingCycle = 'monthly')
{
    try {
        $gateway = initializePaymongoGateway();
        
        if (!$gateway) {
            return [
                'success' => false,
                'error' => 'Failed to initialize Paymongo gateway'
            ];
        }

        // Attach payment method to intent
        $result = $gateway->attachPaymentMethodToIntent($paymentIntentId, $paymentMethodId);

        if (!$result['success']) {
            // Update payment status to failed
            $failSql = "UPDATE subscription_payments SET payment_status = 'failed' 
                       WHERE transaction_reference = '" . mysqli_real_escape_string($conn, $paymentIntentId) . "' LIMIT 1";
            mysqli_query($conn, $failSql);

            return [
                'success' => false,
                'error' => 'Payment processing failed',
                'details' => $result['data'] ?? []
            ];
        }

        $paymentIntentData = $result['data']['data'] ?? [];
        $status = $paymentIntentData['attributes']['status'] ?? 'unknown';

        // Update payment record
        $updateSql = "UPDATE subscription_payments SET 
                      payment_status = '" . mysqli_real_escape_string($conn, $status) . "',
                      cardholder_name = '" . mysqli_real_escape_string($conn, $cardholderName) . "',
                      billing_cycle = '" . mysqli_real_escape_string($conn, $billingCycle) . "',
                      paid_at = NOW()
                      WHERE transaction_reference = '" . mysqli_real_escape_string($conn, $paymentIntentId) . "' LIMIT 1";
        
        if (!mysqli_query($conn, $updateSql)) {
            error_log('Database error: ' . mysqli_error($conn));
        }

        return [
            'success' => $status === 'succeeded' || $status === 'paid',
            'paymentStatus' => $status,
            'paymentIntentData' => $paymentIntentData
        ];
    } catch (Exception $e) {
        error_log('Payment confirmation error: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

?>
