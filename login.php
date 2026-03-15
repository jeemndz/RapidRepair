<?php
session_start();
require_once "db.php";
require_once "log_helper.php";

/* =========================
   DEBUG MODE (TURN ON if blank page)
========================= */
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

$error = "";

/* =========================
   FLASH MESSAGE (Option B)
========================= */
$flash_message = $_SESSION['flash_message'] ?? "";
$flash_type = $_SESSION['flash_type'] ?? "success";

if ($flash_message !== "") {
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

/* =========================
   HANDLE LOGIN POST
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "Please fill in all fields.";

        // ✅ LOG FAILED ATTEMPT (missing fields)
        log_event(
            $conn,
            "Login Failed",
            "users",
            null,
            "Missing fields | username=" . ($username !== '' ? $username : '[blank]')
        );

    } else {

        $sql = "SELECT user_id, fullName, password, role FROM users WHERE username = ? LIMIT 1";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = "Database error: " . $conn->error;

            // ✅ LOG SYSTEM ERROR
            log_event(
                $conn,
                "Login Error",
                "users",
                null,
                "Prepare failed | " . $conn->error . " | username={$username}"
            );

        } else {

            $stmt->bind_param("s", $username);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if (!$user) {
                $error = "Invalid username or password.";

                // ✅ LOG FAILED ATTEMPT (username not found)
                log_event(
                    $conn,
                    "Login Failed",
                    "users",
                    null,
                    "Username not found | username={$username}"
                );

            } else {

                $user_id = (int)$user['user_id'];
                $fullName = $user['fullName'] ?? 'User';
                $hashedPassword = $user['password'] ?? '';
                $role_clean = trim($user['role'] ?? '');

                $passwordOk = password_verify($password, $hashedPassword) || $password === $hashedPassword;

                if (!$passwordOk) {
                    $error = "Invalid username or password.";

                    // ✅ LOG FAILED ATTEMPT (wrong password)
                    log_event(
                        $conn,
                        "Login Failed",
                        "users",
                        $user_id,
                        "Wrong password | username={$username}"
                    );

                } else {

                    // ✅ Set common session values
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['name'] = $fullName;

                    /* =========================
                       ROLE ROUTING
                    ========================= */
                    if (strcasecmp($role_clean, "admin") === 0) {

                        $_SESSION['client_id'] = null;
                        $_SESSION['role'] = "admin";

                        // ✅ LOG SUCCESSFUL LOGIN
                        log_event(
                            $conn,
                            "Login Success",
                            "users",
                            $user_id,
                            "Logged in as ADMIN | username={$username}"
                        );

                        header("Location: dashboardadmin.php");
                        exit();

                    } elseif (strcasecmp($role_clean, "staff") === 0) {

                        $_SESSION['client_id'] = null;
                        $_SESSION['role'] = "staff";

                        // ✅ LOG SUCCESSFUL LOGIN
                        log_event(
                            $conn,
                            "Login Success",
                            "users",
                            $user_id,
                            "Logged in as STAFF | username={$username}"
                        );

                        header("Location: dashboard.php");
                        exit();

                    } else {

                        // ✅ Client / user
                        $stmt2 = $conn->prepare("SELECT client_id FROM client_information WHERE user_id = ? LIMIT 1");

                        if (!$stmt2) {
                            $error = "Database error: " . $conn->error;

                            // ✅ LOG ERROR
                            $_SESSION['role'] = "user"; // set for log consistency
                            log_event(
                                $conn,
                                "Login Error",
                                "client_information",
                                null,
                                "Failed to fetch client_id | " . $conn->error . " | user_id={$user_id}"
                            );

                        } else {

                            $stmt2->bind_param("i", $user_id);
                            $stmt2->execute();
                            $stmt2->bind_result($client_id);
                            $stmt2->fetch();
                            $stmt2->close();

                            if (empty($client_id)) {
                                $error = "No client profile found for this user.";

                                // ✅ LOG ISSUE: user account exists but no client profile
                                $_SESSION['role'] = "user"; // set for log consistency
                                log_event(
                                    $conn,
                                    "Login Failed",
                                    "client_information",
                                    null,
                                    "No client profile found | user_id={$user_id} | username={$username}"
                                );

                            } else {

                                $_SESSION['client_id'] = (int)$client_id;
                                $_SESSION['role'] = "user";

                                // ✅ LOG SUCCESSFUL LOGIN
                                log_event(
                                    $conn,
                                    "Login Success",
                                    "users",
                                    $user_id,
                                    "Logged in as CLIENT | username={$username} | client_id=" . (int)$client_id
                                );

                                header("Location: user_home.php");
                                exit();
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign In | Rapid Repair</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style2.css">
</head>
<body>

<div class="login-container">

    <div class="logo">
        <img src="rapidlogo.png" alt="Rapid Repair Logo">
        <p class="tagline">Commitment is our Passion</p>
    </div>

    <h1>Sign In</h1>
    <p class="subtitle">Please log in to your account</p>

    <!-- ✅ FLASH MESSAGE -->
    <?php if ($flash_message !== ""): ?>
        <div class="alert <?= htmlspecialchars($flash_type) ?>">
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <!-- ✅ ERROR MESSAGE -->
    <?php if ($error !== ""): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <input type="text" name="username" placeholder="Username" required value="<?= htmlspecialchars($username ?? '') ?>">
        <input type="password" name="password" id="password" placeholder="Password" required>

        <div class="show-password">
            <input type="checkbox" id="showPass" onclick="togglePassword()">
            <label for="showPass">Show Password</label>
        </div>

        <button type="submit" class="login-btn">Log In</button>
    </form>

    <p class="login-link">
        <a href="forgot_password.php">Forgot password?</a>
    </p>

    <p class="signup">
        Don’t have an account? <a href="register.php">Sign up</a>
    </p>

</div>

<script>
function togglePassword() {
    const pass = document.getElementById("password");
    pass.type = pass.type === "password" ? "text" : "password";
}
</script>

</body>
</html>
