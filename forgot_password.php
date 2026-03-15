<?php
session_start();
require_once "db.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = "Please enter your email address.";
    } else {

        // Check if email exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user) {

                // Generate token + expiry
                $token = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", time() + 3600); // +1 hour

                // Save token to DB
                $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
                if (!$update) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $update->bind_param("sss", $token, $expires, $email);
                    $update->execute();
                    $update->close();

                    // IMPORTANT: localhost links from email often won't open on phone
                    // Use your PC browser (same machine) OR replace localhost with your LAN IP.
                    $resetLink = "http://localhost/RapidRepair/reset_password.php?token=" . urlencode($token);

                    // Send email
                    $mail = new PHPMailer(true);

                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;

                        // ✅ CHANGE THESE
                        $mail->Username   = 'ekalamosus224@gmail.com';
                        $mail->Password   = 'gppg pfex llqm cxox';

                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        // Debug (optional) - if not working, enable this temporarily:
                        // $mail->SMTPDebug = 2;

                        $mail->setFrom($mail->Username, 'Rapid Repair');
                        $mail->addAddress($email);

                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset - Rapid Repair';

                        $mail->Body = "
                            <div style='font-family:Arial,sans-serif;line-height:1.5'>
                              <h2>Password Reset</h2>
                              <p>You requested a password reset for your Rapid Repair account.</p>
                              <p>
                                <a href='{$resetLink}'
                                   style='display:inline-block;padding:12px 18px;background:#1492ff;color:#fff;
                                          text-decoration:none;border-radius:6px;'>
                                  Reset Password
                                </a>
                              </p>
                              <p style='color:#666;font-size:13px'>This link will expire in 1 hour.</p>
                            </div>
                        ";

                        $mail->AltBody = "Reset your password using this link: $resetLink (expires in 1 hour)";

                        $mail->send();
                        $message = "Password reset link has been sent to your email.";

                    } catch (Exception $e) {
                        $error = "Mailer Error: " . $mail->ErrorInfo;
                    }
                }

            } else {
                $error = "Email address not found.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | Rapid Repair</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style1.css">
</head>
<body>

<div class="login-container">
    <h1>Forgot Password</h1>
    <p class="subtitle">Enter your email to reset your password</p>

    <form method="POST">
        <input type="email" name="email" placeholder="Email address" required>

        <?php if ($error !== ""): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($message !== ""): ?>
            <p style="color:#7CFC9A;font-size:14px;"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <button type="submit">Send Reset Link</button>
    </form>

    <p class="login-link"><a href="login.php">Back to login</a></p>
</div>

<div class="wave wave1"></div>
<div class="wave wave2"></div>

</body>
</html>
