<?php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';
include __DIR__ . '/access_control.php';
include __DIR__ . '/../log_helper.php';

if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];

// Enforce access control for this module
enforceModuleAccess($tenantID, basename(__FILE__));

// Get accessible modules for navigation
$accessibleModules = getAccessibleModules($tenantID);
$isStaffUser = isset($_SESSION['userType']) && $_SESSION['userType'] === 'staff';

// Helper function to check if a module should be accessible
function canAccessModule($moduleFile, $accessibleModules) {
    return in_array($moduleFile, $accessibleModules);
}

// HTML escape helper function
function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Get logged-in user information
$loggedInUserName = '';
$loggedInUserRole = '';
if ($_SESSION['userType'] === 'owner') {
    $loggedInUserName = isset($_SESSION['shopName']) ? $_SESSION['shopName'] : 'Shop Owner';
    $loggedInUserRole = 'Administrator';
} else {
    $loggedInUserName = (isset($_SESSION['firstName']) ? $_SESSION['firstName'] : '') . ' ' . (isset($_SESSION['lastName']) ? $_SESSION['lastName'] : '');
    $loggedInUserName = trim($loggedInUserName) ?: 'User';
    $loggedInUserRole = isset($_SESSION['userRole']) ? $_SESSION['userRole'] : 'Staff Member';
}

$loginSlug = '';
if (isset($_SESSION['login_slug']) && trim((string) $_SESSION['login_slug']) !== '') {
    $loginSlug = trim((string) $_SESSION['login_slug']);
} elseif (isset($_GET['shop']) && trim((string) $_GET['shop']) !== '') {
    $loginSlug = trim((string) $_GET['shop']);
    $_SESSION['login_slug'] = $loginSlug;
}

