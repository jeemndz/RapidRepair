<?php
session_start();

$shopSlug = $_SESSION['login_slug'] ?? '';
$billingUrl = "../../tenant/accountbillingadmin.php";

if ($shopSlug !== '') {
    $billingUrl .= "?shop=" . urlencode($shopSlug);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Failed | RapidRepairCo.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body style="margin:0; font-family:Arial, sans-serif; background:#f6f8fc; color:#0f172a;">

<div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:30px;">

    <div style="width:100%; max-width:520px; background:#ffffff; border-radius:18px; box-shadow:0 20px 50px rgba(15,23,42,0.12); overflow:hidden;">

        <div style="background:#fee2e2; padding:34px 30px; text-align:center; border-bottom:1px solid #fecaca;">
            <div style="width:76px; height:76px; margin:0 auto 18px; border-radius:50%; background:#dc2626; color:#fff; display:flex; align-items:center; justify-content:center; font-size:42px; font-weight:bold;">
                ×
            </div>

            <h1 style="margin:0; font-size:28px; font-weight:800; color:#991b1b;">
                Payment Not Completed
            </h1>

            <p style="margin:10px 0 0; color:#7f1d1d; font-size:15px;">
                Your payment was cancelled, failed, or was not finished.
            </p>
        </div>

        <div style="padding:30px; text-align:center;">

            <p style="margin:0 0 22px; color:#475569; line-height:1.6; font-size:15px;">
                No successful payment was confirmed. You can try again or return to your billing dashboard.
            </p>

            <?php if (isset($_SESSION["checkout_session_id"])): ?>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:24px; text-align:left;">
                    <p style="margin:0 0 6px; font-size:12px; color:#64748b; font-weight:bold; text-transform:uppercase; letter-spacing:.08em;">
                        Checkout Session ID
                    </p>
                    <p style="margin:0; font-size:13px; color:#0f172a; word-break:break-all;">
                        <?= htmlspecialchars($_SESSION["checkout_session_id"]) ?>
                    </p>
                </div>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($billingUrl) ?>"
               style="display:block; width:100%; padding:15px 0; background:#1152d4; color:#fff; text-decoration:none; border-radius:10px; font-weight:800; font-size:14px; text-transform:uppercase; letter-spacing:.06em;">
                Try Payment Again
            </a>

            <a href="<?= htmlspecialchars($billingUrl) ?>"
               style="display:block; margin-top:14px; padding:14px 0; color:#475569; text-decoration:none; font-size:14px; font-weight:600;">
                Go to Billing Dashboard
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