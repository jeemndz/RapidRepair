<?php
header('Content-Type: application/json');

$secretKey = "sk_test_Z6ynXiE7Ywm2KmTCoKsHZnz2"; // your PayMongo secret key

$amount = 2000; // ₱20.00 because PayMongo amount is in centavos

$payload = [
    "data" => [
        "attributes" => [
            "amount" => $amount,
            "payment_method_allowed" => [
                "qrph",
                "card",
                "dob",
                "gcash"
            ],
            "payment_method_options" => [
                "card" => [
                    "request_three_d_secure" => "any"
                ]
            ],
            "currency" => "PHP",
            "capture_type" => "automatic",
            "description" => "Test Payment"
        ]
    ]
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.paymongo.com/v1/payment_intents",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "accept: application/json",
        "content-type: application/json",
        "authorization: Basic " . base64_encode($secretKey . ":")
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($error) {
    echo json_encode([
        "success" => false,
        "message" => "cURL Error",
        "error" => $error
    ]);
    exit;
}

echo json_encode([
    "success" => $httpCode >= 200 && $httpCode < 300,
    "http_code" => $httpCode,
    "response" => json_decode($response, true)
], JSON_PRETTY_PRINT);