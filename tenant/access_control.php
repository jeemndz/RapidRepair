<?php
/**
 * Access Control Helper
 * Manages module access based on user role access_scope
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Module to Access Scope Mapping
 * Maps each module/page to its required access scope
 */
const MODULE_ACCESS_MAP = [
    'dashboardadmin.php' => ['Dashboard', 'All'],
    'appointmentadmin.php' => ['Appointments', 'All'],
    'repairjobsadmin.php' => ['Repair Jobs', 'All'],
    'vehicleadmin.php' => ['Vehicles', 'All'],
    'inventoryadmin.php' => ['Inventory', 'All'],
    'customeradmin.php' => ['Customers', 'All'],
    'paymentsadmin.php' => ['Payments', 'All'],
    'accountbillingadmin.php' => ['Billing', 'All'],
    'reportsadmin.php' => ['Reports', 'All'],
    'settingsadmin.php' => ['Settings', 'All'],
    'storage_managementadmin.php' => ['Settings', 'All'],
    'tenantslogs.php' => ['Logs', 'All'],
    'save_customization.php' => ['Settings', 'All'],
];

/**
 * Get available access scopes for selection
 */
function getAvailableAccessScopes()
{
    return [
        'All' => 'All Modules',
        'Dashboard' => 'Dashboard Only',
        'Appointments' => 'Appointments Management',
        'Repair Jobs' => 'Repair Jobs Management',
        'Vehicles' => 'Vehicles Management',
        'Inventory' => 'Inventory Management',
        'Customers' => 'Customers Management',
        'Payments' => 'Payments Management',
        'Billing' => 'Billing & Accounts',
        'Reports' => 'Reports & Analytics',
        'Settings' => 'Shop Settings',
        'Logs' => 'Activity Logs',
    ];
}

/**
 * Check if current user has access to a module
 * 
 * @param string $module Module name/filename
 * @param int $tenantID Tenant ID
 * @param int $roleId User Role ID (optional, uses session if not provided)
 * @return bool True if user has access, false otherwise
 */
function hasModuleAccess($module, $tenantID, $roleId = null)
{
    global $conn;

    // If not a role-based user, allow access (owner/admin)
    if (!isset($_SESSION['userType']) || $_SESSION['userType'] !== 'staff') {
        return true;
    }

    // Get access scope from session if available
    if (isset($_SESSION['access_scope'])) {
        $accessScope = $_SESSION['access_scope'];
    } else {
        // Query database for access scope
        $effectiveRoleId = $roleId ?? ($_SESSION['userId'] ?? 0);
        if ($effectiveRoleId <= 0) {
            return false;
        }

        $stmt = mysqli_prepare(
            $conn,
            "SELECT access_scope FROM roles WHERE role_id = ? AND tenantID = ? LIMIT 1"
        );

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'ii', $effectiveRoleId, $tenantID);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $roleData = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$roleData) {
            return false;
        }

        $accessScope = $roleData['access_scope'];
        $_SESSION['access_scope'] = $accessScope;
    }

    // Parse comma-separated modules from access_scope
    $userModules = array_map('trim', explode(',', $accessScope));

    // Check if module requires specific access scope
    if (!isset(MODULE_ACCESS_MAP[$module])) {
        // Unknown module, deny access
        return false;
    }

    $allowedScopes = MODULE_ACCESS_MAP[$module];

    // Check if any of user's assigned modules match the required scopes for this page
    foreach ($userModules as $userModule) {
        if (in_array($userModule, $allowedScopes, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Enforce access control for current page
 * Redirects to unauthorized page if access is denied
 * 
 * @param int $tenantID Tenant ID
 * @param string $currentModule Current module/page (optional, auto-detected if not provided)
 */
function enforceModuleAccess($tenantID, $currentModule = null)
{
    if ($currentModule === null) {
        $currentModule = basename($_SERVER['PHP_SELF']);
    }

    if (!hasModuleAccess($currentModule, $tenantID)) {
        header('HTTP/1.1 403 Forbidden');
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="bg-white rounded-xl shadow-lg p-8 max-w-md w-full text-center">
            <div class="text-6xl mb-4">🔒</div>
            <h1 class="text-3xl font-bold text-slate-900 mb-2">Access Denied</h1>
            <p class="text-slate-600 mb-6">Your current role does not have permission to access this module. Please contact your administrator if you believe this is an error.</p>
            <a href="dashboardadmin.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors">
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
HTML;
        exit;
    }
}

/**
 * Get filtered navigation menu based on user's access scope
 * 
 * @param int $tenantID Tenant ID
 * @return array Array of accessible modules with their info
 */
function getAccessibleModules($tenantID)
{
    // If not a role-based user, return all modules
    if (!isset($_SESSION['userType']) || $_SESSION['userType'] !== 'staff') {
        return array_keys(MODULE_ACCESS_MAP);
    }

    $accessScope = $_SESSION['access_scope'] ?? '';

    if (empty($accessScope)) {
        return [];
    }

    // Parse comma-separated modules
    $userModules = array_map('trim', explode(',', $accessScope));

    // Filter modules based on which ones user has been assigned
    $accessibleModules = [];
    foreach (MODULE_ACCESS_MAP as $module => $scopes) {
        foreach ($userModules as $userModule) {
            if (in_array($userModule, $scopes, true)) {
                $accessibleModules[] = $module;
                break;
            }
        }
    }

    return $accessibleModules;
}

/**
 * Check if role is active and belongs to current tenant
 * 
 * @param int $roleId Role ID
 * @param int $tenantID Tenant ID
 * @return bool True if role is valid and active
 */
function isRoleValid($roleId, $tenantID)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "SELECT role_id FROM roles WHERE role_id = ? AND tenantID = ? AND is_active = 1 AND status = 'Active' LIMIT 1"
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $roleId, $tenantID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $valid = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    return $valid;
}

/**
 * Get role information
 * 
 * @param int $roleId Role ID
 * @param int $tenantID Tenant ID
 * @return array|null Role data or null if not found
 */
function getRoleInfo($roleId, $tenantID)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "SELECT role_id, first_name, last_name, role_name, username, email, access_scope, status FROM roles WHERE role_id = ? AND tenantID = ? LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $roleId, $tenantID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $roleData = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $roleData;
}
