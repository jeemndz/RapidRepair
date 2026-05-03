<?php
session_start();
include __DIR__ . "/../session_security.php";

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

if (!isset($_SESSION['superadmin_id'])) {
    header("Location: superaddlogin.php");
    exit();
}

include __DIR__ . "/../db.php";
require_once __DIR__ . "/../log_helper.php";
require_once __DIR__ . "/../PHPMailer/src/Exception.php";
require_once __DIR__ . "/../PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/../PHPMailer/src/SMTP.php";

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

function initials($name)
{
    $name = trim((string)$name);
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

function subscriptionsColumnExists($conn, $columnName)
{
    $safeColumn = mysqli_real_escape_string($conn, $columnName);
    $checkSql = "SHOW COLUMNS FROM subscription_plans LIKE '$safeColumn'";
    $check = mysqli_query($conn, $checkSql);
    return $check && mysqli_num_rows($check) > 0;
}

function generatePlanCode($conn, $planName)
{
    $code = strtolower(trim($planName));
    $code = preg_replace('/[^a-z0-9]+/', '-', $code);
    $code = trim($code, '-');
    if ($code === '') {
        $code = 'plan';
    }

    $originalCode = $code;
    $counter = 1;

    while (true) {
        $safeCode = mysqli_real_escape_string($conn, $code);
        $exists = mysqli_query($conn, "SELECT plan_id FROM subscription_plans WHERE plan_code='$safeCode' LIMIT 1");
        if (!$exists || mysqli_num_rows($exists) === 0) {
            break;
        }
        $code = $originalCode . '-' . $counter;
        $counter++;
    }

    return $code;
}

function getPlanFeaturesJsonFromPost()
{
    $planFeaturesRaw = $_POST['plan_features'] ?? '[]';
    $decodedFeatures = json_decode($planFeaturesRaw, true);
    if (!is_array($decodedFeatures)) {
        $decodedFeatures = [];
    }

    $cleanFeatures = [];
    foreach ($decodedFeatures as $feature) {
        $featureText = trim((string) $feature);
        if ($featureText !== '') {
            $cleanFeatures[] = $featureText;
        }
    }

    return json_encode(array_values($cleanFeatures));
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

// Billing Notification Functions
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

    return $mailTransports;
}

function sendBillingReminderEmail($ownerRow, $planName, $billingCycle, $nextBillingDate, $planPrice)
{
    $email = trim((string) ($ownerRow['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => false, 'reason' => 'invalid_email'];
    }

    $ownerName = trim((string) ($ownerRow['ownerName'] ?? 'Tenant Owner'));
    $shopName = trim((string) ($ownerRow['shopName'] ?? 'Your Shop'));
    $loginSlug = trim((string) ($ownerRow['login_slug'] ?? ''));
    $baseURL = rtrim((string) (getenv('APP_BASE_URL') ?: 'https://rapidrepair-gygpcbczgyg0czek.southeastasia-01.azurewebsites.net'), '/');
    $loginLink = $loginSlug !== ''
        ? $baseURL . '/tenant/tenantlogin.php?shop=' . urlencode($loginSlug)
        : $baseURL . '/tenant/tenantlogin.php';

    $safeOwnerName = htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8');
    $safeShopName = htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safePlanName = htmlspecialchars((string) $planName, ENT_QUOTES, 'UTF-8');
    $safeBillingCycle = htmlspecialchars(ucfirst((string) $billingCycle), ENT_QUOTES, 'UTF-8');
    $safeNextBillingDate = htmlspecialchars((string) $nextBillingDate, ENT_QUOTES, 'UTF-8');
    $safePlanPrice = htmlspecialchars(number_format((float) $planPrice, 2), ENT_QUOTES, 'UTF-8');
    $safeLoginLink = htmlspecialchars($loginLink, ENT_QUOTES, 'UTF-8');

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isHTML(true);
    $mail->Subject = 'Billing Reminder: Your RapidRepair Subscription Renewal is Coming';
    $mail->Body = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>RapidRepair Billing Reminder</title>
        </head>
        <body style='margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;'>
            <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='background:#f1f5f9;padding:24px 0;'>
                <tr>
                    <td align='center'>
                        <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='max-width:640px;background:#ffffff;border:1px solid #dbe1ea;border-radius:14px;overflow:hidden;'>
                            <tr>
                                <td style='padding:22px 24px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#ffffff;'>
                                    <h1 style='margin:0;font-size:26px;line-height:32px;font-weight:700;color:#ffffff;'>⏰ Billing Reminder</h1>
                                    <p style='margin:6px 0 0 0;font-size:14px;line-height:20px;'>Your subscription renewal is coming up</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:24px;'>
                                    <p style='margin:0 0 12px 0;font-size:24px;line-height:30px;font-weight:700;color:#0f172a;'>Hello {$safeOwnerName},</p>
                                    <p style='margin:0 0 18px 0;font-size:16px;line-height:24px;color:#1e293b;'>
                                        This is a reminder that your <strong>{$safeShopName}</strong> subscription will be renewed soon.
                                    </p>

                                    <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border:1px solid #fbbf24;border-radius:12px;background:#fffbeb;margin:0 0 18px 0;'>
                                        <tr><td style='padding:14px 16px;font-size:14px;color:#92400e;'><strong>Plan:</strong> {$safePlanName}</td></tr>
                                        <tr><td style='padding:0 16px 14px 16px;font-size:14px;color:#92400e;'><strong>Billing Cycle:</strong> {$safeBillingCycle}</td></tr>
                                        <tr><td style='padding:0 16px 14px 16px;font-size:14px;color:#92400e;'><strong>🔔 Next Billing Date:</strong> {$safeNextBillingDate}</td></tr>
                                        <tr><td style='padding:0 16px 16px 16px;font-size:14px;color:#92400e;'><strong>Amount Due:</strong> PHP {$safePlanPrice}</td></tr>
                                    </table>

                                    <p style='margin:0 0 18px 0;font-size:15px;line-height:22px;color:#0f172a;'>
                                        Please ensure your payment method is current. Your service will automatically renew on the billing date.
                                    </p>

                                    <p style='margin:0 0 18px 0;'>
                                        <a href='{$safeLoginLink}' style='display:inline-block;padding:12px 24px;background:#b91c1c;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;'>Access Your Account</a>
                                    </p>
                                    
                                    <p style='margin:0 0 0 0;font-size:12px;line-height:18px;color:#666666;'>
                                        If you have any questions, please contact our support team.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:14px 24px;border-top:1px solid #e5e7eb;background:#f8fafc;font-size:11px;line-height:18px;color:#64748b;'>
                                    This is an automated billing reminder from RapidRepair System.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
    ";
    $mail->AltBody = "Billing Reminder: Your RapidRepair Subscription Renewal\n\n"
        . "Hello {$ownerName},\n\n"
        . "This is a reminder that your {$shopName} subscription will be renewed soon.\n\n"
        . "=== SUBSCRIPTION DETAILS ===\n"
        . "Plan: {$planName}\n"
        . "Billing Cycle: " . ucfirst((string) $billingCycle) . "\n"
        . "Next Billing Date: {$nextBillingDate}\n"
        . "Amount Due: PHP " . number_format((float) $planPrice, 2) . "\n\n"
        . "Please ensure your payment method is current.\n\n"
        . "Account Login: {$loginLink}\n";

    $mailTransports = buildMailTransports();
    $lastError = '';

    foreach ($mailTransports as $transport) {
        try {
            $mail->isSMTP();
            $mail->Host = $transport['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $transport['username'];
            $mail->Password = $transport['password'];
            $mail->Port = $transport['port'];

            if ($transport['encryption'] === 'ssl' || $transport['encryption'] === 'smtps') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($transport['encryption'] === 'tls' || $transport['encryption'] === 'starttls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            if (!empty($transport['from_address'])) {
                $mail->setFrom($transport['from_address'], $transport['from_name'] ?? '');
            }
            if (!empty($transport['reply_to_address'])) {
                $mail->addReplyTo($transport['reply_to_address'], $transport['reply_to_name'] ?? '');
            }

            $mail->clearAddresses();
            $mail->addAddress($email, $ownerName);
            $mail->send();

            return ['sent' => true, 'reason' => ''];
        } catch (Exception $e) {
            $lastError = $e->getMessage();
            $mail->clearAddresses();
            continue;
        }
    }

    return ['sent' => false, 'reason' => $lastError];
}

function checkAndSendBillingNotifications($conn, $daysBeforeBilling = 7)
{
    $billingNotificationCheck = date('Y-m-d', strtotime("+{$daysBeforeBilling} days"));
    $notificationsSent = 0;
    $notificationsFailed = 0;

    // Get all active subscriptions with upcoming billing dates
    $query = "SELECT 
                    o.tenantID, 
                    o.ownerName, 
                    o.shopName, 
                    o.email, 
                    o.login_slug,
                    o.subscription_plan, 
                    o.billing_cycle, 
                    o.plan_price,
                    o.next_billing_date,
                    o.billing_notification_sent
              FROM owners o
              WHERE o.status = 'Active' 
              AND o.subscription_plan IS NOT NULL 
              AND o.next_billing_date IS NOT NULL
              AND DATE(o.next_billing_date) <= DATE('{$billingNotificationCheck}')
              AND (o.billing_notification_sent IS NULL OR o.billing_notification_sent = 0)";

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $emailResult = sendBillingReminderEmail(
                $row,
                $row['subscription_plan'],
                $row['billing_cycle'],
                $row['next_billing_date'],
                $row['plan_price']
            );

            if ($emailResult['sent']) {
                // Mark notification as sent
                $tenantID = (int) $row['tenantID'];
                $updateQuery = "UPDATE owners SET billing_notification_sent = 1 WHERE tenantID = {$tenantID} LIMIT 1";
                if (mysqli_query($conn, $updateQuery)) {
                    $notificationsSent++;
                    // Log the event
                    log_event($conn, "Send Billing Notification", "Billing", $tenantID, "Billing reminder sent for next billing date: " . $row['next_billing_date']);
                }
            } else {
                $notificationsFailed++;
            }
        }
    }

    return [
        'sent' => $notificationsSent,
        'failed' => $notificationsFailed
    ];
}

function sendAccountSuspensionEmail($ownerRow, $planName, $billingCycle, $nextBillingDate, $planPrice)
{
    $email = trim((string) ($ownerRow['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => false, 'reason' => 'invalid_email'];
    }

    $ownerName = trim((string) ($ownerRow['ownerName'] ?? 'Tenant Owner'));
    $shopName = trim((string) ($ownerRow['shopName'] ?? 'Your Shop'));
    $loginSlug = trim((string) ($ownerRow['login_slug'] ?? ''));
    $baseURL = rtrim((string) (getenv('APP_BASE_URL') ?: 'https://rapidrepair-gygpcbczgyg0czek.southeastasia-01.azurewebsites.net'), '/');
    $loginLink = $loginSlug !== ''
        ? $baseURL . '/tenant/tenantlogin.php?shop=' . urlencode($loginSlug)
        : $baseURL . '/tenant/tenantlogin.php';

    $safeOwnerName = htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8');
    $safeShopName = htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safePlanName = htmlspecialchars((string) $planName, ENT_QUOTES, 'UTF-8');
    $safeBillingCycle = htmlspecialchars(ucfirst((string) $billingCycle), ENT_QUOTES, 'UTF-8');
    $safeNextBillingDate = htmlspecialchars((string) $nextBillingDate, ENT_QUOTES, 'UTF-8');
    $safePlanPrice = htmlspecialchars(number_format((float) $planPrice, 2), ENT_QUOTES, 'UTF-8');
    $safeLoginLink = htmlspecialchars($loginLink, ENT_QUOTES, 'UTF-8');

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isHTML(true);
    $mail->Subject = '⚠️ URGENT: Your RapidRepair Account Has Been Suspended Due to Unpaid Invoice';
    $mail->Body = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>RapidRepair Account Suspended</title>
        </head>
        <body style='margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;'>
            <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='background:#f1f5f9;padding:24px 0;'>
                <tr>
                    <td align='center'>
                        <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='max-width:640px;background:#ffffff;border:1px solid #dbe1ea;border-radius:14px;overflow:hidden;'>
                            <tr>
                                <td style='padding:22px 24px;background:linear-gradient(135deg,#dc2626,#991b1b);color:#ffffff;'>
                                    <h1 style='margin:0;font-size:26px;line-height:32px;font-weight:700;color:#ffffff;'>⚠️ Account Suspended</h1>
                                    <p style='margin:6px 0 0 0;font-size:14px;line-height:20px;'>Immediate action required to restore service</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:24px;'>
                                    <p style='margin:0 0 12px 0;font-size:24px;line-height:30px;font-weight:700;color:#0f172a;'>Hello {$safeOwnerName},</p>
                                    <p style='margin:0 0 18px 0;font-size:16px;line-height:24px;color:#1e293b;'>
                                        <strong style='color:#dc2626;'>Your {$safeShopName} account has been suspended</strong> due to non-payment on the invoice below.
                                    </p>

                                    <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border:1px solid #dc2626;border-radius:12px;background:#fee2e2;margin:0 0 18px 0;'>
                                        <tr><td style='padding:14px 16px;font-size:14px;color:#991b1b;'><strong>Plan:</strong> {$safePlanName}</td></tr>
                                        <tr><td style='padding:0 16px 14px 16px;font-size:14px;color:#991b1b;'><strong>Billing Cycle:</strong> {$safeBillingCycle}</td></tr>
                                        <tr><td style='padding:0 16px 14px 16px;font-size:14px;color:#991b1b;'><strong>⚠️ Billing Date:</strong> {$safeNextBillingDate}</td></tr>
                                        <tr><td style='padding:0 16px 16px 16px;font-size:14px;color:#991b1b;'><strong>Amount Overdue:</strong> PHP {$safePlanPrice}</td></tr>
                                    </table>

                                    <p style='margin:0 0 18px 0;font-size:15px;line-height:22px;color:#0f172a;'>
                                        <strong>Your service is now temporarily unavailable.</strong> To restore your account and regain access to your RapidRepair system, please settle the outstanding payment immediately.
                                    </p>

                                    <p style='margin:0 0 18px 0;font-size:13px;line-height:20px;color:#dc2626;background:#fee2e2;padding:12px;border-radius:8px;'>
                                        ⏰ <strong>Action Required:</strong> Please complete payment within 48 hours to avoid permanent account termination.
                                    </p>

                                    <p style='margin:0 0 18px 0;'>
                                        <a href='{$safeLoginLink}' style='display:inline-block;padding:12px 24px;background:#dc2626;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;'>Settle Payment & Restore Account</a>
                                    </p>
                                    
                                    <p style='margin:0 0 0 0;font-size:12px;line-height:18px;color:#666666;'>
                                        <strong>Questions?</strong> Contact our billing support team for assistance with payment.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:14px 24px;border-top:1px solid #e5e7eb;background:#f8fafc;font-size:11px;line-height:18px;color:#64748b;'>
                                    This is an automated message from RapidRepair System regarding your suspended account.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
    ";
    $mail->AltBody = "URGENT: Your RapidRepair Account Has Been Suspended\n\n"
        . "Hello {$ownerName},\n\n"
        . "Your {$shopName} account has been suspended due to non-payment on the invoice below.\n\n"
        . "=== OVERDUE INVOICE DETAILS ===\n"
        . "Plan: {$planName}\n"
        . "Billing Cycle: " . ucfirst((string) $billingCycle) . "\n"
        . "Billing Date: {$nextBillingDate}\n"
        . "Amount Overdue: PHP " . number_format((float) $planPrice, 2) . "\n\n"
        . "Your service is now temporarily unavailable.\n\n"
        . "To restore your account and regain access, please settle the outstanding payment immediately.\n\n"
        . "⏰ URGENT: Please complete payment within 48 hours to avoid permanent account termination.\n\n"
        . "Account Login: {$loginLink}\n";

    $mailTransports = buildMailTransports();
    $lastError = '';

    foreach ($mailTransports as $transport) {
        try {
            $mail->isSMTP();
            $mail->Host = $transport['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $transport['username'];
            $mail->Password = $transport['password'];
            $mail->Port = $transport['port'];

            if ($transport['encryption'] === 'ssl' || $transport['encryption'] === 'smtps') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($transport['encryption'] === 'tls' || $transport['encryption'] === 'starttls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            if (!empty($transport['from_address'])) {
                $mail->setFrom($transport['from_address'], $transport['from_name'] ?? '');
            }
            if (!empty($transport['reply_to_address'])) {
                $mail->addReplyTo($transport['reply_to_address'], $transport['reply_to_name'] ?? '');
            }

            $mail->clearAddresses();
            $mail->addAddress($email, $ownerName);
            $mail->send();

            return ['sent' => true, 'reason' => ''];
        } catch (Exception $e) {
            $lastError = $e->getMessage();
            $mail->clearAddresses();
            continue;
        }
    }

    return ['sent' => false, 'reason' => $lastError];
}

function checkAndSuspendUnpaidAccounts($conn)
{
    $today = date('Y-m-d');
    $accountsSuspended = 0;
    $suspensionsFailed = 0;

    // Get all Active accounts with overdue billing (next_billing_date has passed and no paid payment after that date)
    $query = "SELECT 
                    o.tenantID, 
                    o.ownerName, 
                    o.shopName, 
                    o.email, 
                    o.login_slug,
                    o.subscription_plan, 
                    o.billing_cycle, 
                    o.plan_price,
                    o.next_billing_date
              FROM owners o
              LEFT JOIN subscription_payments sp ON o.tenantID = sp.tenantID 
                AND sp.payment_status = 'Paid' 
                AND DATE(sp.paid_at) >= DATE(o.next_billing_date)
              WHERE o.status = 'Active' 
              AND o.subscription_plan IS NOT NULL 
              AND o.next_billing_date IS NOT NULL
              AND DATE(o.next_billing_date) < DATE('{$today}')
              AND sp.payment_id IS NULL";

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $tenantID = (int) $row['tenantID'];
            $ownerName = $row['ownerName'];
            $shopName = $row['shopName'];
            
            // Send suspension notification email
            $emailResult = sendAccountSuspensionEmail(
                $row,
                $row['subscription_plan'],
                $row['billing_cycle'],
                $row['next_billing_date'],
                $row['plan_price']
            );

            // Suspend the account regardless of email success
            $updateQuery = "UPDATE owners SET status = 'Suspended' WHERE tenantID = {$tenantID} LIMIT 1";
            if (mysqli_query($conn, $updateQuery)) {
                $accountsSuspended++;
                
                // Log the suspension event
                log_event(
                    $conn, 
                    "Suspend Account - Unpaid Invoice", 
                    "Account", 
                    $tenantID, 
                    "Account suspended due to unpaid billing. Shop: {$shopName}, Owner: {$ownerName}, Overdue Date: " . $row['next_billing_date'] . ", Amount: PHP " . number_format((float) $row['plan_price'], 2)
                );

                // Update billing notification flag to 0 so reminder can be sent again if reactivated
                mysqli_query($conn, "UPDATE owners SET billing_notification_sent = 0 WHERE tenantID = {$tenantID} LIMIT 1");
            } else {
                $suspensionsFailed++;
            }
        }
    }

    return [
        'suspended' => $accountsSuspended,
        'failed' => $suspensionsFailed
    ];
}

$hasPlanIdColumn = subscriptionsColumnExists($conn, 'plan_id');
$hasPlanCodeColumn = subscriptionsColumnExists($conn, 'plan_code');
$hasPlanNameColumn = subscriptionsColumnExists($conn, 'plan_name');
$hasMonthlyPriceColumn = subscriptionsColumnExists($conn, 'monthly_price');
$hasPlanFeaturesColumn = subscriptionsColumnExists($conn, 'plan_features');
$hasIsActiveColumn = subscriptionsColumnExists($conn, 'is_active');
$hasCreatedAtColumn = subscriptionsColumnExists($conn, 'created_at');

if (isset($_POST['togglePlanStatus'])) {
    $statusValue = (int) ($_POST['status_value'] ?? 0);
    $statusValue = $statusValue === 1 ? 1 : 0;

    if (!$hasIsActiveColumn) {
        header("Location: subscriptionmanage.php?plan_notice=schema_error");
        exit();
    }

    $whereClause = '';
    if ($hasPlanIdColumn && isset($_POST['plan_id']) && $_POST['plan_id'] !== '') {
        $planId = (int) $_POST['plan_id'];
        $whereClause = "plan_id = '$planId'";
    } elseif ($hasPlanCodeColumn && isset($_POST['plan_code']) && $_POST['plan_code'] !== '') {
        $planCode = mysqli_real_escape_string($conn, trim((string) $_POST['plan_code']));
        $whereClause = "plan_code = '$planCode'";
    }

    if ($whereClause === '') {
        header("Location: subscriptionmanage.php?plan_notice=failed");
        exit();
    }

    $toggleSql = "UPDATE subscription_plans SET is_active = '$statusValue' WHERE $whereClause LIMIT 1";
    $toggleResult = mysqli_query($conn, $toggleSql);

    if ($toggleResult) {
        $notice = $statusValue === 1 ? 'activated' : 'deactivated';
        
        // Log the plan status toggle
        $getPlanSql = "SELECT plan_name, plan_id, plan_code FROM subscription_plans WHERE $whereClause LIMIT 1";
        $getPlanRes = mysqli_query($conn, $getPlanSql);
        $planRow = $getPlanRes && mysqli_num_rows($getPlanRes) > 0 ? mysqli_fetch_assoc($getPlanRes) : [];
        $planName = $planRow['plan_name'] ?? 'Unknown Plan';
        $planId = isset($planRow['plan_id']) ? (int)$planRow['plan_id'] : null;
        $logDetails = "Plan status changed to: " . ($statusValue === 1 ? 'Active' : 'Inactive');
        log_event($conn, "Toggle Subscription Plan Status", "Subscription Plan", $planId, $logDetails);
        
        header("Location: subscriptionmanage.php?plan_notice=$notice");
    } else {
        header("Location: subscriptionmanage.php?plan_notice=failed");
    }
    exit();
}

if (isset($_POST['updatePlan'])) {
    $planName = trim($_POST['plan_name'] ?? '');
    $monthlyPriceRaw = $_POST['monthly_price'] ?? '';
    $monthlyPrice = is_numeric($monthlyPriceRaw) ? (float) $monthlyPriceRaw : 0.0;
    $planFeaturesJson = getPlanFeaturesJsonFromPost();

    if ($planName === '' || $monthlyPrice <= 0 || !$hasPlanNameColumn || !$hasMonthlyPriceColumn) {
        header("Location: subscriptionmanage.php?plan_notice=invalid");
        exit();
    }

    $whereClause = '';
    if ($hasPlanIdColumn && isset($_POST['plan_id']) && $_POST['plan_id'] !== '') {
        $planId = (int) $_POST['plan_id'];
        $whereClause = "plan_id = '$planId'";
    } elseif ($hasPlanCodeColumn && isset($_POST['plan_code']) && $_POST['plan_code'] !== '') {
        $planCode = mysqli_real_escape_string($conn, trim((string) $_POST['plan_code']));
        $whereClause = "plan_code = '$planCode'";
    }

    if ($whereClause === '') {
        header("Location: subscriptionmanage.php?plan_notice=failed");
        exit();
    }

    $updateFields = [];
    $updateFields[] = "plan_name='" . mysqli_real_escape_string($conn, $planName) . "'";
    $updateFields[] = "monthly_price='" . mysqli_real_escape_string($conn, number_format($monthlyPrice, 2, '.', '')) . "'";

    if ($hasPlanFeaturesColumn) {
        $updateFields[] = "plan_features='" . mysqli_real_escape_string($conn, $planFeaturesJson) . "'";
    }

    $updateSql = "UPDATE subscription_plans SET " . implode(', ', $updateFields) . " WHERE $whereClause LIMIT 1";
    $updateResult = mysqli_query($conn, $updateSql);

    if ($updateResult) {
        // Log the plan update
        $planId = null;
        if ($hasPlanIdColumn && isset($_POST['plan_id'])) {
            $planId = (int)$_POST['plan_id'];
        }
        $logDetails = "Updated plan: Name=$planName, Price=" . number_format($monthlyPrice, 2) . "/month";
        log_event($conn, "Update Subscription Plan", "Subscription Plan", $planId, $logDetails);
        
        header("Location: subscriptionmanage.php?plan_notice=updated");
    } else {
        header("Location: subscriptionmanage.php?plan_notice=failed");
    }
    exit();
}

if (isset($_POST['publishPlan'])) {
    $planName = trim($_POST['plan_name'] ?? '');
    $monthlyPriceRaw = $_POST['monthly_price'] ?? '';
    $monthlyPrice = is_numeric($monthlyPriceRaw) ? (float) $monthlyPriceRaw : 0.0;
    $planFeaturesJson = getPlanFeaturesJsonFromPost();

    if ($planName === '' || $monthlyPrice <= 0) {
        header("Location: subscriptionmanage.php?plan_notice=invalid");
        exit();
    }

    if (!$hasPlanNameColumn || !$hasMonthlyPriceColumn) {
        header("Location: subscriptionmanage.php?plan_notice=schema_error");
        exit();
    }

    $columns = [];
    $values = [];

    if ($hasPlanCodeColumn) {
        $planCode = generatePlanCode($conn, $planName);
        $columns[] = 'plan_code';
        $values[] = "'" . mysqli_real_escape_string($conn, $planCode) . "'";
    }

    $columns[] = 'plan_name';
    $values[] = "'" . mysqli_real_escape_string($conn, $planName) . "'";

    $columns[] = 'monthly_price';
    $values[] = "'" . mysqli_real_escape_string($conn, number_format($monthlyPrice, 2, '.', '')) . "'";

    if ($hasPlanFeaturesColumn) {
        $columns[] = 'plan_features';
        $values[] = "'" . mysqli_real_escape_string($conn, $planFeaturesJson) . "'";
    }

    if ($hasIsActiveColumn) {
        $columns[] = 'is_active';
        $values[] = '1';
    }

    $insertPlanSql = "INSERT INTO subscription_plans (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
    $insertPlan = mysqli_query($conn, $insertPlanSql);

    if ($insertPlan) {
        // Log the plan creation
        $newPlanId = null;
        if ($hasPlanIdColumn) {
            $newPlanId = (int)$conn->insert_id;
        }
        $logDetails = "Created new subscription plan: Name=$planName, Price=" . number_format($monthlyPrice, 2) . "/month, Status=Active";
        log_event($conn, "Create Subscription Plan", "Subscription Plan", $newPlanId, $logDetails);
        
        header("Location: subscriptionmanage.php?plan_notice=created");
    } else {
        header("Location: subscriptionmanage.php?plan_notice=failed");
    }
    exit();
}

// Handle billing notification requests
$billingNotificationResult = '';
$suspensionResult = '';

// Ensure billing_notification_sent column exists
$checkColumnQuery = "SHOW COLUMNS FROM owners LIKE 'billing_notification_sent'";
$checkColumnResult = mysqli_query($conn, $checkColumnQuery);
if (!$checkColumnResult || mysqli_num_rows($checkColumnResult) === 0) {
    // Column doesn't exist, create it
    $createColumnQuery = "ALTER TABLE owners ADD COLUMN billing_notification_sent TINYINT(1) DEFAULT 0 AFTER plan_price";
    mysqli_query($conn, $createColumnQuery);
}

if (isset($_POST['send_billing_notifications'])) {
    $result = checkAndSendBillingNotifications($conn, 7);
    if ($result['sent'] > 0) {
        $billingNotificationResult = 'success';
    } elseif ($result['failed'] > 0) {
        $billingNotificationResult = 'partial_fail';
    } else {
        $billingNotificationResult = 'no_notifications';
    }
    header("Location: subscriptionmanage.php?billing_notice={$billingNotificationResult}&sent={$result['sent']}&failed={$result['failed']}");
    exit();
}

if (isset($_POST['suspend_unpaid_accounts'])) {
    $result = checkAndSuspendUnpaidAccounts($conn);
    if ($result['suspended'] > 0) {
        $suspensionResult = 'success';
    } elseif ($result['failed'] > 0) {
        $suspensionResult = 'partial_fail';
    } else {
        $suspensionResult = 'no_unpaid';
    }
    header("Location: subscriptionmanage.php?suspension_notice={$suspensionResult}&suspended={$result['suspended']}&suspension_failed={$result['failed']}");
    exit();
}

$planNotice = $_GET['plan_notice'] ?? '';
$billingNotice = $_GET['billing_notice'] ?? '';
$notificationsSent = (int) ($_GET['sent'] ?? 0);
$notificationsFailed = (int) ($_GET['failed'] ?? 0);

$suspensionNotice = $_GET['suspension_notice'] ?? '';
$suspensionCount = (int) ($_GET['suspended'] ?? 0);
$suspensionFailedCount = (int) ($_GET['suspension_failed'] ?? 0);

$planFilter = strtolower(trim((string) ($_GET['plan_filter'] ?? 'all')));
$allowedPlanFilters = ['all', 'active', 'inactive'];
if (!in_array($planFilter, $allowedPlanFilters, true)) {
    $planFilter = 'all';
}

if (!$hasIsActiveColumn) {
    $planFilter = 'all';
}

$savedPlans = [];
if ($hasPlanNameColumn && $hasMonthlyPriceColumn) {
    $selectColumns = ['plan_name', 'monthly_price'];
    if ($hasPlanIdColumn) {
        $selectColumns[] = 'plan_id';
    }
    if ($hasPlanCodeColumn) {
        $selectColumns[] = 'plan_code';
    }
    if ($hasPlanFeaturesColumn) {
        $selectColumns[] = 'plan_features';
    }
    if ($hasIsActiveColumn) {
        $selectColumns[] = 'is_active';
    }
    if ($hasCreatedAtColumn) {
        $selectColumns[] = 'created_at';
    }

    $plansQuery = "SELECT " . implode(', ', $selectColumns) . " FROM subscription_plans";
    if ($hasIsActiveColumn && $planFilter === 'active') {
        $plansQuery .= " WHERE is_active = 1";
    } elseif ($hasIsActiveColumn && $planFilter === 'inactive') {
        $plansQuery .= " WHERE is_active = 0";
    }
    $plansQuery .= " ORDER BY " . ($hasIsActiveColumn ? "is_active DESC, " : "") . "monthly_price ASC, plan_name ASC";

    $plansResult = mysqli_query($conn, $plansQuery);
    if ($plansResult) {
        while ($planRow = mysqli_fetch_assoc($plansResult)) {
            $savedPlans[] = $planRow;
        }
    }
}

// Subscription plans pricing config
$pricingConfig = [
    'basic' => ['monthly' => 999, 'name' => 'Basic', 'emoji' => '📦'],
    'standard' => ['monthly' => 1999, 'name' => 'Standard', 'emoji' => '🚀'],
    'premium' => ['monthly' => 3499, 'name' => 'Premium', 'emoji' => '💎']
];

$planPricingLookup = [];

foreach ($pricingConfig as $planKey => $planConfig) {
    $normalizedPlanKey = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $planKey));
    $planNameValue = (string) ($planConfig['name'] ?? ucfirst((string) $planKey));
    $monthlyValue = (float) ($planConfig['monthly'] ?? 0);

    if ($normalizedPlanKey !== '') {
        $planPricingLookup[$normalizedPlanKey] = ['name' => $planNameValue, 'monthly' => $monthlyValue];
        $planPricingLookup[$normalizedPlanKey . 'plan'] = ['name' => $planNameValue, 'monthly' => $monthlyValue];
    }

    $normalizedNameKey = preg_replace('/[^a-z0-9]+/', '', strtolower($planNameValue));
    if ($normalizedNameKey !== '') {
        $planPricingLookup[$normalizedNameKey] = ['name' => $planNameValue, 'monthly' => $monthlyValue];
    }
}

foreach ($savedPlans as $savedPlan) {
    $savedPlanName = trim((string) ($savedPlan['plan_name'] ?? ''));
    $savedMonthly = (float) ($savedPlan['monthly_price'] ?? 0);

    if ($savedPlanName !== '' && $savedMonthly > 0) {
        $normalizedSavedName = preg_replace('/[^a-z0-9]+/', '', strtolower($savedPlanName));
        if ($normalizedSavedName !== '') {
            $planPricingLookup[$normalizedSavedName] = ['name' => $savedPlanName, 'monthly' => $savedMonthly];
        }
    }

    if (isset($savedPlan['plan_code'])) {
        $savedPlanCode = trim((string) $savedPlan['plan_code']);
        $normalizedSavedCode = preg_replace('/[^a-z0-9]+/', '', strtolower($savedPlanCode));
        if ($normalizedSavedCode !== '' && $savedMonthly > 0) {
            $planPricingLookup[$normalizedSavedCode] = ['name' => $savedPlanName !== '' ? $savedPlanName : $savedPlanCode, 'monthly' => $savedMonthly];
        }
    }
}

// Get subscription statistics
$stats = [
    'total_mrr' => 0,
    'active_subscriptions' => 0,
    'avg_arpu' => 0,
    'churn_rate' => 0,
    'plans' => []
];

// Count active tenants by subscription plan and convert totals into monthly equivalent revenue.
$activeTenantsQuery = "SELECT subscription_plan, billing_cycle, plan_price
                       FROM owners
                       WHERE status = 'Active' AND subscription_plan IS NOT NULL";
$result = mysqli_query($conn, $activeTenantsQuery);

while ($row = mysqli_fetch_assoc($result)) {
    $rawPlanName = (string) ($row['subscription_plan'] ?? '');
    $plan = preg_replace('/[^a-z0-9]+/', '', strtolower($rawPlanName));
    $matchedPlan = ($plan !== '' && isset($planPricingLookup[$plan])) ? $planPricingLookup[$plan] : null;
    $planDisplayName = $matchedPlan['name'] ?? ucfirst($rawPlanName);

    $billingDivisor = getBillingCycleDivisor($row['billing_cycle'] ?? 'monthly');
    $storedAmount = (float) ($row['plan_price'] ?? 0);
    $monthlyEquivalent = 0;

    if ($storedAmount > 0) {
        $monthlyEquivalent = $storedAmount / $billingDivisor;
    } elseif (isset($matchedPlan['monthly'])) {
        $monthlyEquivalent = (float) $matchedPlan['monthly'];
    }

    if (!isset($stats['plans'][$plan])) {
        $stats['plans'][$plan] = [
            'count' => 0,
            'revenue' => 0,
            'name' => $planDisplayName
        ];
    }
    $stats['plans'][$plan]['count'] += 1;
    $stats['plans'][$plan]['revenue'] += $monthlyEquivalent;
    $stats['total_mrr'] += $monthlyEquivalent;
}

// Get total active subscriptions
$totalActiveQuery = "SELECT COUNT(*) as total FROM owners WHERE status = 'Active' AND subscription_plan IS NOT NULL";
$totalResult = mysqli_query($conn, $totalActiveQuery);
$totalActiveRow = mysqli_fetch_assoc($totalResult);
$stats['active_subscriptions'] = $totalActiveRow['total'];

// Calculate ARPU
if ($stats['active_subscriptions'] > 0) {
    $stats['avg_arpu'] = round($stats['total_mrr'] / $stats['active_subscriptions'], 2);
}

// Get churn rate (inactive tenants this month vs total)
$currentMonth = date('Y-m-01');
$churnQuery = "SELECT COUNT(*) as inactive_count FROM owners 
               WHERE status = 'Inactive' AND subscription_end >= '$currentMonth' AND subscription_end < NOW()";
$churnResult = mysqli_query($conn, $churnQuery);
$churnRow = mysqli_fetch_assoc($churnResult);
$inactiveCount = $churnRow['inactive_count'] ?? 0;

if ($stats['active_subscriptions'] > 0) {
    $stats['churn_rate'] = round(($inactiveCount / ($stats['active_subscriptions'] + $inactiveCount)) * 100, 1);
}

// Generate MRR trend data (last 12 months)
$mrrLabels = [];
$mrrData = [];

for ($i = 11; $i >= 0; $i--) {
    $monthDate = strtotime("first day of -$i month");
    $mrrLabels[] = date("M Y", $monthDate);
    $mrrData[] = 0;
}

// Query MRR data by month
$mrrQuery = "SELECT DATE_FORMAT(subscription_start, '%Y-%m') AS month_key, SUM(plan_price / CASE 
    WHEN LOWER(billing_cycle) IN ('annual', 'annually', 'yearly') THEN 12
    WHEN LOWER(billing_cycle) IN ('semiannual', 'semi-annual', 'biannual') THEN 6
    WHEN LOWER(billing_cycle) IN ('quarterly', 'quarter') THEN 3
    ELSE 1
END) AS monthly_revenue
FROM owners 
WHERE status = 'Active' AND subscription_start >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 11 MONTH) 
GROUP BY month_key ORDER BY month_key ASC";

