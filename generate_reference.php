<?php
include "db.php";

// Get last payment ID
$res = $conn->query("SELECT payment_id FROM payments ORDER BY payment_id DESC LIMIT 1");
$row = $res->fetch_assoc();
$lastId = $row ? $row['payment_id'] : 0;

// Create reference number
$refNumber = 'PAY-' . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);

echo json_encode(['referenceNumber' => $refNumber]);
