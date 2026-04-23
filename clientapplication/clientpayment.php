<?php
session_start();
include __DIR__ . "/../db.php";
include __DIR__ . "/../payment_helper.php";
include __DIR__ . "/../paymongo_helper.php";

$errors = [];
$successMessage = "";
$tenantID = isset($_GET['tenantID']) ? (int) trim($_GET['tenantID']) : 0;
$selectedPlanCode = isset($_GET['plan']) ? strtolower(trim($_GET['plan'])) : '';
$billingCycle = isset($_GET['billingCycle']) ? strtolower(trim($_GET['billingCycle'])) : 'monthly';

// Verify tenant exists
$tenant = null;
if ($tenantID !== 0) {
    $tenant = getTenantDetails($conn, $tenantID);
    if (!$tenant) {
        $errors[] = 'Tenant not found. Please complete registration first.';
    }
} else {
    $errors[] = 'Invalid tenant information. Please register first.';
}

// Load subscription plans
$subscriptionPlans = loadSubscriptionPlans($conn);

// Find selected plan
$selectedPlan = null;
if ($selectedPlanCode !== '') {
    $selectedPlan = getPlanByCode($conn, $selectedPlanCode);
}

// Default to first plan if none selected
if (!$selectedPlan) {
    $selectedPlan = $subscriptionPlans[0];
}

