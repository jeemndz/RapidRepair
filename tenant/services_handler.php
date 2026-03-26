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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../db.php';

// Get the action from the request
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : null);
$tenantID = isset($_SESSION['tenantID']) ? $_SESSION['tenantID'] : null;

// Verify tenant is logged in
if (!$tenantID) {
    jsonResponse(401, ['success' => false, 'message' => 'Unauthorized']);
}

switch($action) {
    case 'get_all':
        getServices($conn, $tenantID);
        break;
    case 'get_single':
        getServiceById($conn, $tenantID);
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

/**
 * Get all services for the tenant
 */
function getServices($conn, $tenantID) {
    $query = "SELECT * FROM services WHERE tenantID = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    $stmt->bind_param('i', $tenantID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $services = [];
    while($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    
    $stmt->close();
    
    jsonResponse(200, [
        'success' => true,
        'services' => $services,
        'count' => count($services)
    ]);
}

/**
 * Get a single service by ID
 */
function getServiceById($conn, $tenantID) {
    $service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : null;
    
    if (!$service_id) {
        jsonResponse(400, ['success' => false, 'message' => 'Service ID is required']);
    }
    
    $query = "SELECT * FROM services WHERE service_id = ? AND tenantID = ?";
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

/**
 * Add a new service
 */
function addService($conn, $tenantID) {
    $service_name = isset($_POST['service_name']) ? trim($_POST['service_name']) : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : null;
    $duration_minutes = isset($_POST['duration_minutes']) ? intval($_POST['duration_minutes']) : null;
    $category = isset($_POST['category']) ? trim($_POST['category']) : null;
    $status = isset($_POST['status']) ? $_POST['status'] : 'Active';
    
    // Validate required fields
    if (!$service_name || $price === null) {
        jsonResponse(400, ['success' => false, 'message' => 'Service name and price are required']);
    }
    
    if ($price < 0) {
        jsonResponse(400, ['success' => false, 'message' => 'Price must be a positive number']);
    }

    $allowedCategories = ['Engine', 'Electrical', 'Maintenance', 'Brakes', 'Suspension', 'Transmission', 'Cooling System', 'Diagnostics', 'Other'];
    if (!$category || !in_array($category, $allowedCategories, true)) {
        $category = 'Other';
    }
    
    $query = "INSERT INTO services (tenantID, service_name, description, price, duration_minutes, category, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    $stmt->bind_param('issidss', $tenantID, $service_name, $description, $price, $duration_minutes, $category, $status);
    
    if ($stmt->execute()) {
        $service_id = $stmt->insert_id;
        $stmt->close();
        
        jsonResponse(200, [
            'success' => true,
            'message' => 'Service added successfully',
            'service_id' => $service_id
        ]);
    } else {
        $stmt->close();
        
        // Check for duplicate service name error
        if (strpos($conn->error, 'Duplicate entry') !== false) {
            jsonResponse(409, ['success' => false, 'message' => 'A service with this name already exists']);
        } else {
            jsonResponse(500, ['success' => false, 'message' => 'Error adding service: ' . $conn->error]);
        }
    }
}

/**
 * Update an existing service
 */
function updateService($conn, $tenantID) {
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : null;
    $service_name = isset($_POST['service_name']) ? trim($_POST['service_name']) : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : null;
    $duration_minutes = isset($_POST['duration_minutes']) ? intval($_POST['duration_minutes']) : null;
    $category = isset($_POST['category']) ? trim($_POST['category']) : null;
    $status = isset($_POST['status']) ? $_POST['status'] : null;
    
    // Validate required fields
    if (!$service_id || !$service_name || $price === null) {
        jsonResponse(400, ['success' => false, 'message' => 'Service ID, name and price are required']);
    }
    
    if ($price < 0) {
        jsonResponse(400, ['success' => false, 'message' => 'Price must be a positive number']);
    }

    $allowedCategories = ['Engine', 'Electrical', 'Maintenance', 'Brakes', 'Suspension', 'Transmission', 'Cooling System', 'Diagnostics', 'Other'];
    if (!$category || !in_array($category, $allowedCategories, true)) {
        $category = 'Other';
    }
    
    // Verify service belongs to tenant
    $verify_query = "SELECT service_id FROM services WHERE service_id = ? AND tenantID = ?";
    $verify_stmt = $conn->prepare($verify_query);
    if (!$verify_stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    $verify_stmt->bind_param('ii', $service_id, $tenantID);
    $verify_stmt->execute();
    
    if ($verify_stmt->get_result()->num_rows === 0) {
        $verify_stmt->close();
        jsonResponse(403, ['success' => false, 'message' => 'Service not found or unauthorized']);
    }
    $verify_stmt->close();
    
    if ($status) {
        $query = "UPDATE services SET service_name = ?, description = ?, price = ?, duration_minutes = ?, category = ?, status = ? WHERE service_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->bind_param('ssdissi', $service_name, $description, $price, $duration_minutes, $category, $status, $service_id);
    } else {
        $query = "UPDATE services SET service_name = ?, description = ?, price = ?, duration_minutes = ?, category = ? WHERE service_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->bind_param('ssdisi', $service_name, $description, $price, $duration_minutes, $category, $service_id);
    }
    
    if ($stmt->execute()) {
        $stmt->close();
        
        jsonResponse(200, [
            'success' => true,
            'message' => 'Service updated successfully'
        ]);
    } else {
        $stmt->close();
        
        if (strpos($conn->error, 'Duplicate entry') !== false) {
            jsonResponse(409, ['success' => false, 'message' => 'A service with this name already exists']);
        } else {
            jsonResponse(500, ['success' => false, 'message' => 'Error updating service: ' . $conn->error]);
        }
    }
}

/**
 * Delete a service
 */
function deleteService($conn, $tenantID) {
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : null;
    
    if (!$service_id) {
        jsonResponse(400, ['success' => false, 'message' => 'Service ID is required']);
    }
    
    // Verify service belongs to tenant
    $verify_query = "SELECT service_id FROM services WHERE service_id = ? AND tenantID = ?";
    $verify_stmt = $conn->prepare($verify_query);
    if (!$verify_stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    $verify_stmt->bind_param('ii', $service_id, $tenantID);
    $verify_stmt->execute();
    
    if ($verify_stmt->get_result()->num_rows === 0) {
        $verify_stmt->close();
        jsonResponse(403, ['success' => false, 'message' => 'Service not found or unauthorized']);
    }
    $verify_stmt->close();
    
    $query = "DELETE FROM services WHERE service_id = ? AND tenantID = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    $stmt->bind_param('ii', $service_id, $tenantID);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        jsonResponse(200, [
            'success' => true,
            'message' => 'Service deleted successfully'
        ]);
    } else {
        $stmt->close();
        jsonResponse(500, ['success' => false, 'message' => 'Error deleting service: ' . $conn->error]);
    }
}

/**
 * Change service status (Active/Inactive)
 */
function changeServiceStatus($conn, $tenantID) {
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : null;
    $status = isset($_POST['status']) ? $_POST['status'] : null;
    
    if (!$service_id || !$status || !in_array($status, ['Active', 'Inactive'])) {
        jsonResponse(400, ['success' => false, 'message' => 'Service ID and valid status are required']);
    }
    
    // Verify service belongs to tenant
    $verify_query = "SELECT service_id FROM services WHERE service_id = ? AND tenantID = ?";
    $verify_stmt = $conn->prepare($verify_query);
    if (!$verify_stmt) {
        jsonResponse(500, ['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    $verify_stmt->bind_param('ii', $service_id, $tenantID);
    $verify_stmt->execute();
    
    if ($verify_stmt->get_result()->num_rows === 0) {
        $verify_stmt->close();
        jsonResponse(403, ['success' => false, 'message' => 'Service not found or unauthorized']);
    }
    $verify_stmt->close();
    
    $query = "UPDATE services SET status = ? WHERE service_id = ? AND tenantID = ?";
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
    } else {
        $stmt->close();
        jsonResponse(500, ['success' => false, 'message' => 'Error updating status: ' . $conn->error]);
    }
}
?>
