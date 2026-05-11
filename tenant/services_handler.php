<?php
/**
 * Service Management Handler
 * Handles all CRUD operations for services
 */

ob_start();
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($exception) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $exception->getMessage()
    ]);
    exit;
});

function jsonResponse(int $statusCode, array $payload): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db.php';
require_once '../log_helper.php';

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$tenantID = $_SESSION['tenantID'] ?? null;

if (!$tenantID) {
    jsonResponse(401, ['success' => false, 'message' => 'Unauthorized']);
}

$tenantID = (int) $tenantID;

switch ($action) {
    case 'get_all':
        getServices($conn, $tenantID);
        break;
    case 'get_single':
        getServiceById($conn, $tenantID);
        break;
    case 'get_count':
        getServicesCount($conn, $tenantID);
        break;
    case 'add':
        addService($conn, $tenantID);
        break;
    case 'update':
        updateService($conn, $tenantID);
        break;
    case 'delete':
        deleteService($conn, $tenantID);
        break;
    case 'change_status':
        changeServiceStatus($conn, $tenantID);
        break;
    default:
        jsonResponse(400, ['success' => false, 'message' => 'Invalid action']);
}

function normalizeCategory($category) {
    $allowedCategories = [
        'Engine',
        'Electrical',
        'Maintenance',
        'Brakes',
        'Suspension',
        'Transmission',
        'Cooling System',
        'Diagnostics',
        'Other'
    ];

    $category = trim((string) $category);

    if (!$category || !in_array($category, $allowedCategories, true)) {
        return 'Other';
    }

    return $category;
}

function normalizeStatus($status) {
    return in_array($status, ['Active', 'Inactive'], true) ? $status : 'Active';
}

function normalizeServiceType($service_type) {
    return in_array($service_type, ['Main', 'Sub'], true) ? $service_type : 'Main';
}

function validateParentService($conn, $tenantID, $parent_service_id, $current_service_id = null) {
    if (!$parent_service_id) {
        return null;
    }

    $parent_service_id = (int) $parent_service_id;

    if ($current_service_id && $parent_service_id === (int) $current_service_id) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'A service cannot be its own parent'
        ]);
    }

    $query = "
        SELECT service_id 
        FROM services 
        WHERE service_id = ? 
        AND tenantID = ? 
        AND service_type = 'Main'
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $stmt->bind_param('ii', $parent_service_id, $tenantID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        jsonResponse(400, [
            'success' => false,
            'message' => 'Invalid parent service. Please select an existing main service.'
        ]);
    }

    $stmt->close();

    return $parent_service_id;
}

function insertServiceRow(
    $conn,
    int $tenantID,
    $parent_service_id,
    string $service_type,
    string $service_name,
    string $description,
    float $price,
    int $duration_minutes,
    string $category,
    string $status
) {
    $query = "
        INSERT INTO services (
            tenantID,
            parent_service_id,
            service_type,
            service_name,
            description,
            price,
            duration_minutes,
            category,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        return [
            'success' => false,
            'error' => 'Database error: ' . $conn->error,
            'insert_id' => 0
        ];
    }

    $stmt->bind_param(
        'iisssdiss',
        $tenantID,
        $parent_service_id,
        $service_type,
        $service_name,
        $description,
        $price,
        $duration_minutes,
        $category,
        $status
    );

    if (!$stmt->execute()) {
        $error = $stmt->error ?: $conn->error;
        $stmt->close();

        return [
            'success' => false,
            'error' => $error,
            'insert_id' => 0
        ];
    }

    $insert_id = $stmt->insert_id;
    $stmt->close();

    return [
        'success' => true,
        'error' => null,
        'insert_id' => $insert_id
    ];
}

function getServices($conn, $tenantID) {
    $query = "
        SELECT 
            s.*,
            p.service_name AS parent_service_name
        FROM services s
        LEFT JOIN services p 
            ON s.parent_service_id = p.service_id
            AND p.tenantID = s.tenantID
        WHERE s.tenantID = ?
        ORDER BY 
            COALESCE(s.parent_service_id, s.service_id),
            CASE WHEN s.service_type = 'Main' THEN 0 ELSE 1 END,
            s.service_name ASC
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $stmt->bind_param('i', $tenantID);
    $stmt->execute();
    $result = $stmt->get_result();

    $services = [];

    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }

    $stmt->close();

    jsonResponse(200, [
        'success' => true,
        'services' => $services,
        'count' => count($services)
    ]);
}

