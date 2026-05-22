<?php
session_start();
include __DIR__ . "/../db.php";

$isClientLoggedIn = isset($_SESSION['client_id']);

if (!$isClientLoggedIn) {
    header("Location: clientlogin.php");
    exit();
}

$tenantID = $_GET['tenantID'] ?? '';
$plan = $_GET['plan'] ?? '';
$billingCycle = $_GET['billingCycle'] ?? '';

if (!isset($_SESSION['tenant_application_data']) || !is_array($_SESSION['tenant_application_data'])) {
    $_SESSION['tenant_application_data'] = [];
}

$_SESSION['tenant_application_data']['tenantID'] = $tenantID;
$_SESSION['tenant_application_data']['subscriptionPlan'] = $plan;
$_SESSION['tenant_application_data']['billingCycle'] = $billingCycle;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $documentType = trim($_POST['document_type'] ?? '');

    $requiredFiles = [
        'registration_document',
        'barangay_clearance',
        'business_permit',
        'bir_2303',
        'government_id'
    ];

    foreach ($requiredFiles as $fileField) {
        if (
            !isset($_FILES[$fileField]) ||
            $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE
        ) {
            $errors[] = "Please upload all required documents.";
            break;
        }
    }

    if (empty($errors)) {

        $uploadDir = __DIR__ . '/../uploads/tenant_documents/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        function uploadDocument($fieldName, $tenantID, $uploadDir)
        {
            global $errors;

            $file = $_FILES[$fieldName];

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

            if (!in_array($extension, $allowed)) {
                $errors[] = strtoupper($fieldName) . ' must be JPG, PNG, WEBP, or PDF only.';
                return '';
            }

            // 1 MB LIMIT
            $maxSize = 1 * 1024 * 1024;

            if ($file['size'] > $maxSize) {
                $errors[] = strtoupper($fieldName) . ' exceeds the 1 MB file size limit.';
                return '';
            }

            $fileName = $tenantID . '_' . $fieldName . '_' . time() . '.' . $extension;

            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                return 'uploads/tenant_documents/' . $fileName;
            }

            $errors[] = 'Failed to upload ' . strtoupper($fieldName) . '.';

            return '';
        }

        $registrationDocument = uploadDocument('registration_document', $tenantID, $uploadDir);
        $barangayClearance = uploadDocument('barangay_clearance', $tenantID, $uploadDir);
        $businessPermit = uploadDocument('business_permit', $tenantID, $uploadDir);
        $bir2303 = uploadDocument('bir_2303', $tenantID, $uploadDir);
        $governmentId = uploadDocument('government_id', $tenantID, $uploadDir);

        $updateSql = "
            UPDATE owners SET
                registration_type = '" . mysqli_real_escape_string($conn, $documentType) . "',
                business_permit_image = '" . mysqli_real_escape_string($conn, $businessPermit) . "',
                valid_id_image = '" . mysqli_real_escape_string($conn, $governmentId) . "',
                bir_certificate_image = '" . mysqli_real_escape_string($conn, $bir2303) . "'
            WHERE tenantID = '" . mysqli_real_escape_string($conn, $tenantID) . "'
        ";

        mysqli_query($conn, $updateSql);

        header(
            "Location: clientpayment.php?tenantID=" . urlencode($tenantID) .
            "&plan=" . urlencode($plan) .
            "&billingCycle=" . urlencode($billingCycle)
        );
        exit();
    }
}
?>
<!DOCTYPE html>
<html class="scroll-smooth" lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RapidRepairCo. | Document Requirements</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#1152d4",
                        surface: "#f6f6f8",
                        "surface-variant": "#f1f5f9",
                        outline: "#e2e8f0",
                        "on-surface": "#0f172a",
                        "on-surface-variant": "#64748b",
                    },
                    fontFamily: {
                        inter: ["Inter", "sans-serif"]
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f6f6f8;
            color: #0f172a;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24;
        }

        .file-input::file-selector-button {
            border: 0;
            margin-right: 16px;
            padding: 12px 18px;
            border-radius: 14px;
            background: #1152d4;
            color: white;
            font-weight: 800;
            cursor: pointer;
        }

        .file-input::file-selector-button:hover {
            background: #0d43ad;
        }
    </style>
</head>

