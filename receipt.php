<?php
include "db.php";

if (!isset($_GET['service_id'])) {
    die("Invalid receipt request.");
}
$service_id = (int) $_GET['service_id'];

/*
  REQUIREMENT:
  Your `services` table should have:
   - laborFee (DECIMAL)
   - partsCost (DECIMAL)

  If your column names are different, tell me and I’ll adjust.
*/
$stmt = $conn->prepare("
    SELECT 
        s.service_id,
        s.serviceCategory,
        s.laborFee,
        s.partsCost,
        s.totalCost,
        p.paymentDate,
        p.paymentMethod,
        c.firstName,
        c.lastName
    FROM services s
    INNER JOIN payments p ON s.service_id = p.service_id
    INNER JOIN client_information c ON s.client_id = c.client_id
    WHERE s.service_id = ?
");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Receipt not found.");
}

$data = $result->fetch_assoc();

// If fields are null, default to 0.00 so it won't break
$laborFee  = (float)($data['laborFee'] ?? 0);
$partsCost = (float)($data['partsCost'] ?? 0);

// Total = labor + parts (or use totalCost if you prefer)
$total = (float)($data['totalCost'] ?? ($laborFee + $partsCost));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f5f5; padding:40px; }
        .receipt { background:#fff; padding:30px; width:800px; margin:auto; border:1px solid #ccc; }
        .header { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; }
        .brand { display:flex; align-items:center; gap:12px; }
        .brand img { width:70px; height:auto; }
        .brand .tagline { font-size:12px; color:#555; margin-top:2px; }
        .title { text-align:right; }
        .title h1 { margin:0; }
        .meta p { margin:4px 0; }
        table { width:100%; border-collapse:collapse; margin-top:18px; }
        th, td { padding:10px; border-bottom:1px solid #ccc; }
        th { background:#000; color:#fff; text-align:left; }
        .totals { width:360px; margin-left:auto; margin-top:14px; }
        .totals td { border:none; padding:6px 10px; }
        .actions { display:flex; justify-content:flex-end; gap:10px; margin-top:18px; }
        .btn { padding:8px 14px; border:none; cursor:pointer; border-radius:6px; text-decoration:none; display:inline-block; }
        .btn-print { background:#111; color:#fff; }
        .footer { margin-top:40px; text-align:center; font-style:italic; }

        .shop-details {
            margin-top: 10px;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }

        @media print {
            .actions { display:none; }
            body { background:#fff; padding:0; }
            .receipt { border:none; width:auto; }
        }
    </style>
</head>

<body>
<div class="receipt">

    <div class="header">
        <div class="brand">
            <img src="rapidlogo.png" alt="Rapid Repair Logo">
            <div>
                <div style="font-weight:bold; font-size:16px;">Rapid Repair</div>
                <div class="tagline">Commitment is our Passion</div>

                <!-- SHOP DETAILS -->
                <div class="shop-details">
                    <div><strong>Address:</strong> DRT highway Barangay Sabang, Baliuag City, Bulacan</div>
                    <div><strong>Contact:</strong> 0953 280 7426</div>
                </div>
            </div>
        </div>

        <div class="title">
            <h1>RECEIPT</h1>
        </div>
    </div>

    <hr style="margin:16px 0;">

    <div class="meta">
        <p><strong>Receipt Number:</strong> RR-<?= str_pad($data['service_id'], 5, '0', STR_PAD_LEFT) ?></p>
        <p><strong>Receipt Date:</strong> <?= date("Y-m-d", strtotime($data['paymentDate'])) ?></p>
        <p><strong>Payment Method:</strong> <?= htmlspecialchars($data['paymentMethod']) ?></p>

        <br>

        <p><strong>Customer:</strong><br>
            <?= htmlspecialchars($data['firstName'] . ' ' . $data['lastName']) ?>
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Qty</th>
                <th>Description</th>
                <th>Labor Fee</th>
                <th>Parts Cost</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td><?= htmlspecialchars($data['serviceCategory']) ?></td>
                <td>₱<?= number_format($laborFee, 2) ?></td>
                <td>₱<?= number_format($partsCost, 2) ?></td>
                <td>₱<?= number_format($laborFee + $partsCost, 2) ?></td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Labor Fee:</td>
            <td>₱<?= number_format($laborFee, 2) ?></td>
        </tr>
        <tr>
            <td>Parts Cost:</td>
            <td>₱<?= number_format($partsCost, 2) ?></td>
        </tr>
        <tr>
            <td><strong>Total:</strong></td>
            <td><strong>₱<?= number_format($total, 2) ?></strong></td>
        </tr>
    </table>

    <div class="actions">
        <button class="btn btn-print" onclick="window.print()">🖨 Save as PDF / Print</button>
    </div>

    <div class="footer">Thank you for your payment!</div>
</div>
</body>
</html>
