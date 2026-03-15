<?php

$host = "rapidrepairs.mysql.database.azure.com";
$user = "rradmin1";
$pass = "rradmin123!";
$db   = "rapidrepairs";
$port = 3306;

// Initialize mysqli
$conn = mysqli_init();

// Enable SSL but skip certificate verification
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);

// Connect using SSL
if (!mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL)) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");

?>
