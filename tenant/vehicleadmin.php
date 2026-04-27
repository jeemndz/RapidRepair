<?php
session_start();
require_once __DIR__ . "/../db.php";
include __DIR__ . '/../session_security.php';
include __DIR__ . '/access_control.php';

if (!isset($_SESSION['tenantID'])) {
    header("Location: tenantlogin.php");
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
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (!isset($_GET['shop']) || trim((string) $_GET['shop']) !== $loginSlug)) {
    $redirectParams = $_GET;
    $redirectParams['shop'] = $loginSlug;
    header('Location: ' . $currentScript . '?' . http_build_query($redirectParams));
    exit;
}

$targetUserId = isset($_GET['user_id']) ? max(0, (int) $_GET['user_id']) : 0;
$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : $tenantID;

$fuelOptions = ['Gasoline', 'Diesel', 'Electric', 'Hybrid'];
$transmissionOptions = ['Manual', 'Automatic', 'CVT', 'DCT', 'AMT'];
$statusOptions = ['Active', 'Inactive'];
$phBrandsModels = [
    'Toyota' => ['Vios', 'Wigo', 'Raize', 'Rush', 'Yaris Cross', 'Corolla Altis', 'Corolla Cross', 'Camry', 'Innova', 'Fortuner', 'Hilux', 'Land Cruiser Prado'],
    'Mitsubishi' => ['Mirage G4', 'Xpander', 'Xpander Cross', 'Montero Sport', 'Strada', 'L300', 'Outlander PHEV'],
    'Nissan' => ['Almera', 'Navara', 'Terra', 'Kicks e-POWER', 'Urvan', 'Patrol', 'Z'],
    'Honda' => ['Brio', 'City', 'Civic', 'Accord', 'HR-V', 'CR-V', 'BR-V'],
    'Suzuki' => ['S-Presso', 'Celerio', 'Dzire', 'Ertiga', 'XL7', 'Jimny', 'Carry'],
    'Isuzu' => ['D-MAX', 'mu-X', 'Traviz'],
    'Ford' => ['Ranger', 'Everest', 'Territory', 'Mustang'],
    'Hyundai' => ['Reina', 'Accent', 'Stargazer', 'Staria', 'Tucson', 'Santa Fe', 'Creta', 'Ioniq 5'],
    'Kia' => ['Soluto', 'Sonet', 'Seltos', 'Sportage', 'Carnival', 'EV6'],
    'Mazda' => ['Mazda2', 'Mazda3', 'CX-3', 'CX-30', 'CX-5', 'CX-8', 'BT-50', 'MX-5'],
    'Subaru' => ['Forester', 'Outback', 'XV', 'WRX', 'BRZ', 'Evoltis'],
    'Chevrolet' => ['Tracker', 'Trailblazer', 'Suburban', 'Tahoe'],
    'Geely' => ['Coolray', 'Okavango', 'Azkarra', 'Emgrand', 'GX3 Pro'],
    'MG' => ['MG5', 'ZS', 'HS', 'RX5', 'GT', 'One', 'Marvel R', '4 EV'],
    'Changan' => ['Alsvin', 'CS35 Plus', 'CS55 Plus', 'CS75 Plus', 'UNI-T', 'UNI-K'],
    'GAC' => ['Empow', 'Emzoom', 'GS3 Emzoom', 'GS8', 'M6 Pro', 'M8'],
    'Chery' => ['Tiggo 2 Pro', 'Tiggo 5X Pro', 'Tiggo 7 Pro', 'Tiggo 8 Pro'],
    'Jetour' => ['Ice Cream EV', 'X70', 'X70 Plus', 'Dashing', 'T2'],
    'BYD' => ['Seagull', 'Dolphin', 'Atto 3', 'Seal', 'Han EV', 'Tang EV'],
    'Foton' => ['Toplander', 'Thunder', 'Transvan', 'Traveller', 'Harabas'],
    'Volkswagen' => ['Santana', 'Lavida', 'T-Cross', 'Tharu', 'Tiguan', 'Lamando'],
    'Peugeot' => ['2008', '3008', '5008', 'Landtrek'],
    'BMW' => ['2 Series', '3 Series', '5 Series', 'X1', 'X3', 'X5', 'iX', 'i4'],
    'Mercedes-Benz' => ['A-Class', 'C-Class', 'E-Class', 'GLA', 'GLC', 'GLE', 'EQE', 'EQS'],
    'Audi' => ['A3', 'A4', 'A6', 'Q2', 'Q3', 'Q5', 'Q7', 'e-tron'],
    'Lexus' => ['IS', 'ES', 'LS', 'UX', 'NX', 'RX', 'GX', 'LX'],
    'Volvo' => ['S60', 'S90', 'XC40', 'XC60', 'XC90', 'C40 Recharge'],
    'Mini' => ['3-Door', '5-Door', 'Clubman', 'Countryman', 'Cooper SE'],
    'Land Rover' => ['Range Rover Evoque', 'Range Rover Velar', 'Range Rover Sport', 'Defender', 'Discovery'],
    'Jaguar' => ['XE', 'XF', 'F-PACE', 'E-PACE', 'I-PACE'],
    'Porsche' => ['Macan', 'Cayenne', 'Panamera', 'Taycan', '911'],
    'Lynk and Co' => ['01', '03', '05', '06', '09'],
    'Omoda' => ['C5', 'E5'],
    'Jaecoo' => ['J7', 'J8'],
    'JMC' => ['Vigus', 'Grand Avenue', 'EV Pickup'],
    'Maxus' => ['T60', 'T90', 'G10', 'V80', 'Mifa 9'],
];
$brandList = array_keys($phBrandsModels);

$filterStatus = isset($_GET['filter_status']) ? trim((string) $_GET['filter_status']) : '';
if ($filterStatus !== '' && !in_array($filterStatus, $statusOptions, true)) {
    $filterStatus = '';
}

$filterBrand = isset($_GET['filter_brand']) ? trim((string) $_GET['filter_brand']) : '';
if ($filterBrand !== '' && !in_array($filterBrand, $brandList, true)) {
    $filterBrand = '';
}

$searchTerm = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$customerUserId = isset($_GET['user_id']) ? max(0, (int) $_GET['user_id']) : 0;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$viewVehicleId = isset($_GET['view_vehicle']) ? max(0, (int) $_GET['view_vehicle']) : 0;
$editVehicleId = isset($_GET['edit_vehicle']) ? max(0, (int) $_GET['edit_vehicle']) : 0;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$formError = '';
$actionMessage = '';
$formData = [
    'brand' => '',
    'model' => '',
    'year_model' => '',
    'fuel_type' => 'Gasoline',
    'transmission_type' => 'Automatic',
    'engine_number' => '',
    'mileage_km' => '',
    'vin_number' => '',
    'plate_number' => '',
    'color' => '',
    'status' => 'Active',
];