// Handle payment form submission with card details
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a payment submission with card details
    if (isset($_POST['card_number']) && isset($_POST['card_expiry']) && isset($_POST['card_cvv'])) {
        $cardNumber = isset($_POST['card_number']) ? preg_replace('/\s+/', '', trim($_POST['card_number'])) : '';
        $cardExpiry = isset($_POST['card_expiry']) ? trim($_POST['card_expiry']) : '';
        $cardCVV = isset($_POST['card_cvv']) ? trim($_POST['card_cvv']) : '';
        $cardholderName = trim($_POST['cardholderName'] ?? '');
        $selectedPlanCodeFromForm = isset($_POST['selectedPlanCode']) ? strtolower(trim($_POST['selectedPlanCode'])) : '';
        $billingCycleFromForm = isset($_POST['billingCycle']) ? strtolower(trim($_POST['billingCycle'])) : 'monthly';
        
        // Re-fetch the selected plan from form submission
        if ($selectedPlanCodeFromForm !== '') {
            $selectedPlan = getPlanByCode($conn, $selectedPlanCodeFromForm);
            if (!$selectedPlan) {
                $selectedPlan = $subscriptionPlans[0];
            }
        }
        $billingCycle = $billingCycleFromForm;
        
        // Validation
        if ($cardNumber === '' || strlen($cardNumber) < 13) {
            $errors[] = 'Valid card number is required.';
        }
        
        if ($cardExpiry === '' || !preg_match('/^\d{2}\/\d{2}$/', $cardExpiry)) {
            $errors[] = 'Valid expiry date (MM/YY) is required.';
        }
        
        if ($cardCVV === '' || strlen($cardCVV) < 3) {
            $errors[] = 'Valid CVV is required.';
        }
        
        if ($cardholderName === '' || strlen($cardholderName) < 2) {
            $errors[] = 'Valid cardholder name is required.';
        }
        
        if (count($errors) === 0 && $tenant && isset($_SESSION['paymongo_payment_id'])) {
            // Parse expiry date
            list($expMonth, $expYear) = explode('/', $cardExpiry);
            $expYear = '20' . $expYear; // Convert YY to 20YY
            
            // Create payment source with card details
            $gateway = initializePaymongoGateway();
            $sourceData = [
                'type' => 'card',
                'details' => [
                    'card_number' => $cardNumber,
                    'cvc' => $cardCVV,
                    'exp_month' => (int)$expMonth,
                    'exp_year' => (int)$expYear
                ]
            ];
            
            $sourceResponse = $gateway->createPaymentSource('card', $sourceData['details']);
            
            if (!$sourceResponse['success'] || !isset($sourceResponse['data']['data']['id'])) {
                $errors[] = 'Failed to process card. Please check your card details and try again.';
                if (isset($sourceResponse['data']['errors'])) {
                    foreach ($sourceResponse['data']['errors'] as $error) {
                        $errors[] = $error['detail'] ?? 'Card processing error';
                    }
                }
            } else {
                $sourceId = $sourceResponse['data']['data']['id'];
                $paymentId = $_SESSION['paymongo_payment_id'];
                
                // Attach source to payment and charge
                $attachResponse = $gateway->attachSourceToPayment($paymentId, $sourceId);
                
                if ($attachResponse['success']) {
                    // Payment successful
                    $paymentData = $attachResponse['data']['data'] ?? [];
                    $status = $paymentData['attributes']['status'] ?? 'paid';
                    
                    // Update payment record with final status and cardholder info
                    $updatePaymentSql = "UPDATE subscription_payments SET 
                        payment_status = '" . mysqli_real_escape_string($conn, $status) . "',
                        cardholder_name = '" . mysqli_real_escape_string($conn, $cardholderName) . "',
                        payment_method = 'Paymongo',
                        billing_cycle = '" . mysqli_real_escape_string($conn, $billingCycle) . "',
                        paid_at = NOW()
                        WHERE tenantID = " . $tenantID . " 
                        AND transaction_reference = '" . mysqli_real_escape_string($conn, $paymentId) . "' 
                        LIMIT 1";
                    mysqli_query($conn, $updatePaymentSql);
                    
                    // Redirect to success page
                    header('Location: payment_success.php?tenantID=' . urlencode($tenantID) . '&paymentID=' . urlencode($paymentId));
                    exit();
                } else {
                    $errors[] = 'Payment processing failed. Please try again.';
                    if (isset($attachResponse['data']['errors'])) {
                        foreach ($attachResponse['data']['errors'] as $error) {
                            $errors[] = $error['detail'] ?? 'Payment error';
                        }
                    }
                }
            }
        } else if (!isset($_SESSION['paymongo_payment_id'])) {
            $errors[] = 'Payment session expired. Please refresh and try again.';
        }
    } else if (isset($_POST['processPayment'])) {
        // Initial payment form submission to initialize Paymongo
        $cardholderName = trim($_POST['cardholderName'] ?? '');
        $selectedPlanCodeFromForm = isset($_POST['selectedPlanCode']) ? strtolower(trim($_POST['selectedPlanCode'])) : '';
        $billingCycleFromForm = isset($_POST['billingCycle']) ? strtolower(trim($_POST['billingCycle'])) : 'monthly';
        
        // Re-fetch the selected plan from form submission
        if ($selectedPlanCodeFromForm !== '') {
            $selectedPlan = getPlanByCode($conn, $selectedPlanCodeFromForm);
            if (!$selectedPlan) {
                $selectedPlan = $subscriptionPlans[0];
            }
        }
        $billingCycle = $billingCycleFromForm;
        
        // Validation
        if ($cardholderName === '' || strlen($cardholderName) < 2) {
            $errors[] = 'Valid cardholder name is required (at least 2 characters).';
        }
        
        if (count($errors) === 0 && $tenant) {
            // Initiate Paymongo payment
            $amount = $selectedPlan['monthly_price'];
            
            // Apply billing cycle multiplier for subscription
            if ($billingCycle === 'quarterly') {
                $amount = $amount * 3;
            } else if ($billingCycle === 'yearly') {
                $amount = $amount * 12;
            }
            
            $description = $selectedPlan['plan_name'] . ' Plan - ' . ucfirst($billingCycle) . ' Billing';
            $email = $tenant['email'] ?? '';
            
            $paymentResult = processPaymongoPayment($conn, $tenantID, $amount, 'PHP', $description, $email);
            
            if ($paymentResult['success']) {
                // Store the payment ID in session for later retrieval
                $_SESSION['paymongo_payment_id'] = $paymentResult['paymentId'];
                $_SESSION['paymongo_cardholder'] = $cardholderName;
                $_SESSION['paymongo_amount'] = $amount;
                $_SESSION['paymongo_billing_cycle'] = $billingCycle;
                $successMessage = 'Payment system initialized. Please enter your card details and complete payment.';
            } else {
                $errors[] = $paymentResult['error'] ?? 'Failed to initialize payment. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Complete Payment | RapidRepairCo.</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "secondary-fixed-dim": "#cbd5e1",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "on-secondary-fixed": "#0f172a",
                        "primary-fixed": "#dbeafe",
                        "outline": "#e2e8f0",
                        "on-secondary": "#ffffff",
                        "surface-container": "#ffffff",
                        "on-error-container": "#991b1b",
                        "secondary-fixed": "#e2e8f0",
                        "surface-container-highest": "#ffffff",
                        "tertiary-container": "#fef3c7",
                        "on-secondary-container": "#1e293b",
                        "primary-fixed-dim": "#bfdbfe",
                        "on-primary": "#ffffff",
                        "surface-container-low": "#ffffff",
                        "on-tertiary-fixed": "#7c2d12",
                        "on-secondary-fixed-variant": "#334155",
                        "outline-variant": "#cbd5e1",
                        "error-container": "#fee2e2",
                        "background": "#f6f6f8",
                        "surface-bright": "#ffffff",
                        "surface": "#f6f6f8",
                        "on-tertiary-container": "#92400e",
                        "surface-container-lowest": "#ffffff",
                        "on-primary-fixed": "#1e3a8a",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "inverse-on-surface": "#f8fafc",
                        "tertiary-fixed": "#ffedd5",
                        "on-primary-container": "#1152d4",
                        "on-error": "#ffffff",
                        "inverse-primary": "#b4c5ff",
                        "surface-container-high": "#ffffff",
                        "on-background": "#0f172a",
                        "inverse-surface": "#1e293b",
                        "surface-variant": "#f1f5f9",
                        "secondary": "#475569",
                        "error": "#ef4444",
                        "secondary-container": "#f1f5f9",
                        "primary-container": "#eef2ff",
                        "on-tertiary": "#ffffff",
                        "surface-tint": "#1152d4",
                        "tertiary": "#f59e0b",
                        "surface-dim": "#d9d9e4",
                        "on-surface-variant": "#64748b",
                        "tertiary-fixed-dim": "#fed7aa",
                        "primary": "#1152d4",
                        "on-surface": "#0f172a"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "fontFamily": {
                        "headline": ["Inter"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    }
                },
            },
        }
    </script>
    <script>
        // Simple payment form handler - uses server-side processing
        document.addEventListener('DOMContentLoaded', function() {
            const paymentButton = document.getElementById('paymentButton');
            
            if (paymentButton) {
                paymentButton.addEventListener('click', async function(e) {
                    e.preventDefault();
                    
                    const cardholderName = document.querySelector('input[name="cardholderName"]').value.trim();
                    const cardNumber = document.querySelector('input[name="cardNumber"]').value.replace(/\s+/g, '');
                    const expiryDate = document.querySelector('input[name="expiryDate"]').value.trim();
                    const cvv = document.querySelector('input[name="cvv"]').value.trim();
                    const form = document.querySelector('form');
                    const errorDiv = document.getElementById('card-error');

                    // Validation
                    if (!cardholderName || cardholderName.length < 2) {
                        errorDiv.textContent = 'Please enter a valid cardholder name (at least 2 characters)';
                        return;
                    }

                    if (!cardNumber || cardNumber.length < 13) {
                        errorDiv.textContent = 'Please enter a valid card number';
                        return;
                    }

                    if (!expiryDate || !expiryDate.match(/^\d{2}\/\d{2}$/)) {
                        errorDiv.textContent = 'Please enter expiry date in MM/YY format';
                        return;
                    }

                    if (!cvv || cvv.length < 3) {
                        errorDiv.textContent = 'Please enter a valid CVV';
                        return;
                    }

                    // Disable button during processing
                    paymentButton.disabled = true;
                    paymentButton.textContent = 'Processing Payment...';
                    errorDiv.textContent = '';

                    try {
                        // Add card details to form for server processing
                        const cardNumberInput = document.createElement('input');
                        cardNumberInput.type = 'hidden';
                        cardNumberInput.name = 'card_number';
                        cardNumberInput.value = cardNumber;
                        form.appendChild(cardNumberInput);

                        const expiryInput = document.createElement('input');
                        expiryInput.type = 'hidden';
                        expiryInput.name = 'card_expiry';
                        expiryInput.value = expiryDate;
                        form.appendChild(expiryInput);

                        const cvvInput = document.createElement('input');
                        cvvInput.type = 'hidden';
                        cvvInput.name = 'card_cvv';
                        cvvInput.value = cvv;
                        form.appendChild(cvvInput);

                        // Submit form to server for processing
                        form.submit();
                    } catch (error) {
                        console.error('Payment error:', error);
                        errorDiv.textContent = error.message || 'An error occurred. Please try again.';
                        paymentButton.disabled = false;
                        paymentButton.textContent = 'Complete Payment with Paymongo';
                    }
                });
            }

            // Format card number with spaces
            const cardNumberInput = document.querySelector('input[name="cardNumber"]');
            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\s+/g, '').replace(/[^\d]/g, '');
                    let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
                    e.target.value = formatted;
                });
            }

            // Format expiry date
            const expiryInput = document.querySelector('input[name="expiryDate"]');
            if (expiryInput) {
                expiryInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length >= 2) {
                        value = value.substring(0, 2) + '/' + value.substring(2, 4);
                    }
                    e.target.value = value;
                });
            }

            // Format CVV (numbers only)
            const cvvInput = document.querySelector('input[name="cvv"]');
            if (cvvInput) {
                cvvInput.addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
                });
            }
        });
    </script>
    <script>
        // Plans data from PHP
        const plansData = <?php echo json_encode(array_map(function($plan) {
            return [
                'plan_id' => $plan['plan_id'],
                'plan_code' => $plan['plan_code'],
                'plan_name' => $plan['plan_name'],
                'monthly_price' => (float)$plan['monthly_price'],
                'description' => isset($plan['description']) ? $plan['description'] : ''
            ];
        }, $subscriptionPlans)); ?>;

        function updatePaymentMethodFields() {
            // For Paymongo, only card is available
            const cardFields = document.getElementById('cardFields');
            if (cardFields) {
                cardFields.style.display = 'block';
            }
        }

        function updateSelectedPlan(planCode) {
            // Find the plan in our data
            const plan = plansData.find(p => p.plan_code.toLowerCase() === planCode.toLowerCase());
            if (!plan) return;

            // Update the hidden input
            document.getElementById('selectedPlanCode').value = plan.plan_code;

            // Update display
            document.getElementById('planCodeDisplay').textContent = plan.plan_code;
            document.getElementById('planNameDisplay').textContent = plan.plan_name + ' Plan';
            document.getElementById('planPriceDisplay').textContent = '₱' + plan.monthly_price.toFixed(2);
            document.getElementById('orderSummaryPrice').textContent = '₱' + plan.monthly_price.toFixed(2);
            document.getElementById('subtotalPrice').textContent = '₱' + plan.monthly_price.toFixed(2);
            document.getElementById('totalPrice').textContent = '₱' + plan.monthly_price.toFixed(2);

            // Update plan selector cards if they exist
            document.querySelectorAll('[data-plan-card]').forEach(card => {
                const cardPlanCode = card.dataset.planCard.toLowerCase();
                if (cardPlanCode === planCode.toLowerCase()) {
                    card.classList.remove('border-slate-200', 'hover:border-primary');
                    card.classList.add('border-2', 'border-primary', 'bg-primary-fixed');
                } else {
                    card.classList.remove('border-2', 'border-primary', 'bg-primary-fixed');
                    card.classList.add('border-slate-200', 'hover:border-primary');
                }
            });
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .architectural-gradient {
            background: linear-gradient(180deg, rgba(17, 82, 212, 0.05) 0%, rgba(17, 82, 212, 0) 100%);
        }
    </style>
</head>

<body class="bg-background text-on-background min-h-screen flex flex-col">
    <!-- TopNavBar -->
    <nav
        class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-none flex justify-between items-center w-full px-8 h-16 max-w-full font-['Inter'] tracking-tight fixed top-0 z-50">
        <div class="text-xl font-black text-primary dark:text-blue-500 uppercase">RapidRepairCo.</div>
        <div class="hidden md:flex items-center space-x-8">
            <span class="text-slate-600 dark:text-slate-400 font-medium">Payment Setup</span>
        </div>
        <div class="flex items-center gap-4">
            <a href="clientlanding.php" class="text-slate-600 dark:text-slate-400 font-medium hover:text-primary transition-colors">Back</a>
        </div>
    </nav>
    <main class="mt-16 flex-grow flex flex-col items-center px-4 md:px-8 py-12 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <header class="w-full mb-12 text-center md:text-left">
            <h1 class="text-[30px] font-black text-on-background tracking-tight mb-2">Complete Your Payment</h1>
            <p class="text-on-surface-variant text-sm font-medium">Select your subscription plan and provide payment details. After submission, your application will be reviewed by our team.</p>
        </header>

        <?php if ($successMessage !== ''): ?>
            <div class="w-full mb-6 rounded-lg border border-green-200 bg-green-50 px-6 py-4 text-sm text-green-800">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-green-600">check_circle</span>
                    <div><?php echo htmlspecialchars($successMessage); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (count($errors) > 0): ?>
            <div class="w-full mb-6 rounded-lg border border-red-200 bg-red-50 px-6 py-4 text-sm text-red-700">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-red-600">error</span>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 w-full">
            <!-- Plan Selection & Payment (LHS) -->
            <div class="lg:col-span-8 space-y-8">
                <!-- Plan Selection -->
                <section class="space-y-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined text-primary" data-icon="layers">layers</span>
                        <h2 class="text-2xl font-bold tracking-tight">Choose Your Plan</h2>
                    </div>
                    
                    <!-- Plan Cards Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($subscriptionPlans as $plan): ?>
                            <div 
                                class="plan-card p-6 border-2 rounded-lg transition-all cursor-pointer <?php echo strtolower($selectedPlan['plan_code']) === strtolower($plan['plan_code']) ? 'border-primary bg-primary-fixed' : 'border-slate-200 hover:border-primary'; ?>"
                                data-plan-card="<?php echo htmlspecialchars($plan['plan_code']); ?>"
                                onclick="updateSelectedPlan('<?php echo htmlspecialchars($plan['plan_code']); ?>')">
                                <div class="mb-3">
                                    <span class="text-xs font-bold uppercase tracking-widest text-primary"><?php echo htmlspecialchars($plan['plan_code']); ?></span>
                                    <h3 class="text-lg font-bold mt-2"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                                </div>
                                <div class="mb-4">
                                    <span class="text-2xl font-black">₱<?php echo number_format($plan['monthly_price'], 2); ?></span>
                                    <span class="text-sm text-on-surface-variant">/month</span>
                                </div>
                                <?php if (!empty($plan['description'])): ?>
                                    <p class="text-xs text-on-surface-variant"><?php echo htmlspecialchars($plan['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Selected Plan Summary -->
                <section class="bg-white border-2 border-primary rounded-xl p-8 shadow-md">
                    <h3 class="text-lg font-bold mb-4">Your Selected Plan</h3>
                    <div class="mb-6">
                        <span class="text-xs font-bold uppercase tracking-widest text-primary" id="planCodeDisplay"><?php echo htmlspecialchars($selectedPlan['plan_code']); ?></span>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-4xl font-black text-on-background" id="planPriceDisplay">₱<?php echo number_format($selectedPlan['monthly_price'], 2); ?></span>
                            <span class="text-on-surface-variant text-sm">/month</span>
                        </div>
                        <p class="mt-3 text-sm text-on-surface-variant" id="planNameDisplay"><?php echo htmlspecialchars($selectedPlan['plan_name']); ?> Plan</p>
                    </div>
                    <div class="pt-4 border-t border-outline">
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-tighter mb-2">Billing Cycle</label>
                        <div class="grid grid-cols-3 gap-3">
                            <button type="button" class="py-2 px-3 rounded-lg border-2 transition-all text-sm font-semibold <?php echo $billingCycle === 'monthly' ? 'border-primary bg-primary-fixed text-primary' : 'border-slate-200 text-on-surface-variant hover:border-primary'; ?>"
                                onclick="document.querySelector('input[name=\'billingCycle\']').value='monthly'; this.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('border-primary', 'bg-primary-fixed', 'text-primary')); this.parentElement.querySelectorAll('button').forEach(b => b.classList.add('border-slate-200', 'text-on-surface-variant')); this.classList.remove('border-slate-200', 'text-on-surface-variant'); this.classList.add('border-primary', 'bg-primary-fixed', 'text-primary');">
                                Monthly
                            </button>
                            <button type="button" class="py-2 px-3 rounded-lg border-2 transition-all text-sm font-semibold <?php echo $billingCycle === 'quarterly' ? 'border-primary bg-primary-fixed text-primary' : 'border-slate-200 text-on-surface-variant hover:border-primary'; ?>"
                                onclick="document.querySelector('input[name=\'billingCycle\']').value='quarterly'; this.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('border-primary', 'bg-primary-fixed', 'text-primary')); this.parentElement.querySelectorAll('button').forEach(b => b.classList.add('border-slate-200', 'text-on-surface-variant')); this.classList.remove('border-slate-200', 'text-on-surface-variant'); this.classList.add('border-primary', 'bg-primary-fixed', 'text-primary');">
                                Quarterly
                            </button>
                            <button type="button" class="py-2 px-3 rounded-lg border-2 transition-all text-sm font-semibold <?php echo $billingCycle === 'yearly' ? 'border-primary bg-primary-fixed text-primary' : 'border-slate-200 text-on-surface-variant hover:border-primary'; ?>"
                                onclick="document.querySelector('input[name=\'billingCycle\']').value='yearly'; this.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('border-primary', 'bg-primary-fixed', 'text-primary')); this.parentElement.querySelectorAll('button').forEach(b => b.classList.add('border-slate-200', 'text-on-surface-variant')); this.classList.remove('border-slate-200', 'text-on-surface-variant'); this.classList.add('border-primary', 'bg-primary-fixed', 'text-primary');">
                                Yearly
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Payment Details -->
                <section class="bg-white border border-slate-200 rounded-xl p-8 space-y-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined text-primary" data-icon="lock">lock</span>
                        <h2 class="text-xl font-bold tracking-tight">Secure Payment Information</h2>
                    </div>

                    <form class="space-y-6" method="post" action="">
                        <input type="hidden" name="processPayment" value="1" />
                        <input type="hidden" id="selectedPlanCode" name="selectedPlanCode" value="<?php echo htmlspecialchars($selectedPlan['plan_code']); ?>" />
                        <input type="hidden" name="billingCycle" value="<?php echo htmlspecialchars($billingCycle); ?>" />

                        <!-- Cardholder Name -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-tighter">Cardholder Name</label>
                            <input type="text" name="cardholderName" required
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400"
                                placeholder="Full legal name" />
                        </div>

                        <!-- Payment Method Selection -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-tighter">Payment Method</label>
                            <select id="paymentMethod" name="paymentMethod" onchange="updatePaymentMethodFields()" required
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
                                <option value="Card">Credit / Debit Card (via Paymongo)</option>
                            </select>
                            <p class="text-xs text-slate-500 mt-1">Currently only credit/debit card payments are available. We accept Visa, Mastercard, and other major cards.</p>
                        </div>

                        <!-- Card Fields - Traditional Input -->
                        <div id="cardFields" class="space-y-4">
                            <div class="space-y-2">
                                <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-tighter">Card Number</label>
                                <input type="text" name="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400" required />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-tighter">Expiry Date</label>
                                    <input type="text" name="expiryDate" placeholder="MM/YY" maxlength="5"
                                        class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400" required />
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-tighter">CVV</label>
                                    <input type="text" name="cvv" placeholder="***" maxlength="4"
                                        class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400" required />
                                </div>
                            </div>
                            <div id="card-error" class="text-red-600 text-sm mt-2"></div>
                        </div>

                        <div class="pt-4 border-t border-slate-200">
                            <button type="button" id="paymentButton"
                                class="w-full py-4 bg-primary text-white text-sm font-black uppercase tracking-widest rounded-lg shadow-lg hover:shadow-primary/20 hover:brightness-110 transition-all flex items-center justify-center gap-2">
                                Complete Payment with Paymongo
                                <span class="material-symbols-outlined" data-icon="arrow_forward">arrow_forward</span>
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <!-- Summary (RHS) -->
            <div class="lg:col-span-4">
                <aside class="sticky top-28 space-y-6">
                    <div class="bg-slate-900 text-white rounded-xl p-8 shadow-xl overflow-hidden relative">
                        <!-- Decorative element -->
                        <div class="absolute -right-12 -top-12 w-32 h-32 bg-primary/20 rounded-full blur-3xl"></div>
                        <h3 class="text-lg font-bold mb-6 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary"
                                data-icon="receipt_long">receipt_long</span>
                            Order Summary
                        </h3>
                        <div class="space-y-4 relative z-10">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-bold text-white" id="orderSummaryPlanName"><?php echo htmlspecialchars($selectedPlan['plan_name']); ?> Plan</p>
                                    <p class="text-xs text-slate-400">Billing Cycle: <span class="capitalize font-semibold text-slate-300"><?php echo htmlspecialchars($billingCycle); ?></span></p>
                                </div>
                                <span class="text-sm font-bold" id="orderSummaryPrice">₱<?php echo number_format($selectedPlan['monthly_price'], 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center text-sm border-t border-slate-800 pt-4">
                                <span class="text-slate-400">Subtotal</span>
                                <span id="subtotalPrice">₱<?php echo number_format($selectedPlan['monthly_price'], 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-400">Tax (0%)</span>
                                <span>₱0.00</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-400">Setup Fee</span>
                                <span class="text-primary-fixed-dim font-bold">WAIVED</span>
                            </div>
                            <div class="flex justify-between items-center pt-6 border-t border-slate-800">
                                <span class="text-lg font-black uppercase tracking-tight">Total</span>
                                <span class="text-2xl font-black text-primary-fixed-dim" id="totalPrice">₱<?php echo number_format($selectedPlan['monthly_price'], 2); ?></span>
                            </div>
                        </div>
                        <p
                            class="mt-6 text-[10px] text-center text-slate-500 uppercase tracking-widest leading-relaxed">
                            Secure Encrypted Payment. Your application will be reviewed within 1-2 business days.
                        </p>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-6">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <span class="material-symbols-outlined text-primary text-lg"
                                    data-icon="verified_user">verified_user</span>
                            </div>
                            <p class="text-sm font-bold text-on-primary-container">Secure Guarantee</p>
                        </div>
                        <p class="text-xs text-on-surface-variant leading-relaxed">
                            All payment information is encrypted and secure. You can modify your plan or cancel anytime.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </main>
    <!-- Footer -->
    <footer
        class="bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 flex justify-between items-center px-8 py-4 w-full mt-auto font-['Inter'] text-xs font-semibold">
        <div class="text-slate-500 dark:text-slate-500">© 2026 RapidRepairCo. Secure Encrypted Environment.
        </div>
        <div class="flex gap-6">
            <a class="text-slate-500 dark:text-slate-500 hover:text-primary dark:hover:text-blue-400 transition-colors"
                href="#">Privacy Policy</a>
            <a class="text-slate-500 dark:text-slate-500 hover:text-primary dark:hover:text-blue-400 transition-colors"
                href="#">Terms of Service</a>
            <a class="text-slate-500 dark:text-slate-500 hover:text-primary dark:hover:text-blue-400 transition-colors"
                href="#">Support</a>
        </div>
    </footer>
</body>

</html>