<?php
session_start();

if (!isset($_SESSION['superadmin_id'])) {
    header("Location: superaddlogin.php");
    exit();
}

// ✅ Load environment variables from .env file
if (file_exists(__DIR__ . '/.env')) {
    $envLines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \"'");
            if (!getenv($key)) {
                putenv($key . '=' . $value);
            }
        }
    }
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

include "db.php";

function subscriptionPlansTableExists($conn)
{
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'subscription_plans'");
    return $check && mysqli_num_rows($check) > 0;
}

function subscriptionPlansColumnExists($conn, $columnName)
{
    $safeColumn = mysqli_real_escape_string($conn, $columnName);
    $checkSql = "SHOW COLUMNS FROM subscription_plans LIKE '$safeColumn'";
    $check = mysqli_query($conn, $checkSql);
    return $check && mysqli_num_rows($check) > 0;
}

function normalizePlanKey($value)
{
    $normalized = strtolower(trim((string) $value));
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized);
    $normalized = trim($normalized, '-');
    return $normalized === '' ? 'plan' : $normalized;
}

function loadSubscriptionPlans($conn)
{
    $plans = [];

    if (!subscriptionPlansTableExists($conn)) {
        return $plans;
    }

    $hasPlanName = subscriptionPlansColumnExists($conn, 'plan_name');
    $hasMonthlyPrice = subscriptionPlansColumnExists($conn, 'monthly_price');

    if (!$hasPlanName || !$hasMonthlyPrice) {
        return $plans;
    }

    $hasPlanCode = subscriptionPlansColumnExists($conn, 'plan_code');
    $hasIsActive = subscriptionPlansColumnExists($conn, 'is_active');
    $hasPlanFeatures = subscriptionPlansColumnExists($conn, 'plan_features');

    $columns = [];
    if ($hasPlanCode) {
        $columns[] = 'plan_code';
    }
    $columns[] = 'plan_name';
    $columns[] = 'monthly_price';
    if ($hasPlanFeatures) {
        $columns[] = 'plan_features';
    }

    $sql = "SELECT " . implode(', ', $columns) . " FROM subscription_plans";
    if ($hasIsActive) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY monthly_price ASC, plan_name ASC";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return $plans;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $planName = trim((string) ($row['plan_name'] ?? ''));
        $monthlyPrice = isset($row['monthly_price']) && is_numeric($row['monthly_price']) ? (float) $row['monthly_price'] : 0;
        if ($planName === '' || $monthlyPrice <= 0) {
            continue;
        }

        $planKeySource = $hasPlanCode ? (string) ($row['plan_code'] ?? '') : $planName;
        $planKey = normalizePlanKey($planKeySource);
        $planFeatures = [];

        if ($hasPlanFeatures) {
            $decodedFeatures = json_decode((string) ($row['plan_features'] ?? '[]'), true);
            if (is_array($decodedFeatures)) {
                foreach ($decodedFeatures as $feature) {
                    $feature = trim((string) $feature);
                    if ($feature !== '') {
                        $planFeatures[] = $feature;
                    }
                }
            }
        }

        $plans[$planKey] = [
            'key' => $planKey,
            'name' => $planName,
            'monthly_price' => $monthlyPrice,
            'features' => $planFeatures
        ];
    }

    return $plans;
}

$subscriptionPlans = loadSubscriptionPlans($conn);
$billingCycles = [
    'monthly' => 1,
    'quarterly' => 3,
    'yearly' => 12
];

$fallbackPlans = [
    'basic' => ['key' => 'basic', 'name' => 'Basic', 'monthly_price' => 999, 'features' => []],
    'standard' => ['key' => 'standard', 'name' => 'Standard', 'monthly_price' => 1999, 'features' => []],
    'premium' => ['key' => 'premium', 'name' => 'Premium', 'monthly_price' => 3499, 'features' => []]
];

if (count($subscriptionPlans) === 0) {
    $subscriptionPlans = $fallbackPlans;
}

$defaultPlanKey = array_key_first($subscriptionPlans);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';
require __DIR__ . '/PHPMailer/src/Exception.php';

