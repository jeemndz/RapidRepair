<?php
include __DIR__ . '/../db.php';

// PUBLIC TENANT WEBSITE
// This page does NOT require login.
// It loads the tenant website using the public shop slug in the URL:
// tenantwebsite.php?shop=your-shop-slug

$login_slug = '';
if (isset($_GET['shop']) && trim((string) $_GET['shop']) !== '') {
    $login_slug = trim((string) $_GET['shop']);
}

if ($login_slug === '') {
    http_response_code(404);
    echo 'Shop not found. Please provide a valid shop link.';
    exit;
}

// Get tenant information using public slug only
$stmt = mysqli_prepare($conn, "SELECT * FROM owners WHERE login_slug = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo 'Unable to load shop website.';
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $login_slug);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$owner = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$owner) {
    http_response_code(404);
    echo 'Shop not found.';
    exit;
}

$tenantID = (int)$owner['tenantID'];

// Function to get website customization
function websiteCustomizationsTableExists($conn) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'website_customizations'");
    return $check && mysqli_num_rows($check) > 0;
}

function getWebsiteCustomization($conn, $tenantID) {
    if (!websiteCustomizationsTableExists($conn)) {
        return array();
    }

    $stmt = mysqli_prepare($conn, "
        SELECT * FROM website_customizations 
        WHERE tenantID = ? 
        LIMIT 1
    ");

    if (!$stmt) {
        return array();
    }

    mysqli_stmt_bind_param($stmt, "i", $tenantID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $customization = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $customization ? $customization : array();
}

$shopName = isset($owner['shopName']) && $owner['shopName'] !== '' ? $owner['shopName'] : 'AutoFix Pro';

// Load website customizations
$customization = getWebsiteCustomization($conn, $tenantID);

function rrAssetUrl($path) {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('/^(https?:)?\/\//i', $path) || strpos($path, 'data:') === 0) {
        return $path;
    }
    if (strpos($path, '/uploads/') === 0) {
        return $path;
    }
    if (strpos($path, 'uploads/') === 0) {
        return '/' . $path;
    }
    return $path;
}

$logoPath = '';
if (isset($customization['logoPath']) && trim((string)$customization['logoPath']) !== '') {
    $logoPath = rrAssetUrl($customization['logoPath']);
} elseif (isset($customization['logo_path']) && trim((string)$customization['logo_path']) !== '') {
    $logoPath = rrAssetUrl($customization['logo_path']);
} elseif (isset($customization['shopLogo']) && trim((string)$customization['shopLogo']) !== '') {
    $logoPath = rrAssetUrl($customization['shopLogo']);
}

$carouselImages = array();
if (isset($customization['carouselImages']) && $customization['carouselImages'] !== '') {
    $decodedCarousel = json_decode($customization['carouselImages'], true);
    if (is_array($decodedCarousel)) {
        foreach ($decodedCarousel as $img) {
            $imgUrl = rrAssetUrl($img);
            if ($imgUrl !== '') {
                $carouselImages[] = $imgUrl;
            }
        }
    }
}

$apkFileName = 'RapidRepair-Mobile-App.apk';
$apkDownloadUrl = 'https://www.dropbox.com/scl/fi/zdd8jpohdbhot4tqiryx7/application-7752e345-e721-493c-919e-93e34e01026f.apk?rlkey=xwyu1a5ngfykl1zer6a7go3ou&st=49827vzc&dl=0';

function rrCurrentUrlForDownload($login_slug, $downloadType) {
    $scheme = 'http';
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) {
        $scheme = 'https';
    }

    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : 'tenantwebsite.php';

    return $scheme . '://' . $host . $script . '?shop=' . urlencode($login_slug) . '&download=' . urlencode($downloadType);
}

