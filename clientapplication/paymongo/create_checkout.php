<?php
session_start();
require_once "config.php";

$amount = intval($_POST["amount"] ?? 12100);
$name = $_POST["name"] ?? "John Maverick Mendoza";
$email = $_POST["email"] ?? "test@example.com";
$phone = $_POST["phone"] ?? "09171234567";

$tenantId = $_POST["tenant_id"] ?? "1";
$planName = $_POST["plan_name"] ?? "RapidRepairCo. Subscription";

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
            "description" => "Tenant ID: " . $tenantId,

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
                    "amount" => $amount,
                    "name" => $planName,
                    "description" => $planName,
                    "quantity" => 1
                ]
            ],

            // ✅ USE BASE_URL (AUTO LOCAL/AZURE)
            "success_url" => $BASE_URL . "/payment_success.php",
            "cancel_url"  => $BASE_URL . "/payment_failed.php"
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
        "authorization: " . "Basic " . base64_encode($PAYMONGO_SECRET_KEY . ":")
    ],
]);

$response = curl_exec($curl);
$error = curl_error($curl);
curl_close($curl);

if ($error) {
    die("cURL Error: " . $error);
}

$result = json_decode($response, true);

$checkoutSessionId = $result["data"]["id"] ?? null;
$checkoutUrl = $result["data"]["attributes"]["checkout_url"] ?? null;

// ✅ SAVE SESSION DATA
if ($checkoutSessionId) {
    $_SESSION["checkout_session_id"] = $checkoutSessionId;
    $_SESSION["amount"] = $amount;
    $_SESSION["tenant_id"] = $tenantId;
    $_SESSION["plan_name"] = $planName;
    $_SESSION["customer_name"] = $name;
    $_SESSION["customer_email"] = $email;
    $_SESSION["customer_phone"] = $phone;
}

// ✅ REDIRECT TO PAYMONGO CHECKOUT
if ($checkoutUrl) {
    header("Location: " . $checkoutUrl);
    exit;
}

// ❌ DEBUG OUTPUT
echo "<pre>";
print_r($result);
echo "</pre>";