// HANDLE QUICK STATUS UPDATE (Approve/Reject)
if (isset($_POST['updateTenantStatus'])) {
    $tenantID = mysqli_real_escape_string($conn, $_POST['tenantID']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // If approving (status = Active), populate subscription fields
    if ($status === 'Active') {
        $subscriptionPlan = $_POST['subscriptionPlan'] ?? $defaultPlanKey;
        $billingCycle = $_POST['billingCycle'] ?? 'monthly';

        if (!isset($subscriptionPlans[$subscriptionPlan])) {
            $subscriptionPlan = $defaultPlanKey;
        }

        if (!isset($billingCycles[$billingCycle])) {
            $billingCycle = 'monthly';
        }

        $subscriptionStart = date('Y-m-d');
        $subscriptionEnd = date('Y-m-d', strtotime('+' . $billingCycles[$billingCycle] . ' months'));
        $planTotalPrice = $subscriptionPlans[$subscriptionPlan]['monthly_price'] * $billingCycles[$billingCycle];
        $nextBillingDate = $subscriptionEnd;

        $updateSql = "UPDATE owners SET 
            status = '$status',
            subscription_plan = '" . mysqli_real_escape_string($conn, $subscriptionPlan) . "',
            billing_cycle = '" . mysqli_real_escape_string($conn, $billingCycle) . "',
            subscription_start = '" . mysqli_real_escape_string($conn, $subscriptionStart) . "',
            subscription_end = '" . mysqli_real_escape_string($conn, $subscriptionEnd) . "',
            plan_price = '" . mysqli_real_escape_string($conn, (string) $planTotalPrice) . "',
            next_billing_date = '" . mysqli_real_escape_string($conn, $nextBillingDate) . "'
            WHERE tenantID = '$tenantID'";
    } else {
        $updateSql = "UPDATE owners SET status = '$status' WHERE tenantID = '$tenantID'";
    }

    if (mysqli_query($conn, $updateSql)) {
        $redirect = ($status === 'Active') ? 'tenant_approved' : 'tenant_rejected';
        header("Location: superaddtenants.php?notice=" . $redirect);
    } else {
        header("Location: superaddtenants.php?notice=tenant_status_update_failed");
    }
    exit;
}

$notice = $_GET['notice'] ?? '';
$noticeTypeClass = '';
$noticeIcon = '';
$noticeTitle = '';
$noticeMessage = '';

switch ($notice) {
    case 'tenant_created_email_sent':
        $noticeTypeClass = 'bg-emerald-500';
        $noticeIcon = 'check_circle';
        $noticeTitle = 'Tenant Created';
        $noticeMessage = 'Tenant was created and email was sent successfully.';
        break;
    case 'tenant_created_email_failed':
        $noticeTypeClass = 'bg-amber-500';
        $noticeIcon = 'warning';
        $noticeTitle = 'Tenant Created';
        $noticeMessage = 'Tenant was created, but the email could not be sent.';
        break;
    case 'tenant_create_failed':
        $noticeTypeClass = 'bg-red-500';
        $noticeIcon = 'error';
        $noticeTitle = 'Creation Failed';
        $noticeMessage = 'Could not create tenant. Please try again.';
        break;
    case 'tenant_updated':
        $noticeTypeClass = 'bg-emerald-500';
        $noticeIcon = 'check_circle';
        $noticeTitle = 'Tenant Updated';
        $noticeMessage = 'Tenant details were updated successfully.';
        break;
    case 'tenant_update_failed':
        $noticeTypeClass = 'bg-red-500';
        $noticeIcon = 'error';
        $noticeTitle = 'Update Failed';
        $noticeMessage = 'Could not update tenant. Please try again.';
        break;
    case 'tenant_approved':
        $noticeTypeClass = 'bg-emerald-500';
        $noticeIcon = 'check_circle';
        $noticeTitle = 'Application Approved';
        $noticeMessage = 'Tenant application was approved and activated successfully.';
        break;
    case 'tenant_rejected':
        $noticeTypeClass = 'bg-red-500';
        $noticeIcon = 'cancel';
        $noticeTitle = 'Application Rejected';
        $noticeMessage = 'Tenant application was rejected.';
        break;
    case 'tenant_status_update_failed':
        $noticeTypeClass = 'bg-red-500';
        $noticeIcon = 'error';
        $noticeTitle = 'Status Update Failed';
        $noticeMessage = 'Could not update tenant status. Please try again.';
        break;
}

function ownersColumnExists($conn, $columnName)
{
    $safeColumn = mysqli_real_escape_string($conn, $columnName);
    $checkSql = "SHOW COLUMNS FROM owners LIKE '$safeColumn'";
    $check = mysqli_query($conn, $checkSql);
    return $check && mysqli_num_rows($check) > 0;
}

// Pagination configuration
$rowsPerPage = 5;
$tenantPage = isset($_GET['tenant_page']) ? max(1, (int) $_GET['tenant_page']) : 1;
$pendingPage = isset($_GET['pending_page']) ? max(1, (int) $_GET['pending_page']) : 1;

// ✅ Generate unique login slug
function generateSlug($conn, $shopName)
{
    $slug = strtolower(trim($shopName));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    $originalSlug = $slug;
    $counter = 1;

    while (true) {
        $check = mysqli_query($conn, "SELECT tenantID FROM owners WHERE login_slug='$slug'");
        if (mysqli_num_rows($check) == 0)
            break;
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }

    return $slug;
}

function generateTemporaryPassword($length = 12)
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $maxIndex = strlen($alphabet) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $maxIndex)];
    }

    return $password;
}

