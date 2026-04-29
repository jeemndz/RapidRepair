<?php
session_start();
require_once "config.php";

$paymentIntentId = $_SESSION["payment_intent_id"] ?? null;

if (!$paymentIntentId) {
    die("No payment intent found.");
}

$paymentMethod = $_POST["payment_method"] ?? "gcash";

$methodPayload = [
    "data" => [
        "attributes" => [
            "type" => $paymentMethod,
            "billing" => [
                "name" => "Test User",
                "email" => "test@example.com",
                "phone" => "09171234567"
            ]
        ]
    ]
];

function paymongoPost($url, $payload, $secretKey)
{
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "content-type: application/json",
            "authorization: Basic " . base64_encode($secretKey . ":")
        ],
    ]);

    $response = curl_exec($curl);
    $error = curl_error($curl);

    curl_close($curl);

    if ($error) {
        die("cURL Error: " . $error);
    }

    return json_decode($response, true);
}

// Create Payment Method
$method = paymongoPost(
    "https://api.paymongo.com/v1/payment_methods",
    $methodPayload,
    $PAYMONGO_SECRET_KEY
);

$paymentMethodId = $method["data"]["id"] ?? null;

if (!$paymentMethodId) {
    echo "<pre>";
    print_r($method);
    echo "</pre>";
    die("Failed to create payment method.");
}

// Attach Payment Method to Payment Intent
$attachPayload = [
    "data" => [
        "attributes" => [
            "payment_method" => $paymentMethodId,
            "return_url" => $BASE_URL . "/payment_success.php"
        ]
    ]
];

$attach = paymongoPost(
    "https://api.paymongo.com/v1/payment_intents/" . $paymentIntentId . "/attach",
    $attachPayload,
    $PAYMONGO_SECRET_KEY
);

$redirectUrl = $attach["data"]["attributes"]["next_action"]["redirect"]["url"] ?? null;

if ($redirectUrl) {
    header("Location: " . $redirectUrl);
    exit;
}

echo "<pre>";
print_r($attach);
echo "</pre>";