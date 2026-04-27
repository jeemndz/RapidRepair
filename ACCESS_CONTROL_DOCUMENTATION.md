# Access Control System Documentation

## Overview

The RapidRepair access control system enables module-based access restrictions for role-based users. This allows administrators to create user roles with specific module access permissions, restricting what sections of the system each user can access.

## Architecture

### Components

1. **access_control.php** - Core access control helper file
   - Manages module-to-access-scope mapping
   - Provides access validation functions
   - Handles session management for role-based users

2. **tenantlogin.php** - Unified login page for both owner and staff
   - Authenticates both shop owners and role-based staff
   - Automatically detects user type and sets appropriate session variables
   - Sets up access scope for staff users

3. **Module Enforcement** - All admin modules now include access control
   - Added to: appointmentadmin, repairjobsadmin, vehicleadmin, inventoryadmin, customeradmin, paymentsadmin, accountbillingadmin, reportsadmin, settingsadmin, tenantslogs

### Database Structure

The `roles` table stores:
- `role_id` - Unique identifier
- `first_name`, `last_name` - User name
- `username` - Login username
- `email` - User email
- `password` - Hashed password (uses PASSWORD_DEFAULT PHP hashing)
- `access_scope` - Module access level
- `is_active` - Account status (0/1)
- `status` - enum('Active','Inactive')
- `tenantID` - Which tenant owns this role
- `created_at`, `updated_at` - Timestamps

## Available Access Scopes

Users can be assigned one of these access scopes:

| Scope | Modules Granted Access |
|-------|------------------------|
| **All** | All modules (admin access) |
| **Dashboard** | Dashboard only |
| **Appointments** | Appointments Management |
| **Repair Jobs** | Repair Jobs Management |
| **Vehicles** | Vehicles Management |
| **Inventory** | Inventory Management |
| **Customers** | Customers Management |
| **Payments** | Payments Management |
| **Billing** | Billing & Accounts |
| **Reports** | Reports & Analytics |
| **Settings** | Shop Settings |
| **Logs** | Activity Logs |

## How It Works

### 1. User Login Flow

All users (owners and staff) use the same login page:

**Login Process:**
1. User visits `tenantlogin.php?shop=<shop_slug>` (or tenant-specific login link)
2. Enters username/email and password
3. System first checks if user is an owner
   - If owner found → authenticate and set `userType='owner'` → full access
   - If not owner → check roles table for staff user
4. On staff authentication:
   - Set session variables: `userType='staff'`, `role_id`, `access_scope`, etc.
   - User directed to dashboard with access restrictions applied
5. On login failure → error message displayed, user remains on login page

**User Types:**
- **Owner/Admin**: `userType='owner'` → Full access to all modules
- **Staff/Employee**: `userType='staff'` → Access restricted by `access_scope`

### 2. Access Enforcement

Each module includes:
```php
include __DIR__ . '/access_control.php';

if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];

// Enforce access control for this module
enforceModuleAccess($tenantID, basename(__FILE__));
```

### 3. Access Checking Process

When a user requests a module:
1. `enforceModuleAccess()` checks user type
   - If owner (`userType != 'role'`) → allow access
   - If role user → verify access scope
2. Looks up module in `MODULE_ACCESS_MAP`
3. Compares user's `access_scope` with allowed scopes for module
4. If access denied → displays 403 error page
5. If access allowed → module loads normally

## Implementation Guide

### Adding Access Control to a New Module

To add access control to a new admin module:

1. **Include the access control file:**
```php
<?php
session_start();
include __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';
include __DIR__ . '/access_control.php';

// Check authentication
if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];

// Enforce access control
enforceModuleAccess($tenantID, basename(__FILE__));
```

2. **Update MODULE_ACCESS_MAP in access_control.php:**
```php
const MODULE_ACCESS_MAP = [
    'new_module.php' => ['Dashboard', 'All'],
    // existing entries...
];
```

### Creating a New Access Scope

If you need a new access scope:

1. **Add to getAvailableAccessScopes():**
```php
function getAvailableAccessScopes()
{
    return [
        'All' => 'All Modules',
        'My New Scope' => 'Description of what this grants',
        // existing scopes...
    ];
}
```

2. **Update MODULE_ACCESS_MAP** to include new scope in module lists

