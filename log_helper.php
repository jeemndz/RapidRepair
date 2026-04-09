<?php
// log_helper.php
// usage: log_event($conn, "Create Invoice", "services", 123, "Invoice created for appointment #45");

// Ensure system_logs table has proper schema
function ensure_system_logs_schema(mysqli $conn): void
{
    // Check if system_logs table exists
    $check = $conn->query("SHOW TABLES LIKE 'system_logs'");
    if (!$check || $check->num_rows === 0) {
        // Table doesn't exist, create it
        $createSql = "
            CREATE TABLE IF NOT EXISTS system_logs (
                log_id INT AUTO_INCREMENT PRIMARY KEY,
                tenantID INT NULL,
                user_id INT NULL,
                user_name VARCHAR(100),
                user_role VARCHAR(100),
                action VARCHAR(255),
                entity_type VARCHAR(50),
                entity_id INT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                user_agent VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action (action),
                INDEX idx_entity_type (entity_type),
                INDEX idx_created_at (created_at),
                INDEX idx_user_name (user_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $conn->query($createSql);
    } else {
        // Table exists, make tenantID nullable if it isn't already
        $checkTenant = $conn->query("
            SELECT IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'system_logs' 
            AND COLUMN_NAME = 'tenantID'
        ");
        if ($checkTenant && $checkTenant->num_rows > 0) {
            $row = $checkTenant->fetch_assoc();
            $isNullable = $row['IS_NULLABLE'] === 'YES';
            $hasDefault = $row['COLUMN_DEFAULT'] !== null;
            
            // If tenantID is NOT NULL and has no default, make it nullable
            if (!$isNullable && !$hasDefault) {
                $conn->query("ALTER TABLE system_logs MODIFY COLUMN tenantID INT NULL");
            }
        }
        
        // Check action column size - should be 255
        $checkCol = $conn->query("
            SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'system_logs' 
            AND COLUMN_NAME = 'action'
        ");
        if ($checkCol && $row = $checkCol->fetch_assoc()) {
            $maxLen = (int)($row['CHARACTER_MAXIMUM_LENGTH'] ?? 50);
            if ($maxLen < 255) {
                $conn->query("ALTER TABLE system_logs MODIFY COLUMN action VARCHAR(255)");
            }
        }
    }
}

function log_event(mysqli $conn, string $action, ?string $entity_type = null, ?int $entity_id = null, ?string $details = null): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Ensure schema is correct
    static $schema_checked = false;
    if (!$schema_checked) {
        ensure_system_logs_schema($conn);
        $schema_checked = true;
    }

    // Truncate action to fit in column (just in case)
    $action = substr(trim((string)$action), 0, 255);

    // Truncate entity_type to fit in column (varchar(50))
    if ($entity_type !== null) {
        $entity_type = substr(trim((string)$entity_type), 0, 50);
    }

    // Truncate details if needed
    if ($details !== null) {
        $details = trim((string)$details);
        // LONGTEXT can hold very large amounts, but let's limit to 65000 chars just to be safe
        if (strlen($details) > 65000) {
            $details = substr($details, 0, 65000);
        }
    }

    // -----------------------------
    // Get actor from session - check for superadmin first
    // -----------------------------
    $user_id_session   = null;
    $user_name_session = null;
    $user_role_session = null;

    // Check if this is a superadmin session
    if (isset($_SESSION['superadmin_id'])) {
        $user_id_session = null; // superadmin_id is different from user_id
        $user_role_session = 'superadmin';
        
        // Fetch superadmin name from database
        $q = $conn->prepare("SELECT fullName FROM superadmin WHERE superadmin_id = ? LIMIT 1");
        if ($q) {
            $q->bind_param("i", $_SESSION['superadmin_id']);
            if ($q->execute()) {
                $res = $q->get_result();
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $user_name_session = $row['fullName'] ?? 'Superadmin';
                }
            }
            $q->close();
        }
        if (!$user_name_session) {
            $user_name_session = 'Superadmin';
        }
    } else {
        // Regular user session
        $user_id_session   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $user_name_session = $_SESSION['name'] ?? ($_SESSION['username'] ?? null);
        $user_role_session = $_SESSION['role'] ?? null;
    }

    $user_id   = $user_id_session;
    $user_name = $user_name_session;
    $user_role = $user_role_session;

    // -----------------------------
    // ✅ Stronger: fetch role + name from DB using user_id (for regular users)
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
        $allowed = ['admin', 'staff', 'superadmin'];
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

    // Truncate all values to fit in their columns
    if ($user_name !== null) {
        $user_name = substr(trim((string)$user_name), 0, 100);
    }

    if ($user_role !== null) {
        $user_role = substr(trim((string)$user_role), 0, 100);
    }

    if ($ip !== null) {
        $ip = substr(trim((string)$ip), 0, 45);
    }

    if ($ua !== null) {
        $ua = substr(trim((string)$ua), 0, 255);
    }

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