function getServicesCount($conn, $tenantID) {
    $query = "
        SELECT COUNT(*) AS total 
        FROM services 
        WHERE tenantID = ? 
        AND status = 'Active'
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $stmt->bind_param('i', $tenantID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    jsonResponse(200, [
        'success' => true,
        'count' => (int) $row['total']
    ]);
}

function getServiceById($conn, $tenantID) {
    $service_id = isset($_GET['service_id']) ? (int) $_GET['service_id'] : 0;

    if (!$service_id) {
        jsonResponse(400, ['success' => false, 'message' => 'Service ID is required']);
    }

    $query = "
        SELECT 
            s.*,
            p.service_name AS parent_service_name
        FROM services s
        LEFT JOIN services p 
            ON s.parent_service_id = p.service_id
            AND p.tenantID = s.tenantID
        WHERE s.service_id = ? 
        AND s.tenantID = ?
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $stmt->bind_param('ii', $service_id, $tenantID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        jsonResponse(404, ['success' => false, 'message' => 'Service not found']);
    }

    $service = $result->fetch_assoc();
    $stmt->close();

    jsonResponse(200, [
        'success' => true,
        'service' => $service
    ]);
}

function addService($conn, $tenantID) {
    $service_type = normalizeServiceType($_POST['service_type'] ?? 'Main');
    $parent_service_id = $_POST['parent_service_id'] ?? null;

    $service_name = trim($_POST['service_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = isset($_POST['price']) ? (float) $_POST['price'] : null;
    $duration_minutes = isset($_POST['duration_minutes']) && $_POST['duration_minutes'] !== ''
        ? (int) $_POST['duration_minutes']
        : 0;
    $category = normalizeCategory($_POST['category'] ?? 'Other');
    $status = normalizeStatus($_POST['status'] ?? 'Active');

    if (!$service_name || $price === null) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Service name and price are required'
        ]);
    }

    if ($price < 0) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Price must be a positive number'
        ]);
    }

    if ($duration_minutes < 0) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Duration must be a positive number'
        ]);
    }

    if ($service_type === 'Main') {
        $parent_service_id = null;
    } else {
        if (!$parent_service_id) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'Parent main service is required for sub-services'
            ]);
        }

        $parent_service_id = validateParentService($conn, $tenantID, $parent_service_id);
    }

    $conn->begin_transaction();

    $insertMain = insertServiceRow(
        $conn,
        $tenantID,
        $parent_service_id,
        $service_type,
        $service_name,
        $description,
        $price,
        $duration_minutes,
        $category,
        $status
    );

    if (!$insertMain['success']) {
        $conn->rollback();

        if (strpos($insertMain['error'], 'Duplicate entry') !== false) {
            jsonResponse(409, [
                'success' => false,
                'message' => 'A service with this name already exists'
            ]);
        }

        jsonResponse(500, [
            'success' => false,
            'message' => 'Error adding service: ' . $insertMain['error']
        ]);
    }

    $service_id = (int) $insertMain['insert_id'];
    $sub_services_added = 0;

    if ($service_type === 'Main' && !empty($_POST['sub_services'])) {
        $subServices = json_decode($_POST['sub_services'], true);

        if (!is_array($subServices)) {
            $conn->rollback();
            jsonResponse(400, [
                'success' => false,
                'message' => 'Invalid sub-services data'
            ]);
        }

        foreach ($subServices as $index => $sub) {
            $sub_name = trim($sub['service_name'] ?? $sub['name'] ?? '');
            $sub_description = trim($sub['description'] ?? '');
            $sub_price = isset($sub['price']) && $sub['price'] !== ''
                ? (float) $sub['price']
                : null;
            $sub_duration = isset($sub['duration_minutes']) && $sub['duration_minutes'] !== ''
                ? (int) $sub['duration_minutes']
                : 0;

            if ($sub_name === '' && $sub_price === null && $sub_description === '') {
                continue;
            }

            if ($sub_name === '') {
                $conn->rollback();
                jsonResponse(400, [
                    'success' => false,
                    'message' => 'Sub-service #' . ($index + 1) . ' must have a name'
                ]);
            }

            if ($sub_price === null || $sub_price < 0) {
                $conn->rollback();
                jsonResponse(400, [
                    'success' => false,
                    'message' => 'Sub-service #' . ($index + 1) . ' must have a valid price'
                ]);
            }

            if ($sub_duration < 0) {
                $conn->rollback();
                jsonResponse(400, [
                    'success' => false,
                    'message' => 'Sub-service #' . ($index + 1) . ' duration must be valid'
                ]);
            }

            $insertSub = insertServiceRow(
                $conn,
                $tenantID,
                $service_id,
                'Sub',
                $sub_name,
                $sub_description,
                $sub_price,
                $sub_duration,
                $category,
                'Active'
            );

            if (!$insertSub['success']) {
                $conn->rollback();
                jsonResponse(500, [
                    'success' => false,
                    'message' => 'Error adding sub-service "' . $sub_name . '": ' . $insertSub['error']
                ]);
            }

            $sub_services_added++;

            log_event(
                $conn,
                'CREATE Sub Service',
                'service',
                (int) $insertSub['insert_id'],
                'Created sub-service "' . $sub_name . '" under main service ID ' . $service_id
            );
        }
    }

    $conn->commit();

    log_event(
        $conn,
        'CREATE Service',
        'service',
        $service_id,
        'Created ' . strtolower($service_type) . ' service "' . $service_name . '" (status: ' . $status . ')'
    );

    jsonResponse(200, [
        'success' => true,
        'message' => $sub_services_added > 0
            ? 'Service and sub-services added successfully'
            : 'Service added successfully',
        'service_id' => $service_id,
        'sub_services_added' => $sub_services_added
    ]);
}

