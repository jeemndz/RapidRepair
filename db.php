<?php

$host = "rapidrepairs.mysql.database.azure.com";
$user = "rradmin1";
$pass = "rradmin123!";
$db   = "rapidrepairs";
$port = 3306;

// Production domain configuration
define('PRODUCTION_DOMAIN', 'rapidrepair-gygpcbczgyg0czek.southeastasia-01.azurewebsites.net');
define('ALLOW_LOCALHOST_LINKS', true); // Set to false to always use production domain

$conn = mysqli_init();

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to initialize database connection'
    ]);
    exit;
}

// Azure MySQL SSL
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);

if (!mysqli_real_connect($conn, $host, $user, $pass, $db, $port, null, MYSQLI_CLIENT_SSL)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . mysqli_connect_error()
    ]);
    exit;
}

mysqli_set_charset($conn, "utf8mb4");