3. **Update the select dropdown in settingsadmin.php**

## Database Queries

### Get all active roles for a tenant:
```sql
SELECT * FROM roles 
WHERE tenantID = ? AND is_active = 1 AND status = 'Active'
ORDER BY created_at DESC;
```

### Check if user has access to module:
```sql
SELECT access_scope FROM roles 
WHERE role_id = ? AND tenantID = ? LIMIT 1;
```

## API Functions

### `enforceModuleAccess($tenantID, $module)`
Validates access and redirects to 403 page if denied.
```php
enforceModuleAccess($tenantID, 'appointmentadmin.php');
```

### `hasModuleAccess($module, $tenantID, $roleId = null)`
Returns boolean indicating if user has access.
```php
if (hasModuleAccess('paymentsadmin.php', $tenantID)) {
    // User has access
}
```

### `getAccessibleModules($tenantID)`
Returns array of module filenames accessible to the user.
```php
$accessible = getAccessibleModules($tenantID);
```

### `isRoleValid($roleId, $tenantID)`
Checks if role exists and is active.
```php
if (isRoleValid($roleId, $tenantID)) {
    // Role is valid
}
```

### `getRoleInfo($roleId, $tenantID)`
Gets role details including access_scope.
```php
$role = getRoleInfo($roleId, $tenantID);
echo $role['access_scope']; // e.g., "Appointments"
```

## Session Variables

When a role user logs in, these session variables are set:

```php
$_SESSION['tenantID']       // Tenant ID
$_SESSION['role_id']        // Role ID
$_SESSION['username']       // Username
$_SESSION['userType']       // 'role' for staff, 'owner' for admin
$_SESSION['first_name']     // First name
$_SESSION['last_name']      // Last name
$_SESSION['email']          // Email address
$_SESSION['access_scope']   // Access scope (e.g., "Appointments")
$_SESSION['login_slug']     // Shop login slug
```

## Security Considerations

1. **Password Hashing**: Role passwords use PHP's `PASSWORD_DEFAULT` algorithm
   - Always use `password_hash()` for new passwords
   - Always use `password_verify()` to check passwords

2. **Access Control Bypass Prevention**:
   - Access is checked on every module load
   - Users cannot bypass by modifying URLs
   - Session validation prevents token tampering

3. **Session Security**:
   - Uses existing session_security.php file
   - Auto-logout on invalid sessions
   - Tenant-scoped access (can only see own tenant's data)

4. **Role Deletion Handling**:
   - When a role is deleted, user sessions persist
   - Next module access will fail if role no longer exists

## Troubleshooting

### User sees "Access Denied" but should have access

1. **Check access_scope in database:**
   ```sql
   SELECT access_scope FROM roles WHERE role_id = ?;
   ```

2. **Verify MODULE_ACCESS_MAP** includes the module and scope

3. **Check user status:**
   ```sql
   SELECT is_active, status FROM roles WHERE role_id = ?;
   ```

### User can access module when they shouldn't

1. **Check if user is owner** (userType = 'owner')
   - Owners have full access regardless of access_scope

2. **Verify MODULE_ACCESS_MAP** doesn't grant 'All' scope

3. **Check session for access_scope:**
   ```php
   echo $_SESSION['access_scope'];
   ```

## UI Integration

### Navigation Menu Filtering

To dynamically filter navigation based on access scope:

```php
$accessibleModules = getAccessibleModules($tenantID);

if (in_array('appointmentadmin.php', $accessibleModules)) {
    echo '<a href="appointmentadmin.php">Appointments</a>';
}
```

### Settings Admin

The settings admin page in settingsadmin.php now shows:
- Dropdown with all available access scopes
- Descriptions of what each scope grants
- Required field validation
- Previously selected scope pre-filled when editing

## Future Enhancements

1. **Granular Permissions**: Could split each module into specific actions (view/create/edit/delete)

2. **Custom Scopes**: Allow tenants to create custom access scopes

3. **Permission Groups**: Bundle multiple scopes into permission groups

4. **Audit Logging**: Track all access scope changes and denied access attempts

5. **Role Templates**: Pre-configured role templates (Technician, Receptionist, Manager, etc.)

## Support

For issues or questions, check:
1. The access_control.php file documentation
2. The roles_handler.php for role management
3. Database schema in roles table