function updateService($conn, $tenantID) {
    $service_id = isset($_POST['service_id']) ? (int) $_POST['service_id'] : 0;
    $service_type = normalizeServiceType($_POST['service_type'] ?? 'Main');
    $parent_service_id = $_POST['parent_service_id'] ?? null;

    $service_name = trim($_POST['service_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = isset($_POST['price']) ? (float) $_POST['price'] : null;
    $duration_minutes = isset($_POST['duration_minutes']) && $_POST['duration_minutes'] !== ''
        ? (int) $_POST['duration_minutes']
        : 0;
    $category = normalizeCategory($_POST['category'] ?? 'Other');
    $status = normalizeStatus($_POST['status'] ?? 'Active');

    if (!$service_id || !$service_name || $price === null) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Service ID, name and price are required'
        ]);
    }

    if ($price < 0) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Price must be a positive number'
        ]);
    }

    if ($duration_minutes < 0) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Duration must be a positive number'
        ]);
    }

    $verify_query = "
        SELECT service_id, service_name, service_type, status
        FROM services 
        WHERE service_id = ? 
        AND tenantID = ?
    ";

    $verify_stmt = $conn->prepare($verify_query);

    if (!$verify_stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $verify_stmt->bind_param('ii', $service_id, $tenantID);
    $verify_stmt->execute();

    $verify_result = $verify_stmt->get_result();
    if ($verify_result->num_rows === 0) {
        $verify_stmt->close();
        jsonResponse(403, [
            'success' => false,
            'message' => 'Service not found or unauthorized'
        ]);
    }
    $verify_result->fetch_assoc();

    $verify_stmt->close();

    if ($service_type === 'Main') {
        $parent_service_id = null;
    } else {
        if (!$parent_service_id) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'Parent main service is required for sub-services'
            ]);
        }

        $parent_service_id = validateParentService($conn, $tenantID, $parent_service_id, $service_id);
    }

    $query = "
        UPDATE services 
        SET 
            parent_service_id = ?,
            service_type = ?,
            service_name = ?,
            description = ?,
            price = ?,
            duration_minutes = ?,
            category = ?,
            status = ?
        WHERE service_id = ? 
        AND tenantID = ?
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $stmt->bind_param(
        'isssdissii',
        $parent_service_id,
        $service_type,
        $service_name,
        $description,
        $price,
        $duration_minutes,
        $category,
        $status,
        $service_id,
        $tenantID
    );

    if ($stmt->execute()) {
        $stmt->close();

        log_event(
            $conn,
            'UPDATE Service',
            'service',
            $service_id,
            'Updated service "' . $service_name . '" (type: ' . $service_type . ', status: ' . $status . ')'
        );

        jsonResponse(200, [
            'success' => true,
            'message' => 'Service updated successfully'
        ]);
    }

    $error = $stmt->error ?: $conn->error;
    $stmt->close();

    if (strpos($error, 'Duplicate entry') !== false) {
        jsonResponse(409, [
            'success' => false,
            'message' => 'A service with this name already exists'
        ]);
    }

    jsonResponse(500, [
        'success' => false,
        'message' => 'Error updating service: ' . $error
    ]);
}

