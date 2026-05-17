<?php
session_start();
include __DIR__ . "/../db.php";

$errors = [];
$successMessage = "";
$isClientLoggedIn = isset($_SESSION['client_id']);

$formData = [
    'shopName' => '',
    'shopAddress' => '',
    'ownerName' => '',
    'countryCode' => 'PH',
    'contactNumber' => '',
    'email' => '',
    'subscriptionPlan' => '',
    'billingCycle' => 'monthly'
];

function getCountriesWithPhoneCodes()
{
    return [
        ['code' => 'US', 'name' => 'United States', 'phone' => '+1'],
        ['code' => 'CA', 'name' => 'Canada', 'phone' => '+1'],
        ['code' => 'GB', 'name' => 'United Kingdom', 'phone' => '+44'],
        ['code' => 'AU', 'name' => 'Australia', 'phone' => '+61'],
        ['code' => 'PH', 'name' => 'Philippines', 'phone' => '+63'],
        ['code' => 'SG', 'name' => 'Singapore', 'phone' => '+65'],
        ['code' => 'MY', 'name' => 'Malaysia', 'phone' => '+60'],
        ['code' => 'TH', 'name' => 'Thailand', 'phone' => '+66'],
        ['code' => 'VN', 'name' => 'Vietnam', 'phone' => '+84'],
        ['code' => 'IN', 'name' => 'India', 'phone' => '+91'],
        ['code' => 'JP', 'name' => 'Japan', 'phone' => '+81'],
        ['code' => 'CN', 'name' => 'China', 'phone' => '+86'],
        ['code' => 'KR', 'name' => 'South Korea', 'phone' => '+82'],
        ['code' => 'NZ', 'name' => 'New Zealand', 'phone' => '+64'],
        ['code' => 'ZA', 'name' => 'South Africa', 'phone' => '+27'],
        ['code' => 'DE', 'name' => 'Germany', 'phone' => '+49'],
        ['code' => 'FR', 'name' => 'France', 'phone' => '+33'],
        ['code' => 'IT', 'name' => 'Italy', 'phone' => '+39'],
        ['code' => 'ES', 'name' => 'Spain', 'phone' => '+34'],
        ['code' => 'NL', 'name' => 'Netherlands', 'phone' => '+31'],
        ['code' => 'MX', 'name' => 'Mexico', 'phone' => '+52'],
        ['code' => 'BR', 'name' => 'Brazil', 'phone' => '+55'],
        ['code' => 'AR', 'name' => 'Argentina', 'phone' => '+54'],
    ];
}

function ownersColumnExists($conn, $columnName)
{
    $safeColumn = mysqli_real_escape_string($conn, $columnName);
    $checkSql = "SHOW COLUMNS FROM owners LIKE '$safeColumn'";
    $check = mysqli_query($conn, $checkSql);
    return $check && mysqli_num_rows($check) > 0;
}

function subscriptionPlansTableExists($conn)
{
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'subscription_plans'");
    return $check && mysqli_num_rows($check) > 0;
}

function normalizePlanKey($value)
{
    $normalized = strtolower(trim((string) $value));
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized);
    $normalized = trim((string) $normalized, '-');
    return $normalized === '' ? 'plan' : $normalized;
}

function loadSubscriptionPlansWithDetails($conn)
{
    $plans = [];

    if (!subscriptionPlansTableExists($conn)) {
        return $plans;
    }

    $sql = "SELECT plan_id, plan_code, plan_name, monthly_price, plan_features, is_active 
            FROM subscription_plans 
            WHERE is_active = 1 
            ORDER BY monthly_price ASC, plan_name ASC";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return $plans;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $planName = trim((string) ($row['plan_name'] ?? ''));
        if ($planName === '') {
            continue;
        }

        $planCode = strtolower(trim((string) ($row['plan_code'] ?? '')));
        if ($planCode === '') {
            $planCode = normalizePlanKey($planName);
        }

        $features = [];
        $rawFeatures = trim((string) ($row['plan_features'] ?? ''));

        if ($rawFeatures !== '') {
            $decoded = json_decode($rawFeatures, true);
            if (is_array($decoded)) {
                $features = $decoded;
            } else {
                $features = preg_split('/[\r\n,]+/', $rawFeatures);
                $features = array_map('trim', $features);
                $features = array_filter($features);
            }
        }

        $plans[] = [
            'plan_id' => (int) ($row['plan_id'] ?? 0),
            'plan_code' => $planCode,
            'plan_name' => $planName,
            'monthly_price' => (float) ($row['monthly_price'] ?? 0),
            'plan_features' => $features,
            'is_active' => (int) ($row['is_active'] ?? 0)
        ];
    }

    return $plans;
}

$subscriptionPlans = loadSubscriptionPlansWithDetails($conn);