if (isset($_GET['download']) && $_GET['download'] === 'guide') {
    $inviteCode = isset($owner['invite_code']) && trim((string)$owner['invite_code']) !== ''
        ? trim((string)$owner['invite_code'])
        : 'No invite code available';

    $apkDownloadLink = $apkDownloadUrl;
    $guideFileName = preg_replace('/[^A-Za-z0-9_-]+/', '-', $shopName);
    $guideFileName = trim($guideFileName, '-');
    if ($guideFileName === '') {
        $guideFileName = 'shop';
    }
    $guideFileName .= '-mobile-app-instructions.txt';

    $content  = "MOBILE APP INSTALLATION GUIDE\r\n";
    $content .= "================================\r\n\r\n";
    $content .= "Shop Name: " . $shopName . "\r\n";
    $content .= "Invite Code: " . $inviteCode . "\r\n\r\n";
    $content .= "APK Download Link:\r\n";
    $content .= $apkDownloadLink . "\r\n\r\n";
    $content .= "Instructions:\r\n";
    $content .= "1. Click the APK Download Link above or go back to the website and click Download Here.\r\n";
    $content .= "2. After downloading, open the APK file on your Android phone.\r\n";
    $content .= "3. If your phone asks for permission, allow Install Unknown Apps for your browser or file manager.\r\n";
    $content .= "4. Finish the installation.\r\n";
    $content .= "5. Open the app and register/login using the shop invite code above.\r\n\r\n";
    $content .= "Important:\r\n";
    $content .= "- Keep the invite code private and use only the code for this shop.\r\n";
    $content .= "- If installation is blocked, check Android security settings and enable installation from your browser/file manager.\r\n\r\n";
    $content .= "Powered by RapidRepair System\r\n";

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $guideFileName . '"');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    echo $content;
    exit;
}