$editFormError = '';
$editFormData = [
    'user_id' => 0,
    'brand' => '',
    'model' => '',
    'year_model' => '',
    'fuel_type' => 'Gasoline',
    'transmission_type' => 'Automatic',
    'engine_number' => '',
    'mileage_km' => '',
    'vin_number' => '',
    'plate_number' => '',
    'color' => '',
    'status' => 'Active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle_submit'])) {
    $postedUserId = isset($_POST['user_id']) ? max(0, (int) $_POST['user_id']) : 0;
    $recordUserId = $postedUserId > 0 ? $postedUserId : $targetUserId;
    $shouldValidateRecordUser = $recordUserId > 0;

    foreach ($formData as $key => $value) {
        $formData[$key] = isset($_POST[$key]) ? trim((string) $_POST[$key]) : $value;
    }

    if ($recordUserId <= 0) {
        $formError = 'Please select a customer before registering a vehicle.';
    }

    if ($formError === '' && $shouldValidateRecordUser) {
        $userVerifyStmt = mysqli_prepare($conn, 'SELECT user_id FROM users WHERE user_id = ? AND tenantID = ? LIMIT 1');
        if ($userVerifyStmt) {
            mysqli_stmt_bind_param($userVerifyStmt, 'ii', $recordUserId, $tenantID);
            mysqli_stmt_execute($userVerifyStmt);
            $userVerifyResult = mysqli_stmt_get_result($userVerifyStmt);
            if (!$userVerifyResult || !mysqli_fetch_assoc($userVerifyResult)) {
                $formError = 'Selected customer account was not found for this tenant.';
            }
            mysqli_stmt_close($userVerifyStmt);
        } else {
            $formError = 'Unable to validate the selected customer.';
        }
    }

    if ($formError === '' && ($formData['brand'] === '' || $formData['model'] === '')) {
        $formError = 'Brand and model are required.';
    } elseif (!in_array($formData['fuel_type'], $fuelOptions, true)) {
        $formError = 'Invalid fuel type selected.';
    } elseif (!in_array($formData['transmission_type'], $transmissionOptions, true)) {
        $formError = 'Invalid transmission type selected.';
    } elseif (!in_array($formData['status'], $statusOptions, true)) {
        $formError = 'Invalid status selected.';
    }

    $yearModel = null;
    if ($formData['year_model'] !== '') {
        $yearInt = (int) $formData['year_model'];
        $currentYear = (int) date('Y') + 1;
        if ($yearInt < 1900 || $yearInt > $currentYear) {
            $formError = 'Year model must be between 1900 and ' . $currentYear . '.';
        } else {
            $yearModel = $yearInt;
        }
    }

    $mileageKm = null;
    if ($formData['mileage_km'] !== '') {
        $mileageKm = (int) $formData['mileage_km'];
        if ($mileageKm < 0) {
            $formError = 'Mileage cannot be negative.';
        }
    }

    if ($formError === '') {
        $insertSql = "INSERT INTO vehicleinformation
            (tenantID, user_id, brand, model, year_model, fuel_type, transmission_type, engine_number, mileage_km, vin_number, plate_number, color, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = mysqli_prepare($conn, $insertSql);

        if ($insertStmt) {
            $brand = $formData['brand'] !== '' ? $formData['brand'] : null;
            $model = $formData['model'] !== '' ? $formData['model'] : null;
            $fuelType = $formData['fuel_type'];
            $transmissionType = $formData['transmission_type'];
            $engineNumber = $formData['engine_number'] !== '' ? $formData['engine_number'] : null;
            $vinNumber = $formData['vin_number'] !== '' ? $formData['vin_number'] : null;
            $plateNumber = $formData['plate_number'] !== '' ? $formData['plate_number'] : null;
            $color = $formData['color'] !== '' ? $formData['color'] : null;
            $status = $formData['status'];

            mysqli_stmt_bind_param(
                $insertStmt,
                'iississsissss',
                $tenantID,
                $recordUserId,
                $brand,
                $model,
                $yearModel,
                $fuelType,
                $transmissionType,
                $engineNumber,
                $mileageKm,
                $vinNumber,
                $plateNumber,
                $color,
                $status
            );

            if (mysqli_stmt_execute($insertStmt)) {
                mysqli_stmt_close($insertStmt);
                $redirectParams = [
                    'shop' => $loginSlug,
                    'vehicle_saved' => 1,
                    'page' => $page,
                    'q' => $searchTerm,
                    'filter_status' => $filterStatus,
                    'filter_brand' => $filterBrand,
                ];
                if ($customerUserId > 0) {
                    $redirectParams['user_id'] = $customerUserId;
                }
                header('Location: vehicleadmin.php?' . http_build_query(array_filter($redirectParams, static fn ($value) => $value !== null && $value !== '')));
                exit;
            }

            $formError = 'Unable to save vehicle. ' . mysqli_error($conn);
            mysqli_stmt_close($insertStmt);
        } else {
            $formError = 'Unable to prepare vehicle insert query.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_vehicle_submit'])) {
    $editVehicleId = isset($_POST['edit_vehicle_id']) ? max(0, (int) $_POST['edit_vehicle_id']) : 0;
    $editFormData['user_id'] = isset($_POST['edit_user_id']) ? max(0, (int) $_POST['edit_user_id']) : 0;
    $editFormData['brand'] = isset($_POST['edit_brand']) ? trim((string) $_POST['edit_brand']) : '';
    $editFormData['model'] = isset($_POST['edit_model']) ? trim((string) $_POST['edit_model']) : '';
    $editFormData['year_model'] = isset($_POST['edit_year_model']) ? trim((string) $_POST['edit_year_model']) : '';
    $editFormData['fuel_type'] = isset($_POST['edit_fuel_type']) ? trim((string) $_POST['edit_fuel_type']) : '';
    $editFormData['transmission_type'] = isset($_POST['edit_transmission_type']) ? trim((string) $_POST['edit_transmission_type']) : '';
    $editFormData['engine_number'] = isset($_POST['edit_engine_number']) ? trim((string) $_POST['edit_engine_number']) : '';
    $editFormData['mileage_km'] = isset($_POST['edit_mileage_km']) ? trim((string) $_POST['edit_mileage_km']) : '';
    $editFormData['vin_number'] = isset($_POST['edit_vin_number']) ? trim((string) $_POST['edit_vin_number']) : '';
    $editFormData['plate_number'] = isset($_POST['edit_plate_number']) ? trim((string) $_POST['edit_plate_number']) : '';
    $editFormData['color'] = isset($_POST['edit_color']) ? trim((string) $_POST['edit_color']) : '';
    $editFormData['status'] = isset($_POST['edit_status']) ? trim((string) $_POST['edit_status']) : '';

    if ($editVehicleId <= 0) {
        $editFormError = 'Invalid vehicle record.';
    } elseif ($editFormData['user_id'] <= 0) {
        $editFormError = 'Please select a customer.';
    } elseif ($editFormData['brand'] === '' || $editFormData['model'] === '') {
        $editFormError = 'Brand and model are required.';
    } elseif (!in_array($editFormData['fuel_type'], $fuelOptions, true)) {
        $editFormError = 'Invalid fuel type selected.';
    } elseif (!in_array($editFormData['transmission_type'], $transmissionOptions, true)) {
        $editFormError = 'Invalid transmission type selected.';
    } elseif (!in_array($editFormData['status'], $statusOptions, true)) {
        $editFormError = 'Invalid status selected.';
    }

    $editYearModel = null;
    if ($editFormData['year_model'] !== '') {
        $yearInt = (int) $editFormData['year_model'];
        $currentYear = (int) date('Y') + 1;
        if ($yearInt < 1900 || $yearInt > $currentYear) {
            $editFormError = 'Year model must be between 1900 and ' . $currentYear . '.';
        } else {
            $editYearModel = $yearInt;
        }
    }

    $editMileageKm = null;
    if ($editFormData['mileage_km'] !== '') {
        $editMileageKm = (int) $editFormData['mileage_km'];
        if ($editMileageKm < 0) {
            $editFormError = 'Mileage cannot be negative.';
        }
    }

    if ($editFormError === '') {
        $verifyStmt = mysqli_prepare($conn, 'SELECT user_id FROM users WHERE user_id = ? AND tenantID = ? LIMIT 1');
        if ($verifyStmt) {
            mysqli_stmt_bind_param($verifyStmt, 'ii', $editFormData['user_id'], $tenantID);
            mysqli_stmt_execute($verifyStmt);
            $verifyResult = mysqli_stmt_get_result($verifyStmt);
            if (!$verifyResult || !mysqli_fetch_assoc($verifyResult)) {
                $editFormError = 'Selected customer account was not found for this tenant.';
            }
            mysqli_stmt_close($verifyStmt);
        } else {
            $editFormError = 'Unable to validate selected customer.';
        }
    }

    if ($editFormError === '') {
        $updateStmt = mysqli_prepare(
            $conn,
            'UPDATE vehicleinformation
             SET user_id = ?, brand = ?, model = ?, year_model = ?, fuel_type = ?, transmission_type = ?, engine_number = ?,
                 mileage_km = ?, vin_number = ?, plate_number = ?, color = ?, status = ?
             WHERE vehicle_id = ? AND tenantID = ?
             LIMIT 1'
        );

        if ($updateStmt) {
            $brand = $editFormData['brand'];
            $model = $editFormData['model'];
            $fuelType = $editFormData['fuel_type'];
            $transmissionType = $editFormData['transmission_type'];
            $engineNumber = $editFormData['engine_number'] !== '' ? $editFormData['engine_number'] : null;
            $vinNumber = $editFormData['vin_number'] !== '' ? $editFormData['vin_number'] : null;
            $plateNumber = $editFormData['plate_number'] !== '' ? $editFormData['plate_number'] : null;
            $color = $editFormData['color'] !== '' ? $editFormData['color'] : null;
            $status = $editFormData['status'];

            mysqli_stmt_bind_param(
                $updateStmt,
                'ississsissssii',
                $editFormData['user_id'],
                $brand,
                $model,
                $editYearModel,
                $fuelType,
                $transmissionType,
                $engineNumber,
                $editMileageKm,
                $vinNumber,
                $plateNumber,
                $color,
                $status,
                $editVehicleId,
                $tenantID
            );

            if (mysqli_stmt_execute($updateStmt)) {
                mysqli_stmt_close($updateStmt);
                $redirectParams = [
                    'shop' => $loginSlug,
                    'page' => $page,
                    'q' => $searchTerm,
                    'filter_status' => $filterStatus,
                    'filter_brand' => $filterBrand,
                    'view_vehicle' => $editVehicleId,
                    'vehicle_updated' => 1,
                ];
                if ($customerUserId > 0) {
                    $redirectParams['user_id'] = $customerUserId;
                }
                header('Location: vehicleadmin.php?' . http_build_query(array_filter($redirectParams, static fn ($value) => $value !== null && $value !== '')));
                exit;
            }

            $editFormError = 'Unable to update vehicle details.';
            mysqli_stmt_close($updateStmt);
        } else {
            $editFormError = 'Unable to prepare vehicle update query.';
        }
    }
}

$stats = ['total' => 0, 'active' => 0, 'inactive' => 0];
$statsStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) AS inactive
     FROM vehicleinformation
     WHERE tenantID = ?"
);
if ($statsStmt) {
    mysqli_stmt_bind_param($statsStmt, 'i', $tenantID);
    mysqli_stmt_execute($statsStmt);
    $statsResult = mysqli_stmt_get_result($statsStmt);
    if ($statsResult && $row = mysqli_fetch_assoc($statsResult)) {
        $stats['total'] = (int) ($row['total'] ?? 0);
        $stats['active'] = (int) ($row['active'] ?? 0);
        $stats['inactive'] = (int) ($row['inactive'] ?? 0);
    }
    mysqli_stmt_close($statsStmt);
}

