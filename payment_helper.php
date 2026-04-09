<?php
/**
 * Subscription Payment Helper Functions
 * Used by clientlanding.php and clientpayment.php
 */

/**
 * Load all active subscription plans from the database
 */
function loadSubscriptionPlans($conn)
{
    $plans = [];
    
    // Check if table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'subscription_plans'");
    if ($checkTable && mysqli_num_rows($checkTable) > 0) {
        $sql = "SELECT plan_id, plan_code, plan_name, monthly_price, plan_features FROM subscription_plans WHERE is_active = 1 ORDER BY monthly_price ASC";
        $result = mysqli_query($conn, $sql);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $plans[] = [
                    'plan_id' => (int) $row['plan_id'],
                    'plan_code' => strtolower($row['plan_code']),
                    'plan_name' => $row['plan_name'],
                    'monthly_price' => (float) $row['monthly_price'],
                    'plan_features' => $row['plan_features']
                ];
            }
        }
    }
    
    // Return default plans if none found
    return count($plans) > 0 ? $plans : [
        [
            'plan_id' => 1,
            'plan_code' => 'starter',
            'plan_name' => 'Starter',
            'monthly_price' => 149,
            'plan_features' => '[1 Location, Up to 5 Technicians, Basic Analytics]'
        ],
        [
            'plan_id' => 2,
            'plan_code' => 'professional',
            'plan_name' => 'Professional',
            'monthly_price' => 399,
            'plan_features' => '[Up to 3 Locations, Unlimited Technicians, Full Analytics Suite, SMS Notifications]'
        ],
        [
            'plan_id' => 3,
            'plan_code' => 'enterprise',
            'plan_name' => 'Enterprise',
            'monthly_price' => 0,
            'plan_features' => '[Unlimited Locations, Custom API Access, Dedicated Success Manager, 24/7 Priority Support]'
        ]
    ];
}

/**
 * Get plan details by plan code
 */
function getPlanByCode($conn, $planCode)
{
    $plans = loadSubscriptionPlans($conn);
    
    foreach ($plans as $plan) {
        if ($plan['plan_code'] === strtolower($planCode)) {
            return $plan;
        }
    }
    
    return $plans[0]; // Return first plan as default
}

/**
 * Validate tenant exists and retrieve details
 */
function getTenantDetails($conn, $tenantID)
{
    $tenantID = (int) $tenantID;
    
    if ($tenantID <= 0) {
        return null;
    }
    
    $query = mysqli_query($conn, "SELECT tenantID, shopName, email, subscription_plan, billing_cycle FROM owners WHERE tenantID=" . $tenantID . " LIMIT 1");
    
    if ($query && mysqli_num_rows($query) > 0) {
        return mysqli_fetch_assoc($query);
    }
    
    return null;
}

/**
 * Create a subscription payment record
 * Note: Applicant stays in PENDING status until superadmin approves
 * Subscription is created but not activated until approval
 */
