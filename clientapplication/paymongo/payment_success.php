<?php
session_start();
require_once "config.php";

$shopSlug = $_SESSION['login_slug'] ?? '';
$billingUrl = "../../tenant/accountbillingadmin.php";

if ($shopSlug !== '') {
    $billingUrl .= "?shop=" . urlencode($shopSlug);
}

$checkoutSessionId = $_SESSION["checkout_session_id"] ?? null;

if (!$checkoutSessionId) {
    die("No checkout session found.");
}

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paymongo.com/v1/checkout_sessions/" . $checkoutSessionId,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "accept: application/json",
        "authorization: Basic " . base64_encode($PAYMONGO_SECRET_KEY . ":")
    ],
]);

$response = curl_exec($curl);
$error = curl_error($curl);
curl_close($curl);

if ($error) {
    die("cURL Error: " . $error);
}

$result = json_decode($response, true);
$attributes = $result["data"]["attributes"] ?? [];

$status = $attributes["payment_intent"]["attributes"]["status"] ?? "unknown";
$checkoutStatus = $attributes["status"] ?? "unknown";

$amount = $attributes["line_items"][0]["amount"] ?? ($_SESSION["amount"] ?? 0);
$amountPhp = number_format($amount / 100, 2);

$isPaid = ($status === "succeeded" || $checkoutStatus === "paid");
$isProcessing = ($status === "processing");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Result | RapidRepairCo.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body style="margin:0; font-family:Arial, sans-serif; background:#f6f8fc; color:#0f172a;">

<div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:30px;">

    <div style="width:100%; max-width:540px; background:#ffffff; border-radius:18px; box-shadow:0 20px 50px rgba(15,23,42,0.12); overflow:hidden;">

        <?php if ($isPaid): ?>
            <div style="background:#dcfce7; padding:34px 30px; text-align:center; border-bottom:1px solid #bbf7d0;">
                <div style="width:76px; height:76px; margin:0 auto 18px; border-radius:50%; background:#16a34a; color:#fff; display:flex; align-items:center; justify-content:center; font-size:42px; font-weight:bold;">
                    ✓
                </div>

                <h1 style="margin:0; font-size:28px; font-weight:800; color:#166534;">
                    Payment Successful
                </h1>

                <p style="margin:10px 0 0; color:#166534; font-size:15px;">
                    Your subscription payment has been completed.
                </p>
            </div>

        <?php elseif ($isProcessing): ?>
            <div style="background:#fef3c7; padding:34px 30px; text-align:center; border-bottom:1px solid #fde68a;">
                <div style="width:76px; height:76px; margin:0 auto 18px; border-radius:50%; background:#f59e0b; color:#fff; display:flex; align-items:center; justify-content:center; font-size:34px; font-weight:bold;">
                    …
                </div>

                <h1 style="margin:0; font-size:28px; font-weight:800; color:#92400e;">
                    Payment Processing
                </h1>

                <p style="margin:10px 0 0; color:#92400e; font-size:15px;">
                    Your payment is still being confirmed.
                </p>
            </div>

        <?php else: ?>
            <div style="background:#fee2e2; padding:34px 30px; text-align:center; border-bottom:1px solid #fecaca;">
                <div style="width:76px; height:76px; margin:0 auto 18px; border-radius:50%; background:#dc2626; color:#fff; display:flex; align-items:center; justify-content:center; font-size:42px; font-weight:bold;">
                    ×
                </div>

                <h1 style="margin:0; font-size:28px; font-weight:800; color:#991b1b;">
                    Payment Not Successful
                </h1>

                <p style="margin:10px 0 0; color:#7f1d1d; font-size:15px;">
                    Your payment was not confirmed.
                </p>
            </div>
        <?php endif; ?>

        <div style="padding:30px;">

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px; margin-bottom:22px;">

                <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                    <span style="color:#64748b; font-size:14px;">Amount</span>
                    <strong>₱<?= htmlspecialchars($amountPhp) ?></strong>
                </div>

                <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                    <span style="color:#64748b; font-size:14px;">Payment Status</span>
                    <strong><?= htmlspecialchars($status) ?></strong>
                </div>

                <div style="display:flex; justify-content:space-between;">
                    <span style="color:#64748b; font-size:14px;">Checkout Status</span>
                    <strong><?= htmlspecialchars($checkoutStatus) ?></strong>
                </div>

            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:24px;">
                <p style="margin:0 0 6px; font-size:12px; color:#64748b; font-weight:bold; text-transform:uppercase; letter-spacing:.08em;">
                    Checkout Session ID
                </p>
                <p style="margin:0; font-size:13px; color:#0f172a; word-break:break-all;">
                    <?= htmlspecialchars($checkoutSessionId) ?>
                </p>
            </div>

            <?php if ($isPaid): ?>
                <a href="<?= htmlspecialchars($billingUrl) ?>"
                   style="display:block; width:100%; text-align:center; padding:15px 0; background:#1152d4; color:#fff; text-decoration:none; border-radius:10px; font-weight:800; font-size:14px; text-transform:uppercase;">
                    Go to Billing Dashboard
                </a>

            <?php elseif ($isProcessing): ?>
                <a href="payment_success.php"
                   style="display:block; width:100%; text-align:center; padding:15px 0; background:#f59e0b; color:#fff; text-decoration:none; border-radius:10px; font-weight:800; font-size:14px; text-transform:uppercase;">
                    Refresh Status
                </a>

                <a href="<?= htmlspecialchars($billingUrl) ?>"
                   style="display:block; margin-top:14px; text-align:center; padding:14px 0; color:#475569; text-decoration:none; font-size:14px; font-weight:600;">
                    Back to Billing Dashboard
                </a>

            <?php else: ?>
                <a href="<?= htmlspecialchars($billingUrl) ?>"
                   style="display:block; width:100%; text-align:center; padding:15px 0; background:#1152d4; color:#fff; text-decoration:none; border-radius:10px; font-weight:800; font-size:14px; text-transform:uppercase;">
                    Try Payment Again
                </a>
            <?php endif; ?>

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