$servicedThisMonth = 0;
$servicedStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(DISTINCT appointment_id) AS serviced_count
     FROM appointment_services
     WHERE tenantID = ?
       AND created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
       AND created_at < DATE_ADD(DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01'), INTERVAL 1 MONTH)"
);
if ($servicedStmt) {
    mysqli_stmt_bind_param($servicedStmt, 'i', $tenantID);
    mysqli_stmt_execute($servicedStmt);
    $servicedResult = mysqli_stmt_get_result($servicedStmt);
    if ($servicedResult && $servicedRow = mysqli_fetch_assoc($servicedResult)) {
        $servicedThisMonth = (int) ($servicedRow['serviced_count'] ?? 0);
    }
    mysqli_stmt_close($servicedStmt);
}

$customers = [];
$customerStmt = mysqli_prepare(
    $conn,
    "SELECT user_id, fullName, email, contactNumber
     FROM users
     WHERE tenantID = ? AND role = 'client'
     ORDER BY fullName ASC"
);
if ($customerStmt) {
    mysqli_stmt_bind_param($customerStmt, 'i', $tenantID);
    mysqli_stmt_execute($customerStmt);
    $customerResult = mysqli_stmt_get_result($customerStmt);
    while ($customerResult && $customerRow = mysqli_fetch_assoc($customerResult)) {
        $customers[] = $customerRow;
    }
    mysqli_stmt_close($customerStmt);
}

$filterWhere = "
    WHERE vi.tenantID = ?
    AND (? = 0 OR vi.user_id = ?)
    AND (? = '' OR vi.status = ?)
    AND (? = '' OR vi.brand = ?)
    AND (
        ? = ''
        OR vi.brand LIKE CONCAT('%', ?, '%')
        OR vi.model LIKE CONCAT('%', ?, '%')
        OR vi.vin_number LIKE CONCAT('%', ?, '%')
        OR vi.plate_number LIKE CONCAT('%', ?, '%')
    )
";

$filteredTotal = 0;
$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM vehicleinformation vi " . $filterWhere);
if ($countStmt) {
    mysqli_stmt_bind_param(
        $countStmt,
        'iiisssssssss',
        $tenantID,
        $customerUserId,
        $customerUserId,
        $filterStatus,
        $filterStatus,
        $filterBrand,
        $filterBrand,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    if ($countResult && $countRow = mysqli_fetch_assoc($countResult)) {
        $filteredTotal = (int) ($countRow['total'] ?? 0);
    }
    mysqli_stmt_close($countStmt);
}

$totalPages = max(1, (int) ceil($filteredTotal / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

if (isset($_GET['export']) && $_GET['export'] === '1') {
    $exportStmt = mysqli_prepare(
        $conn,
        "SELECT vi.brand, vi.model, vi.year_model, vi.fuel_type, vi.transmission_type, vi.engine_number, vi.mileage_km,
                vi.vin_number, vi.plate_number, vi.color, vi.status, vi.date_added
         FROM vehicleinformation vi "
        . $filterWhere .
        " ORDER BY vi.date_added DESC"
    );

    if ($exportStmt) {
        mysqli_stmt_bind_param(
            $exportStmt,
            'iiisssssssss',
            $tenantID,
            $customerUserId,
            $customerUserId,
            $filterStatus,
            $filterStatus,
            $filterBrand,
            $filterBrand,
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $searchTerm
        );
        mysqli_stmt_execute($exportStmt);
        $exportResult = mysqli_stmt_get_result($exportStmt);
        $exportData = [];
        while ($exportResult && $exportRow = mysqli_fetch_assoc($exportResult)) {
            $exportData[] = $exportRow;
        }
        mysqli_stmt_close($exportStmt);

        // Generate PDF-friendly HTML
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename=vehicles_export_' . date('Ymd_His') . '.html');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Export Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            color: #333;
        }
        .pdf-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #1152d4;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #1152d4;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .metadata {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        .metadata-item {
            padding: 10px;
        }
        .metadata label {
            display: block;
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .metadata-value {
            font-size: 16px;
            color: #1152d4;
            font-weight: 600;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        thead {
            background: #1152d4;
            color: white;
        }
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #0d3a9e;
        }
        td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            font-size: 13px;
        }
        tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        tbody tr:hover {
            background: #f0f4ff;
        }
        .status-active {
            display: inline-block;
            padding: 4px 12px;
            background: #d1fae5;
            color: #065f46;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
        }
        .status-inactive {
            display: inline-block;
            padding: 4px 12px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
        }
        .no-records {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #1152d4;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(17, 82, 212, 0.3);
            z-index: 1000;
        }
        .print-button:hover {
            background: #0d3a9e;
            box-shadow: 0 4px 12px rgba(17, 82, 212, 0.4);
        }
        .save-button {
            position: fixed;
            top: 20px;
            right: 240px;
            background: #10b981;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
            z-index: 1000;
        }
        .save-button:hover {
            background: #059669;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        .back-button {
            position: fixed;
            top: 20px;
            right: 150px;
            background: #f1f5f9;
            color: #1152d4;
            border: 2px solid #1152d4;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            z-index: 1000;
            text-decoration: none;
            display: inline-block;
        }
        .back-button:hover {
            background: #1152d4;
            color: white;
            box-shadow: 0 2px 8px rgba(17, 82, 212, 0.3);
        }
            @media print {
            body {
                background: white;
                padding: 0;
            }
            .pdf-container {
                max-width: 100%;
                box-shadow: none;
                padding: 0;
            }
            .print-button {
                display: none;
            }
            .save-button {
                display: none;
            }
            .back-button {
                display: none;
            }
            table {
                page-break-inside: avoid;
            }
            tbody tr {
                page-break-inside: avoid;
            }
        }
        @page {
            size: A4;
            margin: 1cm;
        }
    </style>
</head>
<body>
    <a href="vehicleadmin.php" class="back-button">← Back</a>
    <button class="save-button" onclick="window.print()">💾 Save as PDF</button>
    <button class="print-button" onclick="window.print()">🖨️ Print</button>
    
    <div class="pdf-container">
        <div class="header">
            <h1>Vehicle Inventory Report</h1>
            <p>AutoFix Pro Vehicle Management System</p>
        </div>

        <div class="metadata">
            <div class="metadata-item">
                <label>Report Generated</label>
                <div class="metadata-value"><?php echo date('M d, Y · g:i A'); ?></div>
            </div>
            <div class="metadata-item">
                <label>Total Records</label>
                <div class="metadata-value"><?php echo count($exportData); ?></div>
            </div>
            <div class="metadata-item">
                <label>Status Filter</label>
                <div class="metadata-value"><?php echo $filterStatus ?: 'All'; ?></div>
            </div>
        </div>

        <?php if (empty($exportData)): ?>
            <div class="no-records">
                No vehicles found matching your criteria.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Year</th>
                        <th>Fuel Type</th>
                        <th>Transmission</th>
                        <th>Engine #</th>
                        <th>Mileage (km)</th>
                        <th>VIN Number</th>
                        <th>Plate Number</th>
                        <th>Color</th>
                        <th>Status</th>
                        <th>Date Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exportData as $row): ?>
                        <tr>
                            <td><?php echo h($row['brand']); ?></td>
                            <td><?php echo h($row['model']); ?></td>
                            <td><?php echo h($row['year_model']); ?></td>
                            <td><?php echo h($row['fuel_type']); ?></td>
                            <td><?php echo h($row['transmission_type']); ?></td>
                            <td><?php echo h($row['engine_number']); ?></td>
                            <td><?php echo h($row['mileage_km']); ?></td>
                            <td><?php echo h($row['vin_number']); ?></td>
                            <td><?php echo h($row['plate_number']); ?></td>
                            <td><?php echo h($row['color']); ?></td>
                            <td>
                                <?php if ($row['status'] === 'Active'): ?>
                                    <span class="status-active">Active</span>
                                <?php else: ?>
                                    <span class="status-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($row['date_added'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="footer">
            <p>This is an official AutoFix Pro vehicle inventory report. Generated on <?php echo date('l, F j, Y \a\t g:i A'); ?></p>
        </div>
    </div>
</body>
</html>
        <?php
        exit;
    }
}