function createPaymentRecord($conn, $tenantID, $planID, $amount, $paymentMethod, $transactionRef, $gcashRef = '', $bankRef = '')
{
    $tenantID = (int) $tenantID;
    $planID = (int) $planID;
    $amount = (float) $amount;
    
    // Validate payment method
    $validMethods = ['Cash', 'GCash', 'Card', 'Bank Transfer'];
    if (!in_array($paymentMethod, $validMethods)) {
        return ['success' => false, 'error' => 'Invalid payment method'];
    }
    
    // Calculate billing dates
    $billingStart = date('Y-m-d');
    $billingEnd = date('Y-m-d', strtotime('+1 month'));
    $nextBilling = date('Y-m-d', strtotime('+1 month'));
    
    // Step 1: Create subscription record for payment tracking
    // Status set to 'active' but owner remains PENDING until superadmin approves
    $subscriptionQuery = "INSERT INTO subscriptions 
        (tenantID, plan_id, billing_cycle, start_date, end_date, next_billing_date, amount, status, created_at, updated_at) 
        VALUES 
        ('" . mysqli_real_escape_string($conn, (string)$tenantID) . "', " . $planID . ", 'monthly', '" . $billingStart . "', '" . $billingEnd . "', '" . $nextBilling . "', " . $amount . ", 'active', NOW(), NOW())";
    
    $subscriptionResult = mysqli_query($conn, $subscriptionQuery);
    
    if (!$subscriptionResult) {
        return ['success' => false, 'error' => 'Failed to create subscription record: ' . mysqli_error($conn)];
    }
    
    // Get the subscription_id
    $subscriptionID = mysqli_insert_id($conn);
    
    // Step 2: Create payment record linked to subscription
    $paymentQuery = "INSERT INTO subscription_payments 
        (tenantID, subscription_id, plan_id, amount, payment_method, payment_status, transaction_reference, gcash_reference, billing_period_start, billing_period_end, paid_at, next_billing_date, created_at, updated_at) 
        VALUES 
        (" . $tenantID . ", " . $subscriptionID . ", " . $planID . ", " . $amount . ", '" . mysqli_real_escape_string($conn, $paymentMethod) . "', 'Paid', '" . mysqli_real_escape_string($conn, $transactionRef) . "', '" . mysqli_real_escape_string($conn, $gcashRef) . "', '" . $billingStart . "', '" . $billingEnd . "', NOW(), '" . $nextBilling . "', NOW(), NOW())";
    
    $paymentResult = mysqli_query($conn, $paymentQuery);
    
    if (!$paymentResult) {
        return ['success' => false, 'error' => 'Failed to create payment record: ' . mysqli_error($conn)];
    }
    
    // Note: Owner status remains PENDING until superadmin approves in superaddtenants.php
    // Subscription is created but owner is not activated yet
    
    return ['success' => true, 'message' => 'Payment processed successfully. Your application is pending review.', 'subscription_id' => $subscriptionID];
}

/**
 * Generate transaction reference based on payment method
 */
function generateTransactionReference($paymentMethod, $cardholderName, $gcashRef = '', $bankRef = '')
{
    $timestamp = time();
    $random = rand(100000, 999999);
    
    switch (strtolower($paymentMethod)) {
        case 'card':
            $initials = strtoupper(substr(str_replace(' ', '', $cardholderName), 0, 3));
            return 'CARD-' . $initials . '-' . $timestamp . '-' . $random;
            
        case 'gcash':
            return 'GCASH-' . $gcashRef . '-' . $random;
            
        case 'bank transfer':
            return 'BANK-' . $bankRef . '-' . $random;
            
        case 'cash':
        default:
            return 'CASH-' . $timestamp . '-' . $random;
    }
}

/**
 * Get payment history for a tenant
 */
function getPaymentHistory($conn, $tenantID, $limit = 10)
{
    $tenantID = (int) $tenantID;
    
    $query = "SELECT * FROM subscription_payments WHERE tenantID=" . $tenantID . " ORDER BY created_at DESC LIMIT " . (int) $limit;
    
    $result = mysqli_query($conn, $query);
    $payments = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $payments[] = $row;
        }
    }
    
    return $payments;
}

/**
 * Validate card number using Luhn algorithm (basic validation)
 */
function validateCardNumber($cardNumber)
{
    $cardNumber = preg_replace('/\D/', '', $cardNumber);
    
    if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
        return false;
    }
    
    $sum = 0;
    $numDigits = strlen($cardNumber);
    $parity = $numDigits % 2;
    
    for ($i = 0; $i < $numDigits; $i++) {
        $digit = (int) $cardNumber[$i];
        
        if ($i % 2 == $parity) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        
        $sum += $digit;
    }
    
    return ($sum % 10) == 0;
}

/**
 * Validate expiry date format
 */
function validateExpiryDate($expiryDate)
{
    if (!preg_match('/^(\d{2})\/(\d{2})$/', $expiryDate, $matches)) {
        return false;
    }
    
    $month = (int) $matches[1];
    $year = (int) $matches[2];
    
    // Check if month is valid
    if ($month < 1 || $month > 12) {
        return false;
    }
    
    // Check if year is in the future (2-digit format)
    $currentYear = (int) date('y');
    $currentMonth = (int) date('m');
    
    if ($year < $currentYear || ($year == $currentYear && $month < $currentMonth)) {
        return false;
    }
    
    return true;
}

/**
 * Validate CVV
 */
function validateCVV($cvv)
{
    $cvv = preg_replace('/\D/', '', $cvv);
    return strlen($cvv) >= 3 && strlen($cvv) <= 4;
}
?>
