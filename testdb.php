<?php
require_once("db.php"); // include your db.php

// Simple test query
$result = mysqli_query($conn, "SELECT NOW() AS server_time");

if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "Database is online! Current server time: " . $row['server_time'];
} else {
    echo "Database test failed: " . mysqli_error($conn);
}
?>