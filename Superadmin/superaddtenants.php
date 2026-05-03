<?php
session_start();

if (!isset($_SESSION['superadmin_id'])) {
    header("Location: superaddlogin.php");
    exit();
}

// ✅ Load environment variables from .env file
if (file_exists(__DIR__ . '/../.env')) {
    $envLines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

include __DIR__ . "/../db.php";
require_once __DIR__ . "/../log_helper.php";

// Load current branding settings
$brandingSettings = [
    'system_name' => 'RapidRepair',
    'primary_color' => '#b91c1c',
    'logo_path' => ''
];

$brandingStmt = $conn->prepare("SELECT system_name, primary_color, logo_path FROM branding_settings WHERE id = 1");
if ($brandingStmt) {
    $brandingStmt->execute();
    $brandingRes = $brandingStmt->get_result();
    if ($brandingRes && $brandingRes->num_rows > 0) {
        $brandingSettings = $brandingRes->fetch_assoc();
    }
    $brandingStmt->close();
}

/**
 * Check if a tenant has made a payment in subscription_payments table
 */
function getTenantPaymentStatus($conn, $tenantID)
{
    $tenantID = (int) $tenantID;
    
    // Check if subscription_payments table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'subscription_payments'");
    if (!$checkTable || mysqli_num_rows($checkTable) === 0) {
        return ['status' => 'unknown', 'paid_at' => null, 'amount' => null, 'payment_method' => null];
    }
    
    $query = "SELECT payment_status, paid_at, amount, payment_method FROM subscription_payments WHERE tenantID = " . $tenantID . " ORDER BY created_at DESC LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        return ['status' => 'unpaid', 'paid_at' => null, 'amount' => null, 'payment_method' => null];
    }
    
    $payment = mysqli_fetch_assoc($result);
    return [
        'status' => strtolower($payment['payment_status']),
        'paid_at' => $payment['paid_at'],
        'amount' => $payment['amount'],
        'payment_method' => $payment['payment_method']
    ];
}

/**
 * Get formatted payment status badge
 */
function getPaymentStatusBadge($paymentStatus)
{
    switch (strtolower($paymentStatus)) {
        case 'paid':
            return ['badge' => '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-md">Paid</span>', 'icon' => 'check_circle', 'class' => 'text-green-600'];
        case 'pending':
            return ['badge' => '<span class="px-2 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded-md">Pending Payment</span>', 'icon' => 'schedule', 'class' => 'text-amber-600'];
        case 'failed':
            return ['badge' => '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-md">Failed</span>', 'icon' => 'error', 'class' => 'text-red-600'];
        case 'unpaid':
        default:
            return ['badge' => '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-bold rounded-md">No Payment</span>', 'icon' => 'warning', 'class' => 'text-slate-600'];
    }
}

if (isset($_POST['logout_superadmin'])) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    header("Location: superaddlogin.php");
    exit();
}

$superadminName = "Superadmin";
$superadminStmt = $conn->prepare("SELECT fullName FROM superadmin WHERE superadmin_id = ? LIMIT 1");
if ($superadminStmt) {
    $superadminStmt->bind_param("i", $_SESSION['superadmin_id']);
    $superadminStmt->execute();
    $superadminRes = $superadminStmt->get_result();
    if ($superadminRes && $superadminRes->num_rows > 0) {
        $superadminRow = $superadminRes->fetch_assoc();
        $superadminName = $superadminRow['fullName'] ?: $superadminName;
    }
    $superadminStmt->close();
}

function initials($name)
{
    $name = trim((string) $name);
    if ($name === '') {
        return 'NA';
    }

    $parts = preg_split('/\s+/', $name);
    if (!$parts) {
        return 'NA';
    }

    $first = strtoupper(substr($parts[0], 0, 1));
    $second = count($parts) > 1 ? strtoupper(substr($parts[count($parts) - 1], 0, 1)) : '';
    return $first . ($second ?: '');
}

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

function getBillingCycleDivisor($billingCycle)
{
    $cycle = strtolower(trim((string) $billingCycle));

    if ($cycle === 'quarterly' || $cycle === 'quarter') {
        return 3;
    }

    if ($cycle === 'semiannual' || $cycle === 'semi-annual' || $cycle === 'biannual') {
        return 6;
    }

    if ($cycle === 'annual' || $cycle === 'annually' || $cycle === 'yearly') {
        return 12;
    }

    return 1;
}

function calculateNextBillingDate($startDate, $billingCycle)
{
    $monthsToAdd = getBillingCycleDivisor($billingCycle);
    $date = new DateTime($startDate);
    $date->modify("+{$monthsToAdd} month");
    return $date->format('Y-m-d');
}

function calculateSubscriptionDates($startDate, $billingCycle)
{
    $nextBillingDate = calculateNextBillingDate($startDate, $billingCycle);

    return [
        'subscription_start' => $startDate,
        'subscription_end' => $nextBillingDate,
        'next_billing_date' => $nextBillingDate
    ];
}

function subscriptionsTableExists($conn)
{
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'subscriptions'");
    return $check && mysqli_num_rows($check) > 0;
}

function resolvePlanIdForSubscription($conn, $planKey, $planName)
{
    if (!subscriptionPlansTableExists($conn)) {
        return null;
    }

    $hasPlanIdColumn = subscriptionPlansColumnExists($conn, 'plan_id');
    if (!$hasPlanIdColumn) {
        return null;
    }

    $hasPlanCodeColumn = subscriptionPlansColumnExists($conn, 'plan_code');
    $hasPlanNameColumn = subscriptionPlansColumnExists($conn, 'plan_name');

    if ($hasPlanCodeColumn) {
        $safePlanKey = mysqli_real_escape_string($conn, (string) $planKey);
        $queryByCode = mysqli_query($conn, "SELECT plan_id FROM subscription_plans WHERE plan_code='$safePlanKey' LIMIT 1");
        if ($queryByCode && mysqli_num_rows($queryByCode) > 0) {
            $row = mysqli_fetch_assoc($queryByCode);
            return isset($row['plan_id']) ? (int) $row['plan_id'] : null;
        }
    }

    if ($hasPlanNameColumn) {
        $safePlanName = mysqli_real_escape_string($conn, (string) $planName);
        $queryByName = mysqli_query($conn, "SELECT plan_id FROM subscription_plans WHERE plan_name='$safePlanName' LIMIT 1");
        if ($queryByName && mysqli_num_rows($queryByName) > 0) {
            $row = mysqli_fetch_assoc($queryByName);
            return isset($row['plan_id']) ? (int) $row['plan_id'] : null;
        }
    }

    return null;
}

function syncApprovedTenantSubscription($conn, $tenantID, $planId, $billingCycle, $subscriptionStart, $subscriptionEnd, $nextBillingDate, $amount)
{
    if (!subscriptionsTableExists($conn)) {
        return true;
    }

    if ($planId === null) {
        return false;
    }

    $safeTenantID = mysqli_real_escape_string($conn, (string) $tenantID);
    $safeBillingCycle = mysqli_real_escape_string($conn, (string) $billingCycle);
    $safeStartDate = mysqli_real_escape_string($conn, (string) $subscriptionStart);
    $safeEndDate = mysqli_real_escape_string($conn, (string) $subscriptionEnd);
    $safeNextBillingDate = mysqli_real_escape_string($conn, (string) $nextBillingDate);
    $safeAmount = mysqli_real_escape_string($conn, number_format((float) $amount, 2, '.', ''));
    $safePlanId = (int) $planId;

    $existingSql = "SELECT subscription_id FROM subscriptions WHERE tenantID='$safeTenantID' ORDER BY subscription_id DESC LIMIT 1";
    $existingRes = mysqli_query($conn, $existingSql);

    if ($existingRes && mysqli_num_rows($existingRes) > 0) {
        $existing = mysqli_fetch_assoc($existingRes);
        $subscriptionId = (int) ($existing['subscription_id'] ?? 0);

        if ($subscriptionId > 0) {
            $updateSql = "UPDATE subscriptions SET
                plan_id = '$safePlanId',
                billing_cycle = '$safeBillingCycle',
                start_date = '$safeStartDate',
                end_date = '$safeEndDate',
                next_billing_date = '$safeNextBillingDate',
                amount = '$safeAmount',
                status = 'active',
                updated_at = NOW()
                WHERE subscription_id = '$subscriptionId' LIMIT 1";

            return mysqli_query($conn, $updateSql) !== false;
        }
    }

    $insertSql = "INSERT INTO subscriptions (
        tenantID,
        plan_id,
        billing_cycle,
        start_date,
        end_date,
        next_billing_date,
        amount,
        status,
        created_at,
        updated_at
    ) VALUES (
        '$safeTenantID',
        '$safePlanId',
        '$safeBillingCycle',
        '$safeStartDate',
        '$safeEndDate',
        '$safeNextBillingDate',
        '$safeAmount',
        'active',
        NOW(),
        NOW()
    )";

    return mysqli_query($conn, $insertSql) !== false;
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

function buildMailTransports()
{
    $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
    $smtpEncryption = strtolower(trim((string) (getenv('SMTP_ENCRYPTION') ?: '')));
    $smtpUsername = getenv('SMTP_USERNAME') ?: 'rapidrepair224@gmail.com';
    $smtpPassword = getenv('SMTP_PASSWORD') ?: 'gabd xcqy gbgq rtwj';

    if ($smtpEncryption === '') {
        $smtpEncryption = ($smtpPort === 465) ? 'ssl' : 'tls';
    }

    $fallbackSmtpHost = getenv('SMTP_FALLBACK_HOST') ?: $smtpHost;
    $fallbackSmtpPort = (int) (getenv('SMTP_FALLBACK_PORT') ?: $smtpPort);
    $fallbackSmtpEncryption = strtolower(trim((string) (getenv('SMTP_FALLBACK_ENCRYPTION') ?: $smtpEncryption)));
    $fallbackSmtpUsername = getenv('SMTP_FALLBACK_USERNAME') ?: '';
    $fallbackSmtpPassword = getenv('SMTP_FALLBACK_PASSWORD') ?: '';

    $mailTransports = [
        [
            'label' => 'primary',
            'host' => $smtpHost,
            'port' => $smtpPort,
            'encryption' => $smtpEncryption,
            'username' => $smtpUsername,
            'password' => $smtpPassword,
            'from_address' => getenv('MAIL_FROM_ADDRESS') ?: $smtpUsername,
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'Rapid Repair Admin',
            'reply_to_address' => getenv('MAIL_REPLY_TO') ?: (getenv('MAIL_FROM_ADDRESS') ?: $smtpUsername),
            'reply_to_name' => getenv('MAIL_REPLY_TO_NAME') ?: 'Rapid Repair Support'
        ]
    ];

    if ($fallbackSmtpUsername !== '' && $fallbackSmtpPassword !== '') {
        $mailTransports[] = [
            'label' => 'fallback',
            'host' => $fallbackSmtpHost,
            'port' => $fallbackSmtpPort,
            'encryption' => $fallbackSmtpEncryption,
            'username' => $fallbackSmtpUsername,
            'password' => $fallbackSmtpPassword,
            'from_address' => getenv('SMTP_FALLBACK_FROM_ADDRESS') ?: $fallbackSmtpUsername,
            'from_name' => getenv('SMTP_FALLBACK_FROM_NAME') ?: (getenv('MAIL_FROM_NAME') ?: 'Rapid Repair Admin'),
            'reply_to_address' => getenv('SMTP_FALLBACK_REPLY_TO') ?: (getenv('SMTP_FALLBACK_FROM_ADDRESS') ?: $fallbackSmtpUsername),
            'reply_to_name' => getenv('SMTP_FALLBACK_REPLY_TO_NAME') ?: (getenv('MAIL_REPLY_TO_NAME') ?: 'Rapid Repair Support')
        ];
    }

    return $mailTransports;
}

