<?php
session_start();
require_once("../db.php");

function jsonResponse($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function getUploadErrorMessage($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File is too large. Maximum allowed is 2MB.';
        case UPLOAD_ERR_PARTIAL:
            return 'File upload was interrupted. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file selected.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Server upload temp directory is missing.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Server failed to write uploaded file.';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload blocked by a server extension.';
        default:
            return 'Unknown upload error.';
    }
}

function tableColumnExists($conn, $table, $column)
{
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS column_count
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "ss", $table, $column);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    return isset($row['column_count']) && (int)$row['column_count'] > 0;
}

function getTenantDefaultInfo($conn, $tenantID)
{
    $nameColumns = ['shopName', 'shop_name', 'business_name', 'company_name'];
    $addressColumns = ['shopAddress', 'shop_address', 'business_address', 'address'];

    $selectedNameColumn = null;
    $selectedAddressColumn = null;

    foreach ($nameColumns as $column) {
        if (tableColumnExists($conn, 'owners', $column)) {
            $selectedNameColumn = $column;
            break;
        }
    }

    foreach ($addressColumns as $column) {
        if (tableColumnExists($conn, 'owners', $column)) {
            $selectedAddressColumn = $column;
            break;
        }
    }

    $defaultInfo = [
        'shop_name' => '',
        'shop_address' => ''
    ];

    if ($selectedNameColumn === null && $selectedAddressColumn === null) {
        return $defaultInfo;
    }

    $selectParts = [];
    if ($selectedNameColumn !== null) {
        $selectParts[] = "`$selectedNameColumn` AS shop_name";
    } else {
        $selectParts[] = "'' AS shop_name";
    }

    if ($selectedAddressColumn !== null) {
        $selectParts[] = "`$selectedAddressColumn` AS shop_address";
    } else {
        $selectParts[] = "'' AS shop_address";
    }

    $sql = "SELECT " . implode(', ', $selectParts) . " FROM owners WHERE tenantID = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return $defaultInfo;
    }

    mysqli_stmt_bind_param($stmt, "i", $tenantID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if ($row) {
        $defaultInfo['shop_name'] = trim((string)($row['shop_name'] ?? ''));
        $defaultInfo['shop_address'] = trim((string)($row['shop_address'] ?? ''));
    }

    return $defaultInfo;
}

function handleImageUpload($file, $tenantID, $type, &$errorMessage = null)
{
    $allowed_types = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp', 'image/gif', 'image/jpg', 'image/pjpeg', 'image/x-png'];
    $max_size = 2 * 1024 * 1024;

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errorMessage = 'Invalid uploaded file.';
        return false;
    }

    if ($file['size'] > $max_size) {
        $errorMessage = 'File is too large. Maximum allowed is 2MB.';
        return false;
    }

    $detected_type = mime_content_type($file['tmp_name']);
    $client_type = isset($file['type']) ? strtolower((string) $file['type']) : '';
    if (!in_array($detected_type, $allowed_types, true) && !in_array($client_type, $allowed_types, true)) {
        $errorMessage = 'Invalid image format. Allowed: JPG, PNG, SVG, WEBP, GIF.';
        return false;
    }

    $upload_dir = "../pictures/tenant_" . $tenantID;
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $errorMessage = 'Failed to create image directory.';
            return false;
        }
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if ($extension === '') {
        $extension = 'jpg';
    }

    $filename = $type . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . strtolower($extension);
    $filepath = $upload_dir . "/" . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return "tenant_" . $tenantID . "/" . $filename;
    }

    $errorMessage = 'Failed to move uploaded image file.';

    return false;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if (!isset($_SESSION['tenantID'])) {
    if ($action === 'load' || $action === 'save') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    header("Location: tenantlogin.php");
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];
$tenantDefaultInfo = getTenantDefaultInfo($conn, $tenantID);

