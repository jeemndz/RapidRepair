<?php
session_start();

$old = $_SESSION['old'] ?? [];

function old_value($key, $old)
{
    return htmlspecialchars($old[$key] ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create Account | Rapid Repair</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style1.css">

    <style>
        .alert {
            padding: 14px 18px;
            margin: 12px 0 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.4;
        }

        .alert.success {
            background: #e6f9f0;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        .alert.error {
            background: #fdecea;
            color: #842029;
            border: 1px solid #f5c2c7;
        }
    </style>
</head>

<body>

    <div class="container">

        <div class="logo">
            <img src="rapidlogo.png" alt="Rapid Repair Logo" class="logo-img">
            <p class="tagline">Commitment is our Passion</p>
        </div>

        <div class="right">
            <h1>Create an Account</h1>
            <p class="subtitle">
                Enter the following information needed below to create an account.
            </p>

            <!-- ✅ FLASH MESSAGE DISPLAY -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert <?= htmlspecialchars($_SESSION['flash_type'] ?? 'error'); ?>">
                    <?= htmlspecialchars($_SESSION['flash_message']); ?>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <form action="register_process.php" method="POST">

                <label for="fullname">Full Name</label>
                <input id="fullname" type="text" name="fullname" required value="<?= old_value('fullname', $old) ?>">

                <label for="username">Username</label>
                <input id="username" type="text" name="username" required value="<?= old_value('username', $old) ?>">

                <label for="email">Email</label>
                <input id="email" type="email" name="email" required value="<?= old_value('email', $old) ?>">

                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>

                <label for="confirm_password">Confirm Password</label>
                <input id="confirm_password" type="password" name="confirm_password" required>

                <button type="submit" class="register-btn">Register</button>
            </form>

            <p class="login-link">
                Already have an account? <a href="login.php">Log In</a>
            </p>

            <?php
            // ✅ Clear old values after rendering the page
            unset($_SESSION['old']);
            ?>
        </div>
    </div>

</body>

</html>