// HANDLE UPDATE TENANT
if (isset($_POST['updateTenant'])) {
    $tenantID = mysqli_real_escape_string($conn, $_POST['tenantID']);
    $shopName = mysqli_real_escape_string($conn, $_POST['shopName']);
    $shopAddress = mysqli_real_escape_string($conn, $_POST['shopAddress']);
    $ownerName = mysqli_real_escape_string($conn, $_POST['ownerName']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $contactNumber = mysqli_real_escape_string($conn, $_POST['contactNumber']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $subscriptionPlan = $_POST['subscriptionPlan'] ?? $defaultPlanKey;
    $billingCycle = $_POST['billingCycle'] ?? 'monthly';

    if (!isset($subscriptionPlans[$subscriptionPlan])) {
        $subscriptionPlan = $defaultPlanKey;
    }

    if (!isset($billingCycles[$billingCycle])) {
        $billingCycle = 'monthly';
    }

    $updateFields = [
        "shopName = '$shopName'",
        "shopAddress = '$shopAddress'",
        "ownerName = '$ownerName'",
        "email = '$email'",
        "contactNumber = '$contactNumber'",
        "status = '$status'"
    ];

    if (ownersColumnExists($conn, 'subscription_plan')) {
        $updateFields[] = "subscription_plan = '" . mysqli_real_escape_string($conn, $subscriptionPlan) . "'";
    }

    if (ownersColumnExists($conn, 'billing_cycle')) {
        $updateFields[] = "billing_cycle = '" . mysqli_real_escape_string($conn, $billingCycle) . "'";
    }

    if ($status === 'Active') {
        $subscriptionStart = date('Y-m-d');
        $subscriptionEnd = date('Y-m-d', strtotime('+' . $billingCycles[$billingCycle] . ' months'));
        $planTotalPrice = $subscriptionPlans[$subscriptionPlan]['monthly_price'] * $billingCycles[$billingCycle];

        if (ownersColumnExists($conn, 'subscription_start')) {
            $updateFields[] = "subscription_start = '" . mysqli_real_escape_string($conn, $subscriptionStart) . "'";
        }

        if (ownersColumnExists($conn, 'subscription_end')) {
            $updateFields[] = "subscription_end = '" . mysqli_real_escape_string($conn, $subscriptionEnd) . "'";
        }

        if (ownersColumnExists($conn, 'next_billing_date')) {
            $updateFields[] = "next_billing_date = '" . mysqli_real_escape_string($conn, $subscriptionEnd) . "'";
        }

        if (ownersColumnExists($conn, 'plan_price')) {
            $updateFields[] = "plan_price = '" . mysqli_real_escape_string($conn, number_format($planTotalPrice, 2, '.', '')) . "'";
        }
    }

    $updateSql = "UPDATE owners SET " . implode(",\n        ", $updateFields) . "\n        WHERE tenantID = '$tenantID'";

    if (mysqli_query($conn, $updateSql)) {
        header("Location: superaddtenants.php?notice=tenant_updated");
    } else {
        header("Location: superaddtenants.php?notice=tenant_update_failed");
    }
    exit;
}

// HANDLE CREATE TENANT
if (isset($_POST['createTenant'])) {

    $shopName = mysqli_real_escape_string($conn, $_POST['shopName']);
    $shopAddress = mysqli_real_escape_string($conn, $_POST['shopAddress']);
    $ownerName = mysqli_real_escape_string($conn, $_POST['ownerName']);
    $email = trim($_POST['email']);
    $contactNumber = mysqli_real_escape_string($conn, $_POST['contactNumber']);
    $tempPassword = trim((string) ($_POST['tempPassword'] ?? ''));
    if ($tempPassword === '') {
        $tempPassword = generateTemporaryPassword();
    }

    // ✅ VALIDATE EMAIL (VERY IMPORTANT)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email address.");
    }

    // ✅ DO NOT HASH PASSWORD YET - Store as plain text for first login only
    // Hash will happen after user changes password on first login
    $hashedPassword = $tempPassword;  // Store temporarily as plain text

    // ✅ Generate tenant ID
    $getID = mysqli_query($conn, "SELECT tenantID FROM owners ORDER BY tenantID DESC LIMIT 1");

    if (mysqli_num_rows($getID) > 0) {
        $row = mysqli_fetch_assoc($getID);
        $newID = (int) $row['tenantID'] + 1;
    } else {
        $newID = 1;
    }

    $tenantID = str_pad($newID, 3, "0", STR_PAD_LEFT);

    // ✅ Generate slug
    $login_slug = generateSlug($conn, $shopName);

    // ✅ INSERT (subscription fields will be populated when tenant is approved)
    $insertColumns = [
        'tenantID',
        'ownerName',
        'shopName',
        'login_slug',
        'email',
        'contactNumber',
        'shopAddress',
        'password',
        'first_login',
        'status'
    ];

    $insertValues = [
        "'" . mysqli_real_escape_string($conn, $tenantID) . "'",
        "'" . mysqli_real_escape_string($conn, $ownerName) . "'",
        "'" . mysqli_real_escape_string($conn, $shopName) . "'",
        "'" . mysqli_real_escape_string($conn, $login_slug) . "'",
        "'" . mysqli_real_escape_string($conn, $email) . "'",
        "'" . mysqli_real_escape_string($conn, $contactNumber) . "'",
        "'" . mysqli_real_escape_string($conn, $shopAddress) . "'",
        "'" . mysqli_real_escape_string($conn, $hashedPassword) . "'",
        "1",
        "'Pending'"
    ];

    $insertSql = "INSERT INTO owners (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
    $insert = mysqli_query($conn, $insertSql);

    $emailSent = false;

    if ($insert) {

        // ✅ LOGIN LINK
        $baseURL = "https://rapidrepair-gygpcbczgyg0czek.southeastasia-01.azurewebsites.net";
        $loginLink = $baseURL . "/tenantlogin.php?shop=" . urlencode($login_slug);

        $mail = new PHPMailer(true);

        try {
            $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
            $smtpEncryption = strtolower((string) (getenv('SMTP_ENCRYPTION') ?: 'tls'));
            $smtpUsername = getenv('SMTP_USERNAME') ?: 'rapidrepair224@gmail.com';
            $smtpPassword = getenv('SMTP_PASSWORD') ?: 'gabd xcqy gbgq rtwj';

            // ✅ DEBUG: Log loaded configuration
            error_log("SMTP Config - Host: {$smtpHost}, Port: {$smtpPort}, Encryption: {$smtpEncryption}, Username: {$smtpUsername}");

            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = ($smtpEncryption === 'ssl')
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpPort;

            // ✅ TEST CONNECTION
            if (!$mail->smtpConnect()) {
                $connError = "SMTP Connection Failed: " . $mail->ErrorInfo;
                error_log($connError);
                throw new Exception($connError);
            }

            $allowSelfSigned = strtolower((string) (getenv('SMTP_ALLOW_SELF_SIGNED') ?: 'false')) === 'true';
            if ($allowSelfSigned) {
                // Only use this for temporary troubleshooting.
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
            }

            // ✅ CLEAR ANY PREVIOUS RECIPIENTS
            $mail->clearAddresses();

            // ✅ SENDER
            $mailFromAddress = getenv('MAIL_FROM_ADDRESS') ?: $mail->Username;
            $mailFromName = getenv('MAIL_FROM_NAME') ?: 'Rapid Repair Admin';
            $mailReplyToAddress = getenv('MAIL_REPLY_TO') ?: $mailFromAddress;
            $mailReplyToName = getenv('MAIL_REPLY_TO_NAME') ?: 'Rapid Repair Support';

            $mail->setFrom($mailFromAddress, $mailFromName);
            $mail->addReplyTo($mailReplyToAddress, $mailReplyToName);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->WordWrap = 78;

            // ✅ ANTI-SPAM HEADERS
            $mail->addCustomHeader('List-Unsubscribe', '<mailto:' . htmlspecialchars($mailFromAddress) . '?subject=unsubscribe>');
            $mail->addCustomHeader('X-Mailer', 'RapidRepair/Tenant-Onboarding');
            $mail->addCustomHeader('X-Priority', '3');
            $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
            $mail->addCustomHeader('Precedence', 'bulk');

            // ✅ RECEIVER (THIS IS THE FIX)
            $mail->addAddress($email, $ownerName);

            $mail->isHTML(true);
            $mail->Subject = 'Rapid Repair Tenant Access Details';

            $safeOwnerName = htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8');
            $safeShopName = htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8');
            $safeLoginLink = htmlspecialchars($loginLink, ENT_QUOTES, 'UTF-8');
            $safeTempPassword = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');
            $safeLoginEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

            $mail->Body = "
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Rapid Repair Tenant Access</title>
                </head>
                <body style='margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;'>
                    <div style='display:none;max-height:0;overflow:hidden;opacity:0;'>
                        Your Rapid Repair tenant account has been created and is pending approval.
                    </div>
                    <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='background:#f1f5f9;padding:24px 0;'>
                        <tr>
                            <td align='center'>
                                <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='max-width:640px;background:#ffffff;border:1px solid #dbe1ea;border-radius:14px;overflow:hidden;'>
                                    <tr>
                                        <td style='padding:22px 24px;background:linear-gradient(135deg,#123b69,#0b1f42);color:#e2e8f0;'>
                                            <h1 style='margin:0;font-size:28px;line-height:32px;font-weight:700;color:#ffffff;'>RapidRepair</h1>
                                            <p style='margin:6px 0 0 0;font-size:15px;line-height:20px;'>Tenant onboarding details</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='padding:24px;'>
                                            <p style='margin:0 0 12px 0;font-size:27px;line-height:34px;font-weight:700;color:#0f172a;'>Hi {$safeOwnerName},</p>
                                            <p style='margin:0 0 18px 0;font-size:25px;line-height:32px;color:#1e293b;'>
                                                Your Car Repair Shop <strong>{$safeShopName}</strong> has been set up in RapidRepair.
                                            </p>

                                            <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border:1px solid #d1d5db;border-radius:12px;background:#f8fafc;margin:0 0 16px 0;'>
                                                <tr>
                                                    <td style='padding:16px;'>
                                                        <p style='margin:0 0 8px 0;font-size:14px;line-height:20px;color:#64748b;font-weight:700;'>Your tenant login link</p>
                                                        <p style='margin:0 0 12px 0;font-size:17px;line-height:24px;word-break:break-all;'>
                                                            <a href='{$safeLoginLink}' style='color:#1d4ed8;text-decoration:underline;'>{$safeLoginLink}</a>
                                                        </p>
                                                        <p style='margin:0 0 14px 0;font-size:12px;line-height:18px;color:#64748b;'>
                                                            Tip: to copy, highlight the link and press Ctrl+C (or tap-and-hold on mobile).
                                                        </p>

                                                        <table role='presentation' cellpadding='0' cellspacing='0' border='0'>
                                                            <tr>
                                                                <td style='border-radius:999px;background:#22c55e;'>
                                                                    <a href='{$safeLoginLink}' style='display:inline-block;padding:12px 20px;font-size:22px;font-weight:700;color:#083344;text-decoration:none;'>Open RapidRepair Portal</a>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>

                                            <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border:1px solid #bbf7d0;border-radius:12px;background:#dcfce7;margin:0 0 16px 0;'>
                                                <tr>
                                                    <td style='padding:16px;'>
                                                        <p style='margin:0 0 6px 0;font-size:14px;line-height:20px;color:#166534;font-weight:700;'>Temporary password</p>
                                                        <p style='margin:0;font-size:30px;line-height:36px;letter-spacing:1.2px;color:#14532d;font-weight:700;'>{$safeTempPassword}</p>
                                                    </td>
                                                </tr>
                                            </table>

                                            <p style='margin:0 0 8px 0;font-size:25px;line-height:30px;color:#0f172a;font-weight:700;'>Next steps</p>
                                            <ul style='margin:0 0 12px 22px;padding:0;font-size:15px;line-height:24px;color:#0f172a;'>
                                                <li>Open the link above and log in using this email address: <strong>{$safeLoginEmail}</strong></li>
                                                <li>Use the temporary password, then change it immediately after you sign in.</li>
                                                <li>Bookmark your login link for quick access.</li>
                                            </ul>

                                            <p style='margin:0;font-size:13px;line-height:20px;color:#64748b;'>
                                                If the button does not work, copy and paste the URL into your browser.
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='padding:14px 24px;border-top:1px solid #e5e7eb;background:#f8fafc;font-size:11px;line-height:18px;color:#64748b;'>
                                            This email was sent by RapidRepair System.<br>
                                            If you did not request this, ignore this email.
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
            ";

            $mail->AltBody = "Rapid Repair Tenant Account Information\n\n"
                . "Hello {$ownerName},\n\n"
                . "Your tenant account for {$shopName} has been set up and is pending approval.\n\n"
                . "Your tenant login link: {$loginLink}\n"
                . "Tip: copy and paste the link into your browser if needed.\n\n"
                . "Temporary Password: {$tempPassword}\n"
                . "Status: Pending Approval\n\n"
                . "Next steps:\n"
                . "- Open the link above and log in using this email address: {$email}\n"
                . "- Use the temporary password, then change it immediately after you sign in.\n"
                . "- Bookmark your login link for quick access.\n\n"
                . "This email was sent by RapidRepair System\n"
                . "If you did not request this, ignore this email.";

            $mail->send();
            $emailSent = true;

        } catch (Exception $e) {
            $errorMsg = "Mailer Error: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage();
            error_log($errorMsg);
        }
    }

    // ✅ REDIRECT
    if ($insert && $emailSent) {
        header("Location: superaddtenants.php?notice=tenant_created_email_sent");
    } elseif ($insert) {
        header("Location: superaddtenants.php?notice=tenant_created_email_failed");
    } else {
        header("Location: superaddtenants.php?notice=tenant_create_failed");
    }

    exit;
}
?>

<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Superadmin - Tenant Management</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1152d4",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                },
            },
        }
    </script>