if (count($subscriptionPlans) === 0) {
    $subscriptionPlans = [
        [
            'plan_id' => 1,
            'plan_code' => 'starter',
            'plan_name' => 'Starter',
            'monthly_price' => 149,
            'plan_features' => ['1 Location', 'Up to 5 Technicians', 'Basic Analytics'],
            'is_active' => 1
        ],
        [
            'plan_id' => 2,
            'plan_code' => 'professional',
            'plan_name' => 'Professional',
            'monthly_price' => 399,
            'plan_features' => ['Up to 3 Locations', 'Unlimited Technicians', 'Full Analytics Suite', 'SMS Notifications'],
            'is_active' => 1
        ],
        [
            'plan_id' => 3,
            'plan_code' => 'enterprise',
            'plan_name' => 'Enterprise',
            'monthly_price' => 0,
            'plan_features' => ['Unlimited Locations', 'Custom API Access', 'Dedicated Success Manager', '24/7 Priority Support'],
            'is_active' => 1
        ]
    ];
}

function generateSlugForApplication($conn, $shopName)
{
    $slug = strtolower(trim((string) $shopName));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim((string) $slug, '-');

    if ($slug === '') {
        $slug = 'shop';
    }

    $originalSlug = $slug;
    $counter = 1;

    while (true) {
        $safeSlug = mysqli_real_escape_string($conn, $slug);
        $check = mysqli_query($conn, "SELECT tenantID FROM owners WHERE login_slug='$safeSlug' LIMIT 1");

        if ($check && mysqli_num_rows($check) === 0) {
            break;
        }

        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }

    return $slug;
}

function generateTemporaryPasswordForApplication($length = 12)
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $maxIndex = strlen($alphabet) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $maxIndex)];
    }

    return $password;
}

function generateUniqueInviteCode($conn, $length = 6)
{
    $digits = '0123456789';
    $maxIndex = strlen($digits) - 1;

    while (true) {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $digits[random_int(0, $maxIndex)];
        }

        $safeCode = mysqli_real_escape_string($conn, $code);
        $check = mysqli_query($conn, "SELECT tenantID FROM owners WHERE invite_code='$safeCode' LIMIT 1");

        if ($check && mysqli_num_rows($check) === 0) {
            return $code;
        }
    }
}