$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($apkDownloadUrl);
?>
<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?php echo htmlspecialchars($shopName); ?> | Professional Auto Repair</title>
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
                    "colors": {
                        "primary": "<?php echo isset($customization['primaryColor']) ? htmlspecialchars($customization['primaryColor']) : '#1152d4'; ?>",
                        "secondary-fixed-dim": "#cbd5e1",
                        "on-surface": "#0f172a",
                        "on-background": "#0f172a",
                        "inverse-primary": "#b4c5ff",
                        "on-error-container": "#991b1b",
                        "error": "#ef4444",
                        "primary-fixed-dim": "#bfdbfe",
                        "surface-container-low": "#ffffff",
                        "on-secondary-fixed-variant": "#1152d4",
                        "tertiary": "#B8860B",
                        "inverse-on-surface": "#f8fafc",
                        "secondary": "#1152d4",
                        "on-secondary": "#ffffff",
                        "background": "#f6f6f8",
                        "on-tertiary-fixed": "#7c2d12",
                        "outline-variant": "#cbd5e1",
                        "on-tertiary": "#ffffff",
                        "surface": "#f6f6f8",
                        "secondary-container": "#f1f5f9",
                        "surface-container-highest": "#ffffff",
                        "surface-bright": "#ffffff",
                        "inverse-surface": "#1152d4",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "on-error": "#ffffff",
                        "outline": "#e2e8f0",
                        "primary-fixed": "#dbeafe",
                        "secondary-fixed": "#e2e8f0",
                        "tertiary-fixed-dim": "#fed7aa",
                        "on-secondary-container": "#1152d4",
                        "surface-tint": "#1152d4",
                        "on-primary-fixed": "#1e3a8a",
                        "tertiary-container": "#fef3c7",
                        "surface-dim": "#d9d9e4",
                        "primary-container": "#E6F2EC",
                        "tertiary-fixed": "#ffedd5",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-high": "#ffffff",
                        "surface-container": "#ffffff",
                        "surface-variant": "#f1f5f9",
                        "on-surface-variant": "#1152d4",
                        "on-secondary-fixed": "#0f172a",
                        "error-container": "#fee2e2",
                        "on-primary": "#ffffff",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "on-primary-container": "#1152d4",
                        "on-tertiary-container": "#92400e"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "fontFamily": {
                        "headline": ["Inter"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    }
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-surface text-on-surface selection:bg-primary selection:text-white">
    <header class="fixed top-0 w-full z-50 bg-white/90 backdrop-blur-md border-b border-slate-200 shadow-sm">
        <nav class="flex justify-between items-center h-16 px-6 md:px-12 max-w-7xl mx-auto">
            <a href="#home" class="flex items-center gap-3 min-w-0 group" aria-label="<?php echo htmlspecialchars($shopName); ?> home">
                <?php if (!empty($logoPath)): ?>
                    <img
                        src="<?php echo htmlspecialchars($logoPath); ?>"
                        alt="<?php echo htmlspecialchars($shopName); ?> Logo"
                        class="h-10 w-10 object-contain rounded-lg border border-slate-200 bg-white shadow-sm group-hover:scale-105 transition-transform"
                    />
                <?php else: ?>
                    <div class="h-10 w-10 rounded-lg bg-primary text-white flex items-center justify-center font-black text-lg shadow-sm group-hover:scale-105 transition-transform">
                        <?php echo htmlspecialchars(strtoupper(substr($shopName, 0, 1))); ?>
                    </div>
                <?php endif; ?>
                <div class="text-xl font-black tracking-tighter text-[#0F4B3C] truncate max-w-[220px] md:max-w-[280px]">
                    <?php echo htmlspecialchars($shopName); ?>
                </div>
            </a>
            <div class="hidden md:flex items-center space-x-8 font-['Inter'] tracking-tight text-sm font-medium">
                <a class="text-[#0F4B3C] border-b-2 border-[#0F4B3C] pb-1" href="#home">Home</a>
                <a class="text-slate-600 hover:text-[#0F4B3C] transition-colors" href="#services">Services</a>
                <a class="text-slate-600 hover:text-[#0F4B3C] transition-colors" href="#mobile-app">Mobile App</a>
                <a class="text-slate-600 hover:text-[#0F4B3C] transition-colors" href="#about">About</a>
            </div>
            <div class="flex items-center gap-4">
                <a href="#about"
                    class="inline-flex items-center bg-primary text-on-primary px-5 py-2 lg rounded-lg font-semibold text-sm transition-all active:opacity-80 active:scale-[0.98] hover:opacity-90">
                    Learn More
                </a>
            </div>
        </nav>
    </header>
    <main class="pt-16">
        <section id="home" class="relative w-full overflow-hidden bg-[#1A2A2A] py-24 md:py-32 scroll-mt-20">
            <div class="absolute inset-0 opacity-40">
                <img alt="Modern Auto Repair Shop" class="w-full h-full object-cover"
                    data-alt="dramatic interior of a clean professional auto repair shop with blue neon lighting and high-tech diagnostic equipment on tool benches"
                    src="<?php echo isset($customization['heroBackground']) && $customization['heroBackground'] ? htmlspecialchars(rrAssetUrl($customization['heroBackground'])) : 'https://lh3.googleusercontent.com/aida-public/AB6AXuAEtRZx2VtJU_zvHyWwsPzD6V-hQgNfAn2ej099PlXa6HKYmZqm9u0Cl5K4y-AzSzT4KPlh897GoHs2N4t_PifJp3y-dT-rj5YsB98I9Dnp799aPfP0rZ-vQZhqRNpq_Ll2qyR361GWZxFHoYgrFfUTBzh8STIl_1B0aQTSEGfgyxNhO7ix91KeXhv26XzL0sHPtMcsrGNRwCP_RGCYJ8Ny0heOO9T8o7EUb9hcDp1dSNVs5Fja1CgIgUO3RtwhBFeHSdHhfk06o3Lo'; ?>" />
            </div>
            <div class="absolute inset-0 bg-gradient-to-r from-[#1A2A2A] via-[#1A2A2A]/80 to-transparent"></div>
            <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-12">
                <div class="max-w-2xl">
                    <h1 class="text-white text-5xl md:text-6xl font-black tracking-tight mb-6">
                        <?php echo isset($customization['heroHeading']) && $customization['heroHeading'] ? htmlspecialchars($customization['heroHeading']) : 'Precision Engineering. <br /><span class="text-primary">Absolute Reliability.</span>'; ?>
                    </h1>
                    <p class="text-slate-300 text-lg mb-8 font-medium leading-relaxed">
                        <?php echo isset($customization['heroSubtext']) && $customization['heroSubtext'] ? htmlspecialchars($customization['heroSubtext']) : 'Experience clinical-grade automotive care. Our master technicians leverage advanced diagnostics to ensure your vehicle performs at its architectural peak.'; ?>
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="#about"
                            class="inline-flex items-center justify-center bg-primary text-white px-8 py-4 lg rounded-lg font-bold text-base shadow-lg shadow-primary/20 transition-all hover:translate-y-[-2px]">
                            <?php echo isset($customization['ctaButtonText']) && $customization['ctaButtonText'] ? htmlspecialchars($customization['ctaButtonText']) : 'Explore Our Standards'; ?>
                        </a>
                        <a href="#services"
                            class="inline-flex items-center justify-center border border-white/20 bg-white/5 backdrop-blur-md text-white px-8 py-4 lg rounded-lg font-bold text-base hover:bg-white/10 transition-all">
                            View Services
                        </a>
                    </div>
                </div>
            </div>
        </section>
        <?php if (!empty($carouselImages)): ?>
        <section id="gallery" class="py-24 bg-slate-50 scroll-mt-20">
            <div class="max-w-7xl mx-auto px-6 md:px-12">
                <div class="text-center mb-16">
                    <span class="text-primary font-bold tracking-widest text-xs uppercase">Shop Gallery</span>
                    <h2 class="text-4xl font-black tracking-tight mt-2">Inside Our Workshop</h2>
                    <p class="text-slate-600 max-w-2xl mx-auto mt-4">Explore the uploaded pictures from our facility, team, and completed automotive work.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($carouselImages as $index => $carouselImage): ?>
                        <div class="group rounded-xl overflow-hidden bg-white border border-outline shadow-sm hover:shadow-lg transition-all <?php echo $index === 0 ? 'md:col-span-2 md:row-span-2' : ''; ?>">
                            <img
                                src="<?php echo htmlspecialchars($carouselImage); ?>"
                                alt="<?php echo htmlspecialchars($shopName); ?> gallery image <?php echo (int)$index + 1; ?>"
                                class="w-full <?php echo $index === 0 ? 'h-[520px]' : 'h-64'; ?> object-cover group-hover:scale-[1.03] transition-transform duration-500"
                            />
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <section class="py-24 bg-white">
            <div class="max-w-7xl mx-auto px-6 md:px-12">
                <div class="text-center mb-16">
                    <span class="text-primary font-bold tracking-widest text-xs uppercase">The <?php echo htmlspecialchars($shopName); ?> Advantage</span>
                    <h2 class="text-4xl font-black tracking-tight mt-2">Engineered Excellence</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                    <div class="group">
                        <div
                            class="w-14 h-14 bg-slate-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-primary group-hover:text-white transition-colors">
                            <span class="material-symbols-outlined text-3xl"
                                data-icon="troubleshoot">troubleshoot</span>
                        </div>
                        <h3 class="text-xl font-bold mb-3">Expert Diagnostics</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">
                            Our proprietary diagnostic protocol identifies micro-irregularities in engine performance
                            before they escalate into mechanical failures.
                        </p>
                    </div>
                    <div class="group">
                        <div
                            class="w-14 h-14 bg-slate-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-primary group-hover:text-white transition-colors">
                            <span class="material-symbols-outlined text-3xl"
                                data-icon="precision_manufacturing">precision_manufacturing</span>
                        </div>
                        <h3 class="text-xl font-bold mb-3">Precision Tuning</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">
                            Fine-tuning air-fuel ratios and transmission mapping to optimize fuel efficiency and torque
                            delivery for your specific driving environment.
                        </p>
                    </div>
                    <div class="group">
                        <div
                            class="w-14 h-14 bg-slate-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-primary group-hover:text-white transition-colors">
                            <span class="material-symbols-outlined text-3xl" data-icon="verified">verified</span>
                        </div>
                        <h3 class="text-xl font-bold mb-3">Genuine OEM Parts</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">
                            We exclusively utilize original equipment manufacturer components to ensure perfect fitment
                            and maintain your vehicle's factory warranty.
                        </p>
                    </div>
                    <div class="group">
                        <div
                            class="w-14 h-14 bg-slate-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-primary group-hover:text-white transition-colors">
                            <span class="material-symbols-outlined text-3xl" data-icon="speed">speed</span>
                        </div>
                        <h3 class="text-xl font-bold mb-3">Performance Testing</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">
                            Post-repair stress testing on our in-house dynamometer ensures real-world reliability under
                            high-load conditions.
                        </p>
                    </div>
                    <div class="group">
                        <div
                            class="w-14 h-14 bg-slate-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-primary group-hover:text-white transition-colors">
                            <span class="material-symbols-outlined text-3xl" data-icon="history">history</span>
                        </div>
                        <h3 class="text-xl font-bold mb-3">Service Transparency</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">
                            Digital service logs with high-resolution imagery of all worn components, providing full
                            traceability for every repair performed.
                        </p>
                    </div>
                    <div class="group">
                        <div
                            class="w-14 h-14 bg-slate-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-primary group-hover:text-white transition-colors">
                            <span class="material-symbols-outlined text-3xl"
                                data-icon="support_agent">support_agent</span>
                        </div>
                        <h3 class="text-xl font-bold mb-3">Specialist Support</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">
                            Direct access to Master Technicians for technical consultations on performance modifications
                            and long-term maintenance planning.
                        </p>
                    </div>
                </div>
            </div>
        </section>
        <section id="services" class="py-24 max-w-7xl mx-auto px-6 md:px-12 scroll-mt-20">
            <div class="mb-16">
                <span class="text-primary font-bold tracking-widest text-xs uppercase">Core Capabilities</span>
                <h2 class="text-4xl font-black tracking-tight mt-2">Expert Maintenance</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <div
                    class="md:col-span-8 bg-surface-container border border-outline rounded-xl overflow-hidden shadow-sm flex flex-col md:flex-row transition-all hover:shadow-md">
                    <div class="md:w-1/2 p-10 flex flex-col justify-center">
                        <div class="w-12 h-12 bg-primary-container rounded-lg flex items-center justify-center mb-6">
                            <span class="material-symbols-outlined text-primary" data-icon="computer">computer</span>
                        </div>
                        <h3 class="text-2xl font-bold mb-4">Engine Diagnostics</h3>
                        <p class="text-on-surface-variant text-sm leading-relaxed mb-6">
                            Using industry-leading scanning technology to decode ECU performance metrics and identify
                            phantom issues before they lead to failure.
                        </p>
                        <ul class="space-y-2 mb-8 text-sm font-medium">
                            <li class="flex items-center gap-2"><span
                                    class="material-symbols-outlined text-primary text-sm"
                                    data-icon="check_circle">check_circle</span> Fault Code Analysis</li>
                            <li class="flex items-center gap-2"><span
                                    class="material-symbols-outlined text-primary text-sm"
                                    data-icon="check_circle">check_circle</span> Sensor Calibration</li>
                        </ul>
                    </div>
                    <div class="md:w-1/2 h-64 md:h-auto">
                        <img alt="Engine Diagnostics" class="w-full h-full object-cover"
                            data-alt="close up of a professional mechanic using a digital diagnostic tablet connected to a luxury car engine bay"
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuAeKwRX9gbVEj_ZDeKQztbtFR3l2RkUj4tNbp_L-TFGvm_1jZRDe4LRtNoz63vt3g288vvZCM_Tg1tfutLMBK9h5Ojugz9xfAK3phYFqL7orQYkLgJ7BNYUiZXRmr9yhQZWdtcu-H43u1PiDPSiYjV_X8l32DK-Ng8x_9u4W86W7VeI9Xyc0VSEb0QYIXm2S6VSbd-TacddkVNW8kRnGzKMsZ5WzvgQbpNT945kqTAtanzRhHEk4ink6T4g7Gyl7w2l5iUpLdBrUjyK" />
                    </div>
                </div>
                <div
                    class="md:col-span-4 bg-surface-container border border-outline rounded-xl p-10 shadow-sm hover:shadow-md transition-all">
                    <div class="w-12 h-12 bg-primary-container rounded-lg flex items-center justify-center mb-6">
                        <span class="material-symbols-outlined text-primary"
                            data-icon="settings_backup_restore">settings_backup_restore</span>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Brake Systems</h3>
                    <p class="text-on-surface-variant text-sm leading-relaxed mb-6">
                        From ceramic pads to high-performance rotor resurfacing. Stopping power is non-negotiable.
                    </p>
                    <img alt="Brake Repair" class="w-full h-48 object-cover rounded-xl mt-4"
                        data-alt="macro photo of a new metallic car brake rotor and red caliper being installed by a specialist"
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuCHgL7Omj4WiWEJHas4YT92vDrdrYYvnps_C7rR6kHbWoYQiJ5EwtS2Me4VTRLEu_d1g331kBeiQhTeYsGzU1OOBqG5YN4B2bYHWhsgff_drgdgIYZUm9KFxuzMv6IWDoYMvJ-cSp60Kl-OkWuujTDwnS2wSR2Mrs7hMpsI147BLnsT2jpyysr2X7IHncWQhAu2e9l1v3dnspHlDlikQgWEy9cS88ISftLVvPV-hxA1UgSSXzTERGlQ7kLkphAuFxgIq65lASE0IhpN" />
                </div>
                <div
                    class="md:col-span-4 bg-surface-container border border-outline rounded-xl p-10 shadow-sm hover:shadow-md transition-all">
                    <div class="w-12 h-12 bg-primary-container rounded-lg flex items-center justify-center mb-6">
                        <span class="material-symbols-outlined text-primary" data-icon="oil_barrel">oil_barrel</span>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Precision Lube</h3>
                    <p class="text-on-surface-variant text-sm leading-relaxed">
                        Full synthetic performance fluids and premium filtration systems to extend powertrain longevity.
                    </p>
                </div>
                <div
                    class="md:col-span-8 bg-primary text-white rounded-xl p-10 shadow-lg flex flex-col md:flex-row items-center gap-8">
                    <div class="md:w-2/3">
                        <h3 class="text-3xl font-black mb-4">Commercial Fleet Solutions</h3>
                        <p class="text-emerald-100 text-sm leading-relaxed mb-6">
                            Tailored maintenance schedules for business owners. We keep your operations moving with
                            priority scheduling and comprehensive health reporting.
                        </p>
                        <a href="#contact"
                            class="inline-flex items-center bg-white text-primary px-6 py-2 xl rounded-lg font-bold text-xs uppercase tracking-widest">Inquire
                            Now</a>
                    </div>
                    <div class="md:w-1/3 flex justify-center">
                        <span class="material-symbols-outlined text-[120px] text-white/20"
                            data-icon="local_shipping">local_shipping</span>
                    </div>
                </div>
            </div>
        </section>
        <section id="about" class="py-24 bg-white scroll-mt-20">
            <div class="max-w-7xl mx-auto px-6 md:px-12 grid md:grid-cols-2 gap-16 items-center">
                <div class="relative">
                    <div class="aspect-square rounded-xl overflow-hidden border-8 border-surface shadow-xl">
                        <img alt="Workshop Interior" class="w-full h-full object-cover"
                            data-alt="clean brightly lit industrial garage with organized tools and a classic sports car on a hydraulic lift"
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuCNivH_gwqpoPun0DlJsk-9blL2X9GmrPZadgtfgSk_cfbrHcq_2Jj_Kv_acazMye-HCCWv3eZvFJsHjr-YrA_PdQ2Dc85Dk6eNetLGhRSxIlWTGUnZdLAs5em2s8xJ6Xxvy0C3isvjOWRs5KrnrdAJb6MuRGEgio9BCz-zTo3KZ4fhPwzI1i3ItWpxuos6F8vcgwsySr6X68R7sHCMNE6BJhr6e3-3uZv38v00EUgCyTafeawupM7Aoy0SfVrV6rKZjcAueOVkLia1" />
                    </div>
                    <div class="absolute -bottom-6 -right-6 bg-primary text-white p-8 rounded-lg shadow-2xl">
                        <div class="text-4xl font-black">25+</div>
                        <div class="text-xs font-bold uppercase tracking-widest opacity-80">Years Excellence</div>
                    </div>
                </div>
                <div>
                    <span class="text-primary font-bold tracking-widest text-xs uppercase">Legacy &amp; Vision</span>
                    <h2 class="text-4xl font-black tracking-tight mt-2 mb-8">About <?php echo htmlspecialchars($shopName); ?></h2>
                    <div class="space-y-6 text-on-surface-variant leading-relaxed">
                        <p>
                            Founded on the principles of transparency and technical mastery, <?php echo htmlspecialchars($shopName); ?> is
                            not your typical garage. We operate with the sterility of a laboratory and the passion of a
                            racing team.
                        </p>
                        <p>
                            Our facility is equipped with state-of-the-art specialized tools for European, Domestic, and
                            Japanese performance vehicles. Every technician on our floor is ASE Certified and undergoes
                            bi-annual training on emerging automotive technologies.
                        </p>
                    </div>
                    <div class="mt-10 grid grid-cols-2 gap-8 border-t border-outline pt-10">
                        <div>
                            <div class="text-on-surface font-bold text-lg mb-1">Certified Team</div>
                            <div class="text-xs text-on-surface-variant">Master ASE Level 1 Technicians</div>
                        </div>
                        <div>
                            <div class="text-on-surface font-bold text-lg mb-1">OEM Parts</div>
                            <div class="text-xs text-on-surface-variant">100% Genuine Component Policy</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section id="mobile-app" class="py-24 bg-slate-900 overflow-hidden text-white relative scroll-mt-20">
            <div class="absolute top-0 right-0 w-1/3 h-full opacity-10 pointer-events-none">
                <span class="material-symbols-outlined text-[400px]" data-icon="smartphone">smartphone</span>
            </div>
            <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row items-center gap-16">
                <div class="md:w-1/2">
                    <span class="text-primary font-bold tracking-widest text-xs uppercase mb-4 block">Connected
                        Service</span>
                    <h2 class="text-4xl md:text-5xl font-black tracking-tight mb-6">Manage Your Fleet from Your Pocket
                    </h2>
                    <p class="text-slate-400 text-lg mb-10 leading-relaxed">
                        Download the <?php echo htmlspecialchars($shopName); ?> app to track service history, receive real-time diagnostic alerts,
                        and monitor vehicle health with a single tap.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a class="bg-primary text-white border border-primary rounded-xl px-6 py-3 flex items-center gap-3 hover:opacity-90 transition-opacity"
                            href="<?php echo htmlspecialchars($apkDownloadUrl); ?>" target="_blank" rel="noopener">
                            <span class="material-symbols-outlined" data-icon="android">android</span>
                            <div class="text-left">
                                <div class="text-[10px] uppercase font-bold opacity-90 leading-none">Get the Mobile App</div>
                                <div class="text-xl font-bold leading-none">Download Here</div>
                            </div>
                        </a>

                        <a class="bg-white text-primary border border-primary rounded-xl px-6 py-3 flex items-center gap-3 hover:bg-primary hover:text-white transition-all"
                            href="?shop=<?php echo urlencode($login_slug); ?>&download=guide" download>
                            <span class="material-symbols-outlined" data-icon="description">description</span>
                            <div class="text-left">
                                <div class="text-[10px] uppercase font-bold opacity-90 leading-none">Setup Guide</div>
                                <div class="text-xl font-bold leading-none">Read Instructions</div>
                            </div>
                        </a>
                    </div>

                    <div class="mt-8 inline-block bg-white rounded-2xl p-5 shadow-lg text-center">
                        <img
                            src="<?php echo htmlspecialchars($qrCodeUrl); ?>"
                            alt="QR code to download <?php echo htmlspecialchars($shopName); ?> mobile app"
                            class="w-[220px] h-[220px] mx-auto"
                        />
                        <p class="text-slate-900 text-sm font-bold mt-3">Scan to Download App</p>
                        <p class="text-slate-500 text-xs mt-1">APK download via Dropbox</p>
                    </div>
                </div>
                <div class="md:w-1/2 flex justify-center">
                    <div
                        class="relative w-72 h-[580px] bg-slate-800 rounded-[3rem] border-8 border-slate-700 shadow-2xl overflow-hidden">
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-6 bg-slate-700 rounded-b-xl"></div>
                        <div class="p-6 pt-12">
                            <div class="text-xs font-bold text-primary uppercase mb-4">Service Status</div>
                            <div class="space-y-4">
                                <div class="bg-slate-900/50 p-4 rounded-xl border border-white/5">
                                    <div class="flex justify-between items-center mb-2">
                                        <div class="text-sm font-bold">Oil Health</div>
                                        <div class="text-xs text-emerald-400">Optimal</div>
                                    </div>
                                    <div class="w-full bg-slate-700 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-emerald-500 w-[85%] h-full"></div>
                                    </div>
                                </div>
                                <div class="bg-slate-900/50 p-4 rounded-xl border border-white/5">
                                    <div class="flex justify-between items-center mb-2">
                                        <div class="text-sm font-bold">Brake Pads</div>
                                        <div class="text-xs text-amber-400">Attention</div>
                                    </div>
                                    <div class="w-full bg-slate-700 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-amber-500 w-[30%] h-full"></div>
                                    </div>
                                </div>
                                <div class="bg-primary p-4 rounded-xl mt-8 text-center text-sm font-bold">
                                    View Full Diagnostics
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="py-24 bg-slate-50 overflow-hidden">
            <div class="max-w-7xl mx-auto px-6 md:px-12">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <span class="text-primary font-bold tracking-widest text-xs uppercase">Owner Satisfaction</span>
                    <h2 class="text-4xl font-black tracking-tight mt-2">The <?php echo htmlspecialchars($shopName); ?> Standard</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div
                        class="bg-white p-8 border border-outline rounded-xl shadow-sm hover:translate-y-[-4px] transition-all">
                        <div class="flex gap-1 text-tertiary mb-6">
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                        </div>
                        <p class="text-on-surface text-sm italic leading-relaxed mb-8">
                            "The most professional shop I've encountered. Their diagnostic report was 15 pages long with
                            photos of every issue. Truly architectural approach to repair."
                        </p>
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-slate-200"></div>
                            <div>
                                <div class="text-sm font-bold">Marcus Chen</div>
                                <div class="text-xs text-on-surface-variant">Audi R8 Owner</div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-white p-8 border border-outline rounded-xl shadow-sm hover:translate-y-[-4px] transition-all">
                        <div class="flex gap-1 text-tertiary mb-6">
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                        </div>
                        <p class="text-on-surface text-sm italic leading-relaxed mb-8">
                            "Clean, organized, and precise. They didn't just change my oil; they performed a full health
                            check that saved me from a major engine failure later."
                        </p>
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-slate-200"></div>
                            <div>
                                <div class="text-sm font-bold">Sarah Jenkins</div>
                                <div class="text-xs text-on-surface-variant">Fleet Logistics Manager</div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-white p-8 border border-outline rounded-xl shadow-sm hover:translate-y-[-4px] transition-all">
                        <div class="flex gap-1 text-tertiary mb-6">
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined" data-icon="star"
                                style="font-variation-settings: 'FILL' 1;">star</span>
                        </div>
                        <p class="text-on-surface text-sm italic leading-relaxed mb-8">
                            "The only shop I trust with my vintage Porsche. Their attention to detail is clinical. You
                            get what you pay for—absolute peace of mind."
                        </p>
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-slate-200"></div>
                            <div>
                                <div class="text-sm font-bold">David Rossi</div>
                                <div class="text-xs text-on-surface-variant">Classic Car Collector</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section id="services" class="py-24 max-w-7xl mx-auto px-6 md:px-12 scroll-mt-20">
            <div class="bg-[#1A2A2A] rounded-xl p-12 md:p-24 text-center overflow-hidden relative">
                <div
                    class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-primary/20 via-transparent to-transparent">
                </div>
                <div class="relative z-10">
                    <h2 class="text-white text-4xl md:text-5xl font-black tracking-tight mb-6">Redefining Automotive
                        <br />Care Excellence</h2>
                    <p class="text-slate-400 max-w-xl mx-auto mb-10 text-lg">
                        Our commitment to technical mastery ensures your vehicle remains in peak performance condition.
                        Explore our facility and expertise today.
                    </p>
                    <a href="#home"
                        class="inline-flex items-center bg-primary text-white px-10 py-5 lg rounded-lg font-bold text-lg hover:scale-[1.02] transition-transform">
                        Explore Our Facility
                    </a>
                </div>
            </div>
        </section>
    </main>
    <footer class="w-full border-t border-slate-200 bg-slate-50">
        <div class="flex flex-col md:flex-row justify-between items-center py-12 px-6 md:px-12 max-w-7xl mx-auto">
            <div class="mb-8 md:mb-0">
                <div class="text-lg font-black text-slate-900 mb-2"><?php echo htmlspecialchars($shopName); ?></div>
                <div class="font-['Inter'] text-xs font-regular text-slate-500">© 2026 <?php echo htmlspecialchars($shopName); ?>. All rights
                    reserved.</div>
            </div>
            <div class="flex flex-wrap justify-center gap-8 font-['Inter'] text-xs font-regular">
                <a class="text-slate-500 hover:text-[#0F4B3C] underline underline-offset-4 transition-all"
                    href="#home">Privacy Policy</a>
                <a class="text-slate-500 hover:text-[#0F4B3C] underline underline-offset-4 transition-all"
                    href="#services">Terms of Service</a>
                <a class="text-slate-500 hover:text-[#0F4B3C] underline underline-offset-4 transition-all"
                    href="#contact">Contact Support</a>
                <a class="text-slate-500 hover:text-[#0F4B3C] underline underline-offset-4 transition-all"
                    href="#mobile-app">Careers</a>
            </div>
            <div class="mt-8 md:mt-0 flex gap-4">
                <div
                    class="w-8 h-8 rounded-lg bg-white border border-outline flex items-center justify-center text-secondary hover:text-primary transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-sm" data-icon="share">share</span>
                </div>
                <div
                    class="w-8 h-8 rounded-lg bg-white border border-outline flex items-center justify-center text-secondary hover:text-primary transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-sm" data-icon="location_on">location_on</span>
                </div>
            </div>
        </div>
    </footer>
</body>

</html>