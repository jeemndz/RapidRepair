<?php
$source = $_GET["source"] ?? "clientpayment";

if ($source === "accountbillingadmin") {
    $returnUrl = "../../tenant/accountbillingadmin.php";
    $returnText = "Go to Billing Dashboard";
} else {
    $returnUrl = "../clientlanding.php";
    $returnText = "Return Home";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Successful | RapidRepairCo.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body style="margin:0; font-family:Arial, sans-serif; background:#f6f8fc; color:#0f172a;">

<div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:30px;">
    <div style="width:100%; max-width:520px; background:#fff; border-radius:18px; box-shadow:0 20px 50px rgba(15,23,42,0.12); overflow:hidden;">

        <div style="background:#dcfce7; padding:34px 30px; text-align:center; border-bottom:1px solid #bbf7d0;">
            <div style="width:76px; height:76px; margin:0 auto 18px; border-radius:50%; background:#16a34a; color:#fff; display:flex; align-items:center; justify-content:center; font-size:42px; font-weight:bold;">
                ✓
            </div>

            <h1 style="margin:0; font-size:28px; font-weight:800; color:#166534;">
                Payment Submitted
            </h1>

            <p style="margin:10px 0 0; color:#166534; font-size:15px;">
                PayMongo is confirming your payment. Your billing record will update automatically.
            </p>
        </div>

        <div style="padding:30px; text-align:center;">
            <p style="margin:0 0 22px; color:#475569; line-height:1.6; font-size:15px;">
                You may return now. If the payment history does not update immediately, refresh your billing page after a few seconds.
            </p>

            <a href="<?= htmlspecialchars($returnUrl) ?>"
               style="display:block; width:100%; padding:15px 0; background:#1152d4; color:#fff; text-decoration:none; border-radius:10px; font-weight:800; font-size:14px; text-transform:uppercase;">
                <?= htmlspecialchars($returnText) ?>
            </a>
        </div>

        <div style="background:#f8fafc; padding:16px 24px; text-align:center; border-top:1px solid #e2e8f0;">
            <p style="margin:0; font-size:12px; color:#64748b;">
                Secure payment powered by PayMongo
            </p>
        </div>

    </div>
</div>

</body>
</html>