function uploadRequiredDocumentImage($fieldName, $tenantID, &$errors)
{
    $label = ucwords(str_replace('_', ' ', $fieldName));

    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = $label . ' is required.';
        return '';
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload failed for ' . $label . '.';
        return '';
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024;

    $fileTmp = $_FILES[$fieldName]['tmp_name'];
    $fileSize = (int) $_FILES[$fieldName]['size'];
    $mimeType = mime_content_type($fileTmp);

    if (!in_array($mimeType, $allowedTypes, true)) {
        $errors[] = $label . ' must be JPG, PNG, or WEBP only.';
        return '';
    }

    if ($fileSize > $maxSize) {
        $errors[] = $label . ' must not exceed 5MB.';
        return '';
    }

    $uploadDir = __DIR__ . '/../uploads/tenant_documents/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extensionMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    $extension = $extensionMap[$mimeType] ?? 'jpg';
    $safeFileName = $tenantID . '_' . $fieldName . '_' . time() . '_' . random_int(1000, 9999) . '.' . $extension;
    $targetPath = $uploadDir . $safeFileName;

    if (!move_uploaded_file($fileTmp, $targetPath)) {
        $errors[] = 'Could not save ' . $label . '.';
        return '';
    }

    return 'uploads/tenant_documents/' . $safeFileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['createTenantApplication'])) {
    if (!$isClientLoggedIn) {
        $errors[] = 'Please log in first before submitting an application.';
    } else {
        $formData['shopName'] = trim((string) ($_POST['shopName'] ?? ''));
        $formData['shopAddress'] = trim((string) ($_POST['shopAddress'] ?? ''));
        $formData['ownerName'] = trim((string) ($_POST['ownerName'] ?? ''));
        $formData['countryCode'] = strtoupper(trim((string) ($_POST['countryCode'] ?? 'PH')));
        $formData['contactNumber'] = trim((string) ($_POST['contactNumber'] ?? ''));
        $formData['email'] = trim((string) ($_POST['email'] ?? ''));
        $formData['subscriptionPlan'] = strtolower(trim((string) ($_POST['subscriptionPlan'] ?? '')));
        $formData['billingCycle'] = strtolower(trim((string) ($_POST['billingCycle'] ?? 'monthly')));

        if (
            $formData['shopName'] === '' ||
            $formData['shopAddress'] === '' ||
            $formData['ownerName'] === '' ||
            $formData['countryCode'] === '' ||
            $formData['contactNumber'] === '' ||
            $formData['email'] === '' ||
            $formData['subscriptionPlan'] === '' ||
            $formData['billingCycle'] === ''
        ) {
            $errors[] = 'All fields are required.';
        }

        $allowedPlans = array_map(function ($plan) {
            return $plan['plan_code'];
        }, $subscriptionPlans);

        if ($formData['subscriptionPlan'] !== '' && !in_array($formData['subscriptionPlan'], $allowedPlans, true)) {
            $errors[] = 'Please choose a valid plan.';
        }

        $validCountryCodes = array_map(function ($country) {
            return $country['code'];
        }, getCountriesWithPhoneCodes());

        if ($formData['countryCode'] !== '' && !in_array($formData['countryCode'], $validCountryCodes, true)) {
            $errors[] = 'Please choose a valid country.';
        }

        if ($formData['contactNumber'] !== '' && !preg_match('/^[0-9\s\-\+\(\)]{7,20}$/', $formData['contactNumber'])) {
            $errors[] = 'Please enter a valid phone number.';
        }

        if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        $existingEmailSql = "SELECT tenantID FROM owners WHERE email='" . mysqli_real_escape_string($conn, $formData['email']) . "' LIMIT 1";
        $existingEmailResult = mysqli_query($conn, $existingEmailSql);

        if ($existingEmailResult && mysqli_num_rows($existingEmailResult) > 0) {
            $errors[] = 'This email is already registered.';
        }

        $allowedBillingCycles = ['monthly', 'quarterly', 'yearly'];

        if ($formData['billingCycle'] !== '' && !in_array($formData['billingCycle'], $allowedBillingCycles, true)) {
            $errors[] = 'Please choose a valid billing cycle.';
        }

        if (count($errors) === 0) {
            $nextIdResult = mysqli_query($conn, "SELECT tenantID FROM owners ORDER BY CAST(tenantID AS UNSIGNED) DESC LIMIT 1");
            $newNumericId = 1;

            if ($nextIdResult && mysqli_num_rows($nextIdResult) > 0) {
                $last = mysqli_fetch_assoc($nextIdResult);
                $lastId = (int) ($last['tenantID'] ?? 0);
                $newNumericId = $lastId + 1;
            }

            $tenantID = str_pad((string) $newNumericId, 3, '0', STR_PAD_LEFT);
            $loginSlug = generateSlugForApplication($conn, $formData['shopName']);
            $temporaryPassword = generateTemporaryPasswordForApplication();
            $inviteCode = generateUniqueInviteCode($conn);

            $businessPermitImage = uploadRequiredDocumentImage('business_permit_image', $tenantID, $errors);
            $validIdImage = uploadRequiredDocumentImage('valid_id_image', $tenantID, $errors);
            $birCertificateImage = uploadRequiredDocumentImage('bir_certificate_image', $tenantID, $errors);

            if (count($errors) === 0) {
                $insertColumns = [
                    'tenantID',
                    'ownerName',
                    'shopName',
                    'email',
                    'contactNumber',
                    'shopAddress',
                    'password',
                    'first_login',
                    'status'
                ];

                $insertValues = [
                    "'" . mysqli_real_escape_string($conn, $tenantID) . "'",
                    "'" . mysqli_real_escape_string($conn, $formData['ownerName']) . "'",
                    "'" . mysqli_real_escape_string($conn, $formData['shopName']) . "'",
                    "'" . mysqli_real_escape_string($conn, $formData['email']) . "'",
                    "'" . mysqli_real_escape_string($conn, $formData['contactNumber']) . "'",
                    "'" . mysqli_real_escape_string($conn, $formData['shopAddress']) . "'",
                    "'" . mysqli_real_escape_string($conn, $temporaryPassword) . "'",
                    '1',
                    "'Pending'"
                ];

                if (ownersColumnExists($conn, 'login_slug')) {
                    $insertColumns[] = 'login_slug';
                    $insertValues[] = "'" . mysqli_real_escape_string($conn, $loginSlug) . "'";
                }

                if (ownersColumnExists($conn, 'subscription_plan')) {
                    $insertColumns[] = 'subscription_plan';
                    $insertValues[] = "'" . mysqli_real_escape_string($conn, $formData['subscriptionPlan']) . "'";
                }

                if (ownersColumnExists($conn, 'billing_cycle')) {
                    $insertColumns[] = 'billing_cycle';
                    $insertValues[] = "'" . mysqli_real_escape_string($conn, $formData['billingCycle']) . "'";
                }

                if (ownersColumnExists($conn, 'country_code')) {
                    $insertColumns[] = 'country_code';
                    $insertValues[] = "'" . mysqli_real_escape_string($conn, $formData['countryCode']) . "'";
                }

                if (ownersColumnExists($conn, 'invite_code')) {
                    $insertColumns[] = 'invite_code';
                    $insertValues[] = "'" . mysqli_real_escape_string($conn, $inviteCode) . "'";
                }

                if (ownersColumnExists($conn, 'business_permit_image')) {
                    $insertColumns[] = 'business_permit_image';
                    $insertValues[] = "'" . mysqli_real_escape_string($conn, $businessPermitImage) . "'";
                }

                if (ownersColumnExists($conn, 'valid_id_image')) {
                    $insertColumns[] = 'valid_id_image';
                    $insertValues[] = "'" . mysqli_real_escape_string($conn, $validIdImage) . "'";
                }

                if (ownersColumnExists($conn, 'bir_certificate_image')) {
                    $insertColumns[] = 'bir_certificate_image';
                    $insertValues[] = "'" . mysqli_real_escape_string($conn, $birCertificateImage) . "'";
                }

                if (ownersColumnExists($conn, 'created_at')) {
                    $insertColumns[] = 'created_at';
                    $insertValues[] = 'NOW()';
                }

                $insertSql = "INSERT INTO owners (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
                $insertResult = mysqli_query($conn, $insertSql);

                if ($insertResult) {
                    header(
                        'Location: clientpayment.php?tenantID=' . urlencode($tenantID) .
                        '&plan=' . urlencode($formData['subscriptionPlan']) .
                        '&billingCycle=' . urlencode($formData['billingCycle'])
                    );
                    exit();
                } else {
                    $errors[] = 'Unable to submit your application right now. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="scroll-smooth" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>RapidRepairCo. | Operational Excellence</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap"
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
                        "tertiary-container": "#fef3c7",
                        "on-primary-container": "#1152d4",
                        "inverse-on-surface": "#f8fafc",
                        "on-error": "#ffffff",
                        "on-tertiary": "#ffffff",
                        "primary-fixed-dim": "#bfdbfe",
                        "primary-container": "#eef2ff",
                        "secondary-fixed": "#e2e8f0",
                        "error-container": "#fee2e2",
                        "surface-variant": "#f1f5f9",
                        "surface-container-low": "#ffffff",
                        "on-secondary-fixed": "#0f172a",
                        "inverse-primary": "#b4c5ff",
                        "on-surface": "#0f172a",
                        "on-background": "#0f172a",
                        "surface-dim": "#d9d9e4",
                        "on-secondary": "#ffffff",
                        "secondary-container": "#f1f5f9",
                        "on-tertiary-fixed": "#7c2d12",
                        "on-secondary-fixed-variant": "#334155",
                        "outline-variant": "#cbd5e1",
                        "surface-container-lowest": "#ffffff",
                        "error": "#ef4444",
                        "surface-tint": "#1152d4",
                        "secondary-fixed-dim": "#cbd5e1",
                        "on-error-container": "#991b1b",
                        "outline": "#e2e8f0",
                        "on-primary-fixed": "#1e3a8a",
                        "surface": "#f6f6f8",
                        "primary-fixed": "#dbeafe",
                        "primary": "#1152d4",
                        "tertiary-fixed-dim": "#fed7aa",
                        "on-surface-variant": "#64748b",
                        "surface-container": "#ffffff",
                        "secondary": "#475569",
                        "surface-bright": "#ffffff",
                        "on-tertiary-container": "#92400e",
                        "inverse-surface": "#1e293b",
                        "surface-container-high": "#ffffff",
                        "on-secondary-container": "#1e293b",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "on-primary": "#ffffff",
                        "tertiary-fixed": "#ffedd5",
                        "tertiary": "#f59e0b",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "background": "#f6f6f8",
                        "surface-container-highest": "#ffffff"
                    },
                    fontFamily: {
                        "headline": ["Inter"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                },
            },
        }
    </script>

    <script>
        function selectPlanAndScroll(planKey) {
            const planSelect = document.querySelector('select[name="subscriptionPlan"]');

            if (planSelect) {
                planSelect.value = planKey;
            }

            const applicationSection = document.getElementById('application');

            if (applicationSection) {
                applicationSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });

                const formCard = applicationSection.querySelector('.ring-4');

                if (formCard) {
                    formCard.style.transition = 'all 0.3s ease';
                    formCard.style.boxShadow = '0 0 20px rgba(17, 82, 212, 0.3)';

                    setTimeout(() => {
                        formCard.style.boxShadow = '';
                    }, 2000);
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f6f6f8;
            color: #0f172a;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .clinical-shadow {
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        @keyframes point-right {

            0%,
            100% {
                transform: translateX(0);
            }

            50% {
                transform: translateX(-10px);
            }
        }

        .animate-point-right {
            animation: point-right 1.5s ease-in-out infinite;
        }
    </style>
</head>

<body class="bg-surface text-on-surface">
    <nav
        class="fixed top-0 w-full z-50 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-none">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-3">
            <div class="text-xl font-black tracking-tighter text-[#1152d4] dark:text-[#3b82f6]">RapidRepairCo.</div>

            <div class="hidden md:flex items-center gap-8">

                <a class="font-medium text-sm tracking-tight text-slate-600 dark:text-slate-400 hover:text-[#1152d4] transition-colors"
                    href="clientlanding.php">
                    Home
                </a>
                <a class="font-medium text-sm tracking-tight text-slate-600 dark:text-slate-400 hover:text-[#1152d4] transition-colors"
                    href="#features">
                    Features
                </a>
                <a class="font-medium text-sm tracking-tight text-slate-600 dark:text-slate-400 hover:text-[#1152d4] transition-colors"
                    href="#pricing">
                    Pricing
                </a>
                <a class="font-medium text-sm tracking-tight text-slate-600 dark:text-slate-400 hover:text-[#1152d4] transition-colors"
                    href="#about">
                    About
                </a>
                <a class="font-medium text-sm tracking-tight text-slate-600 dark:text-slate-400 hover:text-[#1152d4] transition-colors"
                    href="#support">
                    Support
                </a>

            </div>

            <div class="flex items-center gap-3">
                <?php if ($isClientLoggedIn): ?>

                    <div class="relative group">
                        <button type="button"
                            class="w-10 h-10 inline-flex items-center justify-center rounded-full border border-primary/30 text-primary hover:bg-primary/5 transition-all"
                            title="Profile">
                            <span class="material-symbols-outlined">account_circle</span>
                        </button>

                        <div
                            class="absolute right-0 mt-2 w-44 bg-white border border-outline rounded-xl shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 overflow-hidden">


                            <a href="clientprofile.php"
                                class="flex items-center gap-2 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 transition-all">
                                <span class="material-symbols-outlined text-[18px]">person</span>
                                Profile
                            </a>

                            <a href="../logout/logout.php?redirect=clientlanding.php"
                                class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-all">
                                <span class="material-symbols-outlined text-[18px]">logout</span>
                                Logout
                            </a>
                        </div>
                    </div>

                <?php else: ?>

                    <a href="clientregister.php"
                        class="px-5 py-2 rounded-lg text-sm font-bold tracking-tight border border-primary text-primary hover:bg-primary/5 transition-all">
                        Register
                    </a>

                    <a href="#application"
                        class="bg-primary text-on-primary px-5 py-2 rounded-lg text-sm font-bold tracking-tight hover:opacity-90 transition-all active:scale-95">
                        Get Started
                    </a>

                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="pt-16">
        <section class="relative min-h-[921px] flex items-center overflow-hidden py-20 px-6" id="application">
            <div class="absolute inset-0 z-0 bg-gradient-to-br from-primary/5 to-transparent"></div>

            <div class="max-w-7xl mx-auto w-full grid grid-cols-1 lg:grid-cols-2 gap-16 items-center relative z-10">
                <div class="space-y-8">
                    <span
                        class="inline-block px-3 py-1 bg-primary-container text-primary text-[10px] font-bold tracking-widest uppercase rounded">
                        Operational Excellence
                    </span>

                    <h1 class="text-5xl md:text-6xl font-black tracking-tighter leading-[1.1] text-on-background">
                        The Clinical Standard for <span class="text-primary">Modern Repair.</span>
                    </h1>

                    <p class="text-lg text-on-surface-variant max-w-lg leading-relaxed">
                        High-fidelity operational tools designed for professional shop owners. Move beyond legacy
                        systems with RapidRepairCo.'s architectural rigor and real-time fleet management.
                    </p>

                    <div class="flex items-center gap-4 text-sm font-semibold text-on-surface">
                        <div class="flex -space-x-2">
                            <div class="w-8 h-8 rounded-full border-2 border-white bg-slate-200"></div>
                            <div class="w-8 h-8 rounded-full border-2 border-white bg-slate-300"></div>
                            <div class="w-8 h-8 rounded-full border-2 border-white bg-slate-400"></div>
                        </div>
                        <span>Trusted by 500+ premium auto shops nationwide.</span>
                    </div>
                </div>

                <div
                    class="bg-surface-container border border-outline rounded-xl p-8 shadow-sm relative ring-4 ring-primary/5">
                    <div
                        class="absolute -left-12 top-1/2 -translate-y-1/2 hidden xl:flex flex-col items-center gap-2 text-primary animate-point-right">
                        <span
                            class="text-[10px] font-black uppercase tracking-tighter rotate-90 whitespace-nowrap mb-4">Start
                            Here</span>
                        <span class="material-symbols-outlined text-4xl">arrow_forward</span>
                    </div>

                    <div class="mb-8">
                        <h2 class="text-2xl font-bold tracking-tight mb-2">Application Form</h2>
                        <p class="text-sm text-on-surface-variant">Initialize your digital operational environment.</p>
                    </div>

                    <?php if ($successMessage !== ''): ?>
                        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (count($errors) > 0): ?>
                        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$isClientLoggedIn): ?>
                        <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Please <a href="clientregister.php" class="font-bold underline">register</a> first then
                            <a href="clientlogin.php" class="font-bold underline">login</a> to use this application form.
                        </div>
                    <?php endif; ?>

                    <form class="space-y-5" method="post" action="" enctype="multipart/form-data">
                        <input type="hidden" name="createTenantApplication" value="1" />

                        <fieldset <?php echo !$isClientLoggedIn ? 'disabled' : ''; ?>>
                            <div class="grid grid-cols-1 gap-5">
                                <div class="space-y-1.5">
                                    <label
                                        class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                        Shop Name
                                    </label>
                                    <input
                                        class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                        placeholder="e.g. Precision Euro Works" type="text" name="shopName" required
                                        value="<?php echo htmlspecialchars($formData['shopName'], ENT_QUOTES, 'UTF-8'); ?>" />
                                </div>

                                <div class="space-y-1.5">
                                    <label
                                        class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                        Business Address
                                    </label>
                                    <input
                                        class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                        placeholder="Street, City, State, ZIP" type="text" name="shopAddress" required
                                        value="<?php echo htmlspecialchars($formData['shopAddress'], ENT_QUOTES, 'UTF-8'); ?>" />
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div class="space-y-1.5">
                                        <label
                                            class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                            Owner Name
                                        </label>
                                        <input
                                            class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                            placeholder="Full Name" type="text" name="ownerName" required
                                            value="<?php echo htmlspecialchars($formData['ownerName'], ENT_QUOTES, 'UTF-8'); ?>" />
                                    </div>

                                    <div class="space-y-1.5">
                                        <label
                                            class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                            Country
                                        </label>
                                        <select
                                            class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                            name="countryCode" required>
                                            <?php foreach (getCountriesWithPhoneCodes() as $country): ?>
                                                <option
                                                    value="<?php echo htmlspecialchars($country['code'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php echo $formData['countryCode'] === $country['code'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($country['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                    (<?php echo htmlspecialchars($country['phone'], ENT_QUOTES, 'UTF-8'); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label
                                        class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                        Phone Number
                                    </label>
                                    <input
                                        class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                        placeholder="10-digit phone number" type="tel" name="contactNumber" required
                                        value="<?php echo htmlspecialchars($formData['contactNumber'], ENT_QUOTES, 'UTF-8'); ?>" />
                                </div>

                                <div class="space-y-1.5">
                                    <label
                                        class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                        Email Address
                                    </label>
                                    <input
                                        class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                        placeholder="admin@shop.com" type="email" name="email" required
                                        value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>" />
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div class="space-y-1.5">
                                        <label
                                            class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                            Choose Plan
                                        </label>
                                        <select
                                            class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                            name="subscriptionPlan" required>
                                            <option value="" <?php echo $formData['subscriptionPlan'] === '' ? 'selected' : ''; ?>>
                                                Select a plan
                                            </option>
                                            <?php foreach ($subscriptionPlans as $planOption): ?>
                                                <option
                                                    value="<?php echo htmlspecialchars($planOption['plan_code'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php echo $formData['subscriptionPlan'] === $planOption['plan_code'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($planOption['plan_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label
                                            class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                            Billing Cycle
                                        </label>
                                        <select
                                            class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                            name="billingCycle" required>
                                            <option value="monthly" <?php echo $formData['billingCycle'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                            <option value="quarterly" <?php echo $formData['billingCycle'] === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                            <option value="yearly" <?php echo $formData['billingCycle'] === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-outline bg-surface-container-low p-5 space-y-4">
                                    <div>
                                        <h3 class="text-sm font-black tracking-tight text-on-surface">Required Documents
                                        </h3>
                                        <p class="text-xs text-on-surface-variant mt-1">
                                            Upload clear image copies. Accepted formats: JPG, PNG, WEBP. Max size: 5MB
                                            each.
                                        </p>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label
                                            class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                            Business Permit
                                        </label>
                                        <input
                                            class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                            type="file" name="business_permit_image"
                                            accept="image/jpeg,image/png,image/webp" required />
                                    </div>

                                    <div class="space-y-1.5">
                                        <label
                                            class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                            Valid Owner ID
                                        </label>
                                        <input
                                            class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                            type="file" name="valid_id_image" accept="image/jpeg,image/png,image/webp"
                                            required />
                                    </div>

                                    <div class="space-y-1.5">
                                        <label
                                            class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                            BIR Certificate / Tax Registration
                                        </label>
                                        <input
                                            class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                            type="file" name="bir_certificate_image"
                                            accept="image/jpeg,image/png,image/webp" required />
                                    </div>
                                </div>
                            </div>

                            <button
                                class="w-full bg-primary text-white font-bold py-4 rounded-lg tracking-tight hover:bg-primary/90 transition-all mt-4"
                                type="submit">
                                Submit Application
                            </button>
                        </fieldset>
                    </form>
                </div>
            </div>
        </section>

        <section class="py-24 bg-white" id="features">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center max-w-2xl mx-auto mb-20">
                    <h2 class="text-3xl font-black tracking-tighter mb-4">Engineered for Precision</h2>
                    <p class="text-on-surface-variant">Every module is built with a focus on data integrity and operator
                        efficiency.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="p-8 border border-outline rounded-xl hover:border-primary/30 transition-all">
                        <div class="w-12 h-12 bg-primary-container flex items-center justify-center rounded-lg mb-6">
                            <span class="material-symbols-outlined text-primary">monitoring</span>
                        </div>
                        <h3 class="text-xl font-bold mb-3 tracking-tight">Real-time Analytics</h3>
                        <p class="text-sm text-on-surface-variant leading-relaxed">
                            Continuous data streaming provides instant visibility into shop throughput, technician
                            efficiency, and margin performance.
                        </p>
                    </div>

                    <div class="p-8 border border-outline rounded-xl hover:border-primary/30 transition-all">
                        <div class="w-12 h-12 bg-primary-container flex items-center justify-center rounded-lg mb-6">
                            <span class="material-symbols-outlined text-primary">account_tree</span>
                        </div>
                        <h3 class="text-xl font-bold mb-3 tracking-tight">Unified Fleet Management</h3>
                        <p class="text-sm text-on-surface-variant leading-relaxed">
                            Architectural control over multi-location operations. Sync inventory, staff, and billing
                            across your entire network effortlessly.
                        </p>
                    </div>

                    <div class="p-8 border border-outline rounded-xl hover:border-primary/30 transition-all">
                        <div class="w-12 h-12 bg-primary-container flex items-center justify-center rounded-lg mb-6">
                            <span class="material-symbols-outlined text-primary">architecture</span>
                        </div>
                        <h3 class="text-xl font-bold mb-3 tracking-tight">Clinical Interface Design</h3>
                        <p class="text-sm text-on-surface-variant leading-relaxed">
                            No clutter. No noise. A high-density professional UI that prioritizes critical information
                            for high-stakes decision making.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-24 bg-surface" id="pricing">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-black tracking-tighter mb-4">Scalable Architectures</h2>
                    <p class="text-on-surface-variant">Pricing tiers designed to grow with your operation.</p>
                </div>

                <div
                    class="grid grid-cols-1 md:grid-cols-<?php echo count($subscriptionPlans); ?> gap-0 border border-outline rounded-xl overflow-hidden shadow-sm">
                    <?php foreach ($subscriptionPlans as $index => $plan):
                        $isLast = $index === count($subscriptionPlans) - 1;
                        $isRecommended = count($subscriptionPlans) > 1 && $index === 1;
                        ?>
                        <div class="relative bg-white <?php echo !$isLast ? 'border-r border-outline' : ''; ?> p-10 flex flex-col cursor-pointer hover:shadow-md transition-shadow group hover:border-primary/30 <?php echo $isRecommended ? 'bg-primary-container' : ''; ?>"
                            onclick="selectPlanAndScroll('<?php echo htmlspecialchars($plan['plan_code'], ENT_QUOTES, 'UTF-8'); ?>')">

                            <?php if ($isRecommended): ?>
                                <div
                                    class="absolute top-4 right-4 bg-primary text-white text-[8px] font-black uppercase px-2 py-1 rounded">
                                    Recommended
                                </div>
                            <?php endif; ?>

                            <div class="mb-8">
                                <span
                                    class="text-[10px] font-bold <?php echo $isRecommended ? 'text-primary' : 'text-on-surface-variant'; ?> tracking-widest uppercase">
                                    <?php echo htmlspecialchars($plan['plan_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>

                                <div class="mt-4 flex items-baseline gap-1">
                                    <?php if ($plan['monthly_price'] > 0): ?>
                                        <span class="text-4xl font-black tracking-tighter">
                                            ₱<?php echo number_format($plan['monthly_price'], 0); ?>
                                        </span>
                                        <span class="text-on-surface-variant text-sm">/mo</span>
                                    <?php else: ?>
                                        <span class="text-4xl font-black tracking-tighter">Custom</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <ul class="space-y-4 mb-10 flex-grow">
                                <?php foreach ($plan['plan_features'] as $feature): ?>
                                    <li class="flex items-center gap-3 text-sm">
                                        <span class="material-symbols-outlined text-primary text-lg"
                                            style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                        <?php echo htmlspecialchars(trim((string) $feature), ENT_QUOTES, 'UTF-8'); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <button type="button"
                                onclick="event.stopPropagation(); selectPlanAndScroll('<?php echo htmlspecialchars($plan['plan_code'], ENT_QUOTES, 'UTF-8'); ?>')"
                                class="w-full py-3 <?php echo $isRecommended ? 'bg-primary text-white shadow-md hover:opacity-90' : 'border-2 border-primary text-primary hover:bg-primary/5'; ?> font-bold rounded-lg transition-all text-center block">
                                <?php if ($plan['monthly_price'] > 0): ?>
                                    <?php echo $isRecommended ? 'Go ' . htmlspecialchars($plan['plan_name'], ENT_QUOTES, 'UTF-8') : 'Start ' . htmlspecialchars($plan['plan_name'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php else: ?>
                                    Contact Sales
                                <?php endif; ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="py-24 overflow-hidden" id="about">
            <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-20 items-center">
                <div class="relative">
                    <div class="aspect-square bg-slate-200 rounded-xl overflow-hidden shadow-xl"></div>
                    <div class="absolute -bottom-10 -right-10 w-64 h-64 bg-primary rounded-xl -z-10 opacity-10"></div>
                </div>

                <div class="space-y-6">
                    <h2 class="text-3xl font-black tracking-tighter leading-tight">
                        Modernizing the Foundation of Automotive Repair.
                    </h2>

                    <p class="text-on-surface-variant leading-relaxed">
                        RapidRepairCo. was born from the realization that while cars have become sophisticated
                        computers on wheels, the tools used to manage their repair remained stuck in the 20th century.
                    </p>

                    <p class="text-on-surface-variant leading-relaxed">
                        Our mission is to provide shop owners with high-fidelity operational tools that match the
                        engineering excellence of the vehicles they service.
                    </p>

                    <div class="grid grid-cols-2 gap-8 pt-6">
                        <div>
                            <div class="text-3xl font-black text-primary">99.9%</div>
                            <div class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Uptime SLA
                            </div>
                        </div>

                        <div>
                            <div class="text-3xl font-black text-primary">24ms</div>
                            <div class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Sync Latency
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-24 bg-white border-t border-outline" id="support">
            <div class="max-w-7xl mx-auto px-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-16">
                    <div class="lg:col-span-1">
                        <h2 class="text-3xl font-black tracking-tighter mb-6">Expert Support</h2>
                        <p class="text-on-surface-variant mb-8">
                            Our engineering team is standing by to help you integrate RapidRepairCo. into your existing
                            workflow.
                        </p>

                        <div class="space-y-4">
                            <div class="flex items-center gap-4 p-4 border border-outline rounded-lg">
                                <span class="material-symbols-outlined text-primary">mail</span>
                                <div>
                                    <div class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">
                                        Email Us</div>
                                    <div class="text-sm font-semibold">support@rapidrepairco.com</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 p-4 border border-outline rounded-lg">
                                <span class="material-symbols-outlined text-primary">forum</span>
                                <div>
                                    <div class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Live
                                        Chat</div>
                                    <div class="text-sm font-semibold">Average wait: 2 mins</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2 space-y-4">
                        <div class="p-6 bg-surface rounded-xl border border-outline">
                            <h3 class="font-bold mb-2">How long does migration take?</h3>
                            <p class="text-sm text-on-surface-variant">
                                Most shops complete their data migration from legacy systems within 48 hours with our
                                automated onboarding tool.
                            </p>
                        </div>

                        <div class="p-6 bg-surface rounded-xl border border-outline">
                            <h3 class="font-bold mb-2">Can I manage multiple franchises?</h3>
                            <p class="text-sm text-on-surface-variant">
                                Yes, our Unified Fleet Management module is built specifically for multi-location
                                operations with hierarchical access controls.
                            </p>
                        </div>

                        <div class="p-6 bg-surface rounded-xl border border-outline">
                            <h3 class="font-bold mb-2">Is my data secure?</h3>
                            <p class="text-sm text-on-surface-variant">
                                We use bank-level AES-256 encryption for all data at rest and TLS 1.3 for all data in
                                transit.
                            </p>
                        </div>

                        <a class="inline-flex items-center gap-2 text-primary text-sm font-bold hover:underline"
                            href="clientlogin.php">
                            View full documentation and FAQ
                            <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="w-full bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-6 py-12 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="flex flex-col gap-2">
                <div class="text-lg font-black text-slate-900 dark:text-white">RapidRepairCo.</div>
                <p class="font-['Inter'] text-xs text-slate-500 dark:text-slate-400">
                    © 2026 RapidRepairCo. All rights reserved.
                </p>
            </div>

            <div class="flex flex-wrap justify-center gap-6">
                <a class="font-['Inter'] text-xs text-slate-500 hover:text-[#1152d4] hover:underline transition-all"
                    href="clientlogin.php">Partner Login</a>
                <a class="font-['Inter'] text-xs text-slate-500 hover:text-[#1152d4] hover:underline transition-all"
                    href="clientregister.php">Create Account</a>
                <a class="font-['Inter'] text-xs text-slate-500 hover:text-[#1152d4] hover:underline transition-all"
                    href="#support">Support</a>
                <a class="font-['Inter'] text-xs text-slate-500 hover:text-[#1152d4] hover:underline transition-all"
                    href="mailto:support@rapidrepairco.com">Contact Support</a>
            </div>

            <div class="flex gap-4">
                <span
                    class="material-symbols-outlined text-slate-400 hover:text-primary cursor-pointer transition-colors">language</span>
                <span
                    class="material-symbols-outlined text-slate-400 hover:text-primary cursor-pointer transition-colors">share</span>
            </div>
        </div>
    </footer>
</body>

</html>