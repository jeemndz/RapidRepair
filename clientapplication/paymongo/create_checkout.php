<?php
session_start();

require_once "config.php";

$amountCentavos = intval($_POST["amount"] ?? 12100);

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

$description = "Tenant ID: {$tenantId} | Plan ID: {$planId} | Billing Cycle: {$billingCycle}";

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
            "description" => $description,
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
    die("cURL Error: " . $error);
}

$result = json_decode($response, true);

$checkoutUrl = $result["data"]["attributes"]["checkout_url"] ?? null;

if ($checkoutUrl) {
    header("Location: " . $checkoutUrl);
    exit;
}

echo "<pre>";
print_r($result);
echo "</pre>";