</head>

<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100">

    <!-- Side Navigation -->
    <aside
        class="flex flex-col fixed left-0 top-0 h-full z-40 h-screen w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 font-['Inter'] antialiased tracking-tight shadow-sm dark:shadow-none">
        <!-- Brand Header -->
        <div class="p-6 flex items-center gap-3">
            <div class="bg-primary rounded-lg p-2 text-white">
                <span class="material-symbols-outlined block text-2xl">directions_car</span>
            </div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white leading-none">
                RapidRepair <span class="text-primary">SuperAdmin</span>
            </h2>
        </div>
        <!-- Navigation Links -->
        <nav class="flex-1 px-4 space-y-1 mt-4">
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superadd.php">
                <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
                <span class="text-sm">Dashboard</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 font-bold border-r-4 border-blue-700 dark:border-blue-500 rounded-lg active:scale-95"
                href="superaddtenants.php">
                <span class="material-symbols-outlined" data-icon="groups">groups</span>
                <span class="text-sm">Tenants</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superhealth.php">
                <span class="material-symbols-outlined" data-icon="health_and_safety">health_and_safety</span>
                <span class="text-sm">System Health</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="subscriptionmanage.php">
                <span class="material-symbols-outlined" data-icon="subscriptions">subscriptions</span>
                <span class="text-sm">Subscriptions</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="supersalesreports.php">
                <span class="material-symbols-outlined" data-icon="bar_chart">bar_chart</span>
                <span class="text-sm">Sales Reports</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superauditlogs.php">
                <span class="material-symbols-outlined" data-icon="assignment">assignment</span>
                <span class="text-sm">Audit Logs</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="supersettings.php">
                <span class="material-symbols-outlined" data-icon="settings">settings</span>
                <span class="text-sm">Settings</span>
            </a>
            <!-- Active State Applied for System Backup -->
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superbackup.php">
                <span class="material-symbols-outlined" data-icon="backup"
                    style="font-variation-settings: 'FILL' 1;">backup</span>
                <span class="text-sm">System Backup</span>
            </a>
        </nav>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
            <div
                class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition-colors">
                <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 bg-cover bg-center"
                    data-alt="Admin headshot"
                    style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAA7ZvS0RT24pYl7zsQUKsnC9inrzmoUQVQC8PvdcW5_q4FtMWEC8ZD9Ke8mBa8iRwi4vfG0NbuLhEY9U_mYTQt3gBMRoNS0jNV_aJYQ-QCLtauVwWdyP53SHmFLjb5bQvwjbvvF24yHFp3moy4K6rJ0tVvtMIzdIUNohESEbLUilTPScnQYQQutAW0bzWhFZkGsX1GwwAl_2_9yXjauFnRNg0uTHfeR3lnfDRxLlk9Jo_hIr7N64rr5SWZq57QEfMdbFLkygzUgb-A')">
                </div>
                <div class="flex flex-col min-w-0">
                    <h3 class="text-sm font-semibold truncate text-slate-900 dark:text-white">Admin User</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Superadmin</p>
                </div>
            </div>
            <div
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors cursor-pointer">
                <span class="material-symbols-outlined">logout</span>
                <a href="logout.php" class="text-sm font-medium">Logout</a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 p-8 bg-background-light dark:bg-background-dark ml-64">
        <!-- Header -->
        <header class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Tenant Management</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1 font-medium">Manage and monitor platform tenants
                    and shop applications.</p>
            </div>
            <button onclick="openModal()" class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg">
                <span class="material-symbols-outlined">add</span> Add Tenant
            </button>
        </header>

        <!-- Tenant Table -->
        <div
            class="bg-white dark:bg-background-dark rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr
                            class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">
                            <th class="px-6 py-4">Shop Name</th>
                            <th class="px-6 py-4">Owner</th>
                            <th class="px-6 py-4">Subscription</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Created Date</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php
                        // Get total count for pagination
                        $totalQuery = "SELECT COUNT(*) as total FROM owners";
                        $totalResult = mysqli_query($conn, $totalQuery);
                        $totalRow = mysqli_fetch_assoc($totalResult);
                        $totalTenants = $totalRow['total'];
                        $totalTenantPages = ceil($totalTenants / $rowsPerPage);

                        // Ensure valid page
                        if ($tenantPage > $totalTenantPages && $totalTenantPages > 0) {
                            $tenantPage = $totalTenantPages;
                        }

                        $offset = ($tenantPage - 1) * $rowsPerPage;
                        $query = "SELECT * FROM owners ORDER BY tenantID DESC LIMIT $offset, $rowsPerPage";
                        $result = mysqli_query($conn, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                            $statusColor = "emerald";
                            if (strtolower($row['status']) == "inactive")
                                $statusColor = "red";
                            if (strtolower($row['status']) == "pending")
                                $statusColor = "amber";

                            $tenantPlanKey = strtolower(isset($row['subscription_plan']) ? $row['subscription_plan'] : $defaultPlanKey);
                            if (!isset($subscriptionPlans[$tenantPlanKey])) {
                                $tenantPlanKey = $defaultPlanKey;
                            }
                            $tenantPlan = $subscriptionPlans[$tenantPlanKey]['name'];
                            $tenantCycle = isset($row['billing_cycle']) && $row['billing_cycle'] !== ''
                                ? ucfirst($row['billing_cycle'])
                                : 'Monthly';

                            $tenantCycleKey = strtolower(isset($row['billing_cycle']) ? $row['billing_cycle'] : 'monthly');

                            if (!isset($billingCycles[$tenantCycleKey])) {
                                $tenantCycleKey = 'monthly';
                            }

                            $calculatedPlanPrice = $subscriptionPlans[$tenantPlanKey]['monthly_price'] * $billingCycles[$tenantCycleKey];
                            $tenantPrice = isset($row['plan_price']) && is_numeric($row['plan_price'])
                                ? (float) $row['plan_price']
                                : (float) $calculatedPlanPrice;

                            $tenantNextBillingRaw = '';
                            if (isset($row['next_billing_date']) && $row['next_billing_date'] !== '') {
                                $tenantNextBillingRaw = $row['next_billing_date'];
                            } elseif (isset($row['subscription_end']) && $row['subscription_end'] !== '') {
                                $tenantNextBillingRaw = $row['subscription_end'];
                            } elseif (isset($row['created_at']) && $row['created_at'] !== '') {
                                $tenantNextBillingRaw = date('Y-m-d', strtotime($row['created_at'] . ' +' . $billingCycles[$tenantCycleKey] . ' months'));
                            }

                            $tenantNextBilling = $tenantNextBillingRaw !== ''
                                ? date('M d, Y', strtotime($tenantNextBillingRaw))
                                : 'N/A';
                            ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-6 py-4"><?php echo $row['shopName']; ?> (ID:
                                    <?php echo $row['tenantID']; ?>)
                                </td>
                                <td class="px-6 py-4"><?php echo $row['ownerName']; ?><br><span
                                        class="text-xs text-slate-500"><?php echo $row['email']; ?></span></td>
                                <td class="px-6 py-4">
                                    <span
                                        class="text-sm font-semibold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($tenantPlan); ?></span><br>
                                    <span
                                        class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($tenantCycle); ?></span>
                                    <br>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">PHP
                                        <?php echo number_format($tenantPrice, 2); ?></span>
                                    <br>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Next Billing:
                                        <?php echo htmlspecialchars($tenantNextBilling); ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-2 py-1 text-xs font-semibold bg-<?php echo $statusColor; ?>-100 dark:bg-<?php echo $statusColor; ?>-900/30 text-<?php echo $statusColor; ?>-700 dark:text-<?php echo $statusColor; ?>-400 rounded-full">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?php echo date("M d, Y", strtotime($row['created_at'])); ?></td>
                                <td class="px-6 py-4 text-right">
                                    <button
                                        onclick="openEditModal('<?php echo $row['tenantID']; ?>', '<?php echo addslashes($row['shopName']); ?>', '<?php echo addslashes($row['shopAddress']); ?>', '<?php echo addslashes($row['ownerName']); ?>', '<?php echo addslashes($row['email']); ?>', '<?php echo addslashes($row['contactNumber']); ?>', '<?php echo $row['status']; ?>', '<?php echo addslashes($tenantPlanKey); ?>', '<?php echo addslashes($tenantCycleKey); ?>')"
                                        class="text-slate-400 hover:text-primary transition-colors"><span
                                            class="material-symbols-outlined">more_vert</span></button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <!-- Tenants Table Pagination -->
            <div
                class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center">
                <div class="text-sm text-slate-600 dark:text-slate-400">
                    Showing <?php echo ($totalTenants > 0) ? (($tenantPage - 1) * $rowsPerPage + 1) : 0; ?> -
                    <?php echo min($tenantPage * $rowsPerPage, $totalTenants); ?> of <?php echo $totalTenants; ?>
                    tenants
                </div>
                <div class="flex gap-2">
                    <?php if ($tenantPage > 1): ?>
                        <a href="?tenant_page=1&pending_page=<?php echo $pendingPage; ?>"
                            class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700">First</a>
                        <a href="?tenant_page=<?php echo $tenantPage - 1; ?>&pending_page=<?php echo $pendingPage; ?>"
                            class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalTenantPages; $i++): ?>
                        <?php if ($i === $tenantPage): ?>
                            <button
                                class="px-3 py-1.5 bg-primary text-white rounded-lg text-sm font-medium"><?php echo $i; ?></button>
                        <?php else: ?>
                            <a href="?tenant_page=<?php echo $i; ?>&pending_page=<?php echo $pendingPage; ?>"
                                class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($tenantPage < $totalTenantPages): ?>
                        <a href="?tenant_page=<?php echo $tenantPage + 1; ?>&pending_page=<?php echo $pendingPage; ?>"
                            class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700">Next</a>
                        <a href="?tenant_page=<?php echo $totalTenantPages; ?>&pending_page=<?php echo $pendingPage; ?>"
                            class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700">Last</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pending Applications Table -->
        <div
            class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden mt-8">
            <div class="p-6 border-b border-slate-200 dark:border-slate-800">
                <h2 class="text-lg font-bold">Pending Applications</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Review and approve new shop registration
                    requests.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr
                            class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">
                            <th class="px-6 py-4">Shop Name</th>
                            <th class="px-6 py-4">Applicant</th>
                            <th class="px-6 py-4">Plan</th>
                            <th class="px-6 py-4">Submission Date</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php
                        // Get total count for pending applications pagination
                        $totalPendingQuery = "SELECT COUNT(*) as total FROM owners WHERE status='Pending'";
                        $totalPendingResult = mysqli_query($conn, $totalPendingQuery);
                        $totalPendingRow = mysqli_fetch_assoc($totalPendingResult);
                        $totalPendingApps = $totalPendingRow['total'];
                        $totalPendingPages = ceil($totalPendingApps / $rowsPerPage);

                        // Ensure valid page
                        if ($pendingPage > $totalPendingPages && $totalPendingPages > 0) {
                            $pendingPage = $totalPendingPages;
                        }

                        $pendingOffset = ($pendingPage - 1) * $rowsPerPage;
                        $pendingQuery = "SELECT * FROM owners WHERE status='Pending' ORDER BY created_at DESC LIMIT $pendingOffset, $rowsPerPage";
                        $pendingResult = mysqli_query($conn, $pendingQuery);

                        if (mysqli_num_rows($pendingResult) > 0) {
                            while ($pendingRow = mysqli_fetch_assoc($pendingResult)) {
                                $pendingPlanKey = strtolower(isset($pendingRow['subscription_plan']) ? $pendingRow['subscription_plan'] : $defaultPlanKey);
                                if (!isset($subscriptionPlans[$pendingPlanKey])) {
                                    $pendingPlanKey = $defaultPlanKey;
                                }
                                $tenantPlan = $subscriptionPlans[$pendingPlanKey]['name'];
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-slate-400">storefront</span>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold">
                                                    <?php echo htmlspecialchars($pendingRow['shopName']); ?></div>
                                                <div class="text-xs text-slate-500">ID: <?php echo $pendingRow['tenantID']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold">
                                            <?php echo htmlspecialchars($pendingRow['ownerName']); ?></div>
                                        <div class="text-xs text-slate-500">
                                            <?php echo htmlspecialchars($pendingRow['email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($tenantPlan); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                        <?php echo date("M d, Y", strtotime($pendingRow['created_at'])); ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button onclick="approveTenant('<?php echo $pendingRow['tenantID']; ?>')"
                                                class="px-3 py-1.5 bg-emerald-600 text-white text-xs font-bold rounded-lg hover:bg-emerald-700 transition-colors">Accept</button>
                                            <button onclick="rejectTenant('<?php echo $pendingRow['tenantID']; ?>')"
                                                class="px-3 py-1.5 border border-red-200 text-red-600 text-xs font-bold rounded-lg hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors">Reject</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-slate-500">No pending applications</td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <!-- Pending Applications Table Pagination -->
            <div
                class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center">
                <div class="text-sm text-slate-600 dark:text-slate-400">
                    Showing <?php echo ($totalPendingApps > 0) ? (($pendingPage - 1) * $rowsPerPage + 1) : 0; ?> -
                    <?php echo min($pendingPage * $rowsPerPage, $totalPendingApps); ?> of
                    <?php echo $totalPendingApps; ?> applications
                </div>
                <div class="flex gap-2">
                    <?php if ($pendingPage > 1): ?>
                        <a href="?tenant_page=<?php echo $tenantPage; ?>&pending_page=1"
                            class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700">First</a>
                        <a href="?tenant_page=<?php echo $tenantPage; ?>&pending_page=<?php echo $pendingPage - 1; ?>"
                            class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPendingPages; $i++): ?>
                        <?php if ($i === $pendingPage): ?>
                            <button
                                class="px-3 py-1.5 bg-primary text-white rounded-lg text-sm font-medium"><?php echo $i; ?></button>
                        <?php else: ?>
                            <a href="?tenant_page=<?php echo $tenantPage; ?>&pending_page=<?php echo $i; ?>"
                                class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($pendingPage < $totalPendingPages): ?>
                        <a href="?tenant_page=<?php echo $tenantPage; ?>&pending_page=<?php echo $pendingPage + 1; ?>"
                            class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700">Next</a>
                        <a href="?tenant_page=<?php echo $tenantPage; ?>&pending_page=<?php echo $totalPendingPages; ?>"
                            class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700">Last</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Edit Tenant Modal -->
        <div id="editTenantModal"
            class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div
                class="bg-white dark:bg-slate-900 w-full max-w-xl rounded-xl shadow-2xl border flex flex-col overflow-hidden">
                <div class="px-8 py-6 border-b flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold">Edit Tenant Details</h2>
                        <p class="text-sm text-gray-500">Update tenant information and status</p>
                    </div>
                    <button onclick="closeEditModal()" class="text-gray-400 hover:text-black">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form method="POST" class="p-8 flex flex-col gap-6">
                    <input type="hidden" id="editTenantID" name="tenantID">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Shop Name</label>
                            <input id="editShopName" name="shopName" class="border rounded-lg p-3" required>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Shop Address</label>
                            <input id="editShopAddress" name="shopAddress" class="border rounded-lg p-3" required>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Owner Name</label>
                            <input id="editOwnerName" name="ownerName" class="border rounded-lg p-3" required>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Email</label>
                            <input id="editEmail" name="email" type="email" class="border rounded-lg p-3" required>
                        </div>
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Contact Number</label>
                            <input id="editContactNumber" name="contactNumber" class="border rounded-lg p-3">
                        </div>
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Status</label>
                            <select id="editStatus" name="status" class="border rounded-lg p-3" required>
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Subscription Plan</label>
                            <select id="editSubscriptionPlan" name="subscriptionPlan" class="border rounded-lg p-3" required>
                                <?php foreach ($subscriptionPlans as $plan): ?>
                                    <option value="<?php echo htmlspecialchars($plan['key']); ?>" data-plan-features="<?php echo htmlspecialchars(json_encode($plan['features']), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($plan['name']); ?> - PHP <?php echo number_format($plan['monthly_price'], 2); ?> / month
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="editPlanFeaturesPreview" class="mt-2 text-xs text-slate-600 space-y-1"></div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Billing Cycle</label>
                            <select id="editBillingCycle" name="billingCycle" class="border rounded-lg p-3" required>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <button type="button" onclick="closeEditModal()"
                            class="flex-1 border rounded-lg py-3">Cancel</button>
                        <button name="updateTenant" class="flex-1 bg-primary text-white rounded-lg py-3">Update
                            Tenant</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Approval Modal (for approving pending applications) -->
        <div id="approvalModal"
            class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div
                class="bg-white dark:bg-slate-900 w-full max-w-xl rounded-xl shadow-2xl border flex flex-col overflow-hidden">
                <div class="px-8 py-6 border-b flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold">Approve Application</h2>
                        <p class="text-sm text-gray-500">Select subscription plan and billing cycle</p>
                    </div>
                    <button onclick="closeApprovalModal()" class="text-gray-400 hover:text-black">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-8 flex flex-col gap-6">
                    <input type="hidden" id="approveTenantID" name="tenantID">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Subscription Plan</label>
                            <select id="approvalSubscriptionPlan" class="border rounded-lg p-3" required>
                                <?php foreach ($subscriptionPlans as $plan): ?>
                                    <option value="<?php echo htmlspecialchars($plan['key']); ?>" data-plan-features="<?php echo htmlspecialchars(json_encode($plan['features']), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($plan['name']); ?> - PHP <?php echo number_format($plan['monthly_price'], 2); ?> / month
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="approvalPlanFeaturesPreview" class="mt-2 text-xs text-slate-600 space-y-1"></div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Billing Cycle</label>
                            <select id="approvalBillingCycle" class="border rounded-lg p-3" required>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <button type="button" onclick="closeApprovalModal()"
                            class="flex-1 border rounded-lg py-3">Cancel</button>
                        <button onclick="submitApproval()"
                            class="flex-1 bg-emerald-600 text-white rounded-lg py-3">Approve & Activate</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Tenant Modal -->
        <div id="tenantModal"
            class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white w-full max-w-xl rounded-xl shadow-2xl border flex flex-col overflow-hidden">
                <div class="px-8 py-6 border-b flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold">Create New Tenant</h2>
                        <p class="text-sm text-gray-500">Onboard a new vendor to your platform</p>
                    </div>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-black">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form method="POST" class="p-8 flex flex-col gap-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Shop Name</label>
                            <input name="shopName" class="border rounded-lg p-3" placeholder="Modern Boutique" required>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Shop Address</label>
                            <input name="shopAddress" class="border rounded-lg p-3" placeholder="123 Main Street"
                                required>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Owner Name</label>
                            <input name="ownerName" class="border rounded-lg p-3" placeholder="Juan Dela Cruz" required>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Email</label>
                            <input name="email" type="email" class="border rounded-lg p-3" placeholder="owner@email.com"
                                required>
                        </div>
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Contact Number</label>
                            <input name="contactNumber" class="border rounded-lg p-3" placeholder="09123456789">
                        </div>
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label class="text-xs font-bold uppercase text-gray-500">Temporary Password</label>
                            <div class="flex gap-2">
                                <input id="tempPasswordInput" name="tempPassword" type="text" class="border rounded-lg p-3 flex-1"
                                    placeholder="Auto-generated password" readonly required>
                                <button type="button" onclick="regenerateTempPassword()"
                                    class="px-3 py-2 border rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-50">Regenerate</button>
                            </div>
                            <p class="text-[11px] text-slate-500">Password is auto-generated. Share it securely with the tenant.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <button type="button" onclick="closeModal()"
                            class="flex-1 border rounded-lg py-3">Cancel</button>
                        <button name="createTenant" class="flex-1 bg-primary text-white rounded-lg py-3">Create
                            Tenant</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($noticeTitle !== ''): ?>
            <div id="statusNotification"
                class="fixed bottom-6 right-6 <?php echo $noticeTypeClass; ?> text-white px-6 py-4 rounded-lg shadow-lg flex items-center gap-3 transform translate-y-20 opacity-0 transition-all duration-500 z-50">
                <span class="material-symbols-outlined"><?php echo htmlspecialchars($noticeIcon); ?></span>
                <div>
                    <p class="font-semibold"><?php echo htmlspecialchars($noticeTitle); ?></p>
                    <p class="text-sm"><?php echo htmlspecialchars($noticeMessage); ?></p>
                </div>
                <button onclick="closeNotification()" class="ml-4 text-white hover:text-gray-200">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        <?php endif; ?>

    </main>

    <script>
        function renderPlanFeatures(selectElementId, previewElementId) {
            const select = document.getElementById(selectElementId);
            const preview = document.getElementById(previewElementId);

            if (!select || !preview) {
                return;
            }

            const selectedOption = select.options[select.selectedIndex];
            const rawFeatures = selectedOption ? selectedOption.getAttribute('data-plan-features') : '[]';
            let features = [];

            try {
                const parsed = JSON.parse(rawFeatures || '[]');
                if (Array.isArray(parsed)) {
                    features = parsed.filter(function (item) {
                        return String(item).trim() !== '';
                    });
                }
            } catch (e) {
                features = [];
            }

            if (features.length === 0) {
                preview.innerHTML = '<p class="text-slate-400">No saved features for this plan.</p>';
                return;
            }

            const items = features.map(function (feature) {
                const text = String(feature)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
                return '<div class="flex items-start gap-1.5"><span class="material-symbols-outlined text-emerald-500 text-[14px]">check_circle</span><span>' + text + '</span></div>';
            }).join('');

            preview.innerHTML = items;
        }

        function generateTempPassword(length = 12) {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
            let password = '';
            for (let i = 0; i < length; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return password;
        }

        function regenerateTempPassword() {
            const input = document.getElementById("tempPasswordInput");
            if (!input) {
                return;
            }
            input.value = generateTempPassword(12);
        }

        function openModal() {
            document.getElementById("tenantModal").classList.remove("hidden");
            regenerateTempPassword();
        }
        function closeModal() { document.getElementById("tenantModal").classList.add("hidden"); }

        function openEditModal(tenantID, shopName, shopAddress, ownerName, email, contactNumber, status, subscriptionPlan, billingCycle) {
            document.getElementById("editTenantID").value = tenantID;
            document.getElementById("editShopName").value = shopName;
            document.getElementById("editShopAddress").value = shopAddress;
            document.getElementById("editOwnerName").value = ownerName;
            document.getElementById("editEmail").value = email;
            document.getElementById("editContactNumber").value = contactNumber;
            document.getElementById("editStatus").value = status;
            document.getElementById("editSubscriptionPlan").value = subscriptionPlan;
            document.getElementById("editBillingCycle").value = billingCycle;
            renderPlanFeatures('editSubscriptionPlan', 'editPlanFeaturesPreview');
            document.getElementById("editTenantModal").classList.remove("hidden");
        }

        function closeEditModal() {
            document.getElementById("editTenantModal").classList.add("hidden");
        }

        function approveTenant(tenantID) {
            document.getElementById("approveTenantID").value = tenantID;
            renderPlanFeatures('approvalSubscriptionPlan', 'approvalPlanFeaturesPreview');
            document.getElementById("approvalModal").classList.remove("hidden");
        }

        function closeApprovalModal() {
            document.getElementById("approvalModal").classList.add("hidden");
        }

        function submitApproval() {
            const tenantID = document.getElementById("approveTenantID").value;
            const subscriptionPlan = document.getElementById("approvalSubscriptionPlan").value;
            const billingCycle = document.getElementById("approvalBillingCycle").value;

            const form = document.createElement('form');
            form.method = 'POST';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'tenantID';
            idInput.value = tenantID;

            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = 'Active';

            const planInput = document.createElement('input');
            planInput.type = 'hidden';
            planInput.name = 'subscriptionPlan';
            planInput.value = subscriptionPlan;

            const cycleInput = document.createElement('input');
            cycleInput.type = 'hidden';
            cycleInput.name = 'billingCycle';
            cycleInput.value = billingCycle;

            const updateInput = document.createElement('input');
            updateInput.type = 'hidden';
            updateInput.name = 'updateTenantStatus';
            updateInput.value = '1';

            form.appendChild(idInput);
            form.appendChild(statusInput);
            form.appendChild(planInput);
            form.appendChild(cycleInput);
            form.appendChild(updateInput);

            document.body.appendChild(form);
            form.submit();
        }

        document.getElementById("approvalSubscriptionPlan")?.addEventListener('change', function () {
            renderPlanFeatures('approvalSubscriptionPlan', 'approvalPlanFeaturesPreview');
        });

        document.getElementById("editSubscriptionPlan")?.addEventListener('change', function () {
            renderPlanFeatures('editSubscriptionPlan', 'editPlanFeaturesPreview');
        });

        renderPlanFeatures('approvalSubscriptionPlan', 'approvalPlanFeaturesPreview');
        renderPlanFeatures('editSubscriptionPlan', 'editPlanFeaturesPreview');

        function rejectTenant(tenantID) {
            const form = document.createElement('form');
            form.method = 'POST';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'tenantID';
            idInput.value = tenantID;

            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = 'Inactive';

            const updateInput = document.createElement('input');
            updateInput.type = 'hidden';
            updateInput.name = 'updateTenantStatus';
            updateInput.value = '1';

            form.appendChild(idInput);
            form.appendChild(statusInput);
            form.appendChild(updateInput);

            document.body.appendChild(form);
            form.submit();
        }
    </script>

    <script>
        function showNotification() {
            const notif = document.getElementById('statusNotification');
            if (!notif) return;
            notif.classList.remove('translate-y-20', 'opacity-0');
            notif.classList.add('translate-y-0', 'opacity-100');

            // Auto-hide after 5 seconds
            setTimeout(() => closeNotification(), 5000);
        }

        function closeNotification() {
            const notif = document.getElementById('statusNotification');
            if (!notif) return;
            notif.classList.add('translate-y-20', 'opacity-0');
            notif.classList.remove('translate-y-0', 'opacity-100');
        }

        <?php if ($noticeTitle !== ''): ?>
            window.onload = function () { showNotification(); }
        <?php endif; ?>
    </script>
</body>

</html>