if ($action === 'load' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = mysqli_prepare($conn, "SELECT * FROM tenant_customizations WHERE tenantID = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $tenantID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $customization = mysqli_fetch_assoc($result);

    if (!$customization) {
        $customization = [];
    }

    // Shop name and shop address are already given from the owners table.
    // Customization fields still load normally, but the identity fields are fixed.
    $customization['shop_name'] = $tenantDefaultInfo['shop_name'];
    $customization['shop_address'] = $tenantDefaultInfo['shop_address'];

    jsonResponse([
        'success' => true,
        'data' => $customization
    ]);
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Do not let users manually change these here. They come from owners table.
    $shop_name = $tenantDefaultInfo['shop_name'] !== '' ? $tenantDefaultInfo['shop_name'] : trim($_POST['shop_name'] ?? '');
    $shop_address = $tenantDefaultInfo['shop_address'] !== '' ? $tenantDefaultInfo['shop_address'] : trim($_POST['shop_address'] ?? '');

    $corner_radius = trim($_POST['corner_radius'] ?? 'rounded');
    $primary_color = trim($_POST['primary_color'] ?? '#1152d4');
    $accent_color = trim($_POST['accent_color'] ?? '#1152d4');
    $welcome_heading = trim($_POST['welcome_heading'] ?? '');
    $welcome_subtext = trim($_POST['welcome_subtext'] ?? '');

    if ($shop_name === '') {
        jsonResponse(['success' => false, 'message' => 'Shop name is missing from your account details. Please update it first.'], 422);
    }

    if ($shop_address === '') {
        jsonResponse(['success' => false, 'message' => 'Shop address is missing from your account details. Please update it first.'], 422);
    }

    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $primary_color)) {
        jsonResponse(['success' => false, 'message' => 'Invalid primary color format'], 422);
    }

    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accent_color)) {
        jsonResponse(['success' => false, 'message' => 'Invalid accent color format'], 422);
    }

    $valid_radius = ['sharp', 'rounded', 'pill'];
    if (!in_array($corner_radius, $valid_radius, true)) {
        $corner_radius = 'rounded';
    }

    $logo_path = null;
    $hero_image_path = null;
    $uploadError = null;

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'message' => 'Logo upload failed: ' . getUploadErrorMessage($_FILES['logo']['error'])], 422);
        }

        $logo_path = handleImageUpload($_FILES['logo'], $tenantID, 'logo', $uploadError);
        if ($logo_path === false) {
            jsonResponse(['success' => false, 'message' => 'Failed to upload logo: ' . ($uploadError ?: 'Unknown error')], 422);
        }
    }

    if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['hero_image']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'message' => 'Hero image upload failed: ' . getUploadErrorMessage($_FILES['hero_image']['error'])], 422);
        }

        $hero_image_path = handleImageUpload($_FILES['hero_image'], $tenantID, 'hero', $uploadError);
        if ($hero_image_path === false) {
            jsonResponse(['success' => false, 'message' => 'Failed to upload hero image: ' . ($uploadError ?: 'Unknown error')], 422);
        }
    }

    $exists_stmt = mysqli_prepare($conn, "SELECT tenantID FROM tenant_customizations WHERE tenantID = ? LIMIT 1");
    mysqli_stmt_bind_param($exists_stmt, "i", $tenantID);
    mysqli_stmt_execute($exists_stmt);
    $exists_result = mysqli_stmt_get_result($exists_stmt);
    $exists = mysqli_num_rows($exists_result) > 0;

    if ($exists) {
        $update_sql = "UPDATE tenant_customizations SET
            shop_name = ?,
            shop_address = ?,
            corner_radius = ?,
            primary_color = ?,
            accent_color = ?,
            welcome_heading = ?,
            welcome_subtext = ?,
            updated_at = NOW()";

        $params = [$shop_name, $shop_address, $corner_radius, $primary_color, $accent_color, $welcome_heading, $welcome_subtext];
        $types = "sssssss";

        if ($logo_path !== null) {
            $update_sql .= ", logo_path = ?";
            $types .= "s";
            $params[] = $logo_path;
        }

        if ($hero_image_path !== null) {
            $update_sql .= ", hero_image_path = ?";
            $types .= "s";
            $params[] = $hero_image_path;
        }

        $update_sql .= " WHERE tenantID = ?";
        $types .= "i";
        $params[] = $tenantID;

        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);

        if (!mysqli_stmt_execute($stmt)) {
            jsonResponse(['success' => false, 'message' => 'Database update failed: ' . mysqli_error($conn)], 500);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Customization updated successfully',
            'redirect_url' => 'dashboardadmin.php'
        ]);
    }

    $insert_sql = "INSERT INTO tenant_customizations
        (tenantID, shop_name, shop_address, corner_radius, primary_color, accent_color, welcome_heading, welcome_subtext, logo_path, hero_image_path, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param(
        $stmt,
        "isssssssss",
        $tenantID,
        $shop_name,
        $shop_address,
        $corner_radius,
        $primary_color,
        $accent_color,
        $welcome_heading,
        $welcome_subtext,
        $logo_path,
        $hero_image_path
    );

    if (!mysqli_stmt_execute($stmt)) {
        jsonResponse(['success' => false, 'message' => 'Database insert failed: ' . mysqli_error($conn)], 500);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Customization saved successfully',
        'redirect_url' => 'dashboardadmin.php'
    ]);
}

