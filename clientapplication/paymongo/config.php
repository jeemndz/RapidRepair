<?php

// Secret key (use env if available)
$PAYMONGO_SECRET_KEY = getenv("PAYMONGO_SECRET_KEY") ?: "sk_test_Z6ynXiE7Ywm2KmTCoKsHZnz2";

// Detect if running on localhost or Azure
$host = $_SERVER['HTTP_HOST'] ?? '';

// LOCAL
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    $BASE_URL = "http://localhost/RapidRepair/clientapplication/paymongo";
} 
// AZURE (LIVE / DEPLOYED)
else {
    $BASE_URL = "https://rapidrepair-gygpcbczygy0czek.southeastasia-01.azurewebsites.net/clientapplication/paymongo";
}