$vehicles = [];
$vehicleStmt = mysqli_prepare(
    $conn,
    "SELECT vi.vehicle_id, vi.user_id, vi.brand, vi.model, vi.year_model, vi.fuel_type, vi.transmission_type, vi.engine_number,
            vi.mileage_km, vi.vin_number, vi.plate_number, vi.color, vi.status, vi.date_added,
            u.fullName AS customer_name, u.email AS customer_email, u.contactNumber AS customer_contact "
    . "FROM vehicleinformation vi "
    . "LEFT JOIN users u ON u.user_id = vi.user_id AND u.tenantID = vi.tenantID "
    . $filterWhere .
    " ORDER BY vi.date_added DESC LIMIT ?, ?"
);
if ($vehicleStmt) {
    mysqli_stmt_bind_param(
        $vehicleStmt,
        'iiisssssssssii',
        $tenantID,
        $customerUserId,
        $customerUserId,
        $filterStatus,
        $filterStatus,
        $filterBrand,
        $filterBrand,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $offset,
        $perPage
    );
    mysqli_stmt_execute($vehicleStmt);
    $vehicleResult = mysqli_stmt_get_result($vehicleStmt);
    while ($vehicleResult && $vehicleRow = mysqli_fetch_assoc($vehicleResult)) {
        $vehicles[] = $vehicleRow;
    }
    mysqli_stmt_close($vehicleStmt);
}

$showAddVehicleForm = isset($_GET['add_vehicle']) || $formError !== '';
$vehicleSaved = isset($_GET['vehicle_saved']);
$vehicleUpdated = isset($_GET['vehicle_updated']);

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'Unknown time';
    }

    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $minutes = (int) floor($diff / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    $days = (int) floor($diff / 86400);
    if ($days < 7) {
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }

    return date('M d, Y', $timestamp);
}

function status_badge_class(string $status): string
{
    return $status === 'Active'
        ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
        : 'bg-slate-100 text-slate-600 border-slate-200';
}

$recentActivities = [];
$recentStmt = mysqli_prepare(
    $conn,
    "SELECT brand, model, plate_number, date_added
     FROM vehicleinformation
     WHERE tenantID = ?
     ORDER BY date_added DESC
     LIMIT 5"
);
if ($recentStmt) {
    mysqli_stmt_bind_param($recentStmt, 'i', $tenantID);
    mysqli_stmt_execute($recentStmt);
    $recentResult = mysqli_stmt_get_result($recentStmt);
    while ($recentResult && $recentRow = mysqli_fetch_assoc($recentResult)) {
        $recentActivities[] = $recentRow;
    }
    mysqli_stmt_close($recentStmt);
}

$startEntry = $filteredTotal > 0 ? $offset + 1 : 0;
$endEntry = min($offset + count($vehicles), $filteredTotal);

$selectedVehicleForView = null;
if ($viewVehicleId > 0) {
    $viewStmt = mysqli_prepare(
        $conn,
        "SELECT vi.vehicle_id, vi.user_id, vi.brand, vi.model, vi.year_model, vi.fuel_type, vi.transmission_type,
                vi.engine_number, vi.mileage_km, vi.vin_number, vi.plate_number, vi.color, vi.status, vi.date_added,
                u.fullName AS customer_name, u.email AS customer_email, u.contactNumber AS customer_contact
         FROM vehicleinformation vi
         LEFT JOIN users u ON u.user_id = vi.user_id AND u.tenantID = vi.tenantID
         WHERE vi.vehicle_id = ? AND vi.tenantID = ?
         LIMIT 1"
    );
    if ($viewStmt) {
        mysqli_stmt_bind_param($viewStmt, 'ii', $viewVehicleId, $tenantID);
        mysqli_stmt_execute($viewStmt);
        $viewResult = mysqli_stmt_get_result($viewStmt);
        if ($viewResult) {
            $selectedVehicleForView = mysqli_fetch_assoc($viewResult) ?: null;
        }
        mysqli_stmt_close($viewStmt);
    }
}

$selectedVehicleForEdit = null;
if ($editVehicleId > 0) {
    $editStmt = mysqli_prepare(
        $conn,
        "SELECT vehicle_id, user_id, brand, model, year_model, fuel_type, transmission_type,
                engine_number, mileage_km, vin_number, plate_number, color, status
         FROM vehicleinformation
         WHERE vehicle_id = ? AND tenantID = ?
         LIMIT 1"
    );
    if ($editStmt) {
        mysqli_stmt_bind_param($editStmt, 'ii', $editVehicleId, $tenantID);
        mysqli_stmt_execute($editStmt);
        $editResult = mysqli_stmt_get_result($editStmt);
        if ($editResult) {
            $selectedVehicleForEdit = mysqli_fetch_assoc($editResult) ?: null;
        }
        mysqli_stmt_close($editStmt);
    }

    if ($selectedVehicleForEdit && $editFormError === '') {
        $editFormData['user_id'] = (int) ($selectedVehicleForEdit['user_id'] ?? 0);
        $editFormData['brand'] = (string) ($selectedVehicleForEdit['brand'] ?? '');
        $editFormData['model'] = (string) ($selectedVehicleForEdit['model'] ?? '');
        $editFormData['year_model'] = (string) ($selectedVehicleForEdit['year_model'] ?? '');
        $editFormData['fuel_type'] = (string) ($selectedVehicleForEdit['fuel_type'] ?? 'Gasoline');
        $editFormData['transmission_type'] = (string) ($selectedVehicleForEdit['transmission_type'] ?? 'Automatic');
        $editFormData['engine_number'] = (string) ($selectedVehicleForEdit['engine_number'] ?? '');
        $editFormData['mileage_km'] = (string) ($selectedVehicleForEdit['mileage_km'] ?? '');
        $editFormData['vin_number'] = (string) ($selectedVehicleForEdit['vin_number'] ?? '');
        $editFormData['plate_number'] = (string) ($selectedVehicleForEdit['plate_number'] ?? '');
        $editFormData['color'] = (string) ($selectedVehicleForEdit['color'] ?? '');
        $editFormData['status'] = (string) ($selectedVehicleForEdit['status'] ?? 'Active');
    }
}