?>
<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Theme Settings - Shop Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1152d4",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
                },
            },
        }
    </script>
    <style>
        .primary-glow {
            box-shadow: 0 0 15px rgba(37, 99, 235, 0.4);
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: none;
        }

        .alert.success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
            display: block;
        }

        .alert.error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            display: block;
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
    <script>
        const customizationApi = 'logincustom.php';

        // Load customization data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCustomizationData();
        });

        function loadCustomizationData() {
            fetch(`${customizationApi}?action=load`, {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    populateForm(data.data);
                }
            })
            .catch(error => console.error('Error loading data:', error));
        }

        function populateForm(data) {
            document.getElementById('shop_name').value = data.shop_name || '';
            document.getElementById('shop_address').value = data.shop_address || '';

            if (data.shop_name) {
                document.getElementById('shop_name_note').textContent = 'Shop name is already loaded from your registered shop details.';
            }

            if (data.shop_address) {
                document.getElementById('shop_address_note').textContent = 'Shop address is already loaded from your registered shop details.';
            }
            document.getElementById('corner_radius').value = data.corner_radius || 'rounded';
            document.getElementById('primary_color_input').value = data.primary_color || '#1152d4';
            document.getElementById('primary_color_hex').value = data.primary_color || '#1152d4';
            document.getElementById('accent_color_input').value = data.accent_color || '#1152d4';
            document.getElementById('accent_color_hex').value = data.accent_color || '#1152d4';
            document.getElementById('welcome_heading').value = data.welcome_heading || '';
            document.getElementById('welcome_subtext').value = data.welcome_subtext || '';

            if (data.logo_path) {
                document.getElementById('logo_preview').src = `../pictures/${data.logo_path}`;
                document.getElementById('logo_preview_wrap').classList.remove('hidden');
            }

            if (data.hero_image_path) {
                document.getElementById('hero_preview').src = `../pictures/${data.hero_image_path}`;
            }
        }

        function attachImagePreview(inputId, previewImgId, optionalWrapId = null) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewImgId);
            const wrap = optionalWrapId ? document.getElementById(optionalWrapId) : null;

            if (!input || !preview) {
                return;
            }

            input.addEventListener('change', function() {
                const file = this.files && this.files[0] ? this.files[0] : null;
                if (!file) {
                    return;
                }

                const objectUrl = URL.createObjectURL(file);
                preview.src = objectUrl;
                if (wrap) {
                    wrap.classList.remove('hidden');
                }
            });
        }

        function syncColorInput(inputId, hexId) {
            document.getElementById(inputId).addEventListener('input', function() {
                document.getElementById(hexId).value = this.value;
            });
            document.getElementById(hexId).addEventListener('input', function() {
                if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                    document.getElementById(inputId).value = this.value;
                }
            });
        }

        function submitForm(event) {
            event.preventDefault();
            
            const form = event.target;
            const submitBtn = document.querySelector('button[type="submit"][form="customization_form"]');
            const alertBox = document.getElementById('alert_box');
            
            // Disable submit button
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
            }

            const formData = new FormData(form);

            fetch(`${customizationApi}?action=save`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alertBox.classList.remove('error', 'success');
                
                if (data.success) {
                    alertBox.classList.add('success');
                    alertBox.textContent = data.message;
                    alertBox.style.display = 'block';

                    // On successful onboarding customization, continue to dashboard.
                    setTimeout(() => {
                        window.location.href = data.redirect_url || 'dashboardadmin.php';
                    }, 800);
                } else {
                    alertBox.classList.add('error');
                    alertBox.textContent = data.message || 'An error occurred';
                    alertBox.style.display = 'block';
                }
                
                // Re-enable submit button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertBox.classList.remove('success');
                alertBox.classList.add('error');
                alertBox.textContent = 'An error occurred while saving';
                alertBox.style.display = 'block';
                
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                }
            });
        }

        // Initialize color sync on page load
        window.addEventListener('load', function() {
            syncColorInput('primary_color_input', 'primary_color_hex');
            syncColorInput('accent_color_input', 'accent_color_hex');
            attachImagePreview('logo', 'logo_preview', 'logo_preview_wrap');
            attachImagePreview('hero_image', 'hero_preview');
        });
    </script>
