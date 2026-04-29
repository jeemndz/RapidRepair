<?php

$PAYMONGO_SECRET_KEY = getenv("PAYMONGO_SECRET_KEY") ?: "sk_test_Z6ynXiE7Ywm2KmTCoKsHZnz2";

$host = $_SERVER['HTTP_HOST'] ?? '';

if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    $BASE_URL = "http://localhost/RapidRepair/clientapplication/paymongo";
} else {
    $BASE_URL = "https://rapidrepair-gygpcbczgyg0czek.southeastasia-01.azurewebsites.net/clientapplication/paymongo";
}