?>

<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>Vehicle Management | Cobalt Precision</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap"
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
                        "on-background": "#0f172a",
                        "surface": "#f6f6f8",
                        "secondary-fixed": "#e2e8f0",
                        "on-primary-fixed": "#1e3a8a",
                        "primary-fixed": "#dbeafe",
                        "surface-variant": "#f1f5f9",
                        "secondary-container": "#f1f5f9",
                        "on-secondary-fixed-variant": "#334155",
                        "inverse-on-surface": "#f8fafc",
                        "surface-container-high": "#ffffff",
                        "on-primary-container": "#1152d4",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "background": "#f6f6f8",
                        "error": "#ef4444",
                        "primary": "#1152d4",
                        "outline": "#e2e8f0",
                        "tertiary-container": "#fef3c7",
                        "on-tertiary": "#ffffff",
                        "tertiary-fixed-dim": "#fed7aa",
                        "primary-container": "#eef2ff",
                        "surface-container": "#ffffff",
                        "on-tertiary-container": "#92400e",
                        "tertiary-fixed": "#ffedd5",
                        "surface-container-highest": "#ffffff",
                        "on-error": "#ffffff",
                        "inverse-surface": "#1e293b",
                        "surface-bright": "#ffffff",
                        "primary-fixed-dim": "#bfdbfe",
                        "on-secondary-container": "#1e293b",
                        "inverse-primary": "#b4c5ff",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "on-surface-variant": "#64748b",
                        "on-secondary": "#ffffff",
                        "surface-tint": "#1152d4",
                        "on-tertiary-fixed": "#7c2d12",
                        "on-surface": "#0f172a",
                        "on-primary": "#ffffff",
                        "on-secondary-fixed": "#0f172a",
                        "surface-container-low": "#ffffff",
                        "surface-dim": "#d9d9e4",
                        "surface-container-lowest": "#ffffff",
                        "outline-variant": "#cbd5e1",
                        "on-error-container": "#991b1b",
                        "tertiary": "#f59e0b",
                        "secondary": "#475569",
                        "secondary-fixed-dim": "#cbd5e1",
                        "error-container": "#fee2e2"
                    },
                    fontFamily: {
                        "headline": ["Inter"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    },
                    borderRadius: { "DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem" },
                },
            },
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

