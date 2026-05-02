<?php
session_start();

/*
|--------------------------------------------------------------------------
| Website Customization Handler
|--------------------------------------------------------------------------
| Actions:
|   GET  customization_handler.php?action=get_customization
|   POST customization_handler.php?action=save_customization
|   POST customization_handler.php?action=upload_image
|
| IMPORTANT:
| - This file DOES NOT edit shopName.
| - Shop name should stay from owners.shopName.
| - Images are uploaded to /uploads/website_customizations/tenant_{tenantID}/
| - Database stores only the image path/URL.
*/

// Flexible includes so this works whether the file is inside /tenant or project root
$dbPath1 = __DIR__ . '/../db.php';
$dbPath2 = __DIR__ . '/db.php';

if (file_exists($dbPath1)) {
    include $dbPath1;
} elseif (file_exists($dbPath2)) {
    include $dbPath2;
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'db.php not found']);
    exit;
}

$logPath1 = __DIR__ . '/../log_helper.php';
$logPath2 = __DIR__ . '/log_helper.php';

if (file_exists($logPath1)) {
    include_once $logPath1;
} elseif (file_exists($logPath2)) {
    include_once $logPath2;
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['tenantID'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET' && $action === 'get_customization') {
    getCustomization($conn, $tenantID);
}

if ($method === 'POST' && $action === 'save_customization') {
    saveCustomization($conn, $tenantID);
}

if ($method === 'POST' && $action === 'upload_image') {
    uploadImage($tenantID);
}

jsonResponse('error', 'Invalid request.', null, 400);


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function jsonResponse($status, $message, $data = null, $code = 200)
{
    http_response_code($code);

    $response = [
        'status' => $status,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

function cleanText($value, $maxLength = 255)
{
    $value = trim((string) $value);

    if (function_exists('mb_strlen') && mb_strlen($value) > $maxLength) {
        return mb_substr($value, 0, $maxLength);
    }

    if (strlen($value) > $maxLength) {
        return substr($value, 0, $maxLength);
    }

    return $value;
}

function validHexColor($color)
{
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
}

function getJsonInput()
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (is_array($data)) {
        return $data;
    }

    return $_POST;
}

function normalizeJsonField($value)
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_array($value)) {
        return json_encode($value);
    }

    $decoded = json_decode((string) $value, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($decoded);
    }

    return null;
}

function makePublicUrl($relativePath)
{
    $relativePath = '/' . ltrim($relativePath, '/');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';

    if ($host === '') {
        return $relativePath;
    }

    return $scheme . '://' . $host . $relativePath;
}


/*
|--------------------------------------------------------------------------
| Save Customization
|--------------------------------------------------------------------------
| Does NOT update owners.shopName.
|--------------------------------------------------------------------------
*/

function saveCustomization($conn, $tenantID)
{
    $data = getJsonInput();

    if (!$data || !is_array($data)) {
        jsonResponse('error', 'Invalid data.', null, 400);
    }

    $primaryColor = cleanText($data['primaryColor'] ?? '#1152d4', 7);
    $logoPath = cleanText($data['logoPath'] ?? ($data['shopLogo'] ?? ''), 500);
    $heroHeading = cleanText($data['heroHeading'] ?? '', 255);
    $heroSubtext = trim((string) ($data['heroSubtext'] ?? ''));
    $heroBackground = cleanText($data['heroBackground'] ?? '', 500);
    $ctaButtonText = cleanText($data['ctaButtonText'] ?? 'Book Appointment', 100);

    $services = normalizeJsonField($data['services'] ?? null);
    $carouselImages = normalizeJsonField($data['carouselImages'] ?? null);

    if (!validHexColor($primaryColor)) {
        jsonResponse('error', 'Invalid primary color. Use format like #1152d4.', null, 400);
    }

    if ($heroHeading === '') {
        $heroHeading = 'Precision Engineering. Absolute Reliability.';
    }

    if ($heroSubtext === '') {
        $heroSubtext = 'Expert automotive repair and maintenance services for performance vehicles and daily drivers alike.';
    }

    if ($ctaButtonText === '') {
        $ctaButtonText = 'Book Appointment';
    }

    $stmt = mysqli_prepare($conn, "
        INSERT INTO website_customizations
            (tenantID, logoPath, primaryColor, heroHeading, heroSubtext, heroBackground, ctaButtonText, services, carouselImages)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            logoPath = VALUES(logoPath),
            primaryColor = VALUES(primaryColor),
            heroHeading = VALUES(heroHeading),
            heroSubtext = VALUES(heroSubtext),
            heroBackground = VALUES(heroBackground),
            ctaButtonText = VALUES(ctaButtonText),
            services = VALUES(services),
            carouselImages = VALUES(carouselImages),
            updated_at = CURRENT_TIMESTAMP
    ");

    if (!$stmt) {
        jsonResponse('error', 'Database prepare failed: ' . mysqli_error($conn), null, 500);
    }

    mysqli_stmt_bind_param(
        $stmt,
        "issssssss",
        $tenantID,
        $logoPath,
        $primaryColor,
        $heroHeading,
        $heroSubtext,
        $heroBackground,
        $ctaButtonText,
        $services,
        $carouselImages
    );

    $success = mysqli_stmt_execute($stmt);
    $error = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$success) {
        jsonResponse('error', 'Failed to save customization: ' . $error, null, 500);
    }

    if (function_exists('log_event')) {
        log_event($conn, 'SAVE WebsiteCustomization', 'website_customization', $tenantID, 'Saved website customization for tenant ID: ' . $tenantID);
    }

    jsonResponse('success', 'Website customization saved successfully.', [
        'tenantID' => $tenantID,
        'logoPath' => $logoPath,
        'primaryColor' => $primaryColor,
        'heroHeading' => $heroHeading,
        'heroSubtext' => $heroSubtext,
        'heroBackground' => $heroBackground,
        'ctaButtonText' => $ctaButtonText,
        'services' => $services ? json_decode($services, true) : [],
        'carouselImages' => $carouselImages ? json_decode($carouselImages, true) : []
    ]);
}


