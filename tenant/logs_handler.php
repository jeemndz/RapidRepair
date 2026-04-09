<?php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';

// Verify tenant authentication
if (!isset($_SESSION['tenantID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$tenant_id = (int)$_SESSION['tenantID'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'get_logs';

// Handle different actions
switch ($action) {
    case 'get_logs':
        handle_get_logs($conn, $tenant_id);
        break;
    
    case 'get_entity_types':
        handle_get_entity_types($conn, $tenant_id);
        break;
    
    case 'export_logs':
        handle_export_logs($conn, $tenant_id);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
}

/**
 * Fetch system logs with filtering and pagination
 */
function handle_get_logs($conn, $tenant_id) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, (int)($_GET['limit'] ?? 20));
    $offset = ($page - 1) * $limit;
    
    $search = trim($_GET['search'] ?? '');
    $action_filter = trim($_GET['action_filter'] ?? '');
    $entity_filter = trim($_GET['entity_filter'] ?? '');
    
    $where = ["tenant_id = ?"];
    $params = [$tenant_id];
    $types = "i";
    
    // Build search conditions
    if ($search !== '') {
        $where[] = "(user_name LIKE ? OR action LIKE ? OR details LIKE ? OR entity_type LIKE ?)";
        $search_param = "%{$search}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        $types .= "ssss";
    }
    
    if ($action_filter !== '') {
        $where[] = "action = ?";
        $params[] = $action_filter;
        $types .= "s";
    }
    
    if ($entity_filter !== '') {
        $where[] = "entity_type = ?";
        $params[] = $entity_filter;
        $types .= "s";
    }
    
    $where_clause = implode(" AND ", $where);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM system_logs WHERE {$where_clause}";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total = (int)$count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
    
    $total_pages = max(1, (int)ceil($total / $limit));
    
    // Ensure page doesn't exceed total pages
    if ($page > $total_pages) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }
    
    // Fetch logs
    $sql = "SELECT log_id, user_name, user_role, action, entity_type, entity_id, 
            details, ip_address, created_at, tenant_id
            FROM system_logs
            WHERE {$where_clause}
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'total' => $total,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'message' => "Loaded " . count($logs) . " logs"
    ]);
}

/**
 * Get distinct entity types for dropdown
 */
function handle_get_entity_types($conn, $tenant_id) {
    $sql = "SELECT DISTINCT entity_type FROM system_logs 
            WHERE tenant_id = ? AND entity_type IS NOT NULL AND entity_type <> ''
            ORDER BY entity_type ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $types = [];
    while ($row = $result->fetch_assoc()) {
        $types[] = $row['entity_type'];
    }
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'types' => $types
    ]);
}

/**
 * Export logs as CSV
 */
function handle_export_logs($conn, $tenant_id) {
    $search = trim($_GET['search'] ?? '');
    $action_filter = trim($_GET['action_filter'] ?? '');
    $entity_filter = trim($_GET['entity_filter'] ?? '');
    
    $where = ["tenant_id = ?"];
    $params = [$tenant_id];
    $types = "i";
    
    // Build search conditions
    if ($search !== '') {
        $where[] = "(user_name LIKE ? OR action LIKE ? OR details LIKE ? OR entity_type LIKE ?)";
        $search_param = "%{$search}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        $types .= "ssss";
    }
    
    if ($action_filter !== '') {
        $where[] = "action = ?";
        $params[] = $action_filter;
        $types .= "s";
    }
    
    if ($entity_filter !== '') {
        $where[] = "entity_type = ?";
        $params[] = $entity_filter;
        $types .= "s";
    }
    
    $where_clause = implode(" AND ", $where);
    
    // Fetch all matching logs (no pagination for export)
    $sql = "SELECT log_id, user_name, user_role, action, entity_type, entity_id, 
            details, ip_address, created_at
            FROM system_logs
            WHERE {$where_clause}
            ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Set CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    fputcsv($output, ['Timestamp', 'User', 'Role', 'Action', 'Entity Type', 'Entity ID', 'Details', 'IP Address']);
    
    // Write data rows
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['created_at'],
            $log['user_name'] ?? 'System',
            $log['user_role'] ?? 'N/A',
            $log['action'],
            $log['entity_type'] ?? '-',
            $log['entity_id'] ?? '-',
            $log['details'] ?? '',
            $log['ip_address'] ?? 'N/A'
        ]);
    }
    
    fclose($output);
    exit();
}
?>
