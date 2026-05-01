<?php
session_start();
include __DIR__ . '/db.php';
include __DIR__ . '/../log_helper.php';

// Verify tenant is logged in
if (!isset($_SESSION['tenantID'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];
$method = $_SERVER['REQUEST_METHOD'];

// Route requests
if ($method === 'POST' && isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'save_customization') {
        saveCustomization($conn, $tenantID);
    } elseif ($action === 'upload_image') {
        uploadImage($conn, $tenantID);
    }
} elseif ($method === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'get_customization') {
        getCustomization($conn, $tenantID);
    }
}

/**
 * Save website customization settings
 */
function saveCustomization($conn, $tenantID) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        exit;
    }
    
    // Prepare data
    $shopName = $data['shopName'] ?? null;
    $primaryColor = $data['primaryColor'] ?? null;
    $shopLogo = $data['shopLogo'] ?? null;
    $heroHeading = $data['heroHeading'] ?? null;
    $heroSubtext = $data['heroSubtext'] ?? null;
    $heroBackground = $data['heroBackground'] ?? null;
    $servicesData = isset($data['services']) ? json_encode($data['services']) : null;
    $ctaButtonText = $data['ctaButtonText'] ?? null;
    
    // Check if customization exists for this tenant
    $checkStmt = mysqli_prepare($conn, "SELECT customizationID FROM website_customizations WHERE tenantID = ? LIMIT 1");
    mysqli_stmt_bind_param($checkStmt, "i", $tenantID);
    mysqli_stmt_execute($checkStmt);
    $result = mysqli_stmt_get_result($checkStmt);
    $exists = mysqli_fetch_assoc($result);
    mysqli_stmt_close($checkStmt);
    
    if ($exists) {
        // Update existing
        $updateStmt = mysqli_prepare($conn, "
            UPDATE website_customizations 
            SET shopName = ?, primaryColor = ?, shopLogo = ?, heroHeading = ?, 
                heroSubtext = ?, heroBackground = ?, servicesData = ?, ctaButtonText = ?
            WHERE tenantID = ?
        ");
        mysqli_stmt_bind_param(
            $updateStmt,
            "ssssssssi",
            $shopName, $primaryColor, $shopLogo, $heroHeading,
            $heroSubtext, $heroBackground, $servicesData, $ctaButtonText,
            $tenantID
        );
        $success = mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
        if ($success) {
            log_event($conn, 'UPDATE WebsiteCustomization', 'website_customization', (int) $exists['customizationID'], 'Updated WebsiteCustomization for tenant ID: ' . $tenantID);
        }
        
        $action_taken = 'updated';
    } else {
        // Insert new
        $insertStmt = mysqli_prepare($conn, "
            INSERT INTO website_customizations 
            (tenantID, shopName, primaryColor, shopLogo, heroHeading, heroSubtext, 
             heroBackground, servicesData, ctaButtonText) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param(
            $insertStmt,
            "isssssss",
            $tenantID, $shopName, $primaryColor, $shopLogo, $heroHeading,
            $heroSubtext, $heroBackground, $servicesData, $ctaButtonText
        );
        $success = mysqli_stmt_execute($insertStmt);
        $newCustomizationId = $success ? (int) mysqli_insert_id($conn) : 0;
        mysqli_stmt_close($insertStmt);
        if ($success) {
            log_event($conn, 'CREATE WebsiteCustomization', 'website_customization', $newCustomizationId, 'Created WebsiteCustomization for tenant ID: ' . $tenantID);
        }
        
        $action_taken = 'created';
    }
    
    if ($success) {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Customization ' . $action_taken . ' successfully',
            'data' => $data
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to save customization: ' . mysqli_error($conn)
        ]);
    }
    exit;
}

/**
 * Get website customization settings for a tenant
 */
function getCustomization($conn, $tenantID) {
    $stmt = mysqli_prepare($conn, "
        SELECT * FROM website_customizations 
        WHERE tenantID = ? 
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "i", $tenantID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $customization = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($customization) {
        // Parse JSON fields
        if ($customization['servicesData']) {
            $customization['services'] = json_decode($customization['servicesData'], true);
        }
        unset($customization['servicesData']);
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'data' => $customization
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'No customization found'
        ]);
    }
    exit;
}

/**
 * Handle image upload
 */
function uploadImage($conn, $tenantID) {
    if (!isset($_FILES['image'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No image provided']);
        exit;
    }
    
    $file = $_FILES['image'];
    $uploadDir = __DIR__ . '/uploads/tenant_' . $tenantID . '/customizations/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];
    $maxFileSize = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid image type']);
        exit;
    }
    
    if ($file['size'] > $maxFileSize) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'File too large']);
        exit;
    }
    
    // Generate unique filename
    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'customization_' . time() . '_' . uniqid() . '.' . $fileExt;
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $relativePath = '/uploads/tenant_' . $tenantID . '/customizations/' . $fileName;
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Image uploaded successfully',
            'path' => $relativePath
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload image']);
    }
    exit;
}
?>