$mrrResult = mysqli_query($conn, $mrrQuery);
if ($mrrResult) {
    $mrrLookup = [];
    while ($row = mysqli_fetch_assoc($mrrResult)) {
        $mrrLookup[(string) $row['month_key']] = round((float) $row['monthly_revenue'], 2);
    }

    foreach ($mrrLabels as $idx => $label) {
        $monthKey = date("Y-m", strtotime("1 " . $label));
        $mrrData[$idx] = $mrrLookup[$monthKey] ?? 0;
    }
}

?>
<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>Subscription Management | RapidRepair</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "on-secondary": "#ffffff",
                        "surface-container-high": "#ffffff",
                        "outline-variant": "#d4d4d8",
                        "on-tertiary": "#ffffff",
                        "on-tertiary-container": "#92400e",
                        "error": "#dc2626",
                        "on-secondary-fixed-variant": "#3f3f46",
                        "background": "#ffffff",
                        "on-secondary-container": "#18181b",
                        "inverse-primary": "#fecaca",
                        "surface-container-low": "#ffffff",
                        "surface-dim": "#e5e7eb",
                        "tertiary-container": "#fef3c7",
                        "outline": "#e5e7eb",
                        "on-surface-variant": "#525252",
                        "on-surface": "#111827",
                        "secondary": "#3f3f46",
                        "inverse-on-surface": "#f8fafc",
                        "on-primary": "#ffffff",
                        "on-tertiary-fixed": "#7c2d12",
                        "on-primary-fixed": "#7f1d1d",
                        "surface-container-highest": "#ffffff",
                        "inverse-surface": "#18181b",
                        "primary-fixed": "#fee2e2",
                        "on-background": "#0a0a0a",
                        "tertiary-fixed-dim": "#fed7aa",
                        "surface-tint": "#b91c1c",
                        "surface-container": "#ffffff",
                        "error-container": "#fee2e2",
                        "surface-variant": "#f5f5f5",
                        "surface-container-lowest": "#ffffff",
                        "tertiary-fixed": "#ffedd5",
                        "surface": "#ffffff",
                        "on-error": "#ffffff",
                        "secondary-fixed": "#e5e7eb",
                        "on-secondary-fixed": "#111827",
                        "primary": "#b91c1c",
                        "on-error-container": "#991b1b",
                        "on-primary-fixed-variant": "#991b1b",
                        "on-primary-container": "#7f1d1d",
                        "primary-container": "#fee2e2",
                        "tertiary": "#f59e0b",
                        "surface-bright": "#ffffff",
                        "primary-fixed-dim": "#fecaca",
                        "secondary-container": "#f5f5f5",
                        "secondary-fixed-dim": "#d4d4d8",
                        "on-tertiary-fixed-variant": "#9a3412"
                    },
                    fontFamily: {
                        "headline": ["Inter"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    },
                    borderRadius: { "DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem" },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
            display: block;
        }
    </style>
