<?php
session_start();

// Default values (you can replace with DB/session)
$amount = 12100; // ₱121.00
$name = "Amiel Carl Santos";
$email = "test@example.com";
$phone = "09171234567";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment</title>
</head>

<body style="font-family:Arial; background:#f5f5f5;">

<div style="width:400px; margin:80px auto; background:#fff; padding:30px; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.1);">

    <h2 style="margin-bottom:20px;">Checkout</h2>

    <form method="POST" action="create_checkout.php">

        <!-- CUSTOMER INFO -->
        <label>Full Name</label>
        <input type="text" name="name" value="<?= $name ?>" required
        style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:5px;">

        <label>Email</label>
        <input type="email" name="email" value="<?= $email ?>" required
        style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:5px;">

        <label>Phone</label>
        <input type="text" name="phone" value="<?= $phone ?>" required
        style="width:100%; padding:10px; margin-bottom:20px; border:1px solid #ccc; border-radius:5px;">

        <!-- HIDDEN DATA -->
        <input type="hidden" name="amount" value="<?= $amount ?>">
        <input type="hidden" name="tenant_id" value="1">
        <input type="hidden" name="plan_name" value="OralSync Subscription">

        <!-- SUMMARY -->
        <div style="margin-bottom:20px;">
            <strong>Amount:</strong> ₱<?= number_format($amount / 100, 2) ?>
        </div>

        <!-- BUTTON -->
        <button type="submit"
        style="width:100%; padding:12px; background:#28a745; color:#fff; border:none; border-radius:5px; font-size:16px; cursor:pointer;">
            Proceed to Payment
        </button>

    </form>

</div>

</body>
</html>