if ($loginSlug === '') {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

$ownerStmt = mysqli_prepare($conn, 'SELECT shopName FROM owners WHERE tenantID = ? AND login_slug = ? LIMIT 1');
if (!$ownerStmt) {
    die('Unable to validate tenant.');
}
mysqli_stmt_bind_param($ownerStmt, 'is', $tenantID, $loginSlug);
mysqli_stmt_execute($ownerStmt);
$ownerResult = mysqli_stmt_get_result($ownerStmt);
$owner = $ownerResult ? mysqli_fetch_assoc($ownerResult) : null;
mysqli_stmt_close($ownerStmt);

if (!$owner) {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

$_SESSION['login_slug'] = $loginSlug;
$shopName = !empty($owner['shopName']) ? $owner['shopName'] : 'AutoFix Pro';
$shopQuery = urlencode($loginSlug);
$currentScript = basename($_SERVER['PHP_SELF']);
if (!isset($_GET['shop']) || trim((string) $_GET['shop']) !== $loginSlug) {
    header('Location: /tenant/inventoryadmin.php?shop=' . $shopQuery);
    exit;
}

function bindParams(mysqli_stmt $stmt, string $types, array &$params): bool
{
    $bindArgs = [$stmt, $types];
    foreach ($params as $index => &$value) {
        $bindArgs[] = &$value;
    }

    return call_user_func_array('mysqli_stmt_bind_param', $bindArgs);
}

function stockLabel(array $row): string
{
    $quantity = (int) $row['stock_quantity'];

    if ($quantity <= 0) {
        return 'Out of Stock';
    }

    if ($quantity < LOW_STOCK_THRESHOLD) {
        return 'Low Stock';
    }

    return 'In Stock';
}

function stockBadgeClass(array $row): string
{
    $quantity = (int) $row['stock_quantity'];

    if ($quantity <= 0) {
        return 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-300';
    }

    if ($quantity < LOW_STOCK_THRESHOLD) {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300';
    }

    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300';
}

function movementBadgeClass(string $type): string
{
    if ($type === 'IN') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300';
    }

    if ($type === 'OUT') {
        return 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-300';
    }

    return 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300';
}

if (!defined('LOW_STOCK_THRESHOLD')) {
    define('LOW_STOCK_THRESHOLD', 10);
}

$categories = [
    'Engine',
    'Electrical',
    'Maintenance',
    'Brakes',
    'Suspension',
    'Transmission',
    'Cooling System',
    'Diagnostics',
    'Fluids',
    'Electronics',
    'Other'
];
$statuses = ['Active', 'Inactive'];
$movementTypes = ['IN', 'OUT', 'ADJUSTMENT'];
$referenceTypes = ['Purchase', 'RepairJob', 'Manual'];

$message = '';
$messageType = 'success';

$formData = [
    'item_id' => 0,
    'part_name' => '',
    'part_code' => '',
    'category' => 'Other',
    'stock_quantity' => '0',
    'reorder_level' => '10',
    'unit_price' => '',
    'supplier_name' => '',
    'status' => 'Active',
];

$movementData = [
    'item_id' => 0,
    'movement_type' => 'IN',
    'quantity' => '1',
    'reference_type' => 'Manual',
    'reference_id' => '',
    'notes' => '',
];

$editingItemId = isset($_GET['edit']) ? max(0, (int) $_GET['edit']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['inventory_action']) ? (string) $_POST['inventory_action'] : '';

    if ($action === 'save_item') {
        foreach ($formData as $key => $value) {
            $formData[$key] = isset($_POST[$key]) ? trim((string) $_POST[$key]) : $value;
        }

        $formData['item_id'] = max(0, (int) $formData['item_id']);
        $formData['stock_quantity'] = (int) $formData['stock_quantity'];
        $formData['reorder_level'] = (int) $formData['reorder_level'];
        $formData['unit_price'] = is_numeric($formData['unit_price']) ? (float) $formData['unit_price'] : -1;
        $formData['category'] = in_array($formData['category'], $categories, true) ? $formData['category'] : 'Other';
        $formData['status'] = in_array($formData['status'], $statuses, true) ? $formData['status'] : 'Active';

        if ($formData['part_name'] === '') {
            $message = 'Part name is required.';
            $messageType = 'error';
        } elseif ($formData['unit_price'] < 0) {
            $message = 'Unit price must be a valid positive amount.';
            $messageType = 'error';
        } elseif ($formData['stock_quantity'] < 0) {
            $message = 'Stock quantity cannot be negative.';
            $messageType = 'error';
        } elseif ($formData['reorder_level'] < 0) {
            $message = 'Reorder level cannot be negative.';
            $messageType = 'error';
        } else {
            $previousStock = 0;
            $existingItem = null;

            if ($formData['item_id'] > 0) {
                $verifyStmt = mysqli_prepare($conn, 'SELECT item_id, stock_quantity FROM inventory_items WHERE item_id = ? AND tenantID = ? LIMIT 1');
                if ($verifyStmt) {
                    mysqli_stmt_bind_param($verifyStmt, 'ii', $formData['item_id'], $tenantID);
                    mysqli_stmt_execute($verifyStmt);
                    $verifyResult = mysqli_stmt_get_result($verifyStmt);
                    $existingItem = $verifyResult ? mysqli_fetch_assoc($verifyResult) : null;
                    mysqli_stmt_close($verifyStmt);
                }

                if (!$existingItem) {
                    $message = 'Inventory item not found.';
                    $messageType = 'error';
                } else {
                    $previousStock = (int) $existingItem['stock_quantity'];
                }
            }

            if ($message === '') {
                if ($formData['item_id'] > 0) {
                    $updateStmt = mysqli_prepare(
                        $conn,
                        'UPDATE inventory_items
                         SET part_name = ?, part_code = ?, category = ?, stock_quantity = ?, reorder_level = ?, unit_price = ?, supplier_name = ?, status = ?
                         WHERE item_id = ? AND tenantID = ?'
                    );

                    if (!$updateStmt) {
                        $message = 'Unable to prepare update query.';
                        $messageType = 'error';
                    } else {
                        $partCode = $formData['part_code'] !== '' ? $formData['part_code'] : null;
                        $supplierName = $formData['supplier_name'] !== '' ? $formData['supplier_name'] : null;
                        $params = [
                            $formData['part_name'],
                            $partCode,
                            $formData['category'],
                            $formData['stock_quantity'],
                            $formData['reorder_level'],
                            $formData['unit_price'],
                            $supplierName,
                            $formData['status'],
                            $formData['item_id'],
                            $tenantID,
                        ];

                        if (!bindParams($updateStmt, 'sssiidssii', $params)) {
                            $message = 'Unable to bind update values.';
                            $messageType = 'error';
                        } elseif (!mysqli_stmt_execute($updateStmt)) {
                            $message = 'Unable to update inventory item.';
                            $messageType = 'error';
                        } else {
                            log_event($conn, 'UPDATE InventoryItem', 'inventory_item', (int) $formData['item_id'], 'Updated stock quantity to ' . (int) $formData['stock_quantity']);
                            $newStock = (int) $formData['stock_quantity'];
                            if ($newStock !== $previousStock) {
                                $delta = $newStock - $previousStock;
                                $movementType = $delta >= 0 ? 'IN' : 'OUT';
                                $movementQty = abs($delta);
                                $note = 'Stock adjusted from ' . $previousStock . ' to ' . $newStock . ' via item update.';
                                $movementStmt = mysqli_prepare(
                                    $conn,
                                    'INSERT INTO stock_movements (tenantID, item_id, movement_type, quantity, reference_type, reference_id, notes)
                                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                                );
                                if ($movementStmt) {
                                    $referenceType = 'Manual';
                                    $referenceId = null;
                                    $movementParams = [$tenantID, $formData['item_id'], $movementType, $movementQty, $referenceType, $referenceId, $note];
                                    if (bindParams($movementStmt, 'iisisis', $movementParams)) {
                                        if (mysqli_stmt_execute($movementStmt)) {
                                            log_event($conn, 'CREATE StockMovement', 'stock_movement', (int) $formData['item_id'], 'Created StockMovement with details: ' . $movementType . ' quantity ' . $movementQty);
                                        }
                                    }
                                    mysqli_stmt_close($movementStmt);
                                }
                            }

                            $message = 'Inventory item saved successfully.';
                            $messageType = 'success';
                            $editingItemId = 0;
                            $formData = [
                                'item_id' => 0,
                                'part_name' => '',
                                'part_code' => '',
                                'category' => 'Other',
                                'stock_quantity' => '0',
                                'reorder_level' => '10',
                                'unit_price' => '',
                                'supplier_name' => '',
                                'status' => 'Active',
                            ];
                        }

                        mysqli_stmt_close($updateStmt);
                    }
                } else {
                    $insertStmt = mysqli_prepare(
                        $conn,
                        'INSERT INTO inventory_items (tenantID, part_name, part_code, category, stock_quantity, reorder_level, unit_price, supplier_name, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );

                    if (!$insertStmt) {
                        $message = 'Unable to prepare insert query.';
                        $messageType = 'error';
                    } else {
                        $partCode = $formData['part_code'] !== '' ? $formData['part_code'] : null;
                        $supplierName = $formData['supplier_name'] !== '' ? $formData['supplier_name'] : null;
                        $params = [
                            $tenantID,
                            $formData['part_name'],
                            $partCode,
                            $formData['category'],
                            $formData['stock_quantity'],
                            $formData['reorder_level'],
                            $formData['unit_price'],
                            $supplierName,
                            $formData['status'],
                        ];

                        if (!bindParams($insertStmt, 'isssiidss', $params)) {
                            $message = 'Unable to bind insert values.';
                            $messageType = 'error';
                        } elseif (!mysqli_stmt_execute($insertStmt)) {
                            $errorText = mysqli_stmt_error($insertStmt);
                            $message = strpos($errorText, 'Duplicate entry') !== false ? 'Part code already exists.' : 'Unable to add item.';
                            $messageType = 'error';
                        } else {
                            $newItemId = (int) mysqli_insert_id($conn);
                            log_event($conn, 'CREATE InventoryItem', 'inventory_item', $newItemId, 'Created InventoryItem with details: ' . $formData['part_name']);
                            $initialStock = (int) $formData['stock_quantity'];

                            if ($initialStock > 0) {
                                $movementStmt = mysqli_prepare(
                                    $conn,
                                    'INSERT INTO stock_movements (tenantID, item_id, movement_type, quantity, reference_type, reference_id, notes)
                                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                                );
                                if ($movementStmt) {
                                    $referenceType = 'Manual';
                                    $referenceId = null;
                                    $note = 'Initial stock when item was created.';
                                    $movementParams = [$tenantID, $newItemId, 'IN', $initialStock, $referenceType, $referenceId, $note];
                                    if (bindParams($movementStmt, 'iisisis', $movementParams)) {
                                        if (mysqli_stmt_execute($movementStmt)) {
                                            log_event($conn, 'CREATE StockMovement', 'stock_movement', $newItemId, 'Created StockMovement with details: IN quantity ' . $initialStock);
                                        }
                                    }
                                    mysqli_stmt_close($movementStmt);
                                }
                            }

                            $message = 'Inventory item added successfully.';
                            $messageType = 'success';
                            $formData = [
                                'item_id' => 0,
                                'part_name' => '',
                                'part_code' => '',
                                'category' => 'Other',
                                'stock_quantity' => '0',
                                'reorder_level' => '10',
                                'unit_price' => '',
                                'supplier_name' => '',
                                'status' => 'Active',
                            ];
                        }

                        mysqli_stmt_close($insertStmt);
                    }
                }
            }
        }
    } elseif ($action === 'record_movement') {
        foreach ($movementData as $key => $value) {
            $movementData[$key] = isset($_POST[$key]) ? trim((string) $_POST[$key]) : $value;
        }

        $movementData['item_id'] = max(0, (int) $movementData['item_id']);
        $movementData['quantity'] = (int) $movementData['quantity'];
        $movementData['movement_type'] = in_array($movementData['movement_type'], $movementTypes, true) ? $movementData['movement_type'] : 'IN';
        $movementData['reference_type'] = in_array($movementData['reference_type'], $referenceTypes, true) ? $movementData['reference_type'] : 'Manual';
        $movementData['reference_id'] = $movementData['reference_id'] !== '' ? (int) $movementData['reference_id'] : null;
        $movementData['notes'] = trim((string) $movementData['notes']);

        if ($movementData['item_id'] <= 0) {
            $message = 'Please choose an inventory item.';
            $messageType = 'error';
        } elseif ($movementData['quantity'] === 0) {
            $message = 'Movement quantity must not be zero.';
            $messageType = 'error';
        } else {
            $itemStmt = mysqli_prepare($conn, 'SELECT item_id, stock_quantity FROM inventory_items WHERE item_id = ? AND tenantID = ? LIMIT 1');
            if ($itemStmt) {
                mysqli_stmt_bind_param($itemStmt, 'ii', $movementData['item_id'], $tenantID);
                mysqli_stmt_execute($itemStmt);
                $itemResult = mysqli_stmt_get_result($itemStmt);
                $itemRow = $itemResult ? mysqli_fetch_assoc($itemResult) : null;
                mysqli_stmt_close($itemStmt);

                if (!$itemRow) {
                    $message = 'Inventory item not found.';
                    $messageType = 'error';
                } else {
                    $currentStock = (int) $itemRow['stock_quantity'];
                    $delta = 0;

                    if ($movementData['movement_type'] === 'IN') {
                        if ($movementData['quantity'] < 1) {
                            $message = 'Inbound quantity must be positive.';
                            $messageType = 'error';
                        } else {
                            $delta = $movementData['quantity'];
                        }
                    } elseif ($movementData['movement_type'] === 'OUT') {
                        if ($movementData['quantity'] < 1) {
                            $message = 'Outbound quantity must be positive.';
                            $messageType = 'error';
                        } elseif ($movementData['quantity'] > $currentStock) {
                            $message = 'Cannot remove more stock than is available.';
                            $messageType = 'error';
                        } else {
                            $delta = 0 - $movementData['quantity'];
                        }
                    } else {
                        $delta = $movementData['quantity'];
                        if ($currentStock + $delta < 0) {
                            $message = 'Adjustment would result in negative stock.';
                            $messageType = 'error';
                        }
                    }

                    if ($message === '') {
                        $newStock = $currentStock + $delta;
                        $movementStmt = mysqli_prepare(
                            $conn,
                            'INSERT INTO stock_movements (tenantID, item_id, movement_type, quantity, reference_type, reference_id, notes)
                             VALUES (?, ?, ?, ?, ?, ?, ?)'
                        );

                        if (!$movementStmt) {
                            $message = 'Unable to prepare movement query.';
                            $messageType = 'error';
                        } else {
                            $notes = $movementData['notes'] !== '' ? $movementData['notes'] : null;
                            $movementParams = [
                                $tenantID,
                                $movementData['item_id'],
                                $movementData['movement_type'],
                                $movementData['quantity'],
                                $movementData['reference_type'],
                                $movementData['reference_id'],
                                $notes,
                            ];

                            if (!bindParams($movementStmt, 'iisisis', $movementParams)) {
                                $message = 'Unable to bind movement values.';
                                $messageType = 'error';
                                mysqli_stmt_close($movementStmt);
                            } elseif (!mysqli_stmt_execute($movementStmt)) {
                                $message = 'Unable to save stock movement.';
                                $messageType = 'error';
                                mysqli_stmt_close($movementStmt);
                            } else {
                                $movementAction = $movementData['movement_type'] === 'IN'
                                    ? 'ADD StockMovement'
                                    : ($movementData['movement_type'] === 'OUT' ? 'OUT StockMovement' : 'ADJUSTMENT StockMovement');
                                $movementDetails = $movementData['movement_type'] === 'IN'
                                    ? 'Added ' . (int) $movementData['quantity'] . ' units to stock for item #' . (int) $movementData['item_id'] . '.'
                                    : ($movementData['movement_type'] === 'OUT'
                                        ? 'Removed ' . (int) $movementData['quantity'] . ' units from stock for item #' . (int) $movementData['item_id'] . '.'
                                        : 'Adjusted stock by ' . (int) $movementData['quantity'] . ' units for item #' . (int) $movementData['item_id'] . '.');
                                log_event($conn, $movementAction, 'stock_movement', (int) $movementData['item_id'], $movementDetails);
                                mysqli_stmt_close($movementStmt);

                                $updateStmt = mysqli_prepare($conn, 'UPDATE inventory_items SET stock_quantity = ? WHERE item_id = ? AND tenantID = ?');
                                if ($updateStmt) {
                                    $updateParams = [$newStock, $movementData['item_id'], $tenantID];
                                    if (bindParams($updateStmt, 'iii', $updateParams)) {
                                        if (mysqli_stmt_execute($updateStmt)) {
                                            log_event($conn, 'UPDATE InventoryItem', 'inventory_item', (int) $movementData['item_id'], 'Updated stock quantity to ' . $newStock);
                                        }
                                    }
                                    mysqli_stmt_close($updateStmt);
                                }

                                $message = 'Stock movement recorded successfully.';
                                $messageType = 'success';
                                $movementData = [
                                    'item_id' => 0,
                                    'movement_type' => 'IN',
                                    'quantity' => '1',
                                    'reference_type' => 'Manual',
                                    'reference_id' => '',
                                    'notes' => '',
                                ];
                            }
                        }
                    }
                }
            }
        }
    } elseif ($action === 'toggle_status') {
        $itemId = isset($_POST['item_id']) ? max(0, (int) $_POST['item_id']) : 0;
        $nextStatus = isset($_POST['next_status']) ? trim((string) $_POST['next_status']) : 'Inactive';
        $nextStatus = in_array($nextStatus, $statuses, true) ? $nextStatus : 'Inactive';

        if ($itemId <= 0) {
            $message = 'Invalid item selected.';
            $messageType = 'error';
        } else {
            $toggleStmt = mysqli_prepare($conn, 'UPDATE inventory_items SET status = ? WHERE item_id = ? AND tenantID = ?');
            if (!$toggleStmt) {
                $message = 'Unable to prepare status update.';
                $messageType = 'error';
            } else {
                $toggleParams = [$nextStatus, $itemId, $tenantID];
                if (bindParams($toggleStmt, 'sii', $toggleParams) && mysqli_stmt_execute($toggleStmt)) {
                    log_event($conn, 'UPDATE InventoryItem', 'inventory_item', $itemId, 'Updated status to ' . $nextStatus);
                    $message = 'Item status updated.';
                    $messageType = 'success';
                } else {
                    $message = 'Unable to update item status.';
                    $messageType = 'error';
                }
                mysqli_stmt_close($toggleStmt);
            }
        }
    }
}

if ($editingItemId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $editStmt = mysqli_prepare(
        $conn,
        'SELECT item_id, part_name, part_code, category, stock_quantity, reorder_level, unit_price, supplier_name, status
         FROM inventory_items
         WHERE item_id = ? AND tenantID = ? LIMIT 1'
    );
    if ($editStmt) {
        mysqli_stmt_bind_param($editStmt, 'ii', $editingItemId, $tenantID);
        mysqli_stmt_execute($editStmt);
        $editResult = mysqli_stmt_get_result($editStmt);
        $editRow = $editResult ? mysqli_fetch_assoc($editResult) : null;
        mysqli_stmt_close($editStmt);

        if ($editRow) {
            $formData = [
                'item_id' => (int) $editRow['item_id'],
                'part_name' => (string) $editRow['part_name'],
                'part_code' => (string) ($editRow['part_code'] ?? ''),
                'category' => (string) $editRow['category'],
                'stock_quantity' => (string) $editRow['stock_quantity'],
                'reorder_level' => (string) $editRow['reorder_level'],
                'unit_price' => (string) $editRow['unit_price'],
                'supplier_name' => (string) ($editRow['supplier_name'] ?? ''),
                'status' => (string) $editRow['status'],
            ];
        } else {
            $editingItemId = 0;
            $message = 'Selected item was not found.';
            $messageType = 'error';
        }
    }
}

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$categoryFilter = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$stockFilter = isset($_GET['stock']) ? trim((string) $_GET['stock']) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 10;

if ($categoryFilter !== '' && !in_array($categoryFilter, $categories, true)) {
    $categoryFilter = '';
}
if ($statusFilter !== '' && !in_array($statusFilter, $statuses, true)) {
    $statusFilter = '';
}
if ($stockFilter !== '' && !in_array($stockFilter, ['low', 'out', 'normal'], true)) {
    $stockFilter = '';
}

$whereSql = ' WHERE tenantID = ? ';
$whereParams = [$tenantID];
$whereTypes = 'i';

if ($search !== '') {
    $whereSql .= ' AND (part_name LIKE CONCAT("%", ?, "%") OR part_code LIKE CONCAT("%", ?, "%") OR supplier_name LIKE CONCAT("%", ?, "%")) ';
    $whereParams[] = $search;
    $whereParams[] = $search;
    $whereParams[] = $search;
    $whereTypes .= 'sss';
}
if ($categoryFilter !== '') {
    $whereSql .= ' AND category = ? ';
    $whereParams[] = $categoryFilter;
    $whereTypes .= 's';
}
if ($statusFilter !== '') {
    $whereSql .= ' AND status = ? ';
    $whereParams[] = $statusFilter;
    $whereTypes .= 's';
}
if ($stockFilter === 'low') {
    $whereSql .= ' AND stock_quantity > 0 AND stock_quantity < ' . LOW_STOCK_THRESHOLD . ' ';
} elseif ($stockFilter === 'out') {
    $whereSql .= ' AND stock_quantity <= 0 ';
} elseif ($stockFilter === 'normal') {
    $whereSql .= ' AND stock_quantity >= ' . LOW_STOCK_THRESHOLD . ' ';
}

$countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM inventory_items' . $whereSql);
$filteredTotal = 0;
if ($countStmt) {
    $countParams = $whereParams;
    if (bindParams($countStmt, $whereTypes, $countParams)) {
        mysqli_stmt_execute($countStmt);
        $countResult = mysqli_stmt_get_result($countStmt);
        if ($countResult && $countRow = mysqli_fetch_assoc($countResult)) {
            $filteredTotal = (int) ($countRow['total'] ?? 0);
        }
    }
    mysqli_stmt_close($countStmt);
}

$totalPages = max(1, (int) ceil(max(1, $filteredTotal) / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$stats = [
    'total_items' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0,
    'inventory_value' => 0.0,
];

$statsStmt = mysqli_prepare(
    $conn,
    'SELECT
        COUNT(*) AS total_items,
        SUM(CASE WHEN stock_quantity > 0 AND stock_quantity < ' . LOW_STOCK_THRESHOLD . ' THEN 1 ELSE 0 END) AS low_stock,
        SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
        COALESCE(SUM(stock_quantity * unit_price), 0) AS inventory_value
     FROM inventory_items
     WHERE tenantID = ?'
);
if ($statsStmt) {
    mysqli_stmt_bind_param($statsStmt, 'i', $tenantID);
    mysqli_stmt_execute($statsStmt);
    $statsResult = mysqli_stmt_get_result($statsStmt);
    if ($statsResult && $statsRow = mysqli_fetch_assoc($statsResult)) {
        $stats['total_items'] = (int) ($statsRow['total_items'] ?? 0);
        $stats['low_stock'] = (int) ($statsRow['low_stock'] ?? 0);
        $stats['out_of_stock'] = (int) ($statsRow['out_of_stock'] ?? 0);
        $stats['inventory_value'] = (float) ($statsRow['inventory_value'] ?? 0);
    }
    mysqli_stmt_close($statsStmt);
}

$items = [];
$itemsStmt = mysqli_prepare(
    $conn,
    'SELECT item_id, part_name, part_code, category, stock_quantity, reorder_level, unit_price, supplier_name, status, updated_at
     FROM inventory_items
     ' . $whereSql . '
     ORDER BY updated_at DESC, item_id DESC
     LIMIT ? OFFSET ?'
);
if ($itemsStmt) {
    $itemsParams = $whereParams;
    $itemsParams[] = $perPage;
    $itemsParams[] = $offset;
    if (bindParams($itemsStmt, $whereTypes . 'ii', $itemsParams)) {
        mysqli_stmt_execute($itemsStmt);
        $itemsResult = mysqli_stmt_get_result($itemsStmt);
        while ($itemsResult && $row = mysqli_fetch_assoc($itemsResult)) {
            $items[] = $row;
        }
    }
    mysqli_stmt_close($itemsStmt);
}

$itemOptions = [];
$itemOptionsStmt = mysqli_prepare($conn, 'SELECT item_id, part_name, part_code FROM inventory_items WHERE tenantID = ? ORDER BY part_name ASC');
if ($itemOptionsStmt) {
    mysqli_stmt_bind_param($itemOptionsStmt, 'i', $tenantID);
    mysqli_stmt_execute($itemOptionsStmt);
    $itemOptionsResult = mysqli_stmt_get_result($itemOptionsStmt);
    while ($itemOptionsResult && $row = mysqli_fetch_assoc($itemOptionsResult)) {
        $itemOptions[] = $row;
    }
    mysqli_stmt_close($itemOptionsStmt);
}

$recentMovements = [];
$recentStmt = mysqli_prepare(
    $conn,
    'SELECT sm.movement_id, sm.movement_type, sm.quantity, sm.reference_type, sm.reference_id, sm.notes, sm.created_at,
            ii.part_name, ii.part_code
     FROM stock_movements sm
     INNER JOIN inventory_items ii ON ii.item_id = sm.item_id AND ii.tenantID = sm.tenantID
     WHERE sm.tenantID = ?
     ORDER BY sm.created_at DESC, sm.movement_id DESC
     LIMIT 5'
);
if ($recentStmt) {
    mysqli_stmt_bind_param($recentStmt, 'i', $tenantID);
    mysqli_stmt_execute($recentStmt);
    $recentResult = mysqli_stmt_get_result($recentStmt);
    while ($recentResult && $row = mysqli_fetch_assoc($recentResult)) {
        $recentMovements[] = $row;
    }
    mysqli_stmt_close($recentStmt);
}

$lowStockAlerts = [];
$alertStmt = mysqli_prepare(
    $conn,
    'SELECT item_id, part_name, part_code, stock_quantity, reorder_level, supplier_name
     FROM inventory_items
    WHERE tenantID = ? AND stock_quantity < ' . LOW_STOCK_THRESHOLD . '
     ORDER BY stock_quantity ASC, part_name ASC
     LIMIT 3'
);
if ($alertStmt) {
    mysqli_stmt_bind_param($alertStmt, 'i', $tenantID);
    mysqli_stmt_execute($alertStmt);
    $alertResult = mysqli_stmt_get_result($alertStmt);
    while ($alertResult && $row = mysqli_fetch_assoc($alertResult)) {
        $lowStockAlerts[] = $row;
    }
    mysqli_stmt_close($alertStmt);
}

$queryBase = ['shop=' . $shopQuery];
if ($search !== '') {
    $queryBase[] = 'q=' . urlencode($search);
}
if ($categoryFilter !== '') {
    $queryBase[] = 'category=' . urlencode($categoryFilter);
}
if ($statusFilter !== '') {
    $queryBase[] = 'status=' . urlencode($statusFilter);
}
if ($stockFilter !== '') {
    $queryBase[] = 'stock=' . urlencode($stockFilter);
}
$queryStringBase = implode('&', $queryBase);

$firstRow = $filteredTotal > 0 ? $offset + 1 : 0;
$lastRow = min($offset + $perPage, $filteredTotal);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>AutoFix Admin - Inventory Management</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#1152d4',
                        'background-light': '#f6f6f8',
                        'background-dark': '#101622'
                    },
                    fontFamily: {
                        display: ['Inter', 'sans-serif']
                    },
                    borderRadius: {
                        DEFAULT: '0.25rem',
                        lg: '0.5rem',
                        xl: '0.75rem',
                        full: '9999px'
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>

<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display">
    <div class="flex h-screen overflow-hidden">
        <aside
            class="w-64 flex-shrink-0 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 h-screen sticky top-0 flex flex-col overflow-y-auto">
            <div class="p-6 flex-1">
                <div class="flex items-center gap-3 mb-8">
                    <div class="bg-primary rounded-lg p-2 text-white">
                        <span class="material-symbols-outlined">directions_car</span>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold leading-none">
                            <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Your Repair Shop</p>
                    </div>
                </div>
                <nav class="space-y-1">
                    <?php if (canAccessModule('dashboardadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="dashboardadmin.php?shop=<?php echo $shopQuery; ?>"><span
                            class="material-symbols-outlined text-[22px]">dashboard</span>Dashboard</a>
                    <?php endif; ?>
                    <?php if (canAccessModule('repairjobsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="repairjobsadmin.php?shop=<?php echo $shopQuery; ?>"><span
                            class="material-symbols-outlined text-[22px]">build</span>Repair Jobs</a>
                    <?php endif; ?>
                    <?php if (canAccessModule('vehicleadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="vehicleadmin.php?shop=<?php echo $shopQuery; ?>"><span
                            class="material-symbols-outlined text-[22px]">directions_car</span>Vehicles</a>
                    <?php endif; ?>
                    <?php if (canAccessModule('appointmentadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="appointmentadmin.php?shop=<?php echo $shopQuery; ?>"><span
                            class="material-symbols-outlined text-[22px]">event</span>Appointments</a>
                    <?php endif; ?>
                    <?php if (canAccessModule('reportsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="reportsadmin.php?shop=<?php echo $shopQuery; ?>"><span
                            class="material-symbols-outlined text-[22px]">description</span>Reports</a>
                    <?php endif; ?>
                    <?php if (canAccessModule('inventoryadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-medium"
                        href="inventoryadmin.php?shop=<?php echo $shopQuery; ?>"><span
                            class="material-symbols-outlined text-[22px]">inventory_2</span>Inventory</a>
                    <?php endif; ?>
                    <?php if (canAccessModule('customeradmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="customeradmin.php?shop=<?php echo $shopQuery; ?>"><span
                            class="material-symbols-outlined text-[22px]">group</span>Customers</a>
                    <?php endif; ?>
                    <?php if (canAccessModule('paymentsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="paymentsadmin.php?shop=<?php echo $shopQuery; ?>"><span
                            class="material-symbols-outlined text-[22px]">payments</span>Payments</a>
                    <?php endif; ?>
                    <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800">
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            href="settingsadmin.php?shop=<?php echo $shopQuery; ?>"><span
                                class="material-symbols-outlined text-[22px]">settings</span>Settings</a>
                    </div>
                </nav>
            </div>
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center overflow-hidden">
                        <span class="material-symbols-outlined text-slate-500 dark:text-slate-400">person</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate text-slate-900 dark:text-white"><?php echo h($loggedInUserName); ?></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate"><?php echo h($loggedInUserRole); ?></p>
                    </div>
                    <form method="post" action="../logout/logout.php" class="inline">
                        <input type="hidden" name="action" value="confirm" />
                        <input type="hidden" name="shop"
                            value="<?php echo htmlspecialchars($loginSlug, ENT_QUOTES, 'UTF-8'); ?>" />
                        <button type="submit" class="text-slate-400 hover:text-error transition-colors"
                            title="Logout"><span class="material-symbols-outlined text-xl">logout</span></button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 bg-background-light dark:bg-background-dark overflow-y-auto">
            <header
                class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
                <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Inventory Management</h2>
                <div class="flex items-center gap-4">
                    <button class="p-2 text-slate-500 hover:text-primary transition-all"><span
                            class="material-symbols-outlined">notifications</span></button>
                    <button class="p-2 text-slate-500 hover:text-primary transition-all"><span
                            class="material-symbols-outlined">help_outline</span></button>
                </div>
            </header>

            <main class="p-8 max-w-[1600px] mx-auto w-full space-y-8">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Inventory Control
                        </h2>
                        <p class="text-slate-500 dark:text-slate-400">Track parts, stock movements, and reorder
                            thresholds from one place.</p>
                    </div>
                    <a href="#item-form"
                        class="flex items-center justify-center gap-2 bg-primary text-white px-6 py-2.5 rounded-lg font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20"><span
                            class="material-symbols-outlined text-lg">add</span>Add New Part</a>
                </div>

                <?php if ($message !== ''): ?>
                    <div
                        class="rounded-xl border px-4 py-3 <?php echo $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-900/10 dark:text-emerald-200' : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/40 dark:bg-red-900/10 dark:text-red-200'; ?>">
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div
                        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <div
                                class="p-2 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg">
                                <span class="material-symbols-outlined">category</span></div><span
                                class="text-xs font-bold text-slate-600 bg-slate-100 dark:bg-slate-800 dark:text-slate-300 px-2 py-1 rounded-full">Items</span>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Total Items</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white">
                            <?php echo number_format($stats['total_items']); ?></h3>
                    </div>
                    <div
                        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <div
                                class="p-2 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-lg">
                                <span class="material-symbols-outlined">warning</span></div><span
                                class="text-xs font-bold text-amber-600 bg-amber-100 px-2 py-1 rounded-full">Warning</span>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Low Stock Items</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white">
                            <?php echo number_format($stats['low_stock']); ?></h3>
                    </div>
                    <div
                        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <div class="p-2 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg">
                                <span class="material-symbols-outlined">error</span></div><span
                                class="text-xs font-bold text-red-600 bg-red-100 px-2 py-1 rounded-full">Critical</span>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Out of Stock</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white">
                            <?php echo number_format($stats['out_of_stock']); ?></h3>
                    </div>
                    <div
                        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <div
                                class="p-2 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg">
                                <span class="material-symbols-outlined">payments</span></div><span
                                class="text-xs font-bold text-emerald-600 bg-emerald-100 px-2 py-1 rounded-full">Value</span>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Total Inventory Value</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white">
                            PHP <?php echo number_format($stats['inventory_value'], 2); ?></h3>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-[1fr_380px] gap-8 items-start">
                    <div class="space-y-6">
                        <div id="item-form"
                            class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-6">
                                <div>
                                    <h3 class="text-xl font-bold text-slate-900 dark:text-white">
                                        <?php echo $formData['item_id'] > 0 ? 'Edit Inventory Item' : 'Add Inventory Item'; ?>
                                    </h3>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Save item details and opening
                                        stock for the current tenant.</p>
                                </div>
                                <?php if ($formData['item_id'] > 0): ?><a
                                        href="inventoryadmin.php?shop=<?php echo $shopQuery; ?>"
                                        class="text-sm font-semibold text-primary hover:underline">Cancel
                                        edit</a><?php endif; ?>
                            </div>
                           <form method="post" action="/tenant/inventoryadmin.php?shop=<?php echo urlencode($loginSlug); ?>#item-form" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                                <input type="hidden" name="inventory_action" value="save_item" />
                                <input type="hidden" name="item_id" value="<?php echo (int) $formData['item_id']; ?>" />
                                <div><label
                                        class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Part
                                        Name</label><input name="part_name"
                                        value="<?php echo htmlspecialchars($formData['part_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary"
                                        required /></div>
                                <div><label
                                        class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Part
                                        Code</label><input name="part_code"
                                        value="<?php echo htmlspecialchars($formData['part_code'], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary"
                                        placeholder="Optional unique code" /></div>
                                <div><label
                                        class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Category</label><select
                                        name="category"
                                        class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary"><?php foreach ($categories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php echo $formData['category'] === $category ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select></div>
                                <div><label
                                        class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Stock
                                        Quantity</label><input type="number" min="0" name="stock_quantity"
                                        value="<?php echo htmlspecialchars((string) $formData['stock_quantity'], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary"
                                        required /></div>
                                <div><label
                                        class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Reorder
                                        Level</label><input type="number" min="0" name="reorder_level"
                                        value="<?php echo htmlspecialchars((string) $formData['reorder_level'], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary"
                                        required /></div>
                                <div><label
                                        class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Unit
                                        Price</label><input type="number" step="0.01" min="0" name="unit_price"
                                        value="<?php echo htmlspecialchars((string) $formData['unit_price'], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary"
                                        required /></div>
                                <div><label
                                        class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Supplier
                                        Name</label><input name="supplier_name"
                                        value="<?php echo htmlspecialchars($formData['supplier_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary"
                                        placeholder="Optional supplier" /></div>
                                <div><label
                                        class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Status</label><select
                                        name="status"
                                        class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary"><?php foreach ($statuses as $status): ?>
                                            <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php echo $formData['status'] === $status ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select></div>
                                <div class="md:col-span-2 xl:col-span-3 flex justify-end"><button type="submit"
                                        class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-lg font-bold hover:bg-primary/90 transition-colors"><span
                                            class="material-symbols-outlined text-lg"><?php echo $formData['item_id'] > 0 ? 'save' : 'add'; ?></span><?php echo $formData['item_id'] > 0 ? 'Update Item' : 'Save Item'; ?></button>
                                </div>
                            </form>
                        </div>

                        <div
                            class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-4">
                            <form method="get" action="inventoryadmin.php" class="flex-1 min-w-[220px] relative">
                                <input type="hidden" name="shop"
                                    value="<?php echo htmlspecialchars($loginSlug, ENT_QUOTES, 'UTF-8'); ?>" />
                                <span
                                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                                <input name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                                    class="w-full text-sm bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg pl-9 pr-3 py-2 focus:ring-primary"
                                    placeholder="Filter by part name or code..." type="text" />
                            </form>
                            <form method="get" action="inventoryadmin.php" class="contents">
                                <input type="hidden" name="shop"
                                    value="<?php echo htmlspecialchars($loginSlug, ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="hidden" name="q"
                                    value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" />
                                <select name="category"
                                    class="text-sm bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg py-2 pl-3 pr-8 focus:ring-primary">
                                    <option value="">All Categories</option><?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php echo $categoryFilter === $category ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="status"
                                    class="text-sm bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg py-2 pl-3 pr-8 focus:ring-primary">
                                    <option value="">All Status</option><?php foreach ($statuses as $status): ?>
                                        <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="stock"
                                    class="text-sm bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg py-2 pl-3 pr-8 focus:ring-primary">
                                    <option value="">All Stock Levels</option>
                                    <option value="normal" <?php echo $stockFilter === 'normal' ? 'selected' : ''; ?>>In
                                        Stock</option>
                                    <option value="low" <?php echo $stockFilter === 'low' ? 'selected' : ''; ?>>Low Stock
                                    </option>
                                    <option value="out" <?php echo $stockFilter === 'out' ? 'selected' : ''; ?>>Out of
                                        Stock</option>
                                </select>
                                <button type="submit"
                                    class="p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 transition-colors"><span
                                        class="material-symbols-outlined text-slate-500">filter_list</span></button>
                            </form>
                        </div>

                        <div
                            class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr
                                            class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800">
                                            <th
                                                class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                                Part Name / ID</th>
                                            <th
                                                class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                                Category</th>
                                            <th
                                                class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                                Stock Level</th>
                                            <th
                                                class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                                Unit Price</th>
                                            <th
                                                class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                                Supplier</th>
                                            <th
                                                class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">
                                                Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <?php if (count($items) === 0): ?>
                                            <tr>
                                                <td colspan="6" class="px-6 py-10 text-center text-slate-500">No inventory
                                                    items found.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($items as $item): ?>
                                            <?php $barWidth = min(100, (int) round(((int) $item['stock_quantity'] / max(1, (int) $item['reorder_level'] * 2)) * 100)); ?>
                                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div class="flex flex-col"><span
                                                            class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($item['part_name'], ENT_QUOTES, 'UTF-8'); ?></span><span
                                                            class="text-xs text-slate-500">ID:
                                                            #<?php echo htmlspecialchars((string) $item['item_id'], ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($item['part_code']) ? ' • Code: ' . htmlspecialchars((string) $item['part_code'], ENT_QUOTES, 'UTF-8') : ''; ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4"><span
                                                        class="px-2.5 py-1 text-xs font-medium rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400"><?php echo htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="w-full max-w-[140px]">
                                                        <div class="flex justify-between items-center mb-1"><span
                                                                class="text-xs font-bold <?php echo (int) $item['stock_quantity'] <= 0 ? 'text-red-600' : ((int) $item['stock_quantity'] < LOW_STOCK_THRESHOLD ? 'text-amber-600' : 'text-slate-700 dark:text-slate-300'); ?>"><?php echo number_format((int) $item['stock_quantity']); ?>
                                                                units</span><span
                                                                class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full <?php echo stockBadgeClass($item); ?>"><?php echo stockLabel($item); ?></span>
                                                        </div>
                                                        <div
                                                            class="h-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                                            <div class="h-full <?php echo (int) $item['stock_quantity'] <= 0 ? 'bg-red-500' : ((int) $item['stock_quantity'] < LOW_STOCK_THRESHOLD ? 'bg-amber-500' : 'bg-green-500'); ?>"
                                                                style="width: <?php echo $barWidth; ?>%;"></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">
                                                    PHP <?php echo number_format((float) $item['unit_price'], 2); ?></td>
                                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                                    <?php echo htmlspecialchars((string) (!empty($item['supplier_name']) ? $item['supplier_name'] : 'Not set'), ENT_QUOTES, 'UTF-8'); ?>
                                                </td>
                                                <td class="px-6 py-4 text-right space-x-2">
                                                    <a href="inventoryadmin.php?shop=<?php echo $shopQuery; ?>&edit=<?php echo (int) $item['item_id']; ?>"
                                                        class="inline-flex items-center justify-center p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-400 hover:text-primary transition-colors"
                                                        title="Edit item"><span
                                                            class="material-symbols-outlined text-lg">edit</span></a>
                                                    <form method="post"
                                                        action="inventoryadmin.php?shop=<?php echo $shopQuery; ?>"
                                                        class="inline">
                                                        <input type="hidden" name="inventory_action"
                                                            value="toggle_status" />
                                                        <input type="hidden" name="item_id"
                                                            value="<?php echo (int) $item['item_id']; ?>" />
                                                        <input type="hidden" name="next_status"
                                                            value="<?php echo htmlspecialchars($item['status'] === 'Active' ? 'Inactive' : 'Active', ENT_QUOTES, 'UTF-8'); ?>" />
                                                        <button type="submit"
                                                            class="inline-flex items-center justify-center p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors"
                                                            title="Toggle status"><span
                                                                class="material-symbols-outlined text-lg"><?php echo $item['status'] === 'Active' ? 'visibility_off' : 'visibility'; ?></span></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div
                                class="bg-slate-50 dark:bg-slate-800/50 px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4">
                                <span class="text-xs text-slate-500 font-medium">Showing
                                    <?php echo $firstRow; ?>-<?php echo $lastRow; ?> of
                                    <?php echo number_format($filteredTotal); ?> results</span>
                                <div class="flex gap-2">
                                    <a href="inventoryadmin.php?<?php echo htmlspecialchars($queryStringBase . '&page=' . max(1, $page - 1), ENT_QUOTES, 'UTF-8'); ?>"
                                        class="px-3 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors <?php echo $page <= 1 ? 'pointer-events-none opacity-50' : ''; ?>">Previous</a>
                                    <?php for ($i = max(1, $page - 1); $i <= min($totalPages, $page + 1); $i++): ?>
                                        <a href="inventoryadmin.php?<?php echo htmlspecialchars($queryStringBase . '&page=' . $i, ENT_QUOTES, 'UTF-8'); ?>"
                                            class="px-3 py-1 rounded text-xs font-bold transition-colors <?php echo $i === $page ? 'bg-primary text-white' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'; ?>"><?php echo $i; ?></a>
                                    <?php endfor; ?>
                                    <a href="inventoryadmin.php?<?php echo htmlspecialchars($queryStringBase . '&page=' . min($totalPages, $page + 1), ENT_QUOTES, 'UTF-8'); ?>"
                                        class="px-3 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors <?php echo $page >= $totalPages ? 'pointer-events-none opacity-50' : ''; ?>">Next</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="space-y-6">
                        <div
                            class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2"><span
                                        class="material-symbols-outlined text-amber-500">notifications_active</span>Low
                                    Stock Alerts</h3>
                                <span
                                    class="text-[10px] font-bold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full uppercase tracking-tight">Active</span>
                            </div>
                            <div class="space-y-4">
                                <?php if (count($lowStockAlerts) === 0): ?>
                                    <p class="text-sm text-slate-500">No low stock alerts right now.</p><?php endif; ?>
                                <?php foreach ($lowStockAlerts as $alert): ?>
                                    <div
                                        class="p-3 <?php echo (int) $alert['stock_quantity'] <= 0 ? 'bg-red-50 dark:bg-red-900/10 border-red-500' : 'bg-amber-50 dark:bg-amber-900/10 border-amber-500'; ?> border-l-4 rounded-r-lg">
                                        <div class="flex justify-between items-start mb-1">
                                            <p class="text-sm font-bold text-slate-900 dark:text-white">
                                                <?php echo htmlspecialchars($alert['part_name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </p><span
                                                class="text-[10px] font-black <?php echo (int) $alert['stock_quantity'] <= 0 ? 'text-red-600' : 'text-amber-600'; ?>"><?php echo (int) $alert['stock_quantity'] <= 0 ? 'OUT OF STOCK' : (int) $alert['stock_quantity'] . ' LEFT'; ?></span>
                                        </div>
                                        <p class="text-xs text-slate-500 mb-2">Low stock threshold: less than
                                            <?php echo LOW_STOCK_THRESHOLD; ?>
                                            units<?php echo !empty($alert['supplier_name']) ? ' • Supplier: ' . htmlspecialchars($alert['supplier_name'], ENT_QUOTES, 'UTF-8') : ''; ?>.
                                        </p>
                                        <a href="inventoryadmin.php?shop=<?php echo $shopQuery; ?>&edit=<?php echo (int) $alert['item_id']; ?>"
                                            class="text-xs font-bold text-primary hover:underline flex items-center gap-1">Review
                                            item <span class="material-symbols-outlined text-sm">chevron_right</span></a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div
                            class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2"><span
                                        class="material-symbols-outlined text-blue-500">swap_vert</span>Record Movement
                                </h3>
                            </div>
                            <form method="post" class="space-y-4">
                                <input type="hidden" name="inventory_action" value="record_movement" />
                                <div><label
                                        class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Item</label><select
                                        name="item_id"
                                        class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary">
                                        <option value="">Select item</option><?php foreach ($itemOptions as $option): ?>
                                            <option value="<?php echo (int) $option['item_id']; ?>" <?php echo (int) $movementData['item_id'] === (int) $option['item_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($option['part_name'] . (!empty($option['part_code']) ? ' (' . $option['part_code'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                                            </option><?php endforeach; ?>
                                    </select></div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div><label
                                            class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Type</label><select
                                            name="movement_type"
                                            class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary"><?php foreach ($movementTypes as $type): ?>
                                                <option value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php echo $movementData['movement_type'] === $type ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </select></div>
                                    <div><label
                                            class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Quantity</label><input
                                            type="number" name="quantity"
                                            value="<?php echo htmlspecialchars((string) $movementData['quantity'], ENT_QUOTES, 'UTF-8'); ?>"
                                            class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary"
                                            required /></div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div><label
                                            class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Reference</label><select
                                            name="reference_type"
                                            class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary"><?php foreach ($referenceTypes as $type): ?>
                                                <option value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php echo $movementData['reference_type'] === $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </select></div>
                                    <div><label
                                            class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Reference
                                            ID</label><input type="number" min="0" name="reference_id"
                                            value="<?php echo htmlspecialchars((string) $movementData['reference_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary"
                                            placeholder="Optional" /></div>
                                </div>
                                <div><label
                                        class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Notes</label><textarea
                                        name="notes" rows="3"
                                        class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary"
                                        placeholder="Optional movement notes"><?php echo htmlspecialchars($movementData['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                                <button type="submit"
                                    class="w-full bg-slate-900 text-white px-5 py-2.5 rounded-lg font-bold hover:bg-slate-800 transition-colors dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white">Save
                                    Movement</button>
                            </form>
                        </div>

                        <div
                            class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2"><span
                                        class="material-symbols-outlined text-primary">history</span>Recent Movements
                                </h3>
                            </div>
                            <div class="space-y-4">
                                <?php if (count($recentMovements) === 0): ?>
                                    <p class="text-sm text-slate-500">No stock movements yet.</p><?php endif; ?>
                                <?php foreach ($recentMovements as $movement): ?>
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-bold text-slate-900 dark:text-white">
                                                <?php echo htmlspecialchars($movement['part_name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </p>
                                            <p class="text-[11px] text-slate-500">
                                                <?php echo htmlspecialchars($movement['reference_type'], ENT_QUOTES, 'UTF-8'); ?>    <?php echo $movement['reference_id'] !== null ? '#' . htmlspecialchars((string) $movement['reference_id'], ENT_QUOTES, 'UTF-8') : ''; ?>
                                            </p>
                                            <?php if (!empty($movement['notes'])): ?>
                                                <p class="text-xs text-slate-500 mt-1">
                                                    <?php echo htmlspecialchars($movement['notes'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo movementBadgeClass((string) $movement['movement_type']); ?>"><?php echo htmlspecialchars($movement['movement_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <p class="text-xs text-slate-500 mt-1">
                                                <?php echo ((string) $movement['movement_type'] === 'OUT' ? '-' : '+'); ?>    <?php echo number_format((int) $movement['quantity']); ?>
                                            </p>
                                            <p class="text-[10px] text-slate-400 mt-1">
                                                <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime((string) $movement['created_at'])), ENT_QUOTES, 'UTF-8'); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
    </div>
    <script>
        // Dropdown menu click handler
        document.querySelectorAll('.settings-dropdown-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = document.querySelector('[data-dropdown="settings"].settings-dropdown');
                if (dropdown) {
                    dropdown.classList.toggle('hidden');
                }
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdownBtn = document.querySelector('.settings-dropdown-btn');
            const dropdown = document.querySelector('[data-dropdown="settings"].settings-dropdown');
            if (dropdown && dropdownBtn && !dropdownBtn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>

</html>