/*
|--------------------------------------------------------------------------
| Get Customization
|--------------------------------------------------------------------------
*/

function getCustomization($conn, $tenantID)
{
    $stmt = mysqli_prepare($conn, "
        SELECT
            customization_id,
            tenantID,
            logoPath,
            primaryColor,
            heroHeading,
            heroSubtext,
            heroBackground,
            ctaButtonText,
            services,
            carouselImages,
            is_published,
            created_at,
            updated_at
        FROM website_customizations
        WHERE tenantID = ?
        LIMIT 1
    ");

    if (!$stmt) {
        jsonResponse('error', 'Database prepare failed: ' . mysqli_error($conn), null, 500);
    }

    mysqli_stmt_bind_param($stmt, "i", $tenantID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $customization = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$customization) {
        $customization = [
            'customization_id' => null,
            'tenantID' => $tenantID,
            'logoPath' => '',
            'primaryColor' => '#1152d4',
            'heroHeading' => 'Precision Engineering. Absolute Reliability.',
            'heroSubtext' => 'Expert automotive repair and maintenance services for performance vehicles and daily drivers alike.',
            'heroBackground' => '',
            'ctaButtonText' => 'Book Appointment',
            'services' => [],
            'carouselImages' => [],
            'is_published' => 1,
            'created_at' => null,
            'updated_at' => null
        ];
    } else {
        $customization['services'] = $customization['services']
            ? json_decode($customization['services'], true)
            : [];

        $customization['carouselImages'] = $customization['carouselImages']
            ? json_decode($customization['carouselImages'], true)
            : [];
    }

    jsonResponse('success', 'Customization loaded successfully.', $customization);
}


/*
|--------------------------------------------------------------------------
| Upload Image
|--------------------------------------------------------------------------
| FormData:
|   image = file
|   type = logo | hero | carousel
|--------------------------------------------------------------------------
*/

function uploadImage($tenantID)
{
    if (!isset($_FILES['image'])) {
        jsonResponse('error', 'No image provided.', null, 400);
    }

    $type = cleanText($_POST['type'] ?? 'general', 30);
    $allowedUploadTypes = ['logo', 'hero', 'carousel', 'general'];

    if (!in_array($type, $allowedUploadTypes, true)) {
        $type = 'general';
    }

    $file = $_FILES['image'];

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        jsonResponse('error', 'Invalid upload.', null, 400);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse('error', 'Upload error code: ' . $file['error'], null, 400);
    }

    $maxFileSize = 3 * 1024 * 1024; // 3MB
    if ($file['size'] > $maxFileSize) {
        jsonResponse('error', 'File too large. Maximum allowed size is 3MB.', null, 400);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg'
    ];

    if (!isset($allowedTypes[$mimeType])) {
        jsonResponse('error', 'Invalid image type. Allowed: JPG, PNG, WEBP, SVG.', null, 400);
    }

    /*
     * If this file is inside /tenant, uploads should go one level up to project root:
     * /RapidRepair/uploads/website_customizations/tenant_1/
     */
    $projectRoot = realpath(__DIR__ . '/..');

    if ($projectRoot === false || !file_exists($projectRoot . '/db.php')) {
        $projectRoot = __DIR__;
    }

    $relativeDir = '/uploads/website_customizations/tenant_' . $tenantID . '/';
    $uploadDir = $projectRoot . $relativeDir;

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            jsonResponse('error', 'Failed to create upload directory.', null, 500);
        }
    }

    $extension = $allowedTypes[$mimeType];
    $fileName = $type . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        jsonResponse('error', 'Failed to upload image.', null, 500);
    }

    $relativePath = $relativeDir . $fileName;
    $publicUrl = makePublicUrl($relativePath);

    jsonResponse('success', 'Image uploaded successfully.', [
        'type' => $type,
        'path' => $relativePath,
        'url' => $publicUrl
    ]);
}
?>