</head>

<body class="bg-background text-on-background antialiased selection:bg-primary-fixed selection:text-primary overflow-x-hidden">
    <!-- SideNavBar Shell -->
    <aside
        class="flex flex-col fixed left-0 top-0 h-full z-40 h-screen w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 font-['Inter'] antialiased tracking-tight shadow-sm dark:shadow-none">
        <!-- Brand Header -->
            <div class="p-6 flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg overflow-hidden">
                    <img src="../pictures/RRlogo3.png" alt="Rapid Repair logo" class="h-full w-full object-contain">
                </div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white leading-none">
                    <?= htmlspecialchars($brandingSettings['system_name']) ?> <span class="text-primary">SuperAdmin</span>
                </h2>
            </div>
        <!-- Navigation Links -->
        <nav class="flex-1 px-4 space-y-1 mt-4">
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superadd.php">
                <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
                <span class="text-sm">Dashboard</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superaddtenants.php">
                <span class="material-symbols-outlined" data-icon="groups">groups</span>
                <span class="text-sm">Tenants</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superreports.php">
                <span class="material-symbols-outlined" data-icon="bar_chart">bar_chart</span>
                <span class="text-sm">Reports</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold border-r-4 border-red-700 dark:border-red-500 rounded-lg active:scale-95"
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
        <!-- Footer Actions -->
        <div class="p-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
            <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                <div class="w-10 h-10 rounded-full bg-primary-container text-primary flex items-center justify-center font-semibold text-sm">
                    <?php echo htmlspecialchars(initials($superadminName)); ?>
                </div>
                <div class="flex flex-col min-w-0">
                    <h3 class="text-sm font-semibold truncate text-slate-900 dark:text-white"><?php echo htmlspecialchars($superadminName); ?></h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Superadmin</p>
                </div>
            </div>
            <form method="POST" class="w-full">
                <button type="submit" name="logout_superadmin"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors cursor-pointer text-left">
                    <span class="material-symbols-outlined">logout</span>
                    <span class="text-sm font-medium">Logout</span>
                </button>
            </form>
        </div>
    </aside>
    <!-- TopAppBar Shell -->
    <header
        class="flex items-center justify-between px-8 sticky top-0 z-30 ml-64 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md w-full h-16 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-4">
            <div class="relative">
                <input id="globalSearchInput"
                    class="pl-4 pr-4 py-1.5 bg-surface-variant border-none text-sm rounded-lg focus:ring-2 focus:ring-primary w-64 transition-all"
                    placeholder="Search tenants or plans..." type="text" />
            </div>
            <div class="flex items-center gap-2" id="searchScopeFilters">
                <button type="button" data-scope="all"
                    class="search-scope-btn px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide bg-primary text-white">All</button>
                <button type="button" data-scope="plans"
                    class="search-scope-btn px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide bg-slate-100 text-slate-600 hover:bg-slate-200">Plans</button>
                <button type="button" data-scope="subscriptions"
                    class="search-scope-btn px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide bg-slate-100 text-slate-600 hover:bg-slate-200">Active
                    Subs</button>
            </div>
        </div>
        <div class="flex items-center gap-4"></div>
    </header>
    <!-- Main Content Area -->
    <main class="ml-64 p-8 overflow-x-hidden">
        <?php if ($planNotice !== ''): ?>
            <?php $isSuccessNotice = in_array($planNotice, ['created', 'updated', 'activated', 'deactivated'], true); ?>
            <div
                class="mb-6 rounded-lg px-4 py-3 text-sm font-medium <?php echo $isSuccessNotice ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'; ?>">
                <?php if ($planNotice === 'created'): ?>
                    Subscription plan created successfully.
                <?php elseif ($planNotice === 'updated'): ?>
                    Subscription plan updated successfully.
                <?php elseif ($planNotice === 'activated'): ?>
                    Subscription plan activated successfully.
                <?php elseif ($planNotice === 'deactivated'): ?>
                    Subscription plan deactivated successfully.
                <?php elseif ($planNotice === 'invalid'): ?>
                    Please enter a valid plan name and monthly price.
                <?php elseif ($planNotice === 'schema_error'): ?>
                    subscription_plans table is missing required columns (plan_name/monthly_price).
                <?php else: ?>
                    Failed to create subscription plan. Please try again.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($billingNotice !== ''): ?>
            <?php if ($billingNotice === 'success'): ?>
                <div class="mb-6 rounded-lg px-4 py-3 text-sm font-medium bg-emerald-100 text-emerald-800 flex items-center gap-2">
                    <span class="material-symbols-outlined">check_circle</span>
                    ✓ Billing notifications sent successfully (<?php echo $notificationsSent; ?> reminder<?php echo $notificationsSent !== 1 ? 's' : ''; ?> sent)
                </div>
            <?php elseif ($billingNotice === 'partial_fail'): ?>
                <div class="mb-6 rounded-lg px-4 py-3 text-sm font-medium bg-amber-100 text-amber-800 flex items-center gap-2">
                    <span class="material-symbols-outlined">warning</span>
                    ⚠ Partial failure: <?php echo $notificationsSent; ?> notification<?php echo $notificationsSent !== 1 ? 's' : ''; ?> sent, <?php echo $notificationsFailed; ?> failed
                </div>
            <?php elseif ($billingNotice === 'no_notifications'): ?>
                <div class="mb-6 rounded-lg px-4 py-3 text-sm font-medium bg-blue-100 text-blue-800 flex items-center gap-2">
                    <span class="material-symbols-outlined">info</span>
                    ℹ No upcoming billing dates within 7 days or all have been notified
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($suspensionNotice !== ''): ?>
            <?php if ($suspensionNotice === 'success'): ?>
                <div class="mb-6 rounded-lg px-4 py-3 text-sm font-medium bg-red-100 text-red-800 flex items-center gap-2">
                    <span class="material-symbols-outlined">block</span>
                    ✓ Accounts suspended successfully (<?php echo $suspensionCount; ?> account<?php echo $suspensionCount !== 1 ? 's' : ''; ?> suspended for non-payment)
                </div>
            <?php elseif ($suspensionNotice === 'partial_fail'): ?>
                <div class="mb-6 rounded-lg px-4 py-3 text-sm font-medium bg-amber-100 text-amber-800 flex items-center gap-2">
                    <span class="material-symbols-outlined">warning</span>
                    ⚠ Partial failure: <?php echo $suspensionCount; ?> account<?php echo $suspensionCount !== 1 ? 's' : ''; ?> suspended, <?php echo $suspensionFailedCount; ?> failed
                </div>
            <?php elseif ($suspensionNotice === 'no_unpaid'): ?>
                <div class="mb-6 rounded-lg px-4 py-3 text-sm font-medium bg-blue-100 text-blue-800 flex items-center gap-2">
                    <span class="material-symbols-outlined">info</span>
                    ℹ No overdue unpaid accounts found to suspend
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="flex items-end justify-between mb-8">
            <div>
                <nav class="flex text-xs font-bold text-primary mb-2 uppercase tracking-widest gap-2">
                    <span>Console</span>
                    <span class="text-slate-300">/</span>
                    <span class="text-slate-400">Subscription Management</span>
                </nav>
                <h1 class="text-[30px] font-black tracking-tight text-on-surface leading-none">Subscription Plans</h1>
                <p class="text-on-surface-variant mt-2 text-sm max-w-lg">Manage multi-tenant service tiers, pricing
                    structures, and feature entitlements across the enterprise ecosystem.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <form method="POST" class="inline">
                    <button type="submit" name="send_billing_notifications" value="1"
                        class="flex items-center gap-2 px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold rounded-lg shadow-sm active:scale-95 transition-transform">
                        <span class="material-symbols-outlined text-[20px]">notifications_active</span>
                        Send Billing Notifications
                    </button>
                </form>
                <form method="POST" class="inline">
                    <button type="submit" name="suspend_unpaid_accounts" value="1"
                        class="flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-bold rounded-lg shadow-sm active:scale-95 transition-transform"
                        onclick="return confirm('⚠️ This will suspend all accounts with overdue payments. Continue?');">
                        <span class="material-symbols-outlined text-[20px]">block</span>
                        Suspend Unpaid Accounts
                    </button>
                </form>
                <button type="button" onclick="openCreatePlanModal()"
                    class="flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-bold rounded-lg shadow-sm active:scale-95 transition-transform">
                    <span class="material-symbols-outlined text-[20px]" data-icon="add">add</span>
                    Create New Plan
                </button>
            </div>
        </div>

        <?php if ($hasIsActiveColumn): ?>
            <div class="mb-6 flex items-center gap-2">
                <a href="subscriptionmanage.php?plan_filter=all"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide <?php echo $planFilter === 'all' ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">All</a>
                <a href="subscriptionmanage.php?plan_filter=active"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide <?php echo $planFilter === 'active' ? 'bg-emerald-600 text-white' : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'; ?>">Active</a>
                <a href="subscriptionmanage.php?plan_filter=inactive"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide <?php echo $planFilter === 'inactive' ? 'bg-amber-600 text-white' : 'bg-amber-100 text-amber-700 hover:bg-amber-200'; ?>">Inactive</a>
            </div>
        <?php endif; ?>

        <?php if (count($savedPlans) === 0): ?>
            <!-- Blank Create Plan Box -->
            <div class="rounded-xl border-2 border-dashed border-slate-300 bg-white/70 p-10 text-center">
                <div class="mx-auto mb-4 h-14 w-14 rounded-full bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-3xl">add</span>
                </div>
                <h3 class="text-xl font-black text-on-surface">No Custom Plans Yet</h3>
                <p class="text-sm text-on-surface-variant mt-2 max-w-md mx-auto">Start by creating your first subscription
                    plan. Configure monthly price and included features in the modal.</p>
                <button type="button" onclick="openCreatePlanModal()"
                    class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-bold rounded-lg shadow-sm active:scale-95 transition-transform">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    Create New Plan
                </button>
            </div>
        <?php else: ?>
            <section id="plansGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach ($savedPlans as $plan): ?>
                    <?php
                    $features = [];
                    $planRecordId = isset($plan['plan_id']) ? (int) $plan['plan_id'] : 0;
                    $planCodeValue = isset($plan['plan_code']) ? (string) $plan['plan_code'] : '';
                    $isActive = !isset($plan['is_active']) || (int) $plan['is_active'] === 1;
                    if (isset($plan['plan_features']) && $plan['plan_features'] !== '') {
                        $decoded = json_decode($plan['plan_features'], true);
                        if (is_array($decoded)) {
                            foreach ($decoded as $featureText) {
                                $featureText = trim((string) $featureText);
                                if ($featureText !== '') {
                                    $features[] = $featureText;
                                }
                            }
                        }
                    }
                    $planSearchHaystack = strtolower(trim((string) ($plan['plan_name'] ?? '') . ' ' . $planCodeValue . ' ' . ($isActive ? 'active' : 'inactive') . ' ' . implode(' ', $features)));
                    ?>
                    <article class="searchable-plan rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                        data-search="<?php echo htmlspecialchars($planSearchHaystack, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-black text-on-surface">
                                    <?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                                <p class="text-xs text-slate-500 uppercase tracking-wide mt-1">Monthly Price</p>
                            </div>
                            <?php if (isset($plan['plan_code']) && $plan['plan_code'] !== ''): ?>
                                <span
                                    class="px-2.5 py-1 rounded bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-wide"><?php echo htmlspecialchars($plan['plan_code']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4 text-3xl font-black text-on-surface">
                            ₱<?php echo number_format((float) $plan['monthly_price'], 2); ?><span
                                class="text-sm font-semibold text-slate-500"> / month</span></div>

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span
                                class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wide <?php echo $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'; ?>">
                                <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                            </span>
                            <div class="flex items-center gap-2">
                                <button type="button"
                                    class="edit-plan-btn px-3 py-1.5 rounded border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-50"
                                    data-plan-id="<?php echo $planRecordId; ?>"
                                    data-plan-code="<?php echo htmlspecialchars($planCodeValue, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-plan-name="<?php echo htmlspecialchars((string) $plan['plan_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-plan-price="<?php echo htmlspecialchars((string) $plan['monthly_price'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-plan-features="<?php echo htmlspecialchars(json_encode($features), ENT_QUOTES, 'UTF-8'); ?>">
                                    Edit
                                </button>
                                <?php if ($hasIsActiveColumn): ?>
                                    <form method="POST" class="inline">
                                        <?php if ($hasPlanIdColumn): ?>
                                            <input type="hidden" name="plan_id" value="<?php echo $planRecordId; ?>" />
                                        <?php endif; ?>
                                        <?php if ($hasPlanCodeColumn): ?>
                                            <input type="hidden" name="plan_code"
                                                value="<?php echo htmlspecialchars($planCodeValue, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <?php endif; ?>
                                        <input type="hidden" name="status_value" value="<?php echo $isActive ? '0' : '1'; ?>" />
                                        <button type="submit" name="togglePlanStatus" value="1"
                                            class="px-3 py-1.5 rounded text-xs font-bold <?php echo $isActive ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'; ?>">
                                            <?php echo $isActive ? 'Set Inactive' : 'Set Active'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (count($features) > 0): ?>
                            <ul class="mt-5 space-y-2">
                                <?php foreach ($features as $feature): ?>
                                    <li class="flex items-start gap-2 text-sm text-slate-700">
                                        <span
                                            class="material-symbols-outlined text-emerald-500 text-[18px] mt-[1px]">check_circle</span>
                                        <span><?php echo htmlspecialchars($feature); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="mt-5 text-sm text-slate-500">No feature list saved for this plan yet.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
            <p id="plansSearchEmpty" class="hidden mt-4 text-sm font-medium text-slate-500">No plans match your search.</p>
        <?php endif; ?>
        <!-- Recent Activity / Comparative Chart Placeholder -->
        <section class="mt-8">
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h2 class="text-sm font-bold text-on-surface uppercase tracking-tight">Revenue Stream Analysis</h2>
                    <div class="flex gap-2" id="billingCycleFilters">
                        <button type="button" data-cycle="monthly"
                            class="billing-filter px-3 py-1 text-[10px] font-bold rounded uppercase bg-primary text-white">Monthly</button>
                        <button type="button" data-cycle="quarterly"
                            class="billing-filter px-3 py-1 text-[10px] font-bold rounded uppercase bg-slate-100 text-slate-500 hover:bg-slate-200">Quarterly</button>
                        <button type="button" data-cycle="yearly"
                            class="billing-filter px-3 py-1 text-[10px] font-bold rounded uppercase bg-slate-100 text-slate-500 hover:bg-slate-200">Yearly</button>
                    </div>
                </div>
                <div class="p-6">
                    <!-- MRR Chart -->
                    <div class="chart-wrapper">
                        <canvas id="mrrChart"></canvas>
                    </div>
                    <div class="grid grid-cols-4 gap-6 mt-6">
                        <div class="p-4 rounded-lg bg-slate-50">
                            <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Total MRR</div>
                            <div class="text-xl font-black text-on-surface">
                                ₱<?php echo number_format($stats['total_mrr'], 0); ?></div>
                            <div class="text-[10px] text-slate-500 font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]" data-icon="info">info</span> Monthly
                                recurring
                            </div>
                        </div>
                        <div class="p-4 rounded-lg bg-slate-50">
                            <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Active Subs</div>
                            <div class="text-xl font-black text-on-surface">
                                <?php echo $stats['active_subscriptions']; ?>
                            </div>
                            <div class="text-[10px] text-slate-500 font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]"
                                    data-icon="check_circle">check_circle</span> Active tenants
                            </div>
                        </div>
                        <div class="p-4 rounded-lg bg-slate-50">
                            <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Avg. ARPU</div>
                            <div class="text-xl font-black text-on-surface">
                                ₱<?php echo number_format($stats['avg_arpu'], 0); ?></div>
                            <div class="text-[10px] text-slate-500 font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]"
                                    data-icon="account_balance">account_balance</span> Per user monthly
                            </div>
                        </div>
                        <div class="p-4 rounded-lg bg-slate-50">
                            <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Churn Rate</div>
                            <div class="text-xl font-black text-on-surface"><?php echo $stats['churn_rate']; ?>%</div>
                            <div class="text-[10px] text-slate-500 font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]"
                                    data-icon="trending_down">trending_down</span> This month
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Active Subscriptions Table -->
        <section class="mt-8">
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h2 class="text-sm font-bold text-on-surface uppercase tracking-tight">Active Subscriptions</h2>
                    <button id="exportPdfBtn" type="button" 
                        class="flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg hover:bg-emerald-700 transition-colors active:scale-95"
                        title="Export to PDF">
                        <span class="material-symbols-outlined text-[18px]">file_download</span>
                        Export PDF
                    </button>
                </div>

                <!-- Subscription Filters -->
                <div class="px-8 py-6 border-b border-slate-100 bg-white">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                        <!-- Date Range Filter -->
                        <div class="flex flex-col">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-widest mb-2">Date Range</label>
                            <select id="dateRangeFilter" class="border border-slate-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent bg-white cursor-pointer">
                                <option value="last_30_days">Last 30 Days</option>
                                <option value="last_7_days">Last 7 Days</option>
                                <option value="last_90_days">Last 90 Days</option>
                                <option value="last_year">Last Year</option>
                                <option value="all_time">All Time</option>
                            </select>
                        </div>

                        <!-- Tenant Filter -->
                        <div class="flex flex-col">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-widest mb-2">Tenant</label>
                            <select id="tenantFilter" class="border border-slate-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent bg-white cursor-pointer">
                                <option value="">All Tenants</option>
                                <?php
                                $tenantsQuery = "SELECT DISTINCT tenantID, shopName FROM owners WHERE subscription_plan IS NOT NULL ORDER BY shopName ASC";
                                $tenantsResult = mysqli_query($conn, $tenantsQuery);
                                if ($tenantsResult) {
                                    while ($tenant = mysqli_fetch_assoc($tenantsResult)) {
                                        echo '<option value="' . htmlspecialchars($tenant['tenantID']) . '">' . htmlspecialchars($tenant['shopName']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="flex flex-col">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-widest mb-2">Status</label>
                            <select id="statusFilter" class="border border-slate-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent bg-white cursor-pointer">
                                <option value="">All Status</option>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>

                        <!-- Granularity/Billing Cycle Filter -->
                        <div class="flex flex-col">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-widest mb-2">Billing Cycle</label>
                            <select id="billingCycleFilterSelect" class="border border-slate-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent bg-white cursor-pointer">
                                <option value="">All Billing Cycles</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="semiannual">Semi-Annual</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button id="applyFiltersBtn" type="button" 
                            class="flex items-center gap-2 px-6 py-2.5 bg-red-600 text-white text-sm font-bold rounded-lg hover:bg-red-700 transition-colors active:scale-95">
                            <span class="material-symbols-outlined text-[18px]">filter_list</span>
                            Apply Filters
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="subscriptionsTable">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50">
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Tenant
                                </th>
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Plan</th>
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Billing
                                    Cycle</th>
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Monthly
                                    Rate</th>
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Start
                                    Date</th>
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Next
                                    Billing</th>
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody id="activeSubscriptionsBody">
                            <?php
                            $subscriptionsQuery = "SELECT o.tenantID, o.ownerName, o.shopName, o.email, o.contactNumber, o.shopAddress, 
                                                 o.subscription_plan, o.billing_cycle, o.plan_price, 
                                                 o.subscription_start, o.subscription_end, o.next_billing_date, o.status,
                                                 s.subscription_id, s.start_date, s.end_date, s.amount
                                                 FROM owners o
                                                 LEFT JOIN subscriptions s ON o.tenantID = s.tenantID
                                                 WHERE o.status = 'Active' AND o.subscription_plan IS NOT NULL 
                                                 ORDER BY o.next_billing_date ASC";
                            $subResult = mysqli_query($conn, $subscriptionsQuery);

                            if (mysqli_num_rows($subResult) > 0) {
                                while ($sub = mysqli_fetch_assoc($subResult)) {
                                    $rawPlanName = (string) ($sub['subscription_plan'] ?? '');
                                    $planKey = preg_replace('/[^a-z0-9]+/', '', strtolower($rawPlanName));
                                    $matchedPlan = ($planKey !== '' && isset($planPricingLookup[$planKey])) ? $planPricingLookup[$planKey] : null;

                                    $planName = $matchedPlan['name'] ?? ucfirst($rawPlanName);
                                    $monthlyRate = isset($matchedPlan['monthly']) ? (float) $matchedPlan['monthly'] : 0;

                                    $totalBillingAmount = (float) ($sub['plan_price'] ?? 0);
                                    $billingDivisor = getBillingCycleDivisor($sub['billing_cycle'] ?? 'monthly');

                                    if ($monthlyRate <= 0 && $totalBillingAmount > 0) {
                                        $monthlyRate = $totalBillingAmount / $billingDivisor;
                                    }

                                    if ($totalBillingAmount <= 0 && $monthlyRate > 0) {
                                        $totalBillingAmount = $monthlyRate * $billingDivisor;
                                    }

                                    // Fetch recent payment history
                                    $paymentsQuery = "SELECT payment_id, amount, payment_status, payment_method, paid_at, transaction_reference
                                                   FROM subscription_payments 
                                                   WHERE tenantID = " . (int)$sub['tenantID'] . "
                                                   ORDER BY paid_at DESC 
                                                   LIMIT 3";
                                    $paymentsResult = mysqli_query($conn, $paymentsQuery);
                                    $paymentHistory = [];
                                    if ($paymentsResult && mysqli_num_rows($paymentsResult) > 0) {
                                        while ($payment = mysqli_fetch_assoc($paymentsResult)) {
                                            $paymentHistory[] = $payment;
                                        }
                                    }

                                    $startDate = date('M d, Y', strtotime($sub['subscription_start']));
                                    $nextBilling = date('M d, Y', strtotime($sub['next_billing_date']));
                                    $billingCycle = ucfirst($sub['billing_cycle']);
                                    $subscriptionSearchHaystack = strtolower(trim((string) ($sub['shopName'] ?? '') . ' ' . $planName . ' ' . $billingCycle . ' ' . $nextBilling));
                                    
                                    // Encode payment history as JSON for data attribute
                                    $paymentHistoryJson = htmlspecialchars(json_encode($paymentHistory), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr class="searchable-subscription border-b border-slate-100 hover:bg-slate-50 transition-colors"
                                        data-search="<?php echo htmlspecialchars($subscriptionSearchHaystack, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-billing-cycle="<?php echo htmlspecialchars(strtolower($sub['billing_cycle']), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-tenant="<?php echo htmlspecialchars(strtolower($sub['shopName']), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-tenant-id="<?php echo htmlspecialchars((string)$sub['tenantID'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-owner-name="<?php echo htmlspecialchars($sub['ownerName'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-owner-email="<?php echo htmlspecialchars($sub['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-contact-number="<?php echo htmlspecialchars($sub['contactNumber'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-shop-address="<?php echo htmlspecialchars($sub['shopAddress'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-monthly-rate="<?php echo htmlspecialchars((string)$monthlyRate, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-next-billing="<?php echo htmlspecialchars($sub['next_billing_date'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-status="<?php echo htmlspecialchars($sub['status'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-subscription-start="<?php echo htmlspecialchars($sub['subscription_start'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-subscription-id="<?php echo htmlspecialchars((string)($sub['subscription_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-payment-history="<?php echo $paymentHistoryJson; ?>"
                                        data-total-amount="<?php echo htmlspecialchars((string)$totalBillingAmount, ENT_QUOTES, 'UTF-8'); ?>">
                                        <td class="px-6 py-4 font-medium text-slate-900">
                                            <?php echo htmlspecialchars($sub['shopName']); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="px-2.5 py-1 bg-primary/10 text-primary text-[10px] font-bold rounded uppercase">
                                                <?php echo $planName; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-slate-600"><?php echo $billingCycle; ?></td>
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-slate-900">
                                                ₱<?php echo number_format($monthlyRate, 0); ?>/mo</div>
                                            <div class="text-[10px] text-slate-500">
                                                ₱<?php echo number_format($totalBillingAmount, 0); ?> total</div>
                                        </td>
                                        <td class="px-6 py-4 text-slate-600"><?php echo $startDate; ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2.5 py-1 bg-amber-100 text-amber-800 text-[10px] font-bold rounded">
                                                <?php echo $nextBilling; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <button class="p-1 hover:bg-slate-200 rounded transition-colors" title="Edit">
                                                <span class="material-symbols-outlined text-[18px] text-slate-600">edit</span>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                                        <div class="flex flex-col items-center gap-2">
                                            <span class="material-symbols-outlined text-4xl text-slate-300">inbox</span>
                                            <p class="font-medium">No active subscriptions yet</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                            <tr id="subscriptionsSearchEmpty" class="hidden">
                                <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <span
                                            class="material-symbols-outlined text-4xl text-slate-300">search_off</span>
                                        <p class="font-medium">No active subscriptions match your search</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Edit Plan Modal -->
        <div id="editPlanModal"
            class="hidden fixed inset-0 z-[110] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
            <div class="bg-white w-full max-w-xl rounded-lg shadow-2xl border border-slate-200 overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white">
                    <div>
                        <h2 class="text-xl font-black text-on-surface tracking-tight">Edit Plan</h2>
                        <p class="text-xs text-on-surface-variant font-medium mt-1">Update pricing and plan features.
                        </p>
                    </div>
                    <button type="button" onclick="closeEditPlanModal()"
                        class="text-slate-400 hover:text-on-surface transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form id="editPlanForm" method="POST" class="px-8 py-8 space-y-6">
                    <?php if ($hasPlanIdColumn): ?>
                        <input type="hidden" id="editPlanIdInput" name="plan_id" />
                    <?php endif; ?>
                    <?php if ($hasPlanCodeColumn): ?>
                        <input type="hidden" id="editPlanCodeInput" name="plan_code" />
                    <?php endif; ?>
                    <input type="hidden" id="editPlanFeaturesInput" name="plan_features" value="[]" />

                    <div class="grid grid-cols-2 gap-6">
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plan
                                Name</label>
                            <input id="editPlanNameInput" name="plan_name"
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                                type="text" />
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Monthly
                                Price</label>
                            <div class="relative">
                                <span
                                    class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-sm">₱</span>
                                <input id="editPlanPriceInput" name="monthly_price"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                                    type="number" min="0" step="0.01" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-4 flex justify-between">
                            <span>Included Features</span>
                            <button id="editAddFeatureBtn" type="button"
                                class="text-primary cursor-pointer hover:underline">+ Add Feature</button>
                        </label>
                        <div id="editFeatureList" class="space-y-3"></div>
                    </div>

                    <div class="pt-2 flex gap-4">
                        <button type="button" onclick="closeEditPlanModal()"
                            class="flex-1 py-3 border border-slate-200 text-slate-600 font-bold text-sm rounded-lg hover:bg-slate-50">Cancel</button>
                        <button type="submit" name="updatePlan" value="1"
                            class="flex-1 py-3 bg-primary text-white font-bold text-sm rounded-lg hover:opacity-90">Save
                            Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Create Plan Modal -->
        <div id="createPlanModal"
            class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
            <div class="bg-white w-full max-w-xl rounded-lg shadow-2xl border border-slate-200 overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white">
                    <div>
                        <h2 class="text-xl font-black text-on-surface tracking-tight">Create New Plan</h2>
                        <p class="text-xs text-on-surface-variant font-medium mt-1">Define pricing and features for a
                            new subscription tier.</p>
                    </div>
                    <button type="button" onclick="closeCreatePlanModal()"
                        class="text-slate-400 hover:text-on-surface transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form id="createPlanForm" method="POST" class="px-8 py-8 space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plan
                                Name</label>
                            <input id="planNameInput" name="plan_name"
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                                placeholder="e.g. Enterprise" type="text" />
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Monthly
                                Price (USD)</label>
                            <div class="relative">
                                <span
                                    class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-sm">$</span>
                                <input id="planPriceInput" name="monthly_price"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                                    placeholder="0.00" type="number" min="0" step="0.01" />
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="planFeaturesInput" name="plan_features" value="[]" />

                    <div>
                        <label
                            class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-4 flex justify-between">
                            <span>Included Features</span>
                            <button id="addFeatureBtn" type="button"
                                class="text-primary cursor-pointer hover:underline">+ Add Feature</button>
                        </label>
                        <div id="featureList" class="space-y-3">
                            <div class="flex items-center gap-3 feature-row">
                                <div class="flex-1 relative">
                                    <span
                                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-emerald-500 text-lg">check_circle</span>
                                    <input
                                        class="w-full bg-white border border-slate-200 rounded-lg pl-10 pr-4 py-2 text-sm"
                                        type="text" value="Unlimited user accounts" />
                                </div>
                                <button type="button"
                                    class="remove-feature-btn text-slate-300 hover:text-error transition-colors">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </div>

                            <div class="flex items-center gap-3 feature-row">
                                <div class="flex-1 relative">
                                    <span
                                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-emerald-500 text-lg">check_circle</span>
                                    <input
                                        class="w-full bg-white border border-slate-200 rounded-lg pl-10 pr-4 py-2 text-sm"
                                        type="text" value="24/7 technical support" />
                                </div>
                                <button type="button"
                                    class="remove-feature-btn text-slate-300 hover:text-error transition-colors">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </div>

                            <div class="flex items-center gap-3 feature-row">
                                <div class="flex-1 relative">
                                    <span
                                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-emerald-500 text-lg">check_circle</span>
                                    <input
                                        class="w-full bg-white border border-slate-100 rounded-lg pl-10 pr-4 py-2 text-sm"
                                        placeholder="Add a feature description..." type="text" />
                                </div>
                                <button type="button"
                                    class="remove-feature-btn text-slate-300 hover:text-error transition-colors">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 flex gap-4">
                        <button id="saveDraftBtn"
                            class="flex-1 py-3 border border-slate-200 text-slate-600 font-bold text-sm rounded-lg hover:bg-slate-50 active:scale-[0.99] transition-all"
                            type="button">
                            Save as Draft
                        </button>
                        <button id="publishPlanBtn" name="publishPlan" value="1"
                            class="flex-1 py-3 bg-primary text-white font-bold text-sm rounded-lg hover:opacity-90 active:scale-[0.99] transition-all"
                            type="submit">
                            Publish Plan
                        </button>
                    </div>
                </form>

                <div class="px-8 py-4 bg-slate-50 border-t border-slate-100 flex justify-center">
                    <p class="text-[10px] text-slate-400 font-medium uppercase tracking-[0.1em]">Changes will be applied
                        to all new sign-ups immediately</p>
                </div>
            </div>
        </div>
    </main>

    <script>
            (function setupGlobalSearchFilters() {
                const searchInput = document.getElementById('globalSearchInput');
                const scopeButtons = Array.from(document.querySelectorAll('.search-scope-btn'));
                const planCards = Array.from(document.querySelectorAll('.searchable-plan'));
                const subscriptionRows = Array.from(document.querySelectorAll('.searchable-subscription'));
                const plansEmpty = document.getElementById('plansSearchEmpty');
                const subscriptionsEmpty = document.getElementById('subscriptionsSearchEmpty');

                if (!searchInput || scopeButtons.length === 0) {
                    return;
                }

                let currentScope = 'all';

                function updateScopeButtonStyles() {
                    scopeButtons.forEach(function (button) {
                        const isActive = (button.dataset.scope || 'all') === currentScope;
                        button.className = isActive
                            ? 'search-scope-btn px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide bg-primary text-white'
                            : 'search-scope-btn px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide bg-slate-100 text-slate-600 hover:bg-slate-200';
                    });
                }

                function applySearch() {
                    const query = searchInput.value.trim().toLowerCase();
                    let visiblePlanCount = 0;
                    let visibleSubscriptionCount = 0;

                    planCards.forEach(function (card) {
                        const shouldSearchPlans = currentScope === 'all' || currentScope === 'plans';
                        const matches = query === '' || (card.dataset.search || '').includes(query);
                        const visible = shouldSearchPlans ? matches : true;
                        card.classList.toggle('hidden', !visible);
                        if (visible) {
                            visiblePlanCount++;
                        }
                    });

                    subscriptionRows.forEach(function (row) {
                        const shouldSearchSubscriptions = currentScope === 'all' || currentScope === 'subscriptions';
                        const matches = query === '' || (row.dataset.search || '').includes(query);
                        const visible = shouldSearchSubscriptions ? matches : true;
                        row.classList.toggle('hidden', !visible);
                        if (visible) {
                            visibleSubscriptionCount++;
                        }
                    });

                    if (plansEmpty) {
                        const shouldShow = query !== ''
                            && (currentScope === 'all' || currentScope === 'plans')
                            && planCards.length > 0
                            && visiblePlanCount === 0;
                        plansEmpty.classList.toggle('hidden', !shouldShow);
                    }

                    if (subscriptionsEmpty) {
                        const shouldShow = query !== ''
                            && (currentScope === 'all' || currentScope === 'subscriptions')
                            && subscriptionRows.length > 0
                            && visibleSubscriptionCount === 0;
                        subscriptionsEmpty.classList.toggle('hidden', !shouldShow);
                    }
                }

                scopeButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        currentScope = button.dataset.scope || 'all';
                        updateScopeButtonStyles();
                        applySearch();
                    });
                });

                searchInput.addEventListener('input', applySearch);

                updateScopeButtonStyles();
                applySearch();
            })();

        function createFeatureRowMarkup(value) {
            const safeValue = String(value || '').replace(/"/g, '&quot;');
            return "<div class=\"flex items-center gap-3 feature-row\"><div class=\"flex-1 relative\"><span class=\"material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-emerald-500 text-lg\">check_circle</span><input class=\"w-full bg-white border border-slate-100 rounded-lg pl-10 pr-4 py-2 text-sm\" placeholder=\"Add a feature description...\" type=\"text\" value=\"" + safeValue + "\" /></div><button type=\"button\" class=\"remove-feature-btn text-slate-300 hover:text-error transition-colors\"><span class=\"material-symbols-outlined\">delete</span></button></div>";
        }

        function openCreatePlanModal() {
            document.getElementById('createPlanModal').classList.remove('hidden');
        }

        function closeCreatePlanModal() {
            document.getElementById('createPlanModal').classList.add('hidden');
        }

        (function setupCreatePlanModal() {
            const modal = document.getElementById('createPlanModal');
            const form = document.getElementById('createPlanForm');
            const addFeatureBtn = document.getElementById('addFeatureBtn');
            const featureList = document.getElementById('featureList');
            const planNameInput = document.getElementById('planNameInput');
            const planPriceInput = document.getElementById('planPriceInput');
            const planFeaturesInput = document.getElementById('planFeaturesInput');
            const saveDraftBtn = document.getElementById('saveDraftBtn');

            if (!modal || !form || !addFeatureBtn || !featureList || !planNameInput || !planPriceInput || !planFeaturesInput || !saveDraftBtn) {
                return;
            }

            function getFeatureValues() {
                return Array.from(featureList.querySelectorAll('input')).map((el) => el.value.trim()).filter(Boolean);
            }

            function createFeatureRow(value = '') {
                const row = document.createElement('div');
                row.className = 'flex items-center gap-3 feature-row';
                row.innerHTML = createFeatureRowMarkup(value);
                return row;
            }

            addFeatureBtn.addEventListener('click', function () {
                featureList.appendChild(createFeatureRow(''));
            });

            featureList.addEventListener('click', function (event) {
                const button = event.target.closest('.remove-feature-btn');
                if (!button) {
                    return;
                }

                const rows = featureList.querySelectorAll('.feature-row');
                if (rows.length <= 1) {
                    alert('At least one feature row is required.');
                    return;
                }

                const row = button.closest('.feature-row');
                if (row) {
                    row.remove();
                }
            });

            saveDraftBtn.addEventListener('click', function () {
                const payload = {
                    planName: planNameInput.value.trim(),
                    monthlyPrice: planPriceInput.value,
                    features: getFeatureValues(),
                    savedAt: new Date().toISOString()
                };
                localStorage.setItem('subscription_plan_draft', JSON.stringify(payload));
                alert('Draft saved.');
            });

            form.addEventListener('submit', function (event) {

                const planName = planNameInput.value.trim();
                const monthlyPrice = parseFloat(planPriceInput.value);
                const features = getFeatureValues();

                if (!planName) {
                    event.preventDefault();
                    alert('Please enter a plan name.');
                    planNameInput.focus();
                    return;
                }

                if (Number.isNaN(monthlyPrice) || monthlyPrice <= 0) {
                    event.preventDefault();
                    alert('Please enter a valid monthly price greater than 0.');
                    planPriceInput.focus();
                    return;
                }

                if (features.length === 0) {
                    event.preventDefault();
                    alert('Please add at least one feature.');
                    return;
                }

                planFeaturesInput.value = JSON.stringify(features);
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeCreatePlanModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeCreatePlanModal();
                }
            });
        })();

        (function setupEditPlanModal() {
            const modal = document.getElementById('editPlanModal');
            const form = document.getElementById('editPlanForm');
            const planIdInput = document.getElementById('editPlanIdInput');
            const planCodeInput = document.getElementById('editPlanCodeInput');
            const nameInput = document.getElementById('editPlanNameInput');
            const priceInput = document.getElementById('editPlanPriceInput');
            const featureList = document.getElementById('editFeatureList');
            const addFeatureBtn = document.getElementById('editAddFeatureBtn');
            const featuresInput = document.getElementById('editPlanFeaturesInput');

            if (!modal || !form || !nameInput || !priceInput || !featureList || !addFeatureBtn || !featuresInput) {
                return;
            }

            function addFeatureRow(value) {
                const row = document.createElement('div');
                row.className = 'flex items-center gap-3 feature-row';
                row.innerHTML = createFeatureRowMarkup(value || '');
                featureList.appendChild(row);
            }

            function setFeatureRows(features) {
                featureList.innerHTML = '';
                if (!Array.isArray(features) || features.length === 0) {
                    addFeatureRow('');
                    return;
                }
                features.forEach(function (feature) {
                    addFeatureRow(String(feature));
                });
            }

            function getFeatureValues() {
                return Array.from(featureList.querySelectorAll('input')).map((el) => el.value.trim()).filter(Boolean);
            }

            window.closeEditPlanModal = function () {
                modal.classList.add('hidden');
            };

            document.querySelectorAll('.edit-plan-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (planIdInput) {
                        planIdInput.value = button.dataset.planId || '';
                    }
                    if (planCodeInput) {
                        planCodeInput.value = button.dataset.planCode || '';
                    }
                    nameInput.value = button.dataset.planName || '';
                    priceInput.value = button.dataset.planPrice || '';

                    let features = [];
                    try {
                        const parsed = JSON.parse(button.dataset.planFeatures || '[]');
                        if (Array.isArray(parsed)) {
                            features = parsed;
                        }
                    } catch (e) {
                        features = [];
                    }

                    setFeatureRows(features);
                    modal.classList.remove('hidden');
                });
            });

            addFeatureBtn.addEventListener('click', function () {
                addFeatureRow('');
            });

            featureList.addEventListener('click', function (event) {
                const button = event.target.closest('.remove-feature-btn');
                if (!button) {
                    return;
                }

                const rows = featureList.querySelectorAll('.feature-row');
                if (rows.length <= 1) {
                    alert('At least one feature row is required.');
                    return;
                }

                const row = button.closest('.feature-row');
                if (row) {
                    row.remove();
                }
            });

            form.addEventListener('submit', function (event) {
                const name = nameInput.value.trim();
                const price = parseFloat(priceInput.value);
                const features = getFeatureValues();

                if (!name) {
                    event.preventDefault();
                    alert('Please enter a plan name.');
                    nameInput.focus();
                    return;
                }

                if (Number.isNaN(price) || price <= 0) {
                    event.preventDefault();
                    alert('Please enter a valid monthly price greater than 0.');
                    priceInput.focus();
                    return;
                }

                if (features.length === 0) {
                    event.preventDefault();
                    alert('Please add at least one feature.');
                    return;
                }

                featuresInput.value = JSON.stringify(features);
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeEditPlanModal();
                }
            });
        })();

        // Initialize MRR Chart with Billing Cycle Filters
        let currentChart = null;
        const chartLabels = <?php echo json_encode($mrrLabels); ?>;
        const monthlyData = <?php echo json_encode($mrrData); ?>;
        
        // Setup Subscription Table Filters
        (function setupSubscriptionFilters() {
            const dateRangeSelect = document.getElementById('dateRangeFilter');
            const tenantSelect = document.getElementById('tenantFilter');
            const statusSelect = document.getElementById('statusFilter');
            const billingCycleSelect = document.getElementById('billingCycleFilterSelect');
            const applyFiltersBtn = document.getElementById('applyFiltersBtn');
            const subscriptionRows = document.querySelectorAll('.searchable-subscription');
            const subscriptionsEmpty = document.getElementById('subscriptionsSearchEmpty');
            
            let filters = {
                dateRange: 'last_30_days',
                tenant: '',
                status: '',
                billingCycle: ''
            };
            
            function getDateRange(rangeType) {
                const today = new Date();
                const endDate = new Date(today);
                let startDate = new Date(today);
                
                switch(rangeType) {
                    case 'last_7_days':
                        startDate.setDate(today.getDate() - 7);
                        break;
                    case 'last_30_days':
                        startDate.setDate(today.getDate() - 30);
                        break;
                    case 'last_90_days':
                        startDate.setDate(today.getDate() - 90);
                        break;
                    case 'last_year':
                        startDate.setFullYear(today.getFullYear() - 1);
                        break;
                    case 'all_time':
                        startDate = new Date('2000-01-01');
                        break;
                    default:
                        startDate.setDate(today.getDate() - 30);
                }
                
                return { startDate, endDate };
            }
            
            function normalizeBillingCycle(cycle) {
                const normalized = cycle.toLowerCase().trim();
                const cycleMap = {
                    'quarterly': 'quarterly',
                    'quarter': 'quarterly',
                    'semi-annual': 'semiannual',
                    'semiannual': 'semiannual',
                    'semi annual': 'semiannual',
                    'biannual': 'semiannual',
                    'annual': 'annual',
                    'annually': 'annual',
                    'yearly': 'annual',
                    'monthly': 'monthly',
                    'month': 'monthly',
                    'weekly': 'weekly',
                    'week': 'weekly',
                    'daily': 'daily',
                    'day': 'daily'
                };
                return cycleMap[normalized] || normalized;
            }
            
            function applyFilters() {
                const dateRange = getDateRange(filters.dateRange);
                let visibleCount = 0;
                
                subscriptionRows.forEach(function(row) {
                    const billingCycle = normalizeBillingCycle(row.getAttribute('data-billing-cycle') || '');
                    const tenantId = (row.getAttribute('data-tenant-id') || '').toLowerCase();
                    const status = (row.getAttribute('data-status') || '').toLowerCase();
                    const subscriptionStart = row.getAttribute('data-subscription-start') || '';
                    const nextBilling = row.getAttribute('data-next-billing') || '';
                    
                    // Check date range filter
                    let dateMatch = true;
                    if (filters.dateRange !== 'all_time') {
                        const startDate = new Date(subscriptionStart);
                        dateMatch = startDate >= dateRange.startDate && startDate <= dateRange.endDate;
                    }
                    
                    // Check tenant filter
                    let tenantMatch = filters.tenant === '' || tenantId === filters.tenant.toLowerCase();
                    
                    // Check status filter
                    let statusMatch = filters.status === '' || status === filters.status.toLowerCase();
                    
                    // Check billing cycle filter  
                    let cycleMatch = filters.billingCycle === '' || billingCycle === filters.billingCycle;
                    
                    const shouldShow = dateMatch && tenantMatch && statusMatch && cycleMatch;
                    row.style.display = shouldShow ? '' : 'none';
                    
                    if (shouldShow) {
                        visibleCount++;
                    }
                });
                
                // Show/hide empty message
                if (subscriptionsEmpty) {
                    subscriptionsEmpty.style.display = visibleCount === 0 ? '' : 'none';
                }
            }
            
            // Setup apply filters button
            if (applyFiltersBtn) {
                applyFiltersBtn.addEventListener('click', function() {
                    filters.dateRange = dateRangeSelect.value;
                    filters.tenant = tenantSelect.value;
                    filters.status = statusSelect.value;
                    filters.billingCycle = billingCycleSelect.value;
                    applyFilters();
                });
            }
            
            // Set initial filter application on page load
            if (dateRangeSelect && tenantSelect && statusSelect && billingCycleSelect) {
                filters.dateRange = dateRangeSelect.value || 'last_30_days';
                filters.tenant = tenantSelect.value || '';
                filters.status = statusSelect.value || '';
                filters.billingCycle = billingCycleSelect.value || '';
                applyFilters();
            }
        })();
        
        // Setup PDF Export
        (function setupPdfExport() {
            const exportBtn = document.getElementById('exportPdfBtn');
            const table = document.getElementById('subscriptionsTable');
            
            if (!exportBtn || !table) {
                console.error('PDF Export: Missing elements');
                return;
            }
            
            exportBtn.addEventListener('click', function() {
                const now = new Date();
                const dateStr = now.toLocaleDateString('en-US', {year: 'numeric', month: '2-digit', day: '2-digit'});
                const timeStr = now.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                
                // Clone the entire document to prepare PDF content
                const wrapper = document.createElement('div');
                wrapper.style.position = 'absolute';
                wrapper.style.left = '-9999px';
                wrapper.style.top = '-9999px';
                wrapper.style.width = '1400px';
                wrapper.style.backgroundColor = 'white';
                wrapper.style.padding = '20px';
                wrapper.style.fontFamily = 'Arial, sans-serif';
                
                // Build content
                let html = '<div style="width: 100%; color: #000;">';
                
                // Title
                html += '<h1 style="font-size: 28px; font-weight: bold; text-align: center; margin: 0 0 10px 0; color: #000;">Active Subscriptions Report</h1>';
                html += '<p style="text-align: center; margin: 0 0 5px 0; font-size: 12px; color: #666;">RapidRepair Subscription Management System</p>';
                html += '<p style="text-align: center; margin: 0 0 20px 0; font-size: 11px; color: #999;">Generated on ' + dateStr + ' at ' + timeStr + '</p>';
                
                // Summary
                let rowCount = 0;
                const tbody = table.querySelector('tbody');
                const rows = tbody.querySelectorAll('tr');
                
                rows.forEach(function(row) {
                    if (row.style.display !== 'none' && row.id !== 'subscriptionsSearchEmpty') {
                        const cells = row.querySelectorAll('td');
                        if (cells.length >= 6) {
                            rowCount++;
                        }
                    }
                });
                
                if (rowCount > 0) {
                    html += '<div style="background-color: #e0f2fe; border-left: 4px solid #0284c7; padding: 12px; margin-bottom: 20px; font-size: 12px;">';
                    html += '<strong>Total Active Subscriptions:</strong> ' + rowCount;
                    html += '</div>';
                }
                
                // Detailed Subscriptions Table
                html += '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
                html += '<thead>';
                html += '<tr style="background-color: #1e293b; color: white; border: 2px solid #333;">';
                html += '<th style="padding: 10px; text-align: left; font-weight: bold; border: 1px solid #333; font-size: 11px;">Shop Name</th>';
                html += '<th style="padding: 10px; text-align: left; font-weight: bold; border: 1px solid #333; font-size: 11px;">Owner</th>';
                html += '<th style="padding: 10px; text-align: left; font-weight: bold; border: 1px solid #333; font-size: 11px;">Email</th>';
                html += '<th style="padding: 10px; text-align: left; font-weight: bold; border: 1px solid #333; font-size: 11px;">Plan</th>';
                html += '<th style="padding: 10px; text-align: left; font-weight: bold; border: 1px solid #333; font-size: 11px;">Billing Cycle</th>';
                html += '<th style="padding: 10px; text-align: right; font-weight: bold; border: 1px solid #333; font-size: 11px;">Amount</th>';
                html += '<th style="padding: 10px; text-align: left; font-weight: bold; border: 1px solid #333; font-size: 11px;">Start Date</th>';
                html += '<th style="padding: 10px; text-align: left; font-weight: bold; border: 1px solid #333; font-size: 11px;">Next Billing</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';
                
                let count = 0;
                let totalMRR = 0;
                
                rows.forEach(function(row) {
                    if (row.style.display === 'none' || row.id === 'subscriptionsSearchEmpty') {
                        return;
                    }
                    
                    const cells = row.querySelectorAll('td');
                    if (cells.length < 6) {
                        return;
                    }
                    
                    // Extract data from row
                    const shopName = row.getAttribute('data-tenant') ? 
                        (cells[0].innerText || cells[0].textContent).trim() : '';
                    const ownerName = row.getAttribute('data-owner-name') || '';
                    const ownerEmail = row.getAttribute('data-owner-email') || '';
                    const planName = (cells[1].innerText || cells[1].textContent).trim();
                    const billingCycle = (cells[2].innerText || cells[2].textContent).trim();
                    const monthlyRate = parseFloat(row.getAttribute('data-monthly-rate')) || 0;
                    const totalAmount = parseFloat(row.getAttribute('data-total-amount')) || 0;
                    const startDate = row.getAttribute('data-subscription-start') ? 
                        new Date(row.getAttribute('data-subscription-start')).toLocaleDateString('en-US', {month: 'short', day: '2-digit', year: 'numeric'}) : '';
                    const nextBilling = row.getAttribute('data-next-billing') ? 
                        new Date(row.getAttribute('data-next-billing')).toLocaleDateString('en-US', {month: 'short', day: '2-digit', year: 'numeric'}) : '';
                    
                    const bgColor = count % 2 === 0 ? '#ffffff' : '#f9fafb';
                    html += '<tr style="background-color: ' + bgColor + '; border: 1px solid #d1d5db;">';
                    html += '<td style="padding: 10px; border: 1px solid #d1d5db; font-size: 10px; color: #000; font-weight: 600;">' + shopName + '</td>';
                    html += '<td style="padding: 10px; border: 1px solid #d1d5db; font-size: 10px; color: #000;">' + ownerName + '</td>';
                    html += '<td style="padding: 10px; border: 1px solid #d1d5db; font-size: 10px; color: #000;">' + ownerEmail + '</td>';
                    html += '<td style="padding: 10px; border: 1px solid #d1d5db; font-size: 10px; color: #000;">' + planName + '</td>';
                    html += '<td style="padding: 10px; border: 1px solid #d1d5db; font-size: 10px; color: #000;">' + billingCycle + '</td>';
                    html += '<td style="padding: 10px; border: 1px solid #d1d5db; font-size: 10px; color: #000; text-align: right; font-weight: bold;">₱' + totalAmount.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
                    html += '<td style="padding: 10px; border: 1px solid #d1d5db; font-size: 10px; color: #000;">' + startDate + '</td>';
                    html += '<td style="padding: 10px; border: 1px solid #d1d5db; font-size: 10px; color: #000;">' + nextBilling + '</td>';
                    html += '</tr>';
                    
                    totalMRR += monthlyRate;
                    count++;
                });
                
                if (count === 0) {
                    html += '<tr>';
                    html += '<td colspan="8" style="padding: 20px; text-align: center; color: #999; font-size: 12px;">No subscriptions to display</td>';
                    html += '</tr>';
                }
                
                html += '</tbody>';
                html += '</table>';
                
                // Summary Footer
                if (count > 0) {
                    html += '<div style="margin-top: 20px; border-top: 2px solid #e5e7eb; padding-top: 15px;">';
                    html += '<div style="display: flex; justify-content: flex-end; gap: 40px; margin-top: 10px;">';
                    html += '<div style="text-align: right;">';
                    html += '<p style="margin: 0; font-size: 11px; color: #666;">Total Active Subscriptions</p>';
                    html += '<p style="margin: 5px 0 0 0; font-size: 16px; font-weight: bold; color: #000;">' + count + '</p>';
                    html += '</div>';
                    html += '<div style="text-align: right;">';
                    html += '<p style="margin: 0; font-size: 11px; color: #666;">Monthly Recurring Revenue (MRR)</p>';
                    html += '<p style="margin: 5px 0 0 0; font-size: 16px; font-weight: bold; color: #059669;">₱' + totalMRR.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</p>';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                }
                
                html += '</div>';
                
                wrapper.innerHTML = html;
                document.body.appendChild(wrapper);
                
                const element = wrapper.querySelector('div');
                
                const options = {
                    margin: 10,
                    filename: 'subscription-report-' + now.toISOString().split('T')[0] + '.pdf',
                    image: {type: 'jpeg', quality: 0.98},
                    html2canvas: {scale: 2},
                    jsPDF: {orientation: 'landscape', unit: 'mm', format: 'a4'}
                };
                
                html2pdf()
                    .set(options)
                    .from(element)
                    .save()
                    .then(function() {
                        document.body.removeChild(wrapper);
                    })
                    .catch(function(err) {
                        console.error('PDF Error:', err);
                        document.body.removeChild(wrapper);
                    });
            });
        })();

        
        // Generate quarterly data (multiply monthly by 3 to show actual quarterly rate)
        const quarterlyData = [];
        for (let i = 0; i < monthlyData.length; i += 3) {
            const q = ((monthlyData[i] || 0) + (monthlyData[i + 1] || 0) + (monthlyData[i + 2] || 0)) * 3;
            quarterlyData.push(Math.round(q));
        }
        
        // Generate quarterly labels
        const quarterlyLabels = [];
        for (let i = 0; i < chartLabels.length; i += 3) {
            quarterlyLabels.push('Q' + Math.ceil((i / 3) + 1));
        }
        
        // Generate yearly data (multiply monthly by 12 to show actual yearly rate)
        const yearlyData = [Math.round(monthlyData.reduce((a, b) => a + (b || 0), 0) * 12)];
        const yearlyLabels = ['Year Total'];
        
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('mrrChart');
            if (ctx) {
                try {
                    currentChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                label: 'Monthly Recurring Revenue',
                                data: monthlyData.map(function(val) {
                                    return parseFloat(val) || 0;
                                }),
                                borderColor: '#b91c1c',
                                backgroundColor: 'rgba(185, 28, 28, 0.16)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 4,
                                pointBackgroundColor: '#b91c1c',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                pointHoverRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        maxRotation: 0,
                                        autoSkip: true
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                } catch(e) {
                    console.error('Chart initialization error:', e);
                }
            }
            
            // Setup billing cycle filter buttons
            const filterButtons = document.querySelectorAll('.billing-filter');
            filterButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const cycle = this.dataset.cycle;
                    
                    // Update button styles
                    filterButtons.forEach(function(btn) {
                        btn.className = 'billing-filter px-3 py-1 text-[10px] font-bold rounded uppercase bg-slate-100 text-slate-500 hover:bg-slate-200';
                    });
                    this.className = 'billing-filter px-3 py-1 text-[10px] font-bold rounded uppercase bg-primary text-white';
                    
                    // Update chart data
                    if (currentChart) {
                        if (cycle === 'monthly') {
                            currentChart.data.labels = chartLabels;
                            currentChart.data.datasets[0].data = monthlyData.map(function(val) {
                                return parseFloat(val) || 0;
                            });
                            currentChart.data.datasets[0].label = 'Monthly Recurring Revenue';
                        } else if (cycle === 'quarterly') {
                            currentChart.data.labels = quarterlyLabels;
                            currentChart.data.datasets[0].data = quarterlyData;
                            currentChart.data.datasets[0].label = 'Quarterly Recurring Revenue';
                        } else if (cycle === 'yearly') {
                            currentChart.data.labels = yearlyLabels;
                            currentChart.data.datasets[0].data = yearlyData;
                            currentChart.data.datasets[0].label = 'Yearly Recurring Revenue';
                        }
                        currentChart.update();
                    }
                });
            });
        });
    </script>
    <?php echo getBackButtonDetectionScript(); ?>
</body>

</html>