<?php
// log_helper.php
// usage: log_event($conn, "Create Invoice", "services", 123, "Invoice created for appointment #45");

function log_event(mysqli $conn, string $action, ?string $entity_type = null, ?int $entity_id = null, ?string $details = null): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // -----------------------------
    // Get actor from session
    // -----------------------------
    $user_id_session   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $user_name_session = $_SESSION['name'] ?? ($_SESSION['username'] ?? null);
    $user_role_session = $_SESSION['role'] ?? null;

    $user_id   = $user_id_session;
    $user_name = $user_name_session;
    $user_role = $user_role_session;

    // -----------------------------
    // ✅ Stronger: fetch role + name from DB using user_id
    // (prevents wrong session role = always admin)
    // -----------------------------
    if ($user_id !== null && $user_id > 0) {
        // Adjust column names if yours differ:
        // common: users(user_id, fullName, username, role)
        $q = $conn->prepare("SELECT role, fullName, username FROM users WHERE user_id = ? LIMIT 1");
        if ($q) {
            $q->bind_param("i", $user_id);
            if ($q->execute()) {
                $res = $q->get_result();
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();

                    // prefer DB values
                    $user_role = $row['role'] ?? $user_role;
                    $user_name = $row['fullName'] ?? ($row['username'] ?? $user_name);
                }
            }
            $q->close();
        }
    }

    // -----------------------------
    // Normalize role to avoid weird values
    // -----------------------------
    $user_role = strtolower(trim((string)$user_role));
    if ($user_role === '') {
        $user_role = null;
    } else {
        // optional: restrict to only known roles
        $allowed = ['admin', 'staff'];
        if (!in_array($user_role, $allowed, true)) {
            // keep original but you can also set to null if you want
            // $user_role = null;
        }
    }

    // -----------------------------
    // Meta info
    // -----------------------------
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // -----------------------------
    // Prepare insert
    // -----------------------------
    $stmt = $conn->prepare("
        INSERT INTO system_logs
        (user_id, user_name, user_role, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        return; // fail silently (or throw if you prefer)
    }

    // Bind NULL safely
    $user_id_val   = ($user_id !== null && $user_id > 0) ? $user_id : null;
    $entity_id_val = ($entity_id !== null) ? (int)$entity_id : null;

    // IMPORTANT:
    // mysqli bind_param works with NULL values, but variable must be set as null.
    $stmt->bind_param(
        "issssisss",
        $user_id_val,
        $user_name,
        $user_role,
        $action,
        $entity_type,
        $entity_id_val,
        $details,
        $ip,
        $ua
    );

    $stmt->execute();
    $stmt->close();
}
