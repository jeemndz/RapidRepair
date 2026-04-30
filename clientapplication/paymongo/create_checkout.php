<?php
session_start();

require_once "config.php";
include __DIR__ . "/../../db.php";

$amountCentavos = intval($_POST["amount"] ?? 12100);
$amount = $amountCentavos / 100;

$name = $_POST["name"] ?? "John Maverick Mendoza";
$email = $_POST["email"] ?? "test@example.com";
$phone = $_POST["phone"] ?? "09171234567";

$tenantId = intval($_POST["tenant_id"] ?? 1);
$planId = intval($_POST["plan_id"] ?? 0);
$planName = $_POST["plan_name"] ?? "RapidRepairCo. Subscription";
$billingCycle = $_POST["billingCycle"] ?? "monthly";
$paymentSource = $_POST["payment_source"] ?? "clientpayment";

if (!in_array($billingCycle, ["monthly", "quarterly", "yearly"], true)) {
    $billingCycle = "monthly";
}

$startDate = date("Y-m-d");

if ($billingCycle === "quarterly") {
    $endDate = date("Y-m-d", strtotime("+3 months"));
} elseif ($billingCycle === "yearly") {
    $endDate = date("Y-m-d", strtotime("+1 year"));
} else {
    $endDate = date("Y-m-d", strtotime("+1 month"));
}

$nextBillingDate = $endDate;

mysqli_begin_transaction($conn);

try {
    $subStmt = mysqli_prepare($conn, "
        INSERT INTO subscriptions (
            tenantID,
            plan_id,
            billing_cycle,
            start_date,
            end_date,
            next_billing_date,
            amount,
            status,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
    ");

    mysqli_stmt_bind_param(
        $subStmt,
        "iissssd",
        $tenantId,
        $planId,
        $billingCycle,
        $startDate,
        $endDate,
        $nextBillingDate,
        $amount
    );

    mysqli_stmt_execute($subStmt);
    $subscriptionId = mysqli_insert_id($conn);
    mysqli_stmt_close($subStmt);

    $paymentMethod = "Card";
    $paymentStatus = "Pending";

    $paymentStmt = mysqli_prepare($conn, "
        INSERT INTO subscription_payments (
            tenantID,
            subscription_id,
            plan_id,
            amount,
            payment_method,
            payment_status,
            transaction_reference,
            gcash_reference,
            billing_period_start,
            billing_period_end,
            paid_at,
            next_billing_date,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, NULL, ?, NOW(), NOW())
    ");

    mysqli_stmt_bind_param(
        $paymentStmt,
        "iiidsssss",
        $tenantId,
        $subscriptionId,
        $planId,
        $amount,
        $paymentMethod,
        $paymentStatus,
        $startDate,
        $endDate,
        $nextBillingDate
    );

    mysqli_stmt_execute($paymentStmt);
    $paymentId = mysqli_insert_id($conn);
    mysqli_stmt_close($paymentStmt);

    mysqli_commit($conn);

} catch (Throwable $e) {
    mysqli_rollback($conn);
    die("Database Error: " . $e->getMessage());
}

$payload = [
    "data" => [
        "attributes" => [
            "billing" => [
                "name" => $name,
                "email" => $email,
                "phone" => $phone
            ],
            "send_email_receipt" => false,
            "show_description" => true,
            "show_line_items" => true,
            "description" => "Tenant ID: " . $tenantId . " | Payment ID: " . $paymentId,

            "payment_method_types" => [
                "card",
                "gcash",
                "paymaya",
                "grab_pay",
                "qrph"
            ],

            "line_items" => [
                [
                    "currency" => "PHP",
                    "amount" => $amountCentavos,
                    "name" => $planName,
                    "description" => $planName,
                    "quantity" => 1
                ]
            ],

            "success_url" => $BASE_URL . "/payment_success.php?source=" . urlencode($paymentSource),
            "cancel_url" => $BASE_URL . "/payment_failed.php?source=" . urlencode($paymentSource)
        ]
    ]
];

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paymongo.com/v1/checkout_sessions",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        "accept: application/json",
        "content-type: application/json",
        "authorization: Basic " . base64_encode($PAYMONGO_SECRET_KEY . ":")
    ],
]);

$response = curl_exec($curl);
$error = curl_error($curl);
curl_close($curl);

if ($error) {
    mysqli_query($conn, "
        UPDATE subscription_payments 
        SET payment_status = 'Failed', updated_at = NOW()
        WHERE payment_id = " . intval($paymentId)
    );

    die("cURL Error: " . $error);
}

$result = json_decode($response, true);

$checkoutSessionId = $result["data"]["id"] ?? null;
$checkoutUrl = $result["data"]["attributes"]["checkout_url"] ?? null;

if (!$checkoutSessionId || !$checkoutUrl) {
    mysqli_query($conn, "
        UPDATE subscription_payments 
        SET payment_status = 'Failed', updated_at = NOW()
        WHERE payment_id = " . intval($paymentId)
    );

    echo "<pre>";
    print_r($result);
    echo "</pre>";
    exit;
}

$updateStmt = mysqli_prepare($conn, "
    UPDATE subscription_payments
    SET 
        transaction_reference = ?,
        checkout_session_id = ?,
        updated_at = NOW()
    WHERE payment_id = ?
");

mysqli_stmt_bind_param($updateStmt, "ssi", $checkoutSessionId, $checkoutSessionId, $paymentId);
mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

$_SESSION["payment_id"] = $paymentId;
$_SESSION["checkout_session_id"] = $checkoutSessionId;
$_SESSION["subscription_id"] = $subscriptionId;
$_SESSION["amount"] = $amountCentavos;
$_SESSION["tenant_id"] = $tenantId;
$_SESSION["plan_id"] = $planId;
$_SESSION["plan_name"] = $planName;
$_SESSION["billingCycle"] = $billingCycle;
$_SESSION["customer_name"] = $name;
$_SESSION["customer_email"] = $email;
$_SESSION["customer_phone"] = $phone;
$_SESSION["payment_source"] = $paymentSource;

header("Location: " . $checkoutUrl);
exit;