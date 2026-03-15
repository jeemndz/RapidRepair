<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once "db.php";

$error = "";
$message = "";

// token from GET first, then POST on submit
$token = trim($_GET['token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
}

if ($token === '') {
    die("Invalid reset link (missing token).");
}

function fetchUserByToken(mysqli $conn, string $token): ?array {
    $stmt = $conn->prepare("SELECT user_id, reset_expires FROM users WHERE reset_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Validate token on GET (before showing form)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = fetchUserByToken($conn, $token);

    if (!$user) {
        $error = "Reset token not found in database.";
    } elseif (empty($user['reset_expires']) || strtotime($user['reset_expires']) < time()) {
        $error = "Reset link has expired. Please request again.";
    }
}

// On POST: update password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($password === '' || $password2 === '') {
        $error = "Please fill in both password fields.";
    } elseif ($password !== $password2) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $user = fetchUserByToken($conn, $token);

        if (!$user) {
            $error = "Reset token not found in database (POST).";
        } elseif (empty($user['reset_expires']) || strtotime($user['reset_expires']) < time()) {
            $error = "Reset link has expired. Please request again.";
        } else {
            $user_id = (int)$user['user_id'];
            $hashed  = password_hash($password, PASSWORD_DEFAULT);

            $upd = $conn->prepare("
                UPDATE users
                SET password = ?, reset_token = NULL, reset_expires = NULL
                WHERE user_id = ?
                LIMIT 1
            ");
            $upd->bind_param("si", $hashed, $user_id);
            $upd->execute();

            if ($upd->affected_rows < 1) {
                // This means UPDATE ran but nothing changed
                // Usually wrong database / user_id not matched / query not hitting row
                $error = "UPDATE executed but no rows were changed. Check if you're connected to the correct database.";
            } else {
                $message = "Password updated successfully. You can now log in.";
            }

            $upd->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password | Rapid Repair</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style1.css">
</head>
<body>

<div class="login-container">
  <h1>Reset Password</h1>
  <p class="subtitle">Enter your new password</p>

  <?php if ($error !== ""): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
    <p class="login-link"><a href="forgot_password.php">Request new reset link</a></p>
  <?php endif; ?>

  <?php if ($message !== ""): ?>
    <p style="color:#7CFC9A;font-size:14px;"><?= htmlspecialchars($message) ?></p>
    <p class="login-link"><a href="login.php">Back to login</a></p>
  <?php elseif ($error === ""): ?>
    <form method="POST">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <input type="password" name="password" placeholder="New password" required>
      <input type="password" name="password2" placeholder="Confirm new password" required>
      <button type="submit">Update Password</button>
    </form>
  <?php endif; ?>
</div>

</body>
</html>