function sendTenantActivationDetailsEmail($ownerRow, $planName, $billingCycle, $subscriptionStart, $subscriptionEnd, $nextBillingDate, $planTotalPrice, $username = '', $tempPassword = '', $inviteCode = '')
{
    $email = trim((string) ($ownerRow['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => false, 'reason' => 'general'];
    }

    $ownerName = trim((string) ($ownerRow['ownerName'] ?? 'Tenant Owner'));
    $shopName = trim((string) ($ownerRow['shopName'] ?? 'Your Shop'));
    $loginSlug = trim((string) ($ownerRow['login_slug'] ?? ''));
    $baseURL = rtrim((string) (getenv('APP_BASE_URL') ?: 'https://rapidrepair-gygpcbczgyg0czek.southeastasia-01.azurewebsites.net'), '/');
    $loginLink = $loginSlug !== ''
        ? $baseURL . '/tenant/tenantlogin.php?shop=' . urlencode($loginSlug)
        : $baseURL . '/tenant/tenantlogin.php';
    $tenantWebsiteLink = $loginSlug !== ''
        ? $baseURL . '/tenant/tenantwebsite.php?shop=' . urlencode($loginSlug)
        : $baseURL . '/tenant/tenantwebsite.php';

    $safeOwnerName = htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8');
    $safeShopName = htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safePlanName = htmlspecialchars((string) $planName, ENT_QUOTES, 'UTF-8');
    $safeBillingCycle = htmlspecialchars(ucfirst((string) $billingCycle), ENT_QUOTES, 'UTF-8');
    $safeStartDate = htmlspecialchars((string) $subscriptionStart, ENT_QUOTES, 'UTF-8');
    $safeEndDate = htmlspecialchars((string) $subscriptionEnd, ENT_QUOTES, 'UTF-8');
    $safeNextBillingDate = htmlspecialchars((string) $nextBillingDate, ENT_QUOTES, 'UTF-8');
    $safePlanPrice = htmlspecialchars(number_format((float) $planTotalPrice, 2), ENT_QUOTES, 'UTF-8');
    $safeLoginLink = htmlspecialchars($loginLink, ENT_QUOTES, 'UTF-8');
    $safeTenantWebsiteLink = htmlspecialchars($tenantWebsiteLink, ENT_QUOTES, 'UTF-8');
    $safeUsername = htmlspecialchars((string) $username, ENT_QUOTES, 'UTF-8');
    $safeTempPassword = htmlspecialchars((string) $tempPassword, ENT_QUOTES, 'UTF-8');
    $safeInviteCode = htmlspecialchars((string) $inviteCode, ENT_QUOTES, 'UTF-8');

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isHTML(true);
    $mail->Subject = 'RapidRepair Application Approved';
    $mail->Body = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>RapidRepair Approval</title>
        </head>
        <body style='margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;'>
            <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='background:#f1f5f9;padding:24px 0;'>
                <tr>
                    <td align='center'>
                        <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='max-width:640px;background:#ffffff;border:1px solid #dbe1ea;border-radius:14px;overflow:hidden;'>
                            <tr>
                                <td style='padding:22px 24px;background:linear-gradient(135deg,#123b69,#0b1f42);color:#e2e8f0;'>
                                    <h1 style='margin:0;font-size:26px;line-height:32px;font-weight:700;color:#ffffff;'>RapidRepair</h1>
                                    <p style='margin:6px 0 0 0;font-size:14px;line-height:20px;'>Application approved and activated</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:24px;'>
                                    <p style='margin:0 0 12px 0;font-size:24px;line-height:30px;font-weight:700;color:#0f172a;'>Hello {$safeOwnerName},</p>
                                    <p style='margin:0 0 18px 0;font-size:16px;line-height:24px;color:#1e293b;'>
                                        Your application for <strong>{$safeShopName}</strong> is now approved and your tenant is active.
                                    </p>

                                    <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border:1px solid #d1d5db;border-radius:12px;background:#f8fafc;margin:0 0 18px 0;'>
                                        <tr><td style='padding:14px 16px;font-size:14px;color:#0f172a;'><strong>Plan:</strong> {$safePlanName}</td></tr>
                                        <tr><td style='padding:0 16px 14px 16px;font-size:14px;color:#0f172a;'><strong>Billing Cycle:</strong> {$safeBillingCycle}</td></tr>
                                        <tr><td style='padding:0 16px 14px 16px;font-size:14px;color:#0f172a;'><strong>Subscription Start:</strong> {$safeStartDate}</td></tr>
                                        <tr><td style='padding:0 16px 14px 16px;font-size:14px;color:#0f172a;'><strong>Subscription End:</strong> {$safeEndDate}</td></tr>
                                        <tr><td style='padding:0 16px 14px 16px;font-size:14px;color:#0f172a;'><strong>Next Billing Date:</strong> {$safeNextBillingDate}</td></tr>
                                        <tr><td style='padding:0 16px 16px 16px;font-size:14px;color:#0f172a;'><strong>Amount:</strong> PHP {$safePlanPrice}</td></tr>
                                    </table>

                                    <p style='margin:0 0 18px 0;font-size:16px;font-weight:600;color:#0f172a;'>Login Credentials:</p>
                                    <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border:1px solid #d1d5db;border-radius:12px;background:#f8fafc;margin:0 0 18px 0;'>
                                        <tr><td style='padding:14px 16px;font-size:14px;color:#0f172a;'><strong>Email:</strong> {$safeEmail}</td></tr>
                                        <tr><td style='padding:0 16px 14px 16px;font-size:14px;color:#0f172a;'><strong>Username:</strong> {$safeUsername}</td></tr>
                                        <tr><td style='padding:0 16px 14px 16px;font-size:14px;color:#0f172a;'><strong>Temporary Password:</strong> {$safeTempPassword}</td></tr>
                                        <tr><td style='padding:0 16px 14px 16px;font-size:14px;color:#0f172a;'><strong>Invite Code:</strong> {$safeInviteCode}</td></tr>
                                        <tr><td style='padding:0 16px 16px 16px;font-size:13px;line-height:20px;color:#475569;'><strong>Invite Code Note:</strong> This code is for customer registration so customers can sign up under your tenant.</td></tr>
                                    </table>

                                    <p style='margin:0 0 18px 0;font-size:14px;line-height:22px;word-break:break-all;'>
                                        <strong>Tenant login link:</strong> <a href='{$safeLoginLink}' style='color:#1d4ed8;text-decoration:underline;'>{$safeLoginLink}</a>
                                    </p>

                                    <p style='margin:0 0 18px 0;font-size:14px;line-height:22px;word-break:break-all;'>
                                        <strong>Tenant website link:</strong> <a href='{$safeTenantWebsiteLink}' style='color:#1d4ed8;text-decoration:underline;'>{$safeTenantWebsiteLink}</a>
                                    </p>
                                    
                                    <p style='margin:0 0 0 0;font-size:12px;line-height:18px;color:#666666;'>
                                        <strong>Note:</strong> Please change your temporary password on your first login.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:14px 24px;border-top:1px solid #e5e7eb;background:#f8fafc;font-size:11px;line-height:18px;color:#64748b;'>
                                    This email was sent by RapidRepair System.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
    ";
    $mail->AltBody = "RapidRepair Application Approved\n\n"
        . "Hello {$ownerName},\n\n"
        . "Your application for {$shopName} is approved and your tenant is now active.\n\n"
        . "=== SUBSCRIPTION DETAILS ===\n"
        . "Plan: {$planName}\n"
        . "Billing Cycle: " . ucfirst((string) $billingCycle) . "\n"
        . "Subscription Start: {$subscriptionStart}\n"
        . "Subscription End: {$subscriptionEnd}\n"
        . "Next Billing Date: {$nextBillingDate}\n"
        . "Amount: PHP " . number_format((float) $planTotalPrice, 2) . "\n\n"
        . "=== LOGIN CREDENTIALS ===\n"
        . "Email: {$email}\n"
        . "Username: {$username}\n"
        . "Temporary Password: {$tempPassword}\n"
        . "Invite Code: {$inviteCode}\n\n"
        . "Invite Code Note: This code is for customer registration so customers can sign up under your tenant.\n\n"
        . "Tenant Login Link: {$loginLink}\n\n"
        . "Tenant Website Link: {$tenantWebsiteLink}\n\n"
        . "Note: Please change your temporary password on your first login.\n";

    $emailFailureReason = '';
    $mailTransports = buildMailTransports();

    foreach ($mailTransports as $index => $transport) {
        try {
            $mail->isSMTP();
            $mail->Host = $transport['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $transport['username'];
            $mail->Password = $transport['password'];

            if ($transport['encryption'] === 'ssl' || $transport['encryption'] === 'smtps') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($transport['encryption'] === 'tls' || $transport['encryption'] === 'starttls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
            }

            $mail->Port = (int) $transport['port'];

            $smtpDebug = (int) (getenv('SMTP_DEBUG') ?: 0);
            if ($smtpDebug > 0) {
                $mail->SMTPDebug = $smtpDebug;
            }

            $allowSelfSigned = strtolower((string) (getenv('SMTP_ALLOW_SELF_SIGNED') ?: 'false')) === 'true';
            if ($allowSelfSigned) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
            }

            $mail->clearAddresses();
            $mail->clearReplyTos();
            $mail->clearCustomHeaders();

            $mail->setFrom($transport['from_address'], $transport['from_name']);
            $mail->Sender = $transport['from_address'];
            $mail->addReplyTo($transport['reply_to_address'], $transport['reply_to_name']);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->WordWrap = 78;
            $mail->addCustomHeader('X-Mailer', 'RapidRepair/Tenant-Approval');

            $mail->addAddress($email, $ownerName);
            $mail->send();

            error_log("Activation Mail Sent ({$transport['label']}) for tenant {$shopName} ({$email})");
            return ['sent' => true, 'reason' => ''];
        } catch (Exception $e) {
            $combinedError = strtolower($mail->ErrorInfo . ' | ' . $e->getMessage());
            $isQuotaError = (strpos($combinedError, 'daily user sending limit exceeded') !== false)
                || (strpos($combinedError, '5.4.5') !== false)
                || (strpos($combinedError, 'quota') !== false);

            if ($isQuotaError) {
                $emailFailureReason = 'quota';
            }

            $hasNextTransport = ($index < count($mailTransports) - 1);
            if (!$hasNextTransport && $emailFailureReason === '') {
                $emailFailureReason = 'general';
            }
        }
    }

    if ($emailFailureReason === '') {
        $emailFailureReason = 'general';
    }

    return ['sent' => false, 'reason' => $emailFailureReason];
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

require __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer/src/SMTP.php';
require __DIR__ . '/../PHPMailer/src/Exception.php';

/**
 * Map long status names to database-compatible ENUM values
 * Database ENUM values: 'Pending', 'Active', 'Inactive', 'Suspended'
 */
function mapStatusToDb($status) {
    $cleanStatus = trim($status);
    
    // Map to the enum values that actually exist in the database
    $statusMap = [
        'Active' => 'Active',
        'Pending' => 'Pending',
        'Rejected' => 'Inactive',  // Rejected applications become Inactive
        'Suspended' => 'Suspended',
        'Inactive' => 'Inactive'
    ];
    
    $mapped = $statusMap[$cleanStatus] ?? null;
    if ($mapped !== null) {
        return $mapped;
    }
    
    // Fallback to Pending for unknown values
    return 'Pending';
}

/**
 * Map database status codes back to long names for display
 */
function mapStatusFromDb($status) {
    $cleanStatus = trim($status);
    
    $statusMap = [
        'Active' => 'Active',
        'Pending' => 'Pending',
        'Inactive' => 'Inactive',
        'Suspended' => 'Suspended'
    ];
    return $statusMap[$cleanStatus] ?? $cleanStatus;
}

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
        $subscriptionDates = calculateSubscriptionDates($subscriptionStart, $billingCycle);
        $subscriptionEnd = $subscriptionDates['subscription_end'];
        $nextBillingDate = $subscriptionDates['next_billing_date'];

        $billingDivisor = getBillingCycleDivisor($billingCycle);
        $planTotalPrice = $subscriptionPlans[$subscriptionPlan]['monthly_price'] * $billingDivisor;
        $resolvedPlanId = resolvePlanIdForSubscription($conn, $subscriptionPlan, $subscriptionPlans[$subscriptionPlan]['name']);

        $dbStatus = mapStatusToDb($status);
        $updateSql = "UPDATE owners SET 
            status = '$dbStatus',
            subscription_plan = '" . mysqli_real_escape_string($conn, $subscriptionPlan) . "',
            billing_cycle = '" . mysqli_real_escape_string($conn, $billingCycle) . "',
            subscription_start = '" . mysqli_real_escape_string($conn, $subscriptionStart) . "',
            subscription_end = '" . mysqli_real_escape_string($conn, $subscriptionEnd) . "',
            plan_price = '" . mysqli_real_escape_string($conn, (string) $planTotalPrice) . "',
            next_billing_date = '" . mysqli_real_escape_string($conn, $nextBillingDate) . "'
            WHERE tenantID = '$tenantID'";

        mysqli_begin_transaction($conn);
        $ownersUpdated = mysqli_query($conn, $updateSql);
        $subscriptionSynced = $ownersUpdated && syncApprovedTenantSubscription(
            $conn,
            $tenantID,
            $resolvedPlanId,
            $billingCycle,
            $subscriptionStart,
            $subscriptionEnd,
            $nextBillingDate,
            $planTotalPrice
        );

        if ($ownersUpdated && $subscriptionSynced) {
            mysqli_commit($conn);

            $ownerRes = mysqli_query($conn, "SELECT ownerName, shopName, email, login_slug, username, password, invite_code FROM owners WHERE tenantID = '$tenantID' LIMIT 1");
            $ownerRow = $ownerRes && mysqli_num_rows($ownerRes) > 0 ? mysqli_fetch_assoc($ownerRes) : [];
            
            // If username is empty, generate one
            $username = trim((string) ($ownerRow['username'] ?? ''));
            if ($username === '') {
                $username = generateUsername($conn, $ownerRow['shopName'] ?? 'User');
                // Update the owners table with the generated username
                $updateUsernameQuery = "UPDATE owners SET username = '" . mysqli_real_escape_string($conn, $username) . "' WHERE tenantID = '$tenantID'";
                mysqli_query($conn, $updateUsernameQuery);
                $ownerRow['username'] = $username;
            }
            
            // If password is empty, use a temporary password
            $tempPassword = trim((string) ($ownerRow['password'] ?? ''));
            if ($tempPassword === '') {
                $tempPassword = generateTemporaryPassword();
                // Update the owners table with the password
                $updatePasswordQuery = "UPDATE owners SET password = '" . mysqli_real_escape_string($conn, $tempPassword) . "' WHERE tenantID = '$tenantID'";
                mysqli_query($conn, $updatePasswordQuery);
            }
            
            // Fetch invite code
            $inviteCode = trim((string) ($ownerRow['invite_code'] ?? ''));
            
            $emailResult = sendTenantActivationDetailsEmail(
                $ownerRow,
                $subscriptionPlans[$subscriptionPlan]['name'],
                $billingCycle,
                $subscriptionStart,
                $subscriptionEnd,
                $nextBillingDate,
                $planTotalPrice,
                $username,
                $tempPassword,
                $inviteCode
            );

            // Log the applicant approval
            $shopName = $ownerRow['shopName'] ?? 'Unknown Shop';
            $ownerName = $ownerRow['ownerName'] ?? 'Unknown Owner';
            $logDetails = "Applicant approved and activated. Owner: $ownerName, Shop: $shopName, Plan: " . $subscriptionPlans[$subscriptionPlan]['name'] . ", Billing Cycle: $billingCycle, Amount: PHP " . number_format((float)$planTotalPrice, 2);
            log_event($conn, "Accept Applicant", "Applicant", (int)$tenantID, $logDetails);

            if ($emailResult['sent']) {
                header("Location: ?notice=tenant_approved_email_sent");
            } elseif (($emailResult['reason'] ?? '') === 'quota') {
                header("Location: ?notice=tenant_approved_email_quota_exceeded");
            } else {
                header("Location: ?notice=tenant_approved_email_failed");
            }
        } else {
            mysqli_rollback($conn);
            header("Location: ?notice=tenant_status_update_failed");
        }
        exit;
    } else {
        $dbStatus = mapStatusToDb($status);
        $updateSql = "UPDATE owners SET status = '$dbStatus' WHERE tenantID = '$tenantID'";
    }

    if (mysqli_query($conn, $updateSql)) {
        if ($status === 'Active') {
            $redirect = 'tenant_approved';
        } elseif ($status === 'Suspended') {
            $redirect = 'tenant_suspended';
        } else {
            $redirect = 'tenant_rejected';
        }
        
        // Log the status change
        $tenantRes = mysqli_query($conn, "SELECT shopName, ownerName FROM owners WHERE tenantID = '$tenantID' LIMIT 1");
        $tenantRow = $tenantRes && mysqli_num_rows($tenantRes) > 0 ? mysqli_fetch_assoc($tenantRes) : [];
        $shopName = $tenantRow['shopName'] ?? 'Unknown Shop';
        $ownerName = $tenantRow['ownerName'] ?? 'Unknown Owner';
        
        if ($status === 'Active') {
            $action = "Accept Applicant";
            $logDetails = "Applicant approved and activated. Owner: $ownerName, Shop: $shopName";
        } elseif ($status === 'Suspended') {
            $action = "Suspend Tenant";
            $logDetails = "Tenant suspended. Owner: $ownerName, Shop: $shopName";
        } else {
            $action = "Reject Applicant";
            $logDetails = "Applicant rejected. Owner: $ownerName, Shop: $shopName";
        }
        
        log_event($conn, $action, "Applicant", (int)$tenantID, $logDetails);
        
        header("Location: ?notice=" . $redirect);
    } else {
        header("Location: ?notice=tenant_status_update_failed");
    }
    exit;
}

$notice = $_GET['notice'] ?? '';
$noticeTypeClass = '';
$noticeIcon = '';
$noticeTitle = '';
$noticeMessage = '';

switch ($notice) {
    case 'tenant_approved_email_sent':
        $noticeTypeClass = 'bg-red-600';
        $noticeIcon = 'check_circle';
        $noticeTitle = 'Application Approved';
        $noticeMessage = 'Tenant application was approved, activated, and the email details were sent.';
        break;
    case 'tenant_approved_email_failed':
        $noticeTypeClass = 'bg-amber-500';
        $noticeIcon = 'warning';
        $noticeTitle = 'Application Approved';
        $noticeMessage = 'Tenant was activated, but the approval email could not be sent.';
        break;
    case 'tenant_approved_email_quota_exceeded':
        $noticeTypeClass = 'bg-amber-500';
        $noticeIcon = 'warning';
        $noticeTitle = 'Application Approved';
        $noticeMessage = 'Tenant was activated, but email sending quota was exceeded.';
        break;
    case 'tenant_created_email_sent':
        $noticeTypeClass = 'bg-red-600';
        $noticeIcon = 'check_circle';
        $noticeTitle = 'Tenant Created';
        $noticeMessage = 'Tenant was created and email was sent successfully.';
        break;
    case 'tenant_created_email_failed':
        $noticeTypeClass = 'bg-red-500';
        $noticeIcon = 'warning';
        $noticeTitle = 'Tenant Created';
        $noticeMessage = 'Tenant was created, but the email could not be sent.';
        break;
    case 'tenant_created_email_quota_exceeded':
        $noticeTypeClass = 'bg-amber-500';
        $noticeIcon = 'warning';
        $noticeTitle = 'Tenant Created';
        $noticeMessage = 'Tenant was created, but email sending quota was exceeded. Configure fallback SMTP or retry tomorrow.';
        break;
    case 'tenant_create_failed':
        $noticeTypeClass = 'bg-red-500';
        $noticeIcon = 'error';
        $noticeTitle = 'Creation Failed';
        $noticeMessage = 'Could not create tenant. Please try again.';
        break;
    case 'tenant_create_duplicate_email':
        $noticeTypeClass = 'bg-amber-500';
        $noticeIcon = 'warning';
        $noticeTitle = 'Duplicate Email';
        $noticeMessage = 'A tenant account already exists with that email address.';
        break;
    case 'tenant_updated':
        $noticeTypeClass = 'bg-red-600';
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
        $noticeTypeClass = 'bg-red-600';
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
    case 'tenant_suspended':
        $noticeTypeClass = 'bg-amber-500';
        $noticeIcon = 'pause_circle';
        $noticeTitle = 'Tenant Suspended';
        $noticeMessage = 'Tenant status was updated to suspended.';
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

function generateUsername($conn, $shopName)
{
    // Convert shop name to lowercase and clean it
    $username = strtolower(trim($shopName));
    $username = preg_replace('/[^a-z0-9]/', '', $username); // remove spaces & special characters

    // Truncate to reasonable length while keeping shop name recognizable
    if (strlen($username) > 20) {
        $username = substr($username, 0, 20);
    }

    // Fallback if empty after cleaning
    if ($username === '') {
        $username = 'shop';
    }

    $originalUsername = $username;
    $counter = 1;

    // Ensure UNIQUE username - check if base username already exists
    $check = mysqli_query($conn, "SELECT tenantID FROM owners WHERE username='$username'");
    if (mysqli_num_rows($check) > 0) {
        // If exists, append counter to keep it connected to shop name
        while (true) {
            // Try username with counter
            $testUsername = $originalUsername . $counter;
            $check = mysqli_query($conn, "SELECT tenantID FROM owners WHERE username='$testUsername'");
            if (mysqli_num_rows($check) == 0) {
                $username = $testUsername;
                break;
            }
            $counter++;
            
            // Safety check to avoid infinite loops (max 999 variations)
            if ($counter > 999) {
                // Add timestamp as last resort
                $username = $originalUsername . substr(time(), -4);
                break;
            }
        }
    }

    return $username;
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

function generateUniqueInviteCode($conn, $length = 6)
{
    $digits = '0123456789';
    $maxIndex = strlen($digits) - 1;

    while (true) {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $digits[random_int(0, $maxIndex)];
        }

        $check = mysqli_query($conn, "SELECT tenantID FROM owners WHERE invite_code='" . mysqli_real_escape_string($conn, $code) . "' LIMIT 1");
        if ($check && mysqli_num_rows($check) === 0) {
            return $code;
        }
    }
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
        "status = '" . mapStatusToDb($status) . "'"
    ];

    if (ownersColumnExists($conn, 'subscription_plan')) {
        $updateFields[] = "subscription_plan = '" . mysqli_real_escape_string($conn, $subscriptionPlan) . "'";
    }

    if (ownersColumnExists($conn, 'billing_cycle')) {
        $updateFields[] = "billing_cycle = '" . mysqli_real_escape_string($conn, $billingCycle) . "'";
    }

    if ($status === 'Active') {
        $existingSubscriptionStart = '';

        $existingOwnerRes = mysqli_query($conn, "SELECT subscription_start FROM owners WHERE tenantID = '$tenantID' LIMIT 1");
        if ($existingOwnerRes && mysqli_num_rows($existingOwnerRes) > 0) {
            $existingOwnerRow = mysqli_fetch_assoc($existingOwnerRes);
            $existingSubscriptionStart = trim((string) ($existingOwnerRow['subscription_start'] ?? ''));
        }

        $subscriptionStart = $existingSubscriptionStart !== '' ? $existingSubscriptionStart : date('Y-m-d');
        $subscriptionDates = calculateSubscriptionDates($subscriptionStart, $billingCycle);
        $subscriptionEnd = $subscriptionDates['subscription_end'];
        $nextBillingDate = $subscriptionDates['next_billing_date'];

        $billingDivisor = getBillingCycleDivisor($billingCycle);
        $planTotalPrice = $subscriptionPlans[$subscriptionPlan]['monthly_price'] * $billingDivisor;

        if (ownersColumnExists($conn, 'subscription_start')) {
            $updateFields[] = "subscription_start = '" . mysqli_real_escape_string($conn, $subscriptionStart) . "'";
        }

        if (ownersColumnExists($conn, 'subscription_end')) {
            $updateFields[] = "subscription_end = '" . mysqli_real_escape_string($conn, $subscriptionEnd) . "'";
        }

        if (ownersColumnExists($conn, 'next_billing_date')) {
            $updateFields[] = "next_billing_date = '" . mysqli_real_escape_string($conn, $nextBillingDate) . "'";
        }

        if (ownersColumnExists($conn, 'plan_price')) {
            $updateFields[] = "plan_price = '" . mysqli_real_escape_string($conn, number_format($planTotalPrice, 2, '.', '')) . "'";
        }
    }

    $updateSql = "UPDATE owners SET " . implode(",\n        ", $updateFields) . "\n        WHERE tenantID = '$tenantID'";

    if (mysqli_query($conn, $updateSql)) {
        // Log the tenant update
        $logDetails = "Updated: Shop Name, Address, Owner Name, Email, Contact, Status";
        log_event($conn, "Update Tenant Information", "Tenant", (int)$tenantID, $logDetails);
        
        header("Location: ?notice=tenant_updated");
    } else {
        header("Location: ?notice=tenant_update_failed");
    }
    exit;
}

// HANDLE CREATE TENANT
if (isset($_POST['createTenant'])) {

    $shopName = mysqli_real_escape_string($conn, $_POST['shopName']);
    $shopAddress = mysqli_real_escape_string($conn, $_POST['shopAddress']);
    $ownerName = mysqli_real_escape_string($conn, $_POST['ownerName']);
    $email = trim($_POST['email']);
    $username = generateUsername($conn, $shopName);
    $username = mysqli_real_escape_string($conn, $username);
    $contactNumber = mysqli_real_escape_string($conn, $_POST['contactNumber']);
    $tempPassword = trim((string) ($_POST['tempPassword'] ?? ''));

    if ($tempPassword === '') {
        $tempPassword = generateTemporaryPassword();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email address.");
    }

    $normalizedEmail = trim(strtolower($email));
    $existingEmailSql = "SELECT tenantID FROM owners WHERE LOWER(TRIM(email)) = '" . mysqli_real_escape_string($conn, $normalizedEmail) . "' LIMIT 1";
    $existingEmailResult = mysqli_query($conn, $existingEmailSql);
    if ($existingEmailResult && mysqli_num_rows($existingEmailResult) > 0) {
        header("Location: ?notice=tenant_create_duplicate_email");
        exit;
    }

    // Superadmin-created tenants should be ACTIVE immediately
    $status = 'Active';

    // Use default subscription values for superadmin-created tenants
    $subscriptionPlan = $defaultPlanKey;
    $billingCycle = 'monthly';

    if (!isset($subscriptionPlans[$subscriptionPlan])) {
        $subscriptionPlan = $defaultPlanKey;
    }

    if (!isset($billingCycles[$billingCycle])) {
        $billingCycle = 'monthly';
    }

    $subscriptionStart = date('Y-m-d');
    $subscriptionDates = calculateSubscriptionDates($subscriptionStart, $billingCycle);
    $subscriptionEnd = $subscriptionDates['subscription_end'];
    $nextBillingDate = $subscriptionDates['next_billing_date'];

    $billingDivisor = getBillingCycleDivisor($billingCycle);
    $planTotalPrice = $subscriptionPlans[$subscriptionPlan]['monthly_price'] * $billingDivisor;
    $resolvedPlanId = resolvePlanIdForSubscription($conn, $subscriptionPlan, $subscriptionPlans[$subscriptionPlan]['name']);
    $inviteCode = generateUniqueInviteCode($conn);

    // First login password stays plain text until changed
    $hashedPassword = $tempPassword;

    // Generate tenant ID
    $getID = mysqli_query($conn, "SELECT tenantID FROM owners ORDER BY tenantID DESC LIMIT 1");

    if (mysqli_num_rows($getID) > 0) {
        $row = mysqli_fetch_assoc($getID);
        $newID = (int) $row['tenantID'] + 1;
    } else {
        $newID = 1;
    }

    $tenantID = (string) $newID;

    // Generate slug
    $login_slug = generateSlug($conn, $shopName);

    // Build insert dynamically so it works even if some columns are missing
    $insertColumns = [
        'tenantID',
        'ownerName',
        'shopName',
        'login_slug',
        'username',
        'email',
        'contactNumber',
        'invite_code',
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
        "'" . $username . "'",
        "'" . mysqli_real_escape_string($conn, $email) . "'",
        "'" . mysqli_real_escape_string($conn, $contactNumber) . "'",
        "'" . mysqli_real_escape_string($conn, $inviteCode) . "'",
        "'" . mysqli_real_escape_string($conn, $shopAddress) . "'",
        "'" . mysqli_real_escape_string($conn, $hashedPassword) . "'",
        "1",
        "'" . mapStatusToDb($status) . "'"
    ];

    if (ownersColumnExists($conn, 'subscription_plan')) {
        $insertColumns[] = 'subscription_plan';
        $insertValues[] = "'" . mysqli_real_escape_string($conn, $subscriptionPlan) . "'";
    }

    if (ownersColumnExists($conn, 'billing_cycle')) {
        $insertColumns[] = 'billing_cycle';
        $insertValues[] = "'" . mysqli_real_escape_string($conn, $billingCycle) . "'";
    }

    if (ownersColumnExists($conn, 'subscription_start')) {
        $insertColumns[] = 'subscription_start';
        $insertValues[] = "'" . mysqli_real_escape_string($conn, $subscriptionStart) . "'";
    }

    if (ownersColumnExists($conn, 'subscription_end')) {
        $insertColumns[] = 'subscription_end';
        $insertValues[] = "'" . mysqli_real_escape_string($conn, $subscriptionEnd) . "'";
    }

    if (ownersColumnExists($conn, 'next_billing_date')) {
        $insertColumns[] = 'next_billing_date';
        $insertValues[] = "'" . mysqli_real_escape_string($conn, $nextBillingDate) . "'";
    }

    if (ownersColumnExists($conn, 'plan_price')) {
        $insertColumns[] = 'plan_price';
        $insertValues[] = "'" . mysqli_real_escape_string($conn, number_format($planTotalPrice, 2, '.', '')) . "'";
    }

    $insertSql = "INSERT INTO owners (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";

    mysqli_begin_transaction($conn);

    $insert = mysqli_query($conn, $insertSql);

    $subscriptionSynced = $insert && syncApprovedTenantSubscription(
        $conn,
        $tenantID,
        $resolvedPlanId,
        $billingCycle,
        $subscriptionStart,
        $subscriptionEnd,
        $nextBillingDate,
        $planTotalPrice
    );

    $emailSent = false;
    $emailFailureReason = '';

    if ($insert && $subscriptionSynced) {
        mysqli_commit($conn);

        // LOGIN LINK
        $baseURL = "https://rapidrepair-gygpcbczgyg0czek.southeastasia-01.azurewebsites.net";
        $loginLink = $baseURL . "/tenant/tenantlogin.php?shop=" . urlencode($login_slug);

        $mail = new PHPMailer(true);

        $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
        $smtpEncryption = strtolower(trim((string) (getenv('SMTP_ENCRYPTION') ?: '')));
        $smtpUsername = getenv('SMTP_USERNAME') ?: 'rapidrepair224@gmail.com';
        $smtpPassword = getenv('SMTP_PASSWORD') ?: 'gabd xcqy gbgq rtwj';

        if ($smtpEncryption === '') {
            $smtpEncryption = ($smtpPort === 465) ? 'ssl' : 'tls';
        }

        $fallbackSmtpHost = getenv('SMTP_FALLBACK_HOST') ?: $smtpHost;
        $fallbackSmtpPort = (int) (getenv('SMTP_FALLBACK_PORT') ?: $smtpPort);
        $fallbackSmtpEncryption = strtolower(trim((string) (getenv('SMTP_FALLBACK_ENCRYPTION') ?: $smtpEncryption)));
        $fallbackSmtpUsername = getenv('SMTP_FALLBACK_USERNAME') ?: '';
        $fallbackSmtpPassword = getenv('SMTP_FALLBACK_PASSWORD') ?: '';

        $mailTransports = [
            [
                'label' => 'primary',
                'host' => $smtpHost,
                'port' => $smtpPort,
                'encryption' => $smtpEncryption,
                'username' => $smtpUsername,
                'password' => $smtpPassword,
                'from_address' => getenv('MAIL_FROM_ADDRESS') ?: $smtpUsername,
                'from_name' => getenv('MAIL_FROM_NAME') ?: 'Rapid Repair Admin',
                'reply_to_address' => getenv('MAIL_REPLY_TO') ?: (getenv('MAIL_FROM_ADDRESS') ?: $smtpUsername),
                'reply_to_name' => getenv('MAIL_REPLY_TO_NAME') ?: 'Rapid Repair Support'
            ]
        ];

        if ($fallbackSmtpUsername !== '' && $fallbackSmtpPassword !== '') {
            $mailTransports[] = [
                'label' => 'fallback',
                'host' => $fallbackSmtpHost,
                'port' => $fallbackSmtpPort,
                'encryption' => $fallbackSmtpEncryption,
                'username' => $fallbackSmtpUsername,
                'password' => $fallbackSmtpPassword,
                'from_address' => getenv('SMTP_FALLBACK_FROM_ADDRESS') ?: $fallbackSmtpUsername,
                'from_name' => getenv('SMTP_FALLBACK_FROM_NAME') ?: (getenv('MAIL_FROM_NAME') ?: 'Rapid Repair Admin'),
                'reply_to_address' => getenv('SMTP_FALLBACK_REPLY_TO') ?: (getenv('SMTP_FALLBACK_FROM_ADDRESS') ?: $fallbackSmtpUsername),
                'reply_to_name' => getenv('SMTP_FALLBACK_REPLY_TO_NAME') ?: (getenv('MAIL_REPLY_TO_NAME') ?: 'Rapid Repair Support')
            ];
        }

        $safeOwnerName = htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8');
        $safeShopName = htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8');
        $safeLoginLink = htmlspecialchars($loginLink, ENT_QUOTES, 'UTF-8');
        $safeTempPassword = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');
        $safeLoginEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

        // Fetch invite code from database
        $inviteCodeRes = mysqli_query($conn, "SELECT invite_code FROM owners WHERE tenantID = '" . mysqli_real_escape_string($conn, $tenantID) . "' LIMIT 1");
        $inviteCodeRow = $inviteCodeRes && mysqli_num_rows($inviteCodeRes) > 0 ? mysqli_fetch_assoc($inviteCodeRes) : [];
        $inviteCode = trim((string) ($inviteCodeRow['invite_code'] ?? ''));
        $safeInviteCode = htmlspecialchars($inviteCode, ENT_QUOTES, 'UTF-8');

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
                Your Rapid Repair tenant account has been created and activated.
            </div>
            <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='background:#f1f5f9;padding:24px 0;'>
                <tr>
                    <td align='center'>
                        <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='max-width:640px;background:#ffffff;border:1px solid #dbe1ea;border-radius:14px;overflow:hidden;'>
                            <tr>
                                <td style='padding:22px 24px;background:linear-gradient(135deg,#123b69,#0b1f42);color:#e2e8f0;'>
                                    <h1 style='margin:0;font-size:28px;line-height:32px;font-weight:700;color:#ffffff;'>RapidRepair</h1>
                                    <p style='margin:6px 0 0 0;font-size:15px;line-height:20px;'>Tenant access details</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:24px;'>
                                    <p style='margin:0 0 12px 0;font-size:27px;line-height:34px;font-weight:700;color:#0f172a;'>Hi {$safeOwnerName},</p>
                                    <p style='margin:0 0 18px 0;font-size:20px;line-height:28px;color:#1e293b;'>
                                        Your Car Repair Shop <strong>{$safeShopName}</strong> has been created and is now active in RapidRepair.
                                    </p>

                                    <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border:1px solid #d1d5db;border-radius:12px;background:#f8fafc;margin:0 0 16px 0;'>
                                        <tr>
                                            <td style='padding:16px;'>
                                                <p style='margin:0 0 8px 0;font-size:14px;line-height:20px;color:#64748b;font-weight:700;'>Your tenant login link</p>
                                                <p style='margin:0 0 12px 0;font-size:17px;line-height:24px;word-break:break-all;'>
                                                    <a href='{$safeLoginLink}' style='color:#1d4ed8;text-decoration:underline;'>{$safeLoginLink}</a>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>

                                    <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border:1px solid #d1d5db;border-radius:12px;background:#f8fafc;margin:0 0 16px 0;'>
                                        <tr>
                                            <td style='padding:16px;'>
                                                <p style='margin:0 0 6px 0;font-size:14px;line-height:20px;color:#64748b;font-weight:700;'>Username</p>
                                                <p style='margin:0;font-size:24px;line-height:30px;color:#0f172a;font-weight:700;'>{$safeUsername}</p>
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

                                    <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border:1px solid #fecaca;border-radius:12px;background:#fee2e2;margin:0 0 16px 0;'>
                                        <tr>
                                            <td style='padding:16px;'>
                                                <p style='margin:0 0 6px 0;font-size:14px;line-height:20px;color:#7f1d1d;font-weight:700;'>Invite Code</p>
                                                <p style='margin:0;font-size:24px;line-height:30px;letter-spacing:2px;color:#991b1b;font-weight:700;'>{$safeInviteCode}</p>
                                            </td>
                                        </tr>
                                    </table>

                                    <p style='margin:0 0 8px 0;font-size:25px;line-height:30px;color:#0f172a;font-weight:700;'>Next steps</p>
                                    <ul style='margin:0 0 12px 22px;padding:0;font-size:15px;line-height:24px;color:#0f172a;'>
                                        <li>Open the link above and log in using this username: <strong>{$safeUsername}</strong></li>
                                        <li>You can also log in using this email address: <strong>{$safeLoginEmail}</strong></li>
                                        <li>Use the temporary password, then change it immediately after you sign in.</li>
                                        <li>Your account is already active.</li>
                                    </ul>
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
            . "Your tenant account for {$shopName} has been created and is now active.\n\n"
            . "Your tenant login link: {$loginLink}\n"
            . "Username: {$username}\n"
            . "Login Email: {$email}\n"
            . "Temporary Password: {$tempPassword}\n"
            . "Invite Code: {$inviteCode}\n"
            . "Status: Active\n\n"
            . "Next steps:\n"
            . "- Open the link above and log in using this username: {$username}\n"
            . "- You can also log in using this email address: {$email}\n"
            . "- Use the temporary password, then change it immediately after you sign in.\n";

        foreach ($mailTransports as $index => $transport) {
            try {
                $mail->isSMTP();
                $mail->Host = $transport['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $transport['username'];
                $mail->Password = $transport['password'];

                if ($transport['encryption'] === 'ssl' || $transport['encryption'] === 'smtps') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($transport['encryption'] === 'tls' || $transport['encryption'] === 'starttls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $mail->SMTPSecure = '';
                }

                $mail->Port = (int) $transport['port'];

                $mail->clearAddresses();
                $mail->clearReplyTos();
                $mail->clearCustomHeaders();

                $mail->setFrom($transport['from_address'], $transport['from_name']);
                $mail->Sender = $transport['from_address'];
                $mail->addReplyTo($transport['reply_to_address'], $transport['reply_to_name']);
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';
                $mail->WordWrap = 78;
                $mail->addCustomHeader('X-Mailer', 'RapidRepair/Tenant-Onboarding');
                $mail->addAddress($email, $ownerName);
                $mail->isHTML(true);
                $mail->Subject = 'Rapid Repair Tenant Access Details';
                $mail->send();

                $emailSent = true;
                break;
            } catch (Exception $e) {
                $combinedError = strtolower($mail->ErrorInfo . ' | ' . $e->getMessage());
                $isQuotaError = (strpos($combinedError, 'daily user sending limit exceeded') !== false)
                    || (strpos($combinedError, '5.4.5') !== false)
                    || (strpos($combinedError, 'quota') !== false);

                if ($isQuotaError) {
                    $emailFailureReason = 'quota';
                }

                $hasNextTransport = ($index < count($mailTransports) - 1);
                if (!$hasNextTransport && $emailFailureReason === '') {
                    $emailFailureReason = 'general';
                }
            }
        }
    } else {
        mysqli_rollback($conn);
    }

    if ($insert && $subscriptionSynced && $emailSent) {
        // Log the tenant creation
        $logDetails = "Created new tenant: $shopName, Owner: $ownerName, Subscription: $subscriptionPlan, Status: Active";
        log_event($conn, "Create Tenant", "Tenant", (int)$tenantID, $logDetails);
        
        header("Location: ?notice=tenant_created_email_sent");
    } elseif ($insert && $subscriptionSynced && $emailFailureReason === 'quota') {
        // Log the tenant creation
        $logDetails = "Created new tenant: $shopName, Owner: $ownerName, Subscription: $subscriptionPlan, Status: Active";
        log_event($conn, "Create Tenant", "Tenant", (int)$tenantID, $logDetails);
        
        header("Location: ?notice=tenant_created_email_quota_exceeded");
    } elseif ($insert && $subscriptionSynced) {
        // Log the tenant creation
        $logDetails = "Created new tenant: $shopName, Owner: $ownerName, Subscription: $subscriptionPlan, Status: Active";
        log_event($conn, "Create Tenant", "Tenant", (int)$tenantID, $logDetails);
        
        header("Location: ?notice=tenant_created_email_failed");
    } else {
        header("Location: ?notice=tenant_create_failed");
    }

    exit;
}
?>

<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tenant Management | RapidRepair</title>
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
                        "primary": "#b91c1c",
                        "background-light": "#ffffff",
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

    <aside
        class="flex flex-col fixed left-0 top-0 h-full z-40 h-screen w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 font-['Inter'] antialiased tracking-tight shadow-sm dark:shadow-none">
        <div class="p-6 flex items-center gap-3">
            <div class="h-10 w-10 rounded-lg overflow-hidden">
                <img src="../pictures/RRlogo3.png" alt="Rapid Repair logo"
                    class="h-full w-full object-contain">
            </div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white leading-none">
                <?= htmlspecialchars($brandingSettings['system_name']) ?> <span class="text-primary">SuperAdmin</span>
            </h2>
        </div>

        <nav class="flex-1 px-4 space-y-1 mt-4">
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superadd.php">
                <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
                <span class="text-sm">Dashboard</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold border-r-4 border-red-700 dark:border-red-500 rounded-lg active:scale-95"
                href="superaddtenants.php">
                <span class="material-symbols-outlined" data-icon="groups">groups</span>
                <span class="text-sm">Tenants</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superreports.php">
                <span class="material-symbols-outlined" data-icon="bar_chart">bar_chart</span>
                <span class="text-sm">Reports</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="subscriptionmanage.php">
                <span class="material-symbols-outlined" data-icon="subscriptions">subscriptions</span>
                <span class="text-sm">Subscriptions</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="supersalesreport.php">
                <span class="material-symbols-outlined" data-icon="monitoring">monitoring</span>
                <span class="text-sm">Sales Reports</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superauditlogs.php">
                <span class="material-symbols-outlined" data-icon="assignment">assignment</span>
                <span class="text-sm">Audit Logs</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superbackup.php">
                <span class="material-symbols-outlined" data-icon="backup"
                    style="font-variation-settings: 'FILL' 1;">backup</span>
                <span class="text-sm">System Backup</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="supersettings.php">
                <span class="material-symbols-outlined" data-icon="settings">settings</span>
                <span class="text-sm">Settings</span>
            </a>
        </nav>

        <div class="p-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
            <div
                class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                <div
                    class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 flex items-center justify-center font-semibold text-sm">
                    <?php echo htmlspecialchars(initials($superadminName)); ?>
                </div>
                <div class="flex flex-col min-w-0">
                    <h3 class="text-sm font-semibold truncate text-slate-900 dark:text-white">
                        <?php echo htmlspecialchars($superadminName); ?>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Superadmin</p>
                </div>
            </div>
            <form method="POST" class="w-full">
                <button type="submit" name="logout_superadmin"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors cursor-pointer text-left mt-2">
                    <span class="material-symbols-outlined">logout</span>
                    <p class="text-sm font-medium">Logout</p>
                </button>
            </form>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 bg-background-light dark:bg-background-dark ml-64">
        <header
            class="flex items-center justify-between px-8 sticky top-0 z-30 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md w-full h-16 border-b border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-4">
                <div class="relative">
                    <input id="globalSearchInput"
                        class="pl-4 pr-4 py-1.5 bg-slate-100 dark:bg-slate-800 border-none text-sm rounded-lg focus:ring-2 focus:ring-primary w-64 transition-all"
                        placeholder="Search tenants or applications..." type="text" />
                </div>
                <div class="relative" id="searchScopeFilters">
                    <button type="button" id="filtersToggle"
                        class="flex items-center gap-2 rounded-full bg-white px-6 py-2.5 text-sm font-semibold text-slate-800 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md">
                        <span>Filters</span>
                    </button>
                    <div id="filtersDropdown"
                        class="hidden absolute left-0 top-full mt-2 min-w-[180px] rounded-xl border border-slate-200 bg-white p-2 shadow-lg z-50">
                        <button type="button" data-scope="all"
                            class="tenant-search-scope-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium bg-primary text-white">All</button>
                        <button type="button" data-scope="tenants"
                            class="tenant-search-scope-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-100">Tenants</button>
                        <button type="button" data-scope="pending"
                            class="tenant-search-scope-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-100">Pending</button>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-4"></div>
        </header>

        <div class="p-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Tenant Management</h2>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">Manage and monitor platform tenants and shop applications.</p>
                </div>
                <button onclick="openModal()"
                    class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg">
                    <span class="material-symbols-outlined">add</span> Add Tenant
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <?php
                $activeTenantCountResult = mysqli_query($conn, "SELECT COUNT(*) as count FROM owners WHERE status='Active'");
                $activeTenantCount = mysqli_fetch_assoc($activeTenantCountResult)['count'] ?? 0;

                $pendingAppCountResult = mysqli_query($conn, "SELECT COUNT(*) as count FROM owners WHERE status='Pending'");
                $pendingAppCount = mysqli_fetch_assoc($pendingAppCountResult)['count'] ?? 0;

                $inactiveTenantCountResult = mysqli_query($conn, "SELECT COUNT(*) as count FROM owners WHERE status IN ('Inactive', 'Rejected', 'Suspended')");
                $inactiveTenantCount = mysqli_fetch_assoc($inactiveTenantCountResult)['count'] ?? 0;
                ?>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Active Tenants</p>
                            <p class="text-3xl font-bold text-slate-900 dark:text-white mt-2"><?php echo $activeTenantCount; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-green-600 dark:text-green-400">check_circle</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Pending Approvals</p>
                            <p class="text-3xl font-bold text-slate-900 dark:text-white mt-2"><?php echo $pendingAppCount; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-amber-600 dark:text-amber-400">schedule</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Inactive/Rejected</p>
                            <p class="text-3xl font-bold text-slate-900 dark:text-white mt-2"><?php echo $inactiveTenantCount; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-red-600 dark:text-red-400">cancel</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tenants Section with Search -->
            <div class="mb-8">
                <div class="flex gap-3 mb-4">
                    <div class="flex-1 relative">
                        <span class="material-symbols-outlined absolute left-3 top-3 text-slate-400">search</span>
                        <input type="text" id="globalSearchInput" placeholder="Search tenants by name, email, plan..."
                            class="w-full pl-10 pr-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <button id="filtersToggle" class="flex items-center gap-2 px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
                        <span class="material-symbols-outlined">filter_list</span>
                        <span class="text-sm font-medium">Filters</span>
                    </button>
                </div>
                <div id="filtersDropdown" class="hidden mb-4 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-200 dark:border-slate-700">
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-3">Filter by Status:</p>
                    <div class="flex flex-wrap gap-2">
                        <button class="tenant-search-scope-btn px-3 py-1.5 rounded-full text-sm font-medium transition-colors" data-scope="all">
                            All Tenants
                        </button>
                        <button class="tenant-search-scope-btn px-3 py-1.5 rounded-full text-sm font-medium transition-colors" data-scope="active">
                            Active
                        </button>
                        <button class="tenant-search-scope-btn px-3 py-1.5 rounded-full text-sm font-medium transition-colors" data-scope="suspended">
                            Suspended
                        </button>
                        <button class="tenant-search-scope-btn px-3 py-1.5 rounded-full text-sm font-medium transition-colors" data-scope="inactive">
                            Inactive
                        </button>
                    </div>
                </div>
            </div>

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
                        <tbody id="tenantsTableBody" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php
                            $totalQuery = "SELECT COUNT(*) as total FROM owners";
                            $totalResult = mysqli_query($conn, $totalQuery);
                            $totalRow = mysqli_fetch_assoc($totalResult);
                            $totalTenants = $totalRow['total'];
                            $totalTenantPages = ceil($totalTenants / $rowsPerPage);

                            if ($tenantPage > $totalTenantPages && $totalTenantPages > 0) {
                                $tenantPage = $totalTenantPages;
                            }

                            $offset = ($tenantPage - 1) * $rowsPerPage;
                            $query = "SELECT * FROM owners ORDER BY tenantID DESC LIMIT $offset, $rowsPerPage";
                            $result = mysqli_query($conn, $query);
                            while ($row = mysqli_fetch_assoc($result)) {
                                $statusColor = "emerald";
                                $displayStatus = ucfirst($row['status']);
                                if (strtolower($row['status']) == "inactive") {
                                    $statusColor = "red";
                                    $displayStatus = "Inactive";
                                }
                                if (strtolower($row['status']) == "rejected") {
                                    $statusColor = "red";
                                    $displayStatus = "Rejected";
                                }
                                if (strtolower($row['status']) == "pending")
                                    $statusColor = "amber";
                                if (strtolower($row['status']) == "suspended")
                                    $statusColor = "yellow";

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
                                } elseif (isset($row['subscription_start']) && $row['subscription_start'] !== '') {
                                    $tenantNextBillingRaw = calculateNextBillingDate($row['subscription_start'], $tenantCycleKey);
                                } elseif (isset($row['subscription_end']) && $row['subscription_end'] !== '') {
                                    $tenantNextBillingRaw = $row['subscription_end'];
                                } elseif (isset($row['created_at']) && $row['created_at'] !== '') {
                                    $tenantNextBillingRaw = date('Y-m-d', strtotime($row['created_at'] . ' +' . $billingCycles[$tenantCycleKey] . ' months'));
                                }

                                $tenantNextBilling = $tenantNextBillingRaw !== ''
                                    ? date('M d, Y', strtotime($tenantNextBillingRaw))
                                    : 'N/A';

                                $tenantSearchHaystack = strtolower(trim((string) ($row['shopName'] ?? '') . ' ' . (string) ($row['ownerName'] ?? '') . ' ' . (string) ($row['email'] ?? '') . ' ' . (string) $tenantPlan . ' ' . (string) $tenantCycle . ' ' . (string) ($row['status'] ?? '') . ' ' . (string) ($row['tenantID'] ?? '') . ' ' . (string) $tenantNextBilling));
                                ?>
                                <tr class="searchable-tenant hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors"
                                    data-search="<?php echo htmlspecialchars($tenantSearchHaystack, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-status="<?php echo strtolower($row['status']); ?>"></tr>
                                    <td class="px-6 py-4"><?php echo $row['shopName']; ?> (ID:
                                        <?php echo $row['tenantID']; ?>)
                                    </td>
                                    <td class="px-6 py-4"><?php echo $row['ownerName']; ?><br><span
                                            class="text-xs text-slate-500"><?php echo $row['email']; ?></span></td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="text-sm font-semibold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($tenantPlan); ?></span><br>
                                        <span
                                            class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($tenantCycle); ?></span><br>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">PHP
                                            <?php echo number_format($tenantPrice, 2); ?></span><br>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">Next Billing:
                                            <?php echo htmlspecialchars($tenantNextBilling); ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2 py-1 text-xs font-semibold bg-<?php echo $statusColor; ?>-100 dark:bg-<?php echo $statusColor; ?>-900/30 text-<?php echo $statusColor; ?>-700 dark:text-<?php echo $statusColor; ?>-400 rounded-full">
                                            <?php echo $displayStatus; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4"><?php echo date("M d, Y", strtotime($row['created_at'])); ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <button
                                            onclick="openEditModal('<?php echo $row['tenantID']; ?>', '<?php echo addslashes($row['shopName']); ?>', '<?php echo addslashes($row['shopAddress']); ?>', '<?php echo addslashes($row['ownerName']); ?>', '<?php echo addslashes($row['email']); ?>', '<?php echo addslashes($row['contactNumber']); ?>', '<?php echo $row['status']; ?>', '<?php echo addslashes($tenantPlanKey); ?>', '<?php echo addslashes($tenantCycleKey); ?>')"
                                            class="text-slate-400 hover:text-primary transition-colors">
                                            <span class="material-symbols-outlined">more_vert</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                            <tr id="tenantsSearchEmpty" class="hidden">
                                <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <span
                                            class="material-symbols-outlined text-4xl text-slate-300">search_off</span>
                                        <p class="font-medium">No tenants match your search</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

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

            <!-- Pending Applications Section with Search -->
            <div class="mt-8">
                <div class="mb-4">
                    <input type="text" id="pendingSearchInput" placeholder="Search pending applications..."
                        class="w-full px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
            </div>

            <div
                class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden mt-0">
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
                                <th class="px-6 py-4">Payment</th>
                                <th class="px-6 py-4">Submission Date</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pendingTableBody" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php
                            $totalPendingQuery = "SELECT COUNT(*) as total FROM owners WHERE status='Pending'";
                            $totalPendingResult = mysqli_query($conn, $totalPendingQuery);
                            $totalPendingRow = mysqli_fetch_assoc($totalPendingResult);
                            $totalPendingApps = $totalPendingRow['total'];
                            $totalPendingPages = ceil($totalPendingApps / $rowsPerPage);

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
                                    
                                    // Get payment status for this applicant
                                    $paymentInfo = getTenantPaymentStatus($conn, $pendingRow['tenantID']);
                                    $isPaid = $paymentInfo['status'] === 'paid';
                                    $paymentBadge = getPaymentStatusBadge($paymentInfo['status']);
                                    
                                    $pendingSearchHaystack = strtolower(trim((string) ($pendingRow['shopName'] ?? '') . ' ' . (string) ($pendingRow['ownerName'] ?? '') . ' ' . (string) ($pendingRow['email'] ?? '') . ' ' . (string) $tenantPlan . ' pending ' . date("M d, Y", strtotime($pendingRow['created_at']))));
                                    ?>
                                    <tr class="searchable-pending hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors"
                                        data-search="<?php echo htmlspecialchars($pendingSearchHaystack, ENT_QUOTES, 'UTF-8'); ?>">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                                                    <span class="material-symbols-outlined text-slate-400">storefront</span>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-semibold">
                                                        <?php echo htmlspecialchars($pendingRow['shopName']); ?>
                                                    </div>
                                                    <div class="text-xs text-slate-500">ID:
                                                        <?php echo $pendingRow['tenantID']; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-semibold">
                                                <?php echo htmlspecialchars($pendingRow['ownerName']); ?>
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                <?php echo htmlspecialchars($pendingRow['email']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($tenantPlan); ?></td>
                                        <td class="px-6 py-4 text-sm">
                                            <div class="flex flex-col gap-1">
                                                <?php echo $paymentBadge['badge']; ?>
                                                <?php if ($isPaid && $paymentInfo['amount']): ?>
                                                    <div class="text-xs text-slate-500">₱<?php echo number_format((float) $paymentInfo['amount'], 2); ?></div>
                                                    <div class="text-xs text-slate-500"><?php echo htmlspecialchars($paymentInfo['payment_method']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                            <?php echo date("M d, Y", strtotime($pendingRow['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end gap-2">
                                                <?php
                                                    $planBillingCycle = $pendingRow['billing_cycle'] ?? 'monthly';
                                                    $billingDivisor = getBillingCycleDivisor($planBillingCycle);
                                                    $planPrice = $subscriptionPlans[$pendingPlanKey]['monthly_price'] * $billingDivisor;
                                                ?>
                                                <button
                                                    onclick="openApplicantReview(<?php echo htmlspecialchars(json_encode($pendingRow['tenantID'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($pendingRow['ownerName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($pendingRow['shopName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($pendingRow['shopAddress'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($pendingRow['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($pendingRow['contactNumber'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenantPlan ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($planBillingCycle ?? 'monthly'), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($paymentInfo['status'] ?? 'unpaid'), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($paymentInfo['amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($paymentInfo['payment_method'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string) ($planPrice ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($pendingRow['business_permit_image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($pendingRow['valid_id_image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($pendingRow['bir_certificate_image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)"
                                                    class="px-3 py-1.5 border border-blue-200 text-blue-600 text-xs font-bold rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-colors">
                                                    <span class="flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-sm">info</span>
                                                        View Details
                                                    </span>
                                                </button>
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
                                    <td colspan="6" class="px-6 py-8 text-center text-slate-500">No pending applications
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                            <tr id="pendingSearchEmpty" class="hidden">
                                <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <span
                                            class="material-symbols-outlined text-4xl text-slate-300">search_off</span>
                                        <p class="font-medium">No pending applications match your search</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

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
                                    <option value="Suspended">Suspended</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Subscription Plan</label>
                                <select id="editSubscriptionPlan" name="subscriptionPlan" class="border rounded-lg p-3"
                                    required>
                                    <?php foreach ($subscriptionPlans as $plan): ?>
                                        <option value="<?php echo htmlspecialchars($plan['key']); ?>"
                                            data-plan-features="<?php echo htmlspecialchars(json_encode($plan['features']), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($plan['name']); ?> - PHP
                                            <?php echo number_format($plan['monthly_price'], 2); ?> / month
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="editPlanFeaturesPreview" class="mt-2 text-xs text-slate-600 space-y-1"></div>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Billing Cycle</label>
                                <select id="editBillingCycle" name="billingCycle" class="border rounded-lg p-3"
                                    required>
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

            <div id="approvalModal"
                class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
                <div
                    class="bg-white dark:bg-slate-900 w-full max-w-xl rounded-xl shadow-2xl border flex flex-col overflow-hidden">
                    <div class="px-8 py-6 border-b flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-bold">Approve Application</h2>
                            <p class="text-sm text-gray-500">Review payment and select subscription details</p>
                        </div>
                        <button onclick="closeApprovalModal()" class="text-gray-400 hover:text-black">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                    <div class="p-8 flex flex-col gap-6">
                        <input type="hidden" id="approveTenantID" name="tenantID">
                        <input type="hidden" id="approvalPaymentPaid" name="paymentPaid" value="false">
                        <input type="hidden" id="approvalSubscriptionPlan">
                        <input type="hidden" id="approvalBillingCycle">
                        
                        <!-- Payment Status Alert -->
                        <div id="paymentNotPaidWarning" class="hidden p-4 bg-amber-50 border border-amber-200 rounded-lg">
                            <div class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-amber-600 text-xl">warning</span>
                                <div>
                                    <p class="text-sm font-bold text-amber-800">Payment Required</p>
                                    <p class="text-xs text-amber-700 mt-1">This applicant has not completed payment yet. Please verify payment before approval.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div id="paymentPaidSuccess" class="hidden p-4 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-green-600 text-xl">check_circle</span>
                                <div>
                                    <p class="text-sm font-bold text-green-800">Payment Verified</p>
                                    <p class="text-xs text-green-700 mt-1" id="paymentVerificationText"></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Subscription Details -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 dark:bg-slate-800 p-6 rounded-lg">
                            <div>
                                <p class="text-xs font-bold uppercase text-gray-500">Shop Name</p>
                                <p class="text-lg font-semibold mt-2" id="approvalShopName">-</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase text-gray-500">Owner Name</p>
                                <p class="text-lg font-semibold mt-2" id="approvalOwnerName">-</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase text-gray-500">Subscription Plan</p>
                                <p class="text-lg font-semibold mt-2" id="approvalPlanDisplay">-</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase text-gray-500">Billing Cycle</p>
                                <p class="text-lg font-semibold mt-2 capitalize" id="approvalBillingCycleDisplay">-</p>
                            </div>
                        </div>
                        
                        <div class="flex gap-4">
                            <button type="button" onclick="closeApprovalModal()"
                                class="flex-1 border rounded-lg py-3">Cancel</button>
                            <button onclick="submitApproval()"
                                class="flex-1 bg-red-600 text-white rounded-lg py-3 disabled:opacity-50 disabled:cursor-not-allowed"
                                id="approvalSubmitBtn">Approve & Activate</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Applicant Details Review Modal -->
            <div id="applicantReviewModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
                <div class="bg-white dark:bg-slate-900 w-full max-w-2xl rounded-xl shadow-2xl border flex flex-col overflow-hidden max-h-[90vh] overflow-y-auto">
                    <div class="px-8 py-6 border-b flex justify-between items-center sticky top-0 bg-white dark:bg-slate-900">
                        <div>
                            <h2 class="text-xl font-bold">Application Review</h2>
                            <p class="text-sm text-gray-500">Review applicant details before approval</p>
                        </div>
                        <button onclick="closeApplicantReviewModal()" class="text-gray-400 hover:text-black">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                    
                    <div class="p-8 flex flex-col gap-6">
                        <!-- Applicant Information Section -->
                        <div>
                            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-red-600">person</span>
                                Applicant Information
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50 dark:bg-slate-800 p-6 rounded-lg">
                                <div>
                                    <p class="text-xs font-bold uppercase text-gray-500">Owner Name</p>
                                    <p class="text-lg font-semibold mt-2" id="reviewOwnerName">-</p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold uppercase text-gray-500">Shop Name</p>
                                    <p class="text-lg font-semibold mt-2" id="reviewShopName">-</p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold uppercase text-gray-500">Email Address</p>
                                    <p class="text-sm mt-2 break-all" id="reviewEmail">-</p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold uppercase text-gray-500">Contact Number</p>
                                    <p class="text-sm mt-2" id="reviewContactNumber">-</p>
                                </div>
                            </div>
                        </div>

                        <!-- Shop Information Section -->
                        <div>
                            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-blue-600">location_on</span>
                                Shop Information
                            </h3>
                            <div class="bg-slate-50 dark:bg-slate-800 p-6 rounded-lg">
                                <p class="text-xs font-bold uppercase text-gray-500">Shop Address</p>
                                <p class="text-sm mt-2" id="reviewShopAddress">-</p>
                            </div>
                        </div>

                        <!-- Subscription Details Section -->
                        <div>
                            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-green-600">card_membership</span>
                                Subscription Details
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50 dark:bg-slate-800 p-6 rounded-lg">
                                <div>
                                    <p class="text-xs font-bold uppercase text-gray-500">Selected Plan</p>
                                    <p class="text-lg font-semibold mt-2" id="reviewPlan">-</p>
                                    <p class="text-xs text-gray-600 mt-1" id="reviewPlanPrice">PHP 0.00</p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold uppercase text-gray-500">Billing Cycle</p>
                                    <p class="text-lg font-semibold mt-2 capitalize" id="reviewBillingCycle">-</p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold uppercase text-gray-500">Application Date</p>
                                    <p class="text-lg font-semibold mt-2" id="reviewApplicationDate">-</p>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Status Section -->
                        <div>
                            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-purple-600">payments</span>
                                Payment Status
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50 dark:bg-slate-800 p-6 rounded-lg">
                                <div>
                                    <p class="text-xs font-bold uppercase text-gray-500">Payment Status</p>
                                    <div class="mt-2" id="reviewPaymentBadge">-</div>
                                </div>
                                <div>
                                    <p class="text-xs font-bold uppercase text-gray-500">Payment Amount</p>
                                    <p class="text-lg font-semibold mt-2" id="reviewPaymentAmount">-</p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold uppercase text-gray-500">Payment Method</p>
                                    <p class="text-lg font-semibold mt-2" id="reviewPaymentMethod">-</p>
                                </div>
                            </div>
                        </div>

                        <!-- Uploaded Documents Section -->
                        <div>
                            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-red-600">folder_open</span>
                                Uploaded Documents
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50 dark:bg-slate-800">
                                    <p class="text-xs font-bold uppercase text-gray-500 mb-3">Business Permit</p>
                                    <div id="reviewBusinessPermit">-</div>
                                </div>
                                <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50 dark:bg-slate-800">
                                    <p class="text-xs font-bold uppercase text-gray-500 mb-3">Valid Owner ID</p>
                                    <div id="reviewValidId">-</div>
                                </div>
                                <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50 dark:bg-slate-800">
                                    <p class="text-xs font-bold uppercase text-gray-500 mb-3">BIR Certificate</p>
                                    <div id="reviewBirCertificate">-</div>
                                </div>
                            </div>
                        </div>
                        <!-- Action Buttons -->
                        <div class="flex gap-4 pt-4 border-t">
                            <button type="button" onclick="closeApplicantReviewModal()"
                                class="flex-1 border rounded-lg py-3 font-semibold hover:bg-slate-50">
                                Close
                            </button>
                            <button onclick="openApprovalFromReview()" 
                                class="flex-1 bg-green-600 text-white rounded-lg py-3 font-semibold hover:bg-green-700">
                                <span class="flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    Reviewed
                                </span>
                            </button>
                            <button id="rejectReviewBtn" onclick="rejectTenantFromReview()"
                                class="flex-1 border border-red-200 text-red-600 rounded-lg py-3 font-semibold hover:bg-red-50">
                                <span class="flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined">cancel</span>
                                    Reject
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

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
                                <input name="shopName" class="border rounded-lg p-3" placeholder="Modern Boutique"
                                    required>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Shop Address</label>
                                <input name="shopAddress" class="border rounded-lg p-3" placeholder="123 Main Street"
                                    required>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Owner Name</label>
                                <input name="ownerName" class="border rounded-lg p-3" placeholder="Juan Dela Cruz"
                                    required>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Email</label>
                                <input name="email" type="email" class="border rounded-lg p-3"
                                    placeholder="owner@email.com" required>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Username</label>
                                <input id="usernameInput" name="username" class="border rounded-lg p-3 bg-slate-100"
                                    placeholder="Auto-generated username" readonly required>
                            </div>
                            <div class="flex flex-col gap-2 md:col-span-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Contact Number</label>
                                <input name="contactNumber" class="border rounded-lg p-3" placeholder="09123456789">
                            </div>
                            <div class="flex flex-col gap-2 md:col-span-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Temporary Password</label>
                                <div class="flex gap-2">
                                    <input id="tempPasswordInput" name="tempPassword" type="text"
                                        class="border rounded-lg p-3 flex-1" placeholder="Auto-generated password"
                                        readonly required>
                                    <button type="button" onclick="regenerateTempPassword()"
                                        class="px-3 py-2 border rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-50">Regenerate</button>
                                </div>
                                <p class="text-[11px] text-slate-500">Password is auto-generated. Share it securely with
                                    the tenant.</p>
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
        </div>
    </main>

    <script>
            (function setupGlobalSearchFilters() {
                const searchInput = document.getElementById('globalSearchInput');
                const scopeButtons = Array.from(document.querySelectorAll('.tenant-search-scope-btn'));
                const filtersToggle = document.getElementById('filtersToggle');
                const filtersDropdown = document.getElementById('filtersDropdown');
                const tenantRows = Array.from(document.querySelectorAll('.searchable-tenant'));
                const pendingRows = Array.from(document.querySelectorAll('.searchable-pending'));
                const tenantsEmpty = document.getElementById('tenantsSearchEmpty');
                const pendingEmpty = document.getElementById('pendingSearchEmpty');

                if (!searchInput || scopeButtons.length === 0) {
                    return;
                }

                let currentScope = 'all';

                function updateScopeButtonStyles() {
                    scopeButtons.forEach(function (button) {
                        const isActive = (button.dataset.scope || 'all') === currentScope;
                        button.className = isActive
                            ? 'tenant-search-scope-btn px-3 py-1.5 rounded-full text-sm font-medium bg-primary text-white'
                            : 'tenant-search-scope-btn px-3 py-1.5 rounded-full text-sm font-medium text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700';
                    });
                }

                function applySearch() {
                    const query = searchInput.value.trim().toLowerCase();
                    let visibleTenants = 0;
                    let visiblePending = 0;

                    tenantRows.forEach(function (row) {
                        const rowData = (row.dataset.search || '').toLowerCase();
                        const status = (row.dataset.status || '').toLowerCase();
                        
                        const matchesQuery = query === '' || rowData.includes(query);
                        
                        let matchesStatus = true;
                        if (currentScope !== 'all') {
                            if (currentScope === 'inactive') {
                                matchesStatus = status === 'inactive' || status === 'rejected';
                            } else {
                                matchesStatus = status === currentScope;
                            }
                        }
                        
                        const visible = matchesQuery && matchesStatus;
                        row.classList.toggle('hidden', !visible);
                        if (visible) visibleTenants++;
                    });

                    pendingRows.forEach(function (row) {
                        const rowData = (row.dataset.search || '').toLowerCase();
                        const matches = query === '' || rowData.includes(query);
                        row.classList.toggle('hidden', !matches);
                        if (matches) visiblePending++;
                    });

                    if (tenantsEmpty) {
                        const shouldShow = tenantRows.length > 0 && visibleTenants === 0;
                        tenantsEmpty.classList.toggle('hidden', !shouldShow);
                    }

                    if (pendingEmpty) {
                        const shouldShow = pendingRows.length > 0 && visiblePending === 0;
                        pendingEmpty.classList.toggle('hidden', !shouldShow);
                    }
                }

                scopeButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        currentScope = button.dataset.scope || 'all';
                        updateScopeButtonStyles();
                        applySearch();
                        if (filtersDropdown) {
                            filtersDropdown.classList.add('hidden');
                        }
                    });
                });

                if (filtersToggle && filtersDropdown) {
                    filtersToggle.addEventListener('click', function () {
                        filtersDropdown.classList.toggle('hidden');
                    });

                    document.addEventListener('click', function (event) {
                        if (!filtersDropdown.classList.contains('hidden') && !event.target.closest('#filtersDropdown') && !event.target.closest('#filtersToggle')) {
                            filtersDropdown.classList.add('hidden');
                        }
                    });
                }

                const pendingSearchInput = document.getElementById('pendingSearchInput');
                if (pendingSearchInput) {
                    pendingSearchInput.addEventListener('input', function() {
                        const query = this.value.trim().toLowerCase();
                        pendingRows.forEach(function (row) {
                            const rowData = (row.dataset.search || '').toLowerCase();
                            const matches = query === '' || rowData.includes(query);
                            row.classList.toggle('hidden', !matches);
                        });
                        if (pendingEmpty) {
                            const visibleCount = pendingRows.filter(row => !row.classList.contains('hidden')).length;
                            const shouldShow = pendingRows.length > 0 && visibleCount === 0;
                            pendingEmpty.classList.toggle('hidden', !shouldShow);
                        }
                    });
                }

                searchInput.addEventListener('input', applySearch);

                updateScopeButtonStyles();
                applySearch();
            })();

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
                return '<div class="flex items-start gap-1.5"><span class="material-symbols-outlined text-red-500 text-[14px]">check_circle</span><span>' + text + '</span></div>';
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
            if (!input) return;
            input.value = generateTempPassword(12);
        }

        function openModal() {
            document.getElementById("tenantModal").classList.remove("hidden");
            regenerateTempPassword();
        }

        function closeModal() {
            document.getElementById("tenantModal").classList.add("hidden");
        }

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

        function approveTenant(tenantID, subscriptionPlan = '', isPaid = false, billingCycle = 'monthly', shopName = '', ownerName = '') {
            document.getElementById("approveTenantID").value = tenantID;
            document.getElementById("approvalPaymentPaid").value = isPaid ? 'true' : 'false';
            document.getElementById("approvalSubscriptionPlan").value = subscriptionPlan || '';
            document.getElementById("approvalBillingCycle").value = billingCycle || 'monthly';
            
            // Display static applicant details
            document.getElementById("approvalShopName").textContent = shopName || '-';
            document.getElementById("approvalOwnerName").textContent = ownerName || '-';
            document.getElementById("approvalPlanDisplay").textContent = subscriptionPlan || '-';
            document.getElementById("approvalBillingCycleDisplay").textContent = billingCycle || '-';
            
            // Update payment status display
            const paymentNotPaidWarning = document.getElementById("paymentNotPaidWarning");
            const paymentPaidSuccess = document.getElementById("paymentPaidSuccess");
            const approvalSubmitBtn = document.getElementById("approvalSubmitBtn");
            
            if (isPaid) {
                paymentNotPaidWarning.classList.add("hidden");
                paymentPaidSuccess.classList.remove("hidden");
                document.getElementById("paymentVerificationText").textContent = "Payment confirmed. You may proceed with approval.";
                approvalSubmitBtn.disabled = false;
            } else {
                paymentNotPaidWarning.classList.remove("hidden");
                paymentPaidSuccess.classList.add("hidden");
                approvalSubmitBtn.disabled = true;
            }
            
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
            form.action = '';

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
            form.action = '';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'tenantID';
            idInput.value = tenantID;

            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = 'Rejected';

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
        // Applicant Review Modal Functions
        function renderDocumentPreview(containerId, filePath, label) {
            const container = document.getElementById(containerId);
            if (!container) return;

            if (!filePath || String(filePath).trim() === "") {
                container.innerHTML = "<div class=\"h-32 rounded-lg border border-dashed border-slate-300 flex items-center justify-center text-xs text-slate-400 text-center px-3\">No document uploaded</div>";
                return;
            }

            const cleanPath = String(filePath).replace(/^\/+/, "");
            const imageUrl = cleanPath.startsWith("http://") || cleanPath.startsWith("https://") ? cleanPath : "../" + cleanPath;

            container.innerHTML = `
                <a href="${imageUrl}" target="_blank" rel="noopener" class="block group">
                    <img src="${imageUrl}"
                         alt="${label}"
                         class="w-full h-32 object-cover rounded-lg border border-slate-200 dark:border-slate-700 mb-3 bg-white group-hover:opacity-80 transition"
                         onerror="this.style.display=\"none\"; this.nextElementSibling.classList.remove(\"hidden\");" />
                    <div class="hidden h-32 rounded-lg border border-dashed border-slate-300 mb-3 items-center justify-center text-xs text-slate-400 text-center px-3">
                        Preview unavailable. Click view to open file.
                    </div>
                    <span class="inline-flex items-center justify-center w-full px-3 py-2 bg-red-600 text-white text-xs font-bold rounded-lg hover:bg-red-700 transition-colors">
                        View Document
                    </span>
                </a>
            `;
        }
        function openApplicantReview(tenantID, ownerName, shopName, shopAddress, email, contactNumber, subscriptionPlan, billingCycle, paymentStatus, paymentAmount, paymentMethod, planPrice, businessPermitImage, validIdImage, birCertificateImage) {
            // Set applicant details
            document.getElementById('reviewOwnerName').textContent = ownerName || '-';
            document.getElementById('reviewShopName').textContent = shopName || '-';
            document.getElementById('reviewEmail').textContent = email || '-';
            document.getElementById('reviewContactNumber').textContent = contactNumber || '-';
            document.getElementById('reviewShopAddress').textContent = shopAddress || '-';
            document.getElementById('reviewPlan').textContent = subscriptionPlan || '-';
            document.getElementById('reviewBillingCycle').textContent = (billingCycle || '-').toLowerCase();
            document.getElementById('reviewApplicationDate').textContent = new Date().toLocaleDateString();
            
            // Set plan price
            document.getElementById('reviewPlanPrice').textContent = planPrice ? '₱' + parseFloat(planPrice).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : 'PHP 0.00';
            
            // Set payment details
            document.getElementById('reviewPaymentAmount').textContent = paymentAmount ? '₱' + parseFloat(paymentAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
            document.getElementById('reviewPaymentMethod').textContent = paymentMethod || '-';
            
            // Set payment status badge
            const paymentBadges = {
                'paid': '<span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-md inline-block">✓ Paid</span>',
                'pending': '<span class="px-3 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded-md inline-block">⏱ Pending</span>',
                'failed': '<span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-md inline-block">✗ Failed</span>',
                'unpaid': '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-bold rounded-md inline-block">- No Payment</span>'
            };
            document.getElementById('reviewPaymentBadge').innerHTML = paymentBadges[(paymentStatus || 'unpaid').toLowerCase()] || paymentBadges['unpaid'];

            renderDocumentPreview('reviewBusinessPermit', businessPermitImage, 'Business Permit');
            renderDocumentPreview('reviewValidId', validIdImage, 'Valid Owner ID');
            renderDocumentPreview('reviewBirCertificate', birCertificateImage, 'BIR Certificate');
            
            // Store tenantID and applicant details for later use
            document.getElementById('applicantReviewModal').dataset.tenantID = tenantID;
            document.getElementById('applicantReviewModal').dataset.subscriptionPlan = subscriptionPlan || '';
            document.getElementById('applicantReviewModal').dataset.billingCycle = billingCycle || 'monthly';
            document.getElementById('applicantReviewModal').dataset.shopName = shopName || '';
            document.getElementById('applicantReviewModal').dataset.ownerName = ownerName || '';
            document.getElementById('applicantReviewModal').dataset.isPaid = (paymentStatus || 'unpaid').toLowerCase() === 'paid' ? 'true' : 'false';
            
            // Open the modal
            document.getElementById("applicantReviewModal").classList.remove("hidden");
        }

        function closeApplicantReviewModal() {
            document.getElementById("applicantReviewModal").classList.add("hidden");
        }

        function openApprovalFromReview() {
            const modal = document.getElementById("applicantReviewModal");
            const tenantID = modal.dataset.tenantID;
            const subscriptionPlan = modal.dataset.subscriptionPlan;
            const billingCycle = modal.dataset.billingCycle;
            const shopName = modal.dataset.shopName;
            const ownerName = modal.dataset.ownerName;
            const isPaid = modal.dataset.isPaid === 'true';
            
            // Close review modal and open approval modal
            closeApplicantReviewModal();
            approveTenant(tenantID, subscriptionPlan, isPaid, billingCycle, shopName, ownerName);
        }

        function rejectTenantFromReview() {
            const modal = document.getElementById("applicantReviewModal");
            const tenantID = modal.dataset.tenantID;
            
            if (confirm('Are you sure you want to reject this application?')) {
                closeApplicantReviewModal();
                rejectTenant(tenantID);
            }
        }
    </script>

    <script>
        function showNotification() {
            const notif = document.getElementById('statusNotification');
            if (!notif) return;
            notif.classList.remove('translate-y-20', 'opacity-0');
            notif.classList.add('translate-y-0', 'opacity-100');

            setTimeout(() => closeNotification(), 5000);
        }

        function closeNotification() {
            const notif = document.getElementById('statusNotification');
            if (!notif) return;
            notif.classList.add('translate-y-20', 'opacity-0');
            notif.classList.remove('translate-y-0', 'opacity-100');
        }

        <?php if ($noticeTitle !== ''): ?>
            window.onload = function () {
                showNotification();
            }
        <?php endif; ?>
    </script>

    <script>
        const shopNameInput = document.querySelector('input[name="shopName"]');
        const usernameInput = document.getElementById('usernameInput');

        if (shopNameInput && usernameInput) {
            shopNameInput.addEventListener('input', function () {
                let username = shopNameInput.value.toLowerCase()
                    .replace(/[^a-z0-9]/g, '');

                usernameInput.value = username || 'user';
            });
        }
    </script>
</body>

</html>