function deleteService($conn, $tenantID) {
    $service_id = isset($_POST['service_id']) ? (int) $_POST['service_id'] : 0;

    if (!$service_id) {
        jsonResponse(400, ['success' => false, 'message' => 'Service ID is required']);
    }

    $verify_query = "
        SELECT service_id, service_type, service_name, status
        FROM services 
        WHERE service_id = ? 
        AND tenantID = ?
    ";

    $verify_stmt = $conn->prepare($verify_query);

    if (!$verify_stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $verify_stmt->bind_param('ii', $service_id, $tenantID);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        $verify_stmt->close();
        jsonResponse(403, [
            'success' => false,
            'message' => 'Service not found or unauthorized'
        ]);
    }

    $service = $verify_result->fetch_assoc();
    $verify_stmt->close();

    $conn->begin_transaction();

    try {
        $deleted_sub_services = 0;

        // If deleting a Main service, delete all linked Sub services first.
        // If deleting a Sub service, only that specific sub-service will be deleted.
        if ($service['service_type'] === 'Main') {
            $count_query = "
                SELECT COUNT(*) AS total
                FROM services
                WHERE parent_service_id = ?
                AND tenantID = ?
                AND service_type = 'Sub'
            ";

            $count_stmt = $conn->prepare($count_query);

            if (!$count_stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }

            $count_stmt->bind_param('ii', $service_id, $tenantID);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $count_row = $count_result->fetch_assoc();
            $deleted_sub_services = (int) ($count_row['total'] ?? 0);
            $count_stmt->close();

            $delete_sub_query = "
                DELETE FROM services
                WHERE parent_service_id = ?
                AND tenantID = ?
                AND service_type = 'Sub'
            ";

            $delete_sub_stmt = $conn->prepare($delete_sub_query);

            if (!$delete_sub_stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }

            $delete_sub_stmt->bind_param('ii', $service_id, $tenantID);

            if (!$delete_sub_stmt->execute()) {
                $error = $delete_sub_stmt->error ?: $conn->error;
                $delete_sub_stmt->close();
                throw new Exception('Error deleting sub-services: ' . $error);
            }

            $delete_sub_stmt->close();
        }

        $query = "
            DELETE FROM services 
            WHERE service_id = ? 
            AND tenantID = ?
        ";

        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $stmt->bind_param('ii', $service_id, $tenantID);

        if (!$stmt->execute()) {
            $error = $stmt->error ?: $conn->error;
            $stmt->close();
            throw new Exception('Error deleting service: ' . $error);
        }

        $stmt->close();
        $conn->commit();

        log_event(
            $conn,
            'DELETE Service',
            'service',
            $service_id,
            'Deleted ' . strtolower((string) ($service['service_type'] ?? 'service')) . ' service "' . ((string) ($service['service_name'] ?? 'N/A')) . '"' .
            ($deleted_sub_services > 0 ? ' and ' . $deleted_sub_services . ' linked sub-service(s)' : '')
        );

        jsonResponse(200, [
            'success' => true,
            'message' => $deleted_sub_services > 0
                ? 'Main service and its sub-services deleted successfully'
                : 'Service deleted successfully',
            'deleted_sub_services' => $deleted_sub_services
        ]);
    } catch (Throwable $e) {
        $conn->rollback();

        jsonResponse(500, [
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function changeServiceStatus($conn, $tenantID) {
    $service_id = isset($_POST['service_id']) ? (int) $_POST['service_id'] : 0;
    $status = $_POST['status'] ?? null;

    if (!$service_id || !in_array($status, ['Active', 'Inactive'], true)) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Service ID and valid status are required'
        ]);
    }

    $verify_query = "
        SELECT service_id 
        FROM services 
        WHERE service_id = ? 
        AND tenantID = ?
    ";

    $verify_stmt = $conn->prepare($verify_query);

    if (!$verify_stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $verify_stmt->bind_param('ii', $service_id, $tenantID);
    $verify_stmt->execute();

    if ($verify_stmt->get_result()->num_rows === 0) {
        $verify_stmt->close();
        jsonResponse(403, [
            'success' => false,
            'message' => 'Service not found or unauthorized'
        ]);
    }

    $verify_stmt->close();

    $query = "
        UPDATE services 
        SET status = ? 
        WHERE service_id = ? 
        AND tenantID = ?
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $stmt->bind_param('sii', $status, $service_id, $tenantID);

    if ($stmt->execute()) {
        $stmt->close();

        jsonResponse(200, [
            'success' => true,
            'message' => 'Service status updated successfully'
        ]);
    }

    $error = $stmt->error ?: $conn->error;
    $stmt->close();

    jsonResponse(500, [
        'success' => false,
        'message' => 'Error updating status: ' . $error
    ]);
}
?>