<body class="min-h-screen bg-surface">

    <nav
        class="fixed top-0 w-full z-50 bg-white/80 backdrop-blur-md border-b border-slate-200 shadow-sm">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-3">
            <a href="clientlanding.php?restore=1" class="text-xl font-black tracking-tighter text-primary">
                RapidRepairCo.
            </a>

            <div class="hidden md:flex items-center gap-8">
                <a class="font-medium text-sm tracking-tight text-slate-600 hover:text-primary transition-colors"
                    href="clientlanding.php?restore=1">Home</a>
                <a class="font-medium text-sm tracking-tight text-slate-600 hover:text-primary transition-colors"
                    href="clientlanding.php?restore=1#features">Features</a>
                <a class="font-medium text-sm tracking-tight text-slate-600 hover:text-primary transition-colors"
                    href="clientlanding.php?restore=1#pricing">Pricing</a>
                <a class="font-medium text-sm tracking-tight text-slate-600 hover:text-primary transition-colors"
                    href="clientlanding.php?restore=1#support">Support</a>
            </div>

            <a href="clientprofile.php"
                class="w-10 h-10 inline-flex items-center justify-center rounded-full border border-primary/30 text-primary hover:bg-primary/5 transition-all">
                <span class="material-symbols-outlined">account_circle</span>
            </a>
        </div>
    </nav>

    <main class="pt-24 px-6 pb-16">
        <section class="relative overflow-hidden rounded-[2rem] max-w-7xl mx-auto border border-outline bg-white shadow-sm">
            <div class="absolute inset-0 bg-gradient-to-br from-primary/10 via-white to-slate-50"></div>

            <div class="absolute -top-28 -left-28 w-80 h-80 bg-primary/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-32 -right-24 w-96 h-96 bg-blue-200/30 rounded-full blur-3xl"></div>

            <div class="relative z-10 grid grid-cols-1 lg:grid-cols-[0.85fr_1.15fr] gap-0">

                <aside class="p-8 md:p-12 lg:p-14 border-b lg:border-b-0 lg:border-r border-outline">
                    <a href="clientlanding.php?restore=1"
                        class="inline-flex items-center gap-2 text-sm font-bold text-primary hover:underline mb-10">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                        Back to Application
                    </a>

                    <div class="mb-10">
                        <span
                            class="inline-flex items-center gap-2 px-3 py-1 bg-blue-50 text-primary text-[10px] font-black tracking-widest uppercase rounded-full border border-blue-100">
                            <span class="material-symbols-outlined text-[15px]">verified</span>
                            Step 2 of 3
                        </span>

                        <h1 class="text-4xl md:text-5xl font-black tracking-tighter leading-tight mt-6">
                            Legal Document <span class="text-primary">Requirements.</span>
                        </h1>

                        <p class="text-slate-500 mt-5 text-base leading-relaxed">
                            Upload the legal documents required for business verification in the Philippines.
                            Accepted file types are JPG, PNG, WEBP, and PDF.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-start gap-4 rounded-2xl bg-white/70 border border-outline p-5 shadow-sm">
                            <div class="w-11 h-11 rounded-2xl bg-blue-50 text-primary flex items-center justify-center">
                                <span class="material-symbols-outlined">upload_file</span>
                            </div>
                            <div>
                                <h3 class="font-black tracking-tight">1 MB limit</h3>
                                <p class="text-sm text-slate-500 mt-1">Each document must not exceed 1 MB.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 rounded-2xl bg-white/70 border border-outline p-5 shadow-sm">
                            <div class="w-11 h-11 rounded-2xl bg-blue-50 text-primary flex items-center justify-center">
                                <span class="material-symbols-outlined">description</span>
                            </div>
                            <div>
                                <h3 class="font-black tracking-tight">Clear copies only</h3>
                                <p class="text-sm text-slate-500 mt-1">Make sure document names and details are readable.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 rounded-2xl bg-white/70 border border-outline p-5 shadow-sm">
                            <div class="w-11 h-11 rounded-2xl bg-blue-50 text-primary flex items-center justify-center">
                                <span class="material-symbols-outlined">payments</span>
                            </div>
                            <div>
                                <h3 class="font-black tracking-tight">Next step: payment</h3>
                                <p class="text-sm text-slate-500 mt-1">After uploading, you will continue to the payment page.</p>
                            </div>
                        </div>
                    </div>
                </aside>

                <section class="p-8 md:p-12 lg:p-14">
                    <div class="bg-white rounded-[2rem] border border-outline shadow-[0_25px_80px_rgba(15,23,42,0.08)] p-6 md:p-8">

                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-5 mb-8">
                            <div>
                                <h2 class="text-2xl font-black tracking-tight">Upload Documents</h2>
                                <p class="text-sm text-slate-500 mt-2">Complete all required fields before continuing.</p>
                            </div>

                            <div class="px-4 py-3 rounded-2xl bg-slate-50 border border-outline">
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Tenant ID</p>
                                <p class="font-black text-primary">
                                    <?php echo htmlspecialchars($tenantID ?: 'Pending', ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            </div>
                        </div>

                        <?php if (count($errors) > 0): ?>
                            <div class="mb-6 rounded-2xl bg-red-50 border border-red-200 px-5 py-4 text-red-700">
                                <?php foreach ($errors as $error): ?>
                                    <p class="text-sm font-semibold"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" class="space-y-6">

                            <div class="rounded-3xl border border-outline bg-slate-50/70 p-5">
                                <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-600 mb-3">
                                    <span class="material-symbols-outlined text-primary text-[18px]">business</span>
                                    DTI Registration or SEC Registration
                                </label>

                                <select name="document_type"
                                    class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                    required>
                                    <option value="">Select Registration Type</option>
                                    <option value="DTI Registration">DTI Registration</option>
                                    <option value="SEC Registration">SEC Registration</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                                <div class="rounded-3xl border border-outline bg-white p-5 hover:border-primary/40 transition-all">
                                    <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-600 mb-3">
                                        <span class="material-symbols-outlined text-primary text-[18px]">article</span>
                                        Upload DTI / SEC Document
                                    </label>
                                    <input type="file" name="registration_document" accept=".jpg,.jpeg,.png,.webp,.pdf" required
                                        class="file-input w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                    <p class="text-xs text-slate-500 mt-3">Maximum file size: 1 MB</p>
                                </div>

                                <div class="rounded-3xl border border-outline bg-white p-5 hover:border-primary/40 transition-all">
                                    <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-600 mb-3">
                                        <span class="material-symbols-outlined text-primary text-[18px]">location_city</span>
                                        Barangay Clearance
                                    </label>
                                    <input type="file" name="barangay_clearance" accept=".jpg,.jpeg,.png,.webp,.pdf" required
                                        class="file-input w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                    <p class="text-xs text-slate-500 mt-3">Maximum file size: 1 MB</p>
                                </div>

                                <div class="rounded-3xl border border-outline bg-white p-5 hover:border-primary/40 transition-all">
                                    <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-600 mb-3">
                                        <span class="material-symbols-outlined text-primary text-[18px]">storefront</span>
                                        Business Permit
                                    </label>
                                    <input type="file" name="business_permit" accept=".jpg,.jpeg,.png,.webp,.pdf" required
                                        class="file-input w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                    <p class="text-xs text-slate-500 mt-3">Maximum file size: 1 MB</p>
                                </div>

                                <div class="rounded-3xl border border-outline bg-white p-5 hover:border-primary/40 transition-all">
                                    <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-600 mb-3">
                                        <span class="material-symbols-outlined text-primary text-[18px]">receipt_long</span>
                                        BIR 2303
                                    </label>
                                    <input type="file" name="bir_2303" accept=".jpg,.jpeg,.png,.webp,.pdf" required
                                        class="file-input w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                    <p class="text-xs text-slate-500 mt-3">Maximum file size: 1 MB</p>
                                </div>

                                <div class="rounded-3xl border border-outline bg-white p-5 hover:border-primary/40 transition-all md:col-span-2">
                                    <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-600 mb-3">
                                        <span class="material-symbols-outlined text-primary text-[18px]">badge</span>
                                        Government ID
                                    </label>
                                    <input type="file" name="government_id" accept=".jpg,.jpeg,.png,.webp,.pdf" required
                                        class="file-input w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                    <p class="text-xs text-slate-500 mt-3">Maximum file size: 1 MB</p>
                                </div>

                            </div>

                            <button type="submit"
                                class="w-full bg-primary hover:bg-blue-700 transition-all text-white font-black py-5 rounded-2xl text-lg flex items-center justify-center gap-2 shadow-[0_18px_40px_rgba(17,82,212,0.25)]">
                                Continue to Payment
                                <span class="material-symbols-outlined text-[22px]">arrow_forward</span>
                            </button>

                        </form>
                    </div>
                </section>
            </div>
        </section>
    </main>

</body>

</html>