<body class="bg-background text-on-background min-h-screen">
    <!-- Updated SideNavBar Implementation based on SCREEN_106 -->
    <aside
        class="fixed inset-y-0 left-0 flex flex-col z-40 h-full w-64 border-r border-slate-200 bg-white overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-primary rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none"><?php echo h($shopName); ?></h1>
                    <p class="text-xs text-slate-500 mt-1">Repair Management</p>
                </div>
            </div>
            <nav class="space-y-1">
                <?php if (canAccessModule('dashboardadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="dashboardadmin.php">
                    <span class="material-symbols-outlined text-[22px]">dashboard</span>
                    Dashboard
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('repairjobsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="repairjobsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">build</span>
                    Repair Jobs
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('vehicleadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-medium"
                    href="vehicleadmin.php">
                    <span class="material-symbols-outlined text-[22px]">directions_car</span>
                    Vehicles
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('appointmentadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="appointmentadmin.php">
                    <span class="material-symbols-outlined text-[22px]">event</span>
                    Appointments
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('reportsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="reportsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">description</span>
                    Reports
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('inventoryadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="inventoryadmin.php">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                    Inventory
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('customeradmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="customeradmin.php">
                    <span class="material-symbols-outlined text-[22px]">group</span>
                    Customers
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('paymentsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="paymentsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">payments</span>
                    Payments
                </a>
                <?php endif; ?>
                <div class="pt-4 mt-4 border-t border-slate-100">
                    <div class="relative group">
                        <button class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors w-full text-left settings-dropdown-btn" data-dropdown="settings">
                            <span class="material-symbols-outlined text-[22px]">settings</span>
                            <span>Settings</span>
                            <span class="material-symbols-outlined text-[16px] ml-auto">expand_more</span>
                        </button>
                        <div class="absolute left-0 top-full mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg hidden z-50 settings-dropdown" data-dropdown="settings">
                            <?php if (canAccessModule('settingsadmin.php', $accessibleModules)): ?>
                            <a class="flex items-center gap-3 px-3 py-2.5 rounded-t-lg text-slate-600 hover:bg-blue-50 transition-colors text-sm"
                                href="settingsadmin.php">
                                <span class="material-symbols-outlined text-[18px]">settings</span>
                                Settings
                            </a>
                            <?php endif; ?>
                            <?php if (canAccessModule('accountbillingadmin.php', $accessibleModules)): ?>
                            <a class="flex items-center gap-3 px-3 py-2.5 rounded-b-lg text-slate-600 hover:bg-blue-50 transition-colors text-sm border-t border-slate-100"
                                href="accountbillingadmin.php">
                                <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                                Account Billing
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
        <div class="mt-auto w-full p-4 border-t border-slate-200">
            <div class="flex items-center gap-3">
                <div
                    class="size-10 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden">
                    <span class="material-symbols-outlined text-slate-500">person</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate"><?php echo h($loggedInUserName); ?></p>
                    <p class="text-xs text-slate-500 truncate"><?php echo h($loggedInUserRole); ?></p>
                </div>
                <form method="post" action="../logout/logout.php" class="inline">
                    <input type="hidden" name="action" value="confirm" />
                    <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>" />
                    <button type="submit" class="text-slate-400 hover:text-error transition-colors" title="Logout">
                        <span class="material-symbols-outlined text-xl">logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>
    <!-- Main Content Area -->
    <main class="ml-64 min-h-screen">
        <!-- Top Nav Bar -->
        <header
            class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Vehicle Management</h2>
            <div class="flex items-center gap-4">
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">help_outline</span>
                </button>
            </div>
        </header>

        <!-- Dashboard Canvas -->
        <div class="p-8 space-y-8">
            <!-- Summary Metrics (Bento Style) -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-primary/10 rounded-lg text-primary">
                            <span class="material-symbols-outlined">directions_car</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded uppercase tracking-wider">+12%</span>
                    </div>
                    <div>
                        <p class="text-2xl font-black text-slate-900 tracking-tight"><?php echo number_format($stats['total']); ?></p>
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mt-1">Total Vehicles
                        </p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-primary/10 rounded-lg text-primary">
                            <span class="material-symbols-outlined">handyman</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded uppercase tracking-wider">Target
                            250</span>
                    </div>
                    <div>
                        <p class="text-2xl font-black text-slate-900 tracking-tight"><?php echo number_format($servicedThisMonth); ?></p>
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mt-1">Serviced This
                            Month</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-primary/10 rounded-lg text-primary">
                            <span class="material-symbols-outlined">fiber_new</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-primary bg-primary/10 px-2 py-1 rounded uppercase tracking-wider">New</span>
                    </div>
                    <div>
                        <p class="text-2xl font-black text-slate-900 tracking-tight"><?php echo number_format($stats['inactive']); ?></p>
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mt-1">Inactive
                        </p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-error/10 rounded-lg text-error">
                            <span class="material-symbols-outlined">priority_high</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-error bg-error/10 px-2 py-1 rounded uppercase tracking-wider">Urgent</span>
                    </div>
                    <div>
                        <p class="text-2xl font-black text-slate-900 tracking-tight"><?php echo number_format($stats['total']); ?></p>
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mt-1">Registered
                            Records</p>
                    </div>
                </div>
            </div>
            <!-- Main Table Section -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <!-- Table Controls -->
                <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="flex items-center gap-4 w-full md:w-auto">
                        <h3 class="font-bold text-slate-900">Vehicle Inventory</h3>
                        <div class="flex gap-2">
                            <span
                                class="bg-blue-50 text-primary text-[10px] font-bold px-2 py-1 rounded border border-blue-100">ALL
                                (<?php echo number_format($stats['total']); ?>)</span>
                            <span
                                class="bg-emerald-50 text-emerald-600 text-[10px] font-bold px-2 py-1 rounded border border-emerald-100">ACTIVE
                                (<?php echo number_format($stats['active']); ?>)</span>
                            <span
                                class="bg-slate-50 text-slate-500 text-[10px] font-bold px-2 py-1 rounded border border-slate-100">INACTIVE
                                (<?php echo number_format($stats['inactive']); ?>)</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 w-full md:w-auto flex-wrap justify-end">
                        <a href="vehicleadmin.php?<?php echo h(http_build_query(array_filter([
                            'shop' => $loginSlug,
                            'add_vehicle' => 1,
                            'user_id' => $customerUserId > 0 ? $customerUserId : null,
                        ], static fn ($value) => $value !== null))); ?>"
                            class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-primary rounded-lg hover:bg-on-primary-container transition-colors">
                            <span class="material-symbols-outlined text-sm">add</span>
                            Register Vehicle
                        </a>
                        <form method="GET" action="vehicleadmin.php" class="flex items-center gap-2 flex-wrap">
                            <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>">
                            <select name="user_id" class="rounded-lg border-slate-200 text-sm px-3 py-2">
                                <option value="">All Customers</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo (int) $customer['user_id']; ?>" <?php echo $customerUserId === (int) $customer['user_id'] ? 'selected' : ''; ?>>
                                        <?php echo h($customer['fullName'] ?: ('Customer #' . $customer['user_id'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="filter_status" class="rounded-lg border-slate-200 text-sm px-3 py-2">
                                <option value="">All Status</option>
                                <?php foreach ($statusOptions as $statusOption): ?>
                                    <option value="<?php echo h($statusOption); ?>" <?php echo $filterStatus === $statusOption ? 'selected' : ''; ?>><?php echo h($statusOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="filter_brand" class="rounded-lg border-slate-200 text-sm px-3 py-2">
                                <option value="">All Brands</option>
                                <?php foreach ($brandList as $brandOption): ?>
                                    <option value="<?php echo h($brandOption); ?>" <?php echo $filterBrand === $brandOption ? 'selected' : ''; ?>><?php echo h($brandOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="q" value="<?php echo h($searchTerm); ?>">
                            <button
                                class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors" type="submit">
                                <span class="material-symbols-outlined text-sm">filter_list</span>
                                Filter
                            </button>
                            <a href="vehicleadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'user_id' => $customerUserId > 0 ? $customerUserId : null,
                            ], static fn ($value) => $value !== null))); ?>"
                                class="px-3 py-2 text-sm font-medium text-slate-500 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">Reset</a>
                        </form>
                        <a
                            href="vehicleadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'filter_status' => $filterStatus,
                                'filter_brand' => $filterBrand,
                                'q' => $searchTerm,
                                'user_id' => $customerUserId > 0 ? $customerUserId : null,
                                'export' => 1,
                            ], static fn ($value) => $value !== null && $value !== ''))); ?>"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                            <span class="material-symbols-outlined text-sm">file_download</span>
                            Export
                        </a>
                    </div>
                </div>
                <?php if ($vehicleSaved): ?>
                    <div class="mx-6 mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        Vehicle has been registered successfully.
                    </div>
                <?php endif; ?>
                <?php if ($vehicleUpdated): ?>
                    <div class="mx-6 mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700">
                        Vehicle details have been updated.
                    </div>
                <?php endif; ?>
                <?php if ($selectedVehicleForView): ?>
                    <div class="mx-6 mb-6 rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-bold text-slate-900">Vehicle Details</h4>
                            <a href="vehicleadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'page' => $page,
                                'q' => $searchTerm,
                                'filter_status' => $filterStatus,
                                'filter_brand' => $filterBrand,
                                'user_id' => $customerUserId > 0 ? $customerUserId : null,
                            ], static fn ($value) => $value !== null && $value !== ''))); ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Close</a>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <div><span class="text-xs text-slate-500 uppercase font-semibold">Customer</span><p class="font-semibold text-slate-800 mt-1"><?php echo h($selectedVehicleForView['customer_name'] ?: 'Unassigned'); ?></p></div>
                            <div><span class="text-xs text-slate-500 uppercase font-semibold">Contact</span><p class="font-semibold text-slate-800 mt-1"><?php echo h($selectedVehicleForView['customer_contact'] ?: ($selectedVehicleForView['customer_email'] ?: 'No contact info')); ?></p></div>
                            <div><span class="text-xs text-slate-500 uppercase font-semibold">Status</span><p class="font-semibold text-slate-800 mt-1"><?php echo h($selectedVehicleForView['status']); ?></p></div>
                            <div><span class="text-xs text-slate-500 uppercase font-semibold">Vehicle</span><p class="font-semibold text-slate-800 mt-1"><?php echo h(trim(($selectedVehicleForView['brand'] ?? '') . ' ' . ($selectedVehicleForView['model'] ?? ''))); ?></p></div>
                            <div><span class="text-xs text-slate-500 uppercase font-semibold">Year / Fuel</span><p class="font-semibold text-slate-800 mt-1"><?php echo h(($selectedVehicleForView['year_model'] ?: 'N/A') . ' / ' . ($selectedVehicleForView['fuel_type'] ?: 'N/A')); ?></p></div>
                            <div><span class="text-xs text-slate-500 uppercase font-semibold">Transmission</span><p class="font-semibold text-slate-800 mt-1"><?php echo h($selectedVehicleForView['transmission_type'] ?: 'N/A'); ?></p></div>
                            <div><span class="text-xs text-slate-500 uppercase font-semibold">VIN</span><p class="font-semibold text-slate-800 mt-1"><?php echo h($selectedVehicleForView['vin_number'] ?: 'N/A'); ?></p></div>
                            <div><span class="text-xs text-slate-500 uppercase font-semibold">Plate</span><p class="font-semibold text-slate-800 mt-1"><?php echo h($selectedVehicleForView['plate_number'] ?: 'N/A'); ?></p></div>
                            <div><span class="text-xs text-slate-500 uppercase font-semibold">Mileage</span><p class="font-semibold text-slate-800 mt-1"><?php echo h($selectedVehicleForView['mileage_km'] !== null ? number_format((int) $selectedVehicleForView['mileage_km']) . ' km' : 'N/A'); ?></p></div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($showAddVehicleForm): ?>
                    <div class="mx-6 mb-6 rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-bold text-slate-900">Register New Vehicle</h4>
                            <a href="vehicleadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'user_id' => $customerUserId > 0 ? $customerUserId : null,
                            ], static fn ($value) => $value !== null && $value !== ''))); ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Close</a>
                        </div>
                        <?php if ($formError !== ''): ?>
                            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                <?php echo h($formError); ?>
                            </div>
                        <?php endif; ?>
                        <form action="vehicleadmin.php" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <?php if ($customerUserId > 0): ?>
                                <input type="hidden" name="user_id" value="<?php echo (int) $customerUserId; ?>">
                            <?php else: ?>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Customer *</label>
                                    <select name="user_id" class="w-full rounded-lg border-slate-200 text-sm" required>
                                        <option value="">Select customer</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo (int) $customer['user_id']; ?>" <?php echo (isset($_POST['user_id']) && (int) $_POST['user_id'] === (int) $customer['user_id']) ? 'selected' : ''; ?>>
                                                <?php echo h($customer['fullName'] ?: 'Customer #' . $customer['user_id']); ?>
                                                <?php if (!empty($customer['contactNumber'])): ?>
                                                    (<?php echo h($customer['contactNumber']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Brand *</label>
                                <select id="brand_select" name="brand" class="w-full rounded-lg border-slate-200 text-sm" required>
                                    <option value="">Select brand</option>
                                    <?php foreach ($brandList as $brand): ?>
                                        <option value="<?php echo h($brand); ?>" <?php echo $formData['brand'] === $brand ? 'selected' : ''; ?>><?php echo h($brand); ?></option>
                                    <?php endforeach; ?>
                                    <?php if ($formData['brand'] !== '' && !in_array($formData['brand'], $brandList, true)): ?>
                                        <option value="<?php echo h($formData['brand']); ?>" selected><?php echo h($formData['brand']); ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Model *</label>
                                <select id="model_select" name="model" class="w-full rounded-lg border-slate-200 text-sm" required>
                                    <option value="">Select model</option>
                                    <?php if ($formData['model'] !== ''): ?>
                                        <option value="<?php echo h($formData['model']); ?>" selected><?php echo h($formData['model']); ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Year Model</label>
                                <input type="number" min="1900" max="<?php echo (int) date('Y') + 1; ?>" name="year_model" value="<?php echo h($formData['year_model']); ?>" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Fuel Type *</label>
                                <select name="fuel_type" class="w-full rounded-lg border-slate-200 text-sm" required>
                                    <?php foreach ($fuelOptions as $fuel): ?>
                                        <option value="<?php echo h($fuel); ?>" <?php echo $formData['fuel_type'] === $fuel ? 'selected' : ''; ?>><?php echo h($fuel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Transmission *</label>
                                <select name="transmission_type" class="w-full rounded-lg border-slate-200 text-sm" required>
                                    <?php foreach ($transmissionOptions as $transmission): ?>
                                        <option value="<?php echo h($transmission); ?>" <?php echo $formData['transmission_type'] === $transmission ? 'selected' : ''; ?>><?php echo h($transmission); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Mileage (km)</label>
                                <input type="number" min="0" name="mileage_km" value="<?php echo h($formData['mileage_km']); ?>" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Engine Number</label>
                                <input type="text" name="engine_number" value="<?php echo h($formData['engine_number']); ?>" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">VIN Number</label>
                                <input type="text" name="vin_number" value="<?php echo h($formData['vin_number']); ?>" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Plate Number</label>
                                <input type="text" name="plate_number" value="<?php echo h($formData['plate_number']); ?>" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Color</label>
                                <input type="text" name="color" value="<?php echo h($formData['color']); ?>" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Status *</label>
                                <select name="status" class="w-full rounded-lg border-slate-200 text-sm" required>
                                    <?php foreach ($statusOptions as $status): ?>
                                        <option value="<?php echo h($status); ?>" <?php echo $formData['status'] === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-3 flex items-center justify-end gap-2 pt-1">
                                <a href="vehicleadmin.php?<?php echo h(http_build_query(array_filter([
                                    'shop' => $loginSlug,
                                    'user_id' => $customerUserId > 0 ? $customerUserId : null,
                                ], static fn ($value) => $value !== null && $value !== ''))); ?>" class="px-3 py-2 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-100">Cancel</a>
                                <button type="submit" name="add_vehicle_submit" value="1" class="px-4 py-2 text-xs font-bold text-white bg-primary rounded-lg hover:bg-on-primary-container transition-colors">Save Vehicle</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
                <?php if ($editVehicleId > 0): ?>
                    <div id="edit-vehicle-form" class="mx-6 mb-6 rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-bold text-slate-900">Edit Vehicle #<?php echo (int) $editVehicleId; ?></h4>
                            <a href="vehicleadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'page' => $page,
                                'q' => $searchTerm,
                                'filter_status' => $filterStatus,
                                'filter_brand' => $filterBrand,
                                'user_id' => $customerUserId > 0 ? $customerUserId : null,
                            ], static fn ($value) => $value !== null && $value !== ''))); ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Close</a>
                        </div>
                        <?php if ($editFormError !== ''): ?>
                            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?php echo h($editFormError); ?></div>
                        <?php endif; ?>
                        <?php if (!$selectedVehicleForEdit && $editFormError === ''): ?>
                            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">Vehicle record was not found for this tenant.</div>
                        <?php else: ?>
                            <form action="vehicleadmin.php" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <input type="hidden" name="edit_vehicle_id" value="<?php echo (int) $editVehicleId; ?>">
                                <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>">
                                <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
                                <input type="hidden" name="q" value="<?php echo h($searchTerm); ?>">
                                <input type="hidden" name="filter_status" value="<?php echo h($filterStatus); ?>">
                                <input type="hidden" name="filter_brand" value="<?php echo h($filterBrand); ?>">
                                <?php if ($customerUserId > 0): ?><input type="hidden" name="user_id" value="<?php echo (int) $customerUserId; ?>"><?php endif; ?>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Customer *</label>
                                    <select name="edit_user_id" class="w-full rounded-lg border-slate-200 text-sm" required>
                                        <option value="">Select customer</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo (int) $customer['user_id']; ?>" <?php echo (int) $editFormData['user_id'] === (int) $customer['user_id'] ? 'selected' : ''; ?>><?php echo h($customer['fullName'] ?: 'Customer #' . $customer['user_id']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Brand *</label>
                                    <input type="text" name="edit_brand" value="<?php echo h($editFormData['brand']); ?>" class="w-full rounded-lg border-slate-200 text-sm" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Model *</label>
                                    <input type="text" name="edit_model" value="<?php echo h($editFormData['model']); ?>" class="w-full rounded-lg border-slate-200 text-sm" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Year Model</label>
                                    <input type="number" min="1900" max="<?php echo (int) date('Y') + 1; ?>" name="edit_year_model" value="<?php echo h($editFormData['year_model']); ?>" class="w-full rounded-lg border-slate-200 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Fuel Type *</label>
                                    <select name="edit_fuel_type" class="w-full rounded-lg border-slate-200 text-sm" required>
                                        <?php foreach ($fuelOptions as $fuel): ?><option value="<?php echo h($fuel); ?>" <?php echo $editFormData['fuel_type'] === $fuel ? 'selected' : ''; ?>><?php echo h($fuel); ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Transmission *</label>
                                    <select name="edit_transmission_type" class="w-full rounded-lg border-slate-200 text-sm" required>
                                        <?php foreach ($transmissionOptions as $transmission): ?><option value="<?php echo h($transmission); ?>" <?php echo $editFormData['transmission_type'] === $transmission ? 'selected' : ''; ?>><?php echo h($transmission); ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Mileage (km)</label>
                                    <input type="number" min="0" name="edit_mileage_km" value="<?php echo h($editFormData['mileage_km']); ?>" class="w-full rounded-lg border-slate-200 text-sm">
                                </div>
                                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Engine Number</label><input type="text" name="edit_engine_number" value="<?php echo h($editFormData['engine_number']); ?>" class="w-full rounded-lg border-slate-200 text-sm"></div>
                                <div><label class="block text-xs font-semibold text-slate-600 mb-1">VIN Number</label><input type="text" name="edit_vin_number" value="<?php echo h($editFormData['vin_number']); ?>" class="w-full rounded-lg border-slate-200 text-sm"></div>
                                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Plate Number</label><input type="text" name="edit_plate_number" value="<?php echo h($editFormData['plate_number']); ?>" class="w-full rounded-lg border-slate-200 text-sm"></div>
                                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Color</label><input type="text" name="edit_color" value="<?php echo h($editFormData['color']); ?>" class="w-full rounded-lg border-slate-200 text-sm"></div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Status *</label>
                                    <select name="edit_status" class="w-full rounded-lg border-slate-200 text-sm" required>
                                        <?php foreach ($statusOptions as $status): ?><option value="<?php echo h($status); ?>" <?php echo $editFormData['status'] === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="md:col-span-3 flex items-center justify-end gap-2 pt-1">
                                    <button type="submit" name="update_vehicle_submit" value="1" class="px-4 py-2 text-xs font-bold text-white bg-primary rounded-lg hover:bg-on-primary-container transition-colors">Update Vehicle</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50">
                                <th class="px-6 py-4 text-[11px] font-black text-slate-400 uppercase tracking-widest">
                                    Vehicle Details</th>
                                <th class="px-6 py-4 text-[11px] font-black text-slate-400 uppercase tracking-widest">
                                    Customer</th>
                                <th class="px-6 py-4 text-[11px] font-black text-slate-400 uppercase tracking-widest">
                                    VIN / Plate</th>
                                <th class="px-6 py-4 text-[11px] font-black text-slate-400 uppercase tracking-widest">
                                    Status</th>
                                <th class="px-6 py-4 text-[11px] font-black text-slate-400 uppercase tracking-widest">
                                    Actions</th>
                                <th
                                    class="px-6 py-4 text-[11px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    ID</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (count($vehicles) === 0): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No vehicles found. Click Register Vehicle to add your first record.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <?php
                                        $isActive = ($vehicle['status'] === 'Active');
                                        $customerName = trim((string) ($vehicle['customer_name'] ?? ''));
                                        $customerLabel = $customerName !== '' ? $customerName : 'Unassigned';
                                    ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors group">
                                        <td class="px-6 py-4">
                                            <div>
                                                <p class="font-bold text-slate-900 text-sm"><?php echo h(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? '')); ?></p>
                                                <p class="text-xs text-slate-500"><?php echo h($vehicle['year_model'] ?: 'Year N/A'); ?> · <?php echo h($vehicle['fuel_type']); ?> · <?php echo h($vehicle['transmission_type']); ?></p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-semibold text-slate-900"><?php echo h($customerLabel); ?></p>
                                            <p class="text-xs text-slate-500"><?php echo h($vehicle['customer_contact'] ?: ($vehicle['customer_email'] ?: 'No contact info')); ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-medium text-slate-900">Plate: <?php echo h($vehicle['plate_number'] ?: 'N/A'); ?></p>
                                            <p class="text-xs text-slate-500">VIN: <?php echo h($vehicle['vin_number'] ?: 'N/A'); ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full border text-[10px] font-bold uppercase <?php echo h(status_badge_class((string) $vehicle['status'])); ?>">
                                                <span class="w-1.5 h-1.5 rounded-full <?php echo $isActive ? 'bg-emerald-500' : 'bg-slate-500'; ?>"></span>
                                                <?php echo h($vehicle['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <a href="vehicleadmin.php?<?php echo h(http_build_query(array_filter([
                                                    'shop' => $loginSlug,
                                                    'page' => $page,
                                                    'q' => $searchTerm,
                                                    'filter_status' => $filterStatus,
                                                    'filter_brand' => $filterBrand,
                                                    'user_id' => $customerUserId > 0 ? $customerUserId : null,
                                                    'view_vehicle' => (int) $vehicle['vehicle_id'],
                                                ], static fn ($value) => $value !== null && $value !== ''))); ?>"
                                                    class="px-3 py-1.5 text-xs font-bold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">View</a>
                                                <a href="vehicleadmin.php?<?php echo h(http_build_query(array_filter([
                                                    'shop' => $loginSlug,
                                                    'page' => $page,
                                                    'q' => $searchTerm,
                                                    'filter_status' => $filterStatus,
                                                    'filter_brand' => $filterBrand,
                                                    'user_id' => $customerUserId > 0 ? $customerUserId : null,
                                                    'edit_vehicle' => (int) $vehicle['vehicle_id'],
                                                ], static fn ($value) => $value !== null && $value !== ''))); ?>#edit-vehicle-form"
                                                    class="px-3 py-1.5 text-xs font-bold text-slate-700 bg-slate-100 border border-slate-200 rounded-lg hover:bg-slate-200 transition-colors">Edit</a>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="text-[11px] text-slate-400 font-semibold">#<?php echo (int) $vehicle['vehicle_id']; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="p-6 bg-slate-50/50 border-t border-slate-100 flex justify-between items-center">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Showing <?php echo number_format($startEntry); ?> to <?php echo number_format($endEntry); ?> of <?php echo number_format($filteredTotal); ?>
                        entries</p>
                    <div class="flex gap-2 items-center flex-wrap justify-end">
                        <?php
                            $baseParams = [
                                'shop' => $loginSlug,
                                'filter_status' => $filterStatus,
                                'filter_brand' => $filterBrand,
                                'q' => $searchTerm,
                                'user_id' => $customerUserId > 0 ? $customerUserId : null,
                            ];
                            $prevDisabled = $page <= 1;
                            $nextDisabled = $page >= $totalPages;
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                        ?>
                        <?php if ($prevDisabled): ?>
                            <span class="px-3 py-1 text-xs font-bold border border-slate-200 bg-white rounded opacity-50">Previous</span>
                        <?php else: ?>
                            <a href="vehicleadmin.php?<?php echo h(http_build_query(array_merge($baseParams, ['page' => $page - 1]))); ?>"
                                class="px-3 py-1 text-xs font-bold border border-slate-200 bg-white rounded hover:bg-slate-50">Previous</a>
                        <?php endif; ?>

                        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                            <?php if ($p === $page): ?>
                                <span class="px-3 py-1 text-xs font-bold border border-primary bg-primary text-white rounded"><?php echo $p; ?></span>
                            <?php else: ?>
                                <a href="vehicleadmin.php?<?php echo h(http_build_query(array_merge($baseParams, ['page' => $p]))); ?>"
                                    class="px-3 py-1 text-xs font-bold border border-slate-200 bg-white rounded hover:bg-slate-50"><?php echo $p; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($nextDisabled): ?>
                            <span class="px-3 py-1 text-xs font-bold border border-slate-200 bg-white rounded opacity-50">Next</span>
                        <?php else: ?>
                            <a href="vehicleadmin.php?<?php echo h(http_build_query(array_merge($baseParams, ['page' => $page + 1]))); ?>"
                                class="px-3 py-1 text-xs font-bold border border-slate-200 bg-white rounded hover:bg-slate-50">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Secondary Bento Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Activity Feed -->
                <div class="md:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h4 class="font-bold text-slate-900">Recent Activity</h4>
                        <button
                            class="text-[10px] font-bold text-primary uppercase tracking-widest hover:underline">View
                            All Activity</button>
                    </div>
                    <div class="space-y-6">
                        <?php if (count($recentActivities) === 0): ?>
                            <p class="text-sm text-slate-500">No recent vehicle activity yet.</p>
                        <?php else: ?>
                            <?php foreach ($recentActivities as $index => $activity): ?>
                                <?php $hasNext = $index < count($recentActivities) - 1; ?>
                                <div class="flex gap-4">
                                    <div class="relative">
                                        <div
                                            class="w-8 h-8 rounded-full flex items-center justify-center relative z-10 bg-blue-100 text-blue-600">
                                            <span class="material-symbols-outlined text-sm">add_circle</span>
                                        </div>
                                        <?php if ($hasNext): ?>
                                            <div class="absolute top-8 left-1/2 -translate-x-1/2 w-px h-10 bg-slate-100"></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-900">New Vehicle Added</p>
                                        <p class="text-xs text-slate-500 mt-1">
                                            <?php echo h(trim(($activity['brand'] ?? '') . ' ' . ($activity['model'] ?? ''))); ?>
                                            <?php if (!empty($activity['plate_number'])): ?>
                                                (Plate: <?php echo h($activity['plate_number']); ?>)
                                            <?php endif; ?>
                                            · <?php echo h(time_ago((string) $activity['date_added'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Quick Insights -->
                <div
                    class="bg-primary text-white rounded-xl shadow-lg p-6 flex flex-col justify-between overflow-hidden relative">
                    <!-- Technical Texture Background -->
                    <div
                        class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -translate-y-12 translate-x-12 blur-2xl">
                    </div>
                    <div class="relative">
                        <span class="material-symbols-outlined text-white/40 text-4xl mb-4">analytics</span>
                        <h4 class="text-lg font-bold leading-tight">Monthly Performance Insight</h4>
                        <p class="text-sm text-white/70 mt-2">Vehicle throughput is up <span
                                class="text-white font-bold">18%</span> compared to the previous quarter. Recommended:
                            Increase technician capacity for high-end electric maintenance.</p>
                    </div>
                    <button
                        class="mt-8 bg-white text-primary px-4 py-2 rounded font-bold text-xs uppercase tracking-widest hover:bg-blue-50 transition-colors w-full">Generate
                        Detailed Report</button>
                </div>
            </div>
        </div>
    </main>
</body>

<script>
    const brandModels = <?php echo json_encode($phBrandsModels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const brandSelect = document.getElementById('brand_select');
    const modelSelect = document.getElementById('model_select');
    const selectedModelFromServer = <?php echo json_encode($formData['model'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function populateModels(brand, selectedModel = '') {
        if (!modelSelect) return;

        modelSelect.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Select model';
        modelSelect.appendChild(defaultOption);

        const models = brandModels[brand] || [];
        models.forEach((model) => {
            const option = document.createElement('option');
            option.value = model;
            option.textContent = model;
            if (model === selectedModel) {
                option.selected = true;
            }
            modelSelect.appendChild(option);
        });

        if (selectedModel && !models.includes(selectedModel)) {
            const customOption = document.createElement('option');
            customOption.value = selectedModel;
            customOption.textContent = selectedModel;
            customOption.selected = true;
            modelSelect.appendChild(customOption);
        }
    }

    if (brandSelect) {
        populateModels(brandSelect.value, selectedModelFromServer);
        brandSelect.addEventListener('change', function () {
            populateModels(this.value, '');
        });
    }

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

</html>