</head>

<body class="font-display text-slate-900 antialiased bg-slate-50">
    <div class="relative flex h-auto min-h-screen w-full flex-col group/design-root overflow-x-hidden">
        <div class="layout-container flex h-full grow flex-col">
            <!-- Top Navigation -->
            <header
                class="flex items-center justify-between whitespace-nowrap border-b border-solid border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 py-3 lg:px-10">

                <div class="flex items-center gap-4 text-primary">

                    <!-- BACK BUTTON -->
                    <a href="tenantlogin.php"
                        class="flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 transition">
                        <span class="material-symbols-outlined text-slate-700">arrow_back</span>
                    </a>

                    <div class="size-8 bg-primary rounded-lg flex items-center justify-center text-white">
                        <span class="material-symbols-outlined">build</span>
                    </div>

                    <h2 class="text-slate-900 dark:text-slate-100 text-lg font-bold leading-tight tracking-[-0.015em]">
                        ShopAdmin Portal
                    </h2>

                </div>

                <div class="flex flex-1 justify-end gap-8 items-center">
                </div>

            </header>
            <main class="flex flex-1 overflow-hidden bg-slate-50">
                <!-- Left Sidebar Settings -->
                <aside
                    class="w-full flex flex-col border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-y-auto">
                    <div class="p-6 space-y-8 max-w-2xl mx-auto w-full">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 dark:text-slate-100 tracking-tight">Branding
                                Customizer</h1>
                            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Configure how your customers see
                                your shop's portal.</p>
                        </div>

                        <!-- Alert Box -->
                        <div id="alert_box" class="alert"></div>

                        <!-- Form Sections -->
                        <form id="customization_form" onsubmit="submitForm(event)" enctype="multipart/form-data" class="space-y-6">
                            <!-- Shop Identity -->
                            <section class="space-y-4">
                                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400">Shop Identity</h3>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Shop
                                        Name</label>
                                    <input
                                        id="shop_name"
                                        name="shop_name"
                                        class="w-full rounded-lg border-slate-300 bg-slate-100 text-slate-600 cursor-not-allowed focus:border-slate-300 focus:ring-0 text-sm"
                                        type="text" value="" readonly />
                                    <p id="shop_name_note" class="text-xs text-slate-500">This is automatically loaded from your registered shop details.</p>
                                </div>
                                <div class="space-y-2 mt-2">
                                    <label class="text-sm font-semibold text-slate-700">Corner Radius</label>
                                    <select
                                        id="corner_radius"
                                        name="corner_radius"
                                        class="w-full rounded-xl border-slate-200 bg-white focus:border-primary focus:ring-0 text-sm py-3 px-4">
                                        <option value="sharp">Sharp</option>
                                        <option value="rounded" selected="">Rounded (8px)</option>
                                        <option value="pill">Full (Pill)</option>
                                    </select>
                                </div>
                                <div class="space-y-2 mt-4">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Primary Color</label>
                                    <div class="flex items-stretch rounded-xl overflow-hidden border border-slate-200 focus-within:border-primary transition-colors bg-white">
                                        <input
                                            id="primary_color_input"
                                            type="color"
                                            class="w-16 h-12 border-none cursor-pointer"
                                            value="#1152d4" />
                                        <input
                                            id="primary_color_hex"
                                            name="primary_color"
                                            class="flex-1 border-none text-slate-900 px-4 py-3 focus:ring-0 text-sm bg-transparent"
                                            type="text"
                                            value="#1152d4"
                                            placeholder="Hex color code" />
                                    </div>
                                </div>
                                <div class="space-y-2 mt-4">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Accent Color</label>
                                    <div class="flex items-stretch rounded-xl overflow-hidden border border-slate-200 focus-within:border-primary transition-colors bg-white">
                                        <input
                                            id="accent_color_input"
                                            type="color"
                                            class="w-16 h-12 border-none cursor-pointer"
                                            value="#1152d4" />
                                        <input
                                            id="accent_color_hex"
                                            name="accent_color"
                                            class="flex-1 border-none text-slate-900 px-4 py-3 focus:ring-0 text-sm bg-transparent"
                                            type="text"
                                            value="#1152d4"
                                            placeholder="Hex color code" />
                                    </div>
                                </div>
                                <div class="space-y-2 mt-4">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Shop Address</label>
                                    <div class="flex items-stretch rounded-xl overflow-hidden border border-slate-200 focus-within:border-primary transition-colors bg-white">
                                        <div
                                            class="flex items-center justify-center bg-slate-100 px-3 border-r border-slate-200">
                                            <span
                                                class="material-symbols-outlined text-slate-400 text-xl">location_on</span>
                                        </div>
                                        <input
                                            id="shop_address"
                                            name="shop_address"
                                            class="w-full border-none text-slate-600 px-4 py-3 focus:ring-0 text-sm bg-slate-100 cursor-not-allowed"
                                            placeholder="Shop address is loaded automatically" type="text"
                                            value="" readonly />
                                    </div>
                                    <p id="shop_address_note" class="text-xs text-slate-500 mt-2">This is automatically loaded from your registered shop details.</p>
                                </div>
                            </section>
                            <!-- Messaging -->
                            <section class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400">Portal Messaging
                                </h3>
                                <div class="space-y-2"><label class="text-sm font-semibold text-slate-700">Welcome
                                        Heading</label>
                                    <div
                                        class="flex items-stretch rounded-xl overflow-hidden border border-slate-200 focus-within:border-primary transition-colors bg-white">
                                        <div
                                            class="flex items-center justify-center bg-slate-100 px-3 border-r border-slate-200">
                                            <span class="material-symbols-outlined text-slate-400 text-xl">title</span>
                                        </div>
                                        <input
                                            id="welcome_heading"
                                            name="welcome_heading"
                                            class="w-full border-none text-slate-900 px-4 py-3 focus:ring-0 text-sm bg-transparent"
                                            type="text" value="" />
                                    </div>
                                </div>
                                <div class="space-y-2"><label class="text-sm font-semibold text-slate-700">Welcome
                                        Subtext</label>
                                    <textarea
                                        id="welcome_subtext"
                                        name="welcome_subtext"
                                        class="w-full rounded-xl border-slate-200 bg-white focus:border-primary focus:ring-0 text-sm p-4"
                                        rows="3"></textarea>
                                </div>
                            </section>
                            <!-- Assets -->
                            <section class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400">Visual Assets</h3>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Shop
                                        Logo</label>
                                    <div id="logo_preview_wrap" class="hidden mb-2">
                                        <img id="logo_preview" class="h-16 w-16 rounded-lg object-cover border border-slate-200" src="" alt="Logo preview" />
                                    </div>
                                    <div class="flex items-center justify-center w-full">
                                        <label
                                            class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-lg cursor-pointer bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 transition-colors rounded-xl border-slate-200 bg-white">
                                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                <span
                                                    class="material-symbols-outlined text-slate-400 mb-2">upload_file</span>
                                                <p class="text-xs text-slate-500">PNG or SVG (Max 2MB)</p>
                                            </div>
                                            <input id="logo" name="logo" class="hidden" type="file" accept="image/*" />
                                        </label>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Portal
                                        Hero Image</label>
                                    <div class="relative group rounded-lg overflow-hidden h-40">
                                        <div
                                            class="absolute inset-0 bg-slate-900/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-10">
                                            <label
                                                class="flex items-center gap-2 bg-primary text-white px-6 py-2.5 rounded-xl font-bold text-sm shadow-lg primary-glow hover:scale-105 transition-transform cursor-pointer">
                                                <span class="material-symbols-outlined text-xl">upload</span>
                                                Change Hero Image
                                                <input id="hero_image" name="hero_image" class="hidden" type="file" accept="image/*" />
                                            </label>
                                        </div>
                                        <div class="absolute top-3 right-3 z-20">
                                            <div
                                                class="bg-white/90 backdrop-blur p-2 rounded-lg text-slate-700 shadow-sm opacity-100 group-hover:opacity-0 transition-opacity">
                                                <span class="material-symbols-outlined block">edit</span>
                                            </div>
                                        </div>
                                        <img id="hero_preview" class="w-full h-full object-cover"
                                            data-alt="Modern car repair shop interior with clean tools"
                                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuDSegrbShYyM64WxJ8okS0ff5yA9nm2lb7E8Ww8uOx-WxDfuCqO14ve2yA2fIhY-pJZcCRwoe5QwqcsH_RCKkRCl8HGMPCrv_-OmHh75QNnnOyVe2ArnbPLsS-j-6sAHidVirdVW7A_wJd9jfpympPMjpD6XwAqJPxQ7Qz7s4jWchmvJpt0vQsQFHRMNJL0eX17tJBgHbD098yAUFGDDD1ImCQ5HNdiaFH0F-8ITNDOYv7V3a4Fq0sheXWovPJc9I07FJhMLT13LL6J" />
                                    </div>
                                </div>
                            </section>
                        </form>
                    <!-- Actions Footer -->
                    <div
                        class="sticky bottom-0 mt-auto p-6 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 flex gap-3 max-w-2xl mx-auto w-full justify-center max-w-2xl mx-auto w-full border-x lg:border-t-0">
                        <button
                            type="submit"
                            form="customization_form"
                            class="flex-1 bg-primary text-white font-bold py-2.5 rounded-lg hover:brightness-110 transition-all shadow-lg shadow-primary/20 h-12 rounded-xl primary-glow text-lg">Save
                            Changes</button>
                        <button
                            type="reset"
                            form="customization_form"
                            class="px-4 py-2.5 border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors h-12 rounded-xl">Reset</button>
                    </div>
                </aside>
                <!-- Right Side Preview -->
            </main>
        </div>
    </div>
</body>

</html>