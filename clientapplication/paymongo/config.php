<?php

$PAYMONGO_PUBLIC_KEY = getenv("PAYMONGO_PUBLIC_KEY");
$PAYMONGO_SECRET_KEY = getenv("PAYMONGO_SECRET_KEY");

$BASE_URL = getenv("BASE_URL") ?: "http://localhost/RapidRepair/clientapplication/paymongo";

if (!$PAYMONGO_SECRET_KEY) {
    die("PAYMONGO_SECRET_KEY is not configured.");
}