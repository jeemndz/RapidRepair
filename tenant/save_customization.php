<?php
require_once("../db.php");
session_start();

// Check if user is logged in (adjust based on your session variable)
if (!isset($_SESSION['tenantID'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$tenantID = $_SESSION['tenantID'];
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $shop_name = mysqli_real_escape_string($conn, $_POST['shop_name'] ?? '');
    $shop_address = mysqli_real_escape_string($conn, $_POST['shop_address'] ?? '');
    $corner_radius = mysqli_real_escape_string($conn, $_POST['corner_radius'] ?? 'rounded');
    $primary_color = mysqli_real_escape_string($conn, $_POST['primary_color'] ?? '#1152d4');
    $accent_color = mysqli_real_escape_string($conn, $_POST['accent_color'] ?? '#1152d4');
    $welcome_heading = mysqli_real_escape_string($conn, $_POST['welcome_heading'] ?? '');
    $welcome_subtext = mysqli_real_escape_string($conn, $_POST['welcome_subtext'] ?? '');

    // Validate required fields
    if (empty($shop_name)) {
        $response['message'] = 'Shop name is required';
        echo json_encode($response);
        exit();
    }

    // Validate colors (basic hex validation)
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $primary_color)) {
        $response['message'] = 'Invalid primary color format';
        echo json_encode($response);
        exit();
    }

    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accent_color)) {
        $response['message'] = 'Invalid accent color format';
        echo json_encode($response);
        exit();
    }

    $logo_path = null;
    $hero_image_path = null;

    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo_path = handleImageUpload($_FILES['logo'], $tenantID, 'logo');
        if (!$logo_path) {
            $response['message'] = 'Failed to upload logo';
            echo json_encode($response);
            exit();
        }
    }

    // Handle hero image upload
    if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
        $hero_image_path = handleImageUpload($_FILES['hero_image'], $tenantID, 'hero');
        if (!$hero_image_path) {
            $response['message'] = 'Failed to upload hero image';
            echo json_encode($response);
            exit();
        }
    }

    // Check if record exists
    $check_query = "SELECT id FROM tenant_customizations WHERE tenantID = $tenantID";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        // Update existing record
        $update_parts = [
            "shop_name = '$shop_name'",
            "shop_address = '$shop_address'",
            "corner_radius = '$corner_radius'",
            "primary_color = '$primary_color'",
            "accent_color = '$accent_color'",
            "welcome_heading = '$welcome_heading'",
            "welcome_subtext = '$welcome_subtext'",
            "updated_at = NOW()"
        ];

        if ($logo_path) {
            $update_parts[] = "logo_path = '$logo_path'";
        }
        if ($hero_image_path) {
            $update_parts[] = "hero_image_path = '$hero_image_path'";
        }

        $update_query = "UPDATE tenant_customizations SET " . implode(", ", $update_parts) . " WHERE tenantID = $tenantID";
        
        if (mysqli_query($conn, $update_query)) {
            $response['success'] = true;
            $response['message'] = 'Customization updated successfully';
        } else {
            $response['message'] = 'Database update failed: ' . mysqli_error($conn);
        }
    } else {
        // Insert new record
        $insert_query = "INSERT INTO tenant_customizations 
            (tenantID, shop_name, shop_address, corner_radius, primary_color, accent_color, welcome_heading, welcome_subtext, logo_path, hero_image_path, created_at, updated_at)
            VALUES 
            ($tenantID, '$shop_name', '$shop_address', '$corner_radius', '$primary_color', '$accent_color', '$welcome_heading', '$welcome_subtext', " . 
            ($logo_path ? "'$logo_path'" : "NULL") . ", " .
            ($hero_image_path ? "'$hero_image_path'" : "NULL") . ", NOW(), NOW())";
        
        if (mysqli_query($conn, $insert_query)) {
            $response['success'] = true;
            $response['message'] = 'Customization saved successfully';
        } else {
            $response['message'] = 'Database insert failed: ' . mysqli_error($conn);
        }
    }

    echo json_encode($response);
    exit();
}

/**
 * Handle image upload and return the path
 */
function handleImageUpload($file, $tenantID, $type) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB

    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        return false;
    }

    // Create tenant directory if it doesn't exist
    $upload_dir = "../pictures/tenant_" . $tenantID;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $type . "_" . time() . "." . $extension;
    $filepath = $upload_dir . "/" . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Return relative path
        return "tenant_" . $tenantID . "/" . $filename;
    }

    return false;
}

// GET request - load existing customization data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = "SELECT * FROM tenant_customizations WHERE tenantID = $tenantID";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $customization = mysqli_fetch_assoc($result);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $customization]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No customization found', 'data' => null]);
    }
    exit();
}

?>
