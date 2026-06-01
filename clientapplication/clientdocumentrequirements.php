<?php
session_start();
include __DIR__ . "/../db.php";

$isClientLoggedIn = isset($_SESSION['client_id']);

if (!$isClientLoggedIn) {
    header("Location: clientlogin.php");
    exit();
}

$tenantID = trim((string) ($_GET['tenantID'] ?? ''));
$plan = trim((string) ($_GET['plan'] ?? ''));
$billingCycle = trim((string) ($_GET['billingCycle'] ?? ''));

if ($tenantID === '') {
    die("Missing tenant ID.");
}

if (!isset($_SESSION['tenant_application_data']) || !is_array($_SESSION['tenant_application_data'])) {
    $_SESSION['tenant_application_data'] = [];
}

$_SESSION['tenant_application_data']['tenantID'] = $tenantID;
$_SESSION['tenant_application_data']['subscriptionPlan'] = $plan;
$_SESSION['tenant_application_data']['billingCycle'] = $billingCycle;

$errors = [];

function safeDocumentLabel($fieldName)
{
    $labels = [
        'business_permit' => 'Business Permit',
        'bir_2303'        => 'BIR 2303',
        'government_id'   => 'Government ID'
    ];
    return $labels[$fieldName] ?? ucwords(str_replace('_', ' ', $fieldName));
}

function uploadTenantDocument($fieldName, $tenantID, $uploadDir, &$errors)
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = safeDocumentLabel($fieldName) . " is required.";
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Upload failed for " . safeDocumentLabel($fieldName) . ".";
        return null;
    }

    $originalName   = basename((string) $file['name']);
    $extension      = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExt     = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    if (!in_array($extension, $allowedExt, true)) {
        $errors[] = safeDocumentLabel($fieldName) . " must be JPG, JPEG, PNG, WEBP, or PDF only.";
        return null;
    }

    if ((int) $file['size'] > 3 * 1024 * 1024) {
        $errors[] = safeDocumentLabel($fieldName) . " exceeds the 3 MB file size limit.";
        return null;
    }

    $mimeType         = mime_content_type($file['tmp_name']);
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        $errors[] = safeDocumentLabel($fieldName) . " has an invalid file type.";
        return null;
    }

    $tenantFolder = $uploadDir . $tenantID . '/';
    if (!is_dir($tenantFolder)) {
        mkdir($tenantFolder, 0777, true);
    }

    $safeBase       = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $storedFileName = $fieldName . '_' . time() . '_' . random_int(1000, 9999) . '_' . $safeBase . '.' . $extension;
    $targetPath     = $tenantFolder . $storedFileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $errors[] = "Failed to save " . safeDocumentLabel($fieldName) . ".";
        return null;
    }

    return [
        'original_name' => $originalName,
        'stored_name'   => $storedFileName,
        'file_path'     => 'uploads/tenant_documents/' . $tenantID . '/' . $storedFileName,
        'extension'     => $extension,
        'mime_type'     => $mimeType,
        'file_size'     => (int) $file['size']
    ];
}

function saveTenantDocument($conn, $tenantID, $documentType, $fileInfo, $extractedData = null)
{
    $sql = "INSERT INTO tenant_documents
                (tenantID, document_type, file_name, file_path,
                 file_extension, mime_type, file_size, verification_status, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, "sssssss",
        $tenantID, $documentType,
        $fileInfo['original_name'], $fileInfo['file_path'],
        $fileInfo['extension'], $fileInfo['mime_type'], $fileInfo['file_size']
    );

    if (!mysqli_stmt_execute($stmt)) return false;

    if ($extractedData && !empty($extractedData)) {
        return saveDocumentExtraction($conn, $tenantID, $documentType, $extractedData);
    }

    return true;
}

function saveDocumentExtraction($conn, $tenantID, $documentType, $extractedData)
{
    $businessName     = $extractedData['business_name']    ?? null;
    $ownerName        = $extractedData['owner_name']       ?? null;
    $permitNumber     = $extractedData['permit_number']    ?? null;
    $issueDate        = $extractedData['issue_date']       ?? null;
    $expiryDate       = $extractedData['expiry_date']      ?? null;
    $address          = $extractedData['address']          ?? null;
    $rawOcrText       = $extractedData['raw_ocr_text']     ?? null;
    $confidenceScore  = $extractedData['confidence_score'] ?? 0;
    $shopName         = $extractedData['shop_name']        ?? null;
    $homeAddress      = $extractedData['home_address']     ?? null;
    $businessAddress  = $extractedData['business_address'] ?? null;
    $orNumber         = $extractedData['or_number']        ?? null;
    $tinNumber        = $extractedData['tin_number']       ?? null;
    $branchCode       = $extractedData['branch_code']      ?? null;
    $tinIssuanceDate  = $extractedData['tin_issuance_date']?? null;
    $idNumber         = $extractedData['id_number']        ?? null;

    $sql = "INSERT INTO document_extractions
                (tenantID, document_type, business_name, owner_name, permit_number,
                 issue_date, expiry_date, address, raw_ocr_text, confidence_score,
                 verified_by_user, shop_name, home_address, business_address,
                 or_number, tin_number, branch_code, tin_issuance_date, id_number, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, "sssssssssissssssss",
        $tenantID, $documentType, $businessName, $ownerName, $permitNumber,
        $issueDate, $expiryDate, $address, $rawOcrText, $confidenceScore,
        $shopName, $homeAddress, $businessAddress, $orNumber, $tinNumber,
        $branchCode, $tinIssuanceDate, $idNumber
    );

    return mysqli_stmt_execute($stmt);
}

/* ───────────────────────────────────────────────
   AJAX – Dummy OCR extraction endpoint
─────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'extract_document') {
    header('Content-Type: application/json');

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'File upload failed']);
        exit();
    }

    $file         = $_FILES['file'];
    $documentType = trim((string) ($_POST['document_type'] ?? ''));

    if (empty($documentType)) {
        echo json_encode(['success' => false, 'error' => 'Document type required']);
        exit();
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $extension  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExt)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type']);
        exit();
    }

    if ($file['size'] > 3 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File too large (3 MB max)']);
        exit();
    }

    /* Dummy extracted data – fixed values as requested */
    $dummyData = [
        'business_permit' => [
            'shop_name'  => 'Autotribe Auto Repair Shop',
            'issue_date' => '2024-02-25',
            'expiry_date'=> '2028-02-28',
        ],
        'bir_2303' => [
            'tin_number'        => '010-742-803-000',
            'branch_code'       => '000',
            'owner_name'        => 'John Maverick Mendoza',
            'tin_issuance_date' => '2026-04-28',
            'address'           => '218, Ulingao, San Rafael, Bulacan',
        ],
        'government_id' => [
            'owner_name' => 'JOHN MAVERICK MENDOZA',
            'birthday'   => '2004-12-01',
            'id_number'  => '1234-5678-9101-1213',
        ],
    ];

    echo json_encode([
        'success'        => true,
        'document_type'  => $documentType,
        'extracted_data' => $dummyData[$documentType] ?? [],
    ]);
    exit();
}

/* ───────────────────────────────────────────────
   Form POST – save documents
─────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $requiredDocuments = [
        'business_permit' => 'Business Permit',
        'bir_2303'        => 'BIR 2303',
        'government_id'   => 'Government ID',
    ];

    foreach (array_keys($requiredDocuments) as $fileField) {
        if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = "Please upload all required documents.";
            break;
        }
    }

    if (empty($errors)) {
        $uploadDir = __DIR__ . '/../uploads/tenant_documents/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $uploadedFiles = [];
        foreach ($requiredDocuments as $fieldName => $documentType) {
            $uploadedFiles[$fieldName] = uploadTenantDocument($fieldName, $tenantID, $uploadDir, $errors);
        }

        if (empty($errors)) {
            $ownerName  = trim((string) ($_POST['extracted_owner_name']  ?? ''));
            $expiryDate = trim((string) ($_POST['extracted_expiry_date'] ?? ''));

            if (empty($ownerName))  $errors[] = "Owner name is required.";
            if (empty($expiryDate)) $errors[] = "Document expiry date could not be determined.";

            if (empty($errors)) {
                $expiryTs = strtotime($expiryDate);
                if ($expiryTs === false) {
                    $errors[] = "Invalid expiry date format.";
                } else {
                    $now = time();

                    if ($expiryTs < $now) {
                        $errors[] = "Document has already expired on " . date('Y-m-d', $expiryTs) . ". Please upload a valid document.";
                        $del = mysqli_prepare($conn, "DELETE FROM owners WHERE tenantID = ?");
                        if ($del) { mysqli_stmt_bind_param($del, "s", $tenantID); mysqli_stmt_execute($del); }
                    } else {
                        $months       = ($billingCycle === 'Annual') ? 12 : 1;
                        $requiredTs   = strtotime("+$months months", $now);

                        if ($expiryTs < $requiredTs) {
                            $errors[] = "Document expiry (" . date('Y-m-d', $expiryTs) . ") does not cover the full {$billingCycle} subscription period.";
                            $del = mysqli_prepare($conn, "DELETE FROM owners WHERE tenantID = ?");
                            if ($del) { mysqli_stmt_bind_param($del, "s", $tenantID); mysqli_stmt_execute($del); }
                        }
                    }
                }
            }

            if (empty($errors)) {
                $allExtractedData = [
                    'business_permit' => [
                        'document_type'    => 'Business Permit',
                        'business_name'    => trim((string) ($_POST['permit_shop_name']   ?? '')),
                        'issue_date'       => trim((string) ($_POST['permit_issue_date']  ?? '')),
                        'expiry_date'      => trim((string) ($_POST['permit_expiry_date'] ?? '')),
                    ],
                    'bir_2303' => [
                        'document_type'     => 'BIR 2303',
                        'tin_number'        => trim((string) ($_POST['bir_tin_number']        ?? '')),
                        'branch_code'       => trim((string) ($_POST['bir_branch_code']       ?? '')),
                        'owner_name'        => trim((string) ($_POST['bir_owner_name']        ?? '')),
                        'tin_issuance_date' => trim((string) ($_POST['bir_tin_issuance_date'] ?? '')),
                        'address'           => trim((string) ($_POST['bir_address']           ?? '')),
                    ],
                    'government_id' => [
                        'document_type' => 'Government ID',
                        'owner_name'    => trim((string) ($_POST['id_owner_name'] ?? '')),
                        'birthday'      => trim((string) ($_POST['id_birthday']   ?? '')),
                        'id_number'     => trim((string) ($_POST['id_number']     ?? '')),
                    ],
                ];

                mysqli_begin_transaction($conn);
                try {
                    $del = mysqli_prepare($conn, "DELETE FROM tenant_documents WHERE tenantID = ?");
                    if (!$del) throw new Exception("Cannot prepare document cleanup.");
                    mysqli_stmt_bind_param($del, "s", $tenantID);
                    if (!mysqli_stmt_execute($del)) throw new Exception("Cannot remove old documents.");

                    $del2 = mysqli_prepare($conn, "DELETE FROM document_extractions WHERE tenantID = ?");
                    if (!$del2) throw new Exception("Cannot prepare extraction cleanup.");
                    mysqli_stmt_bind_param($del2, "s", $tenantID);
                    if (!mysqli_stmt_execute($del2)) throw new Exception("Cannot remove old extractions.");

                    $registrationType = 'business';
                    foreach ($requiredDocuments as $fieldName => $documentType) {
                        if (!saveTenantDocument($conn, $tenantID, $documentType,
                                                $uploadedFiles[$fieldName], $allExtractedData[$fieldName] ?? null)) {
                            throw new Exception("Cannot save " . safeDocumentLabel($fieldName) . ".");
                        }
                    }

                    mysqli_commit($conn);
                    header("Location: clientpayment.php?tenantID=" . urlencode($tenantID)
                           . "&plan=" . urlencode($plan)
                           . "&billingCycle=" . urlencode($billingCycle));
                    exit();
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RapidRepairCo. | Document Requirements</title>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                primary: "#1152d4",
                surface: "#f6f6f8",
                outline: "#e2e8f0",
                "on-surface": "#0f172a",
                "on-surface-variant": "#64748b",
            },
            fontFamily: { inter: ["Inter", "sans-serif"] }
        }
    }
}
</script>

<style>
* { font-family: 'Inter', sans-serif; }
body { background: #f6f6f8; color: #0f172a; }

.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24; }

.file-input::file-selector-button {
    border: 0; margin-right: 14px; padding: 10px 16px;
    border-radius: 10px; background: #1152d4; color: white;
    font-weight: 700; cursor: pointer; font-size: 13px;
    transition: background .2s;
}
.file-input::file-selector-button:hover { background: #0d43ad; }

/* ── Upload card states ── */
.doc-card { transition: all .25s; }
.doc-card.ready   { border-color: #1152d4; background: #eff6ff; }
.doc-card.success { border-color: #10b981; background: #f0fdf4; }
.doc-card.error   { border-color: #ef4444; background: #fff1f2; }

/* ── Scanning overlay ── */
#scanOverlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.55);
    backdrop-filter: blur(4px);
    z-index: 900;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 24px;
}
#scanOverlay.active { display: flex; }

.scan-card {
    background: white;
    border-radius: 1.25rem;
    padding: 2rem 2.5rem;
    min-width: 340px;
    max-width: 90vw;
    box-shadow: 0 32px 64px rgba(0,0,0,.2);
}
.scan-title {
    font-size: 1.25rem; font-weight: 900;
    color: #0f172a; margin-bottom: 1.5rem;
    display: flex; align-items: center; gap: 10px;
}
.scan-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 0; border-bottom: 1px solid #f1f5f9;
}
.scan-item:last-child { border-bottom: none; }
.scan-label { font-size: .875rem; font-weight: 600; flex: 1; color: #334155; }
.scan-icon { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; }

/* spinner */
@keyframes spin { to { transform: rotate(360deg); } }
.spinner {
    width: 22px; height: 22px;
    border: 3px solid #e2e8f0;
    border-top-color: #1152d4;
    border-radius: 50%;
    animation: spin .8s linear infinite;
}

/* pulse dot */
@keyframes pulse-dot {
    0%,100% { opacity: 1; transform: scale(1); }
    50%      { opacity: .4; transform: scale(.7); }
}
.pulse-dot {
    width: 10px; height: 10px; border-radius: 50%;
    background: #f59e0b;
    animation: pulse-dot 1s ease-in-out infinite;
}

.check-icon  { color: #10b981; font-size: 22px; }
.pend-icon   { color: #94a3b8; font-size: 22px; }

/* ── Review Modal ── */
#reviewModal {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.55);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
#reviewModal.active { display: flex; }

.review-box {
    background: white;
    border-radius: 1.5rem;
    width: 100%;
    max-width: 680px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 40px 80px rgba(0,0,0,.2);
    animation: slideUp .3s ease;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
}
.review-header {
    position: sticky; top: 0; background: white;
    border-bottom: 1px solid #f1f5f9;
    padding: 1.5rem 2rem;
    display: flex; align-items: center; gap: 12px;
    border-radius: 1.5rem 1.5rem 0 0;
    z-index: 10;
}
.review-body { padding: 1.5rem 2rem 2rem; }

.review-section {
    margin-bottom: 1.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    overflow: hidden;
}
.review-section-head {
    background: #f8fafc;
    padding: .75rem 1.25rem;
    font-size: .75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #1152d4;
    display: flex; align-items: center; gap: 8px;
}
.review-fields { padding: 1.25rem; display: grid; gap: 1rem; }
.review-fields.two { grid-template-columns: 1fr 1fr; }

.rfield label {
    display: block; font-size: .7rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em;
    color: #94a3b8; margin-bottom: 5px;
}
.rfield input {
    width: 100%; padding: .65rem .85rem;
    border: 1.5px solid #e2e8f0; border-radius: .6rem;
    font-size: .875rem; color: #0f172a;
    transition: border-color .2s, box-shadow .2s;
    background: #fafafa;
}
.rfield input:focus {
    outline: none; border-color: #1152d4;
    box-shadow: 0 0 0 3px rgba(17,82,212,.1);
    background: white;
}
.rfield.span2 { grid-column: span 2; }

.review-footer {
    position: sticky; bottom: 0; background: white;
    border-top: 1px solid #f1f5f9;
    padding: 1.25rem 2rem;
    display: flex; gap: 1rem;
    border-radius: 0 0 1.5rem 1.5rem;
}

/* ── Rejection Modal ── */
#rejectModal {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.55);
    backdrop-filter: blur(4px);
    z-index: 1100;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
#rejectModal.active { display: flex; }
.reject-box {
    background: white;
    border-radius: 1.5rem;
    padding: 2.5rem;
    max-width: 440px;
    width: 100%;
    text-align: center;
    box-shadow: 0 40px 80px rgba(0,0,0,.2);
    animation: slideUp .3s ease;
}
</style>
</head>
<body class="min-h-screen bg-surface">

<!-- NAV -->
<nav class="fixed top-0 w-full z-50 bg-white/80 backdrop-blur-md border-b border-slate-200 shadow-sm">
    <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-3">
        <a href="clientlanding.php?restore=1" class="text-xl font-black tracking-tighter text-primary">RapidRepairCo.</a>
        <div class="hidden md:flex items-center gap-8">
            <a class="font-medium text-sm text-slate-600 hover:text-primary transition-colors" href="clientlanding.php?restore=1">Home</a>
            <a class="font-medium text-sm text-slate-600 hover:text-primary transition-colors" href="clientlanding.php?restore=1#features">Features</a>
            <a class="font-medium text-sm text-slate-600 hover:text-primary transition-colors" href="clientlanding.php?restore=1#pricing">Pricing</a>
            <a class="font-medium text-sm text-slate-600 hover:text-primary transition-colors" href="clientlanding.php?restore=1#support">Support</a>
        </div>
        <a href="clientprofile.php" class="w-10 h-10 inline-flex items-center justify-center rounded-full border border-primary/30 text-primary hover:bg-primary/5 transition-all">
            <span class="material-symbols-outlined">account_circle</span>
        </a>
    </div>
</nav>

<!-- MAIN -->
<main class="pt-24 px-6 pb-16">
    <section class="relative overflow-hidden rounded-[2rem] max-w-7xl mx-auto border border-outline bg-white shadow-sm">
        <div class="absolute inset-0 bg-gradient-to-br from-primary/10 via-white to-slate-50"></div>
        <div class="absolute -top-28 -left-28 w-80 h-80 bg-primary/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-32 -right-24 w-96 h-96 bg-blue-200/30 rounded-full blur-3xl"></div>

        <div class="relative z-10 grid grid-cols-1 lg:grid-cols-[0.85fr_1.15fr]">

            <!-- SIDEBAR -->
            <aside class="p-8 md:p-12 lg:p-14 border-b lg:border-b-0 lg:border-r border-outline">
                <a href="clientlanding.php?restore=1" class="inline-flex items-center gap-2 text-sm font-bold text-primary hover:underline mb-10">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Back to Application
                </a>

                <div class="mb-10">
                    <span class="inline-flex items-center gap-2 px-3 py-1 bg-blue-50 text-primary text-[10px] font-black tracking-widest uppercase rounded-full border border-blue-100">
                        <span class="material-symbols-outlined text-[15px]">verified</span>
                        Step 2 of 3
                    </span>

                    <h1 class="text-4xl md:text-5xl font-black tracking-tighter leading-tight mt-6">
                        Legal Document <span class="text-primary">Requirements.</span>
                    </h1>

                    <p class="text-slate-500 mt-5 text-base leading-relaxed">
                        Upload the legal documents required for business verification in the Philippines.
                        Accepted file types: JPG, PNG, WEBP, and PDF.
                    </p>
                </div>

                <div class="space-y-4">
                    <div class="flex items-start gap-4 rounded-2xl bg-white/70 border border-outline p-5 shadow-sm">
                        <div class="w-11 h-11 rounded-2xl bg-blue-50 text-primary flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined">upload_file</span>
                        </div>
                        <div>
                            <h3 class="font-black tracking-tight">3 MB limit</h3>
                            <p class="text-sm text-slate-500 mt-1">Each document must not exceed 3 MB.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4 rounded-2xl bg-white/70 border border-outline p-5 shadow-sm">
                        <div class="w-11 h-11 rounded-2xl bg-blue-50 text-primary flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined">document_scanner</span>
                        </div>
                        <div>
                            <h3 class="font-black tracking-tight">Auto-scan included</h3>
                            <p class="text-sm text-slate-500 mt-1">Documents are scanned and data is auto-extracted for review.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4 rounded-2xl bg-white/70 border border-outline p-5 shadow-sm">
                        <div class="w-11 h-11 rounded-2xl bg-blue-50 text-primary flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined">payments</span>
                        </div>
                        <div>
                            <h3 class="font-black tracking-tight">Next step: payment</h3>
                            <p class="text-sm text-slate-500 mt-1">After verifying documents, you'll proceed to payment.</p>
                        </div>
                    </div>

                    <!-- Plan info -->
                    <div class="rounded-2xl bg-primary/5 border border-primary/20 p-5">
                        <p class="text-xs font-black uppercase tracking-widest text-primary mb-2">Selected Plan</p>
                        <p class="font-bold text-slate-700"><?php echo htmlspecialchars($plan ?: '—', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="text-sm text-slate-500 mt-1"><?php echo htmlspecialchars($billingCycle ?: '—', ENT_QUOTES, 'UTF-8'); ?> billing</p>
                    </div>
                </div>
            </aside>

            <!-- FORM -->
            <section class="p-8 md:p-12 lg:p-14">
                <div class="bg-white rounded-[2rem] border border-outline shadow-[0_25px_80px_rgba(15,23,42,0.08)] p-6 md:p-8">

                    <div class="mb-8">
                        <h2 class="text-2xl font-black tracking-tight">Upload Documents</h2>
                        <p class="text-sm text-slate-500 mt-1">All three documents are required before continuing.</p>
                    </div>

                    <?php if ($errors): ?>
                    <div class="mb-6 rounded-2xl bg-red-50 border border-red-200 px-5 py-4">
                        <?php foreach ($errors as $e): ?>
                            <p class="text-sm font-semibold text-red-700"><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="documentForm" class="space-y-6">

                        <!-- Hidden extracted fields -->
                        <input type="hidden" name="extracted_owner_name"  id="h_owner_name">
                        <input type="hidden" name="extracted_expiry_date" id="h_expiry_date">
                        <input type="hidden" name="permit_shop_name"      id="h_permit_shop_name">
                        <input type="hidden" name="permit_issue_date"     id="h_permit_issue_date">
                        <input type="hidden" name="permit_expiry_date"    id="h_permit_expiry_date">
                        <input type="hidden" name="bir_tin_number"        id="h_bir_tin">
                        <input type="hidden" name="bir_branch_code"       id="h_bir_branch">
                        <input type="hidden" name="bir_owner_name"        id="h_bir_owner">
                        <input type="hidden" name="bir_tin_issuance_date" id="h_bir_tin_date">
                        <input type="hidden" name="bir_address"           id="h_bir_address">
                        <input type="hidden" name="id_owner_name"         id="h_id_owner">
                        <input type="hidden" name="id_birthday"           id="h_id_birthday">
                        <input type="hidden" name="id_number"             id="h_id_number">

                        <!-- Upload cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            <div class="doc-card rounded-3xl border-2 border-outline bg-white p-5 transition-all" id="card_business_permit">
                                <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-600 mb-3">
                                    <span class="material-symbols-outlined text-primary text-[18px]">storefront</span>
                                    Business Permit
                                </label>
                                <input type="file" name="business_permit" accept=".jpg,.jpeg,.png,.webp,.pdf"
                                    class="file-input w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm"
                                    data-document-type="business_permit" data-card="card_business_permit">
                                <p class="text-xs text-slate-400 mt-2">JPG, PNG, WEBP or PDF · Max 3 MB</p>
                                <div class="doc-badge mt-2 hidden text-xs font-semibold flex items-center gap-1"></div>
                            </div>

                            <div class="doc-card rounded-3xl border-2 border-outline bg-white p-5 transition-all" id="card_bir_2303">
                                <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-600 mb-3">
                                    <span class="material-symbols-outlined text-primary text-[18px]">receipt_long</span>
                                    BIR 2303
                                </label>
                                <input type="file" name="bir_2303" accept=".jpg,.jpeg,.png,.webp,.pdf"
                                    class="file-input w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm"
                                    data-document-type="bir_2303" data-card="card_bir_2303">
                                <p class="text-xs text-slate-400 mt-2">JPG, PNG, WEBP or PDF · Max 3 MB</p>
                                <div class="doc-badge mt-2 hidden text-xs font-semibold flex items-center gap-1"></div>
                            </div>

                            <div class="doc-card rounded-3xl border-2 border-outline bg-white p-5 transition-all md:col-span-2 md:w-1/2" id="card_government_id">
                                <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-600 mb-3">
                                    <span class="material-symbols-outlined text-primary text-[18px]">badge</span>
                                    Government ID
                                </label>
                                <input type="file" name="government_id" accept=".jpg,.jpeg,.png,.webp,.pdf"
                                    class="file-input w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm"
                                    data-document-type="government_id" data-card="card_government_id">
                                <p class="text-xs text-slate-400 mt-2">JPG, PNG, WEBP or PDF · Max 3 MB</p>
                                <div class="doc-badge mt-2 hidden text-xs font-semibold flex items-center gap-1"></div>
                            </div>

                        </div>

                        <!-- Continue button -->
                        <button type="button" id="continueBtn" onclick="startScan()"
                            class="w-full bg-primary hover:bg-blue-700 transition-all text-white font-black py-5 rounded-2xl text-lg flex items-center justify-center gap-2 shadow-[0_18px_40px_rgba(17,82,212,0.25)]">
                            <span class="material-symbols-outlined text-[22px]">document_scanner</span>
                            Continue to Payment
                        </button>

                    </form>
                </div>
            </section>
        </div>
    </section>
</main>

<!-- ════════════════════════════════════════════
     SCANNING OVERLAY
════════════════════════════════════════════ -->
<div id="scanOverlay">
    <div class="scan-card">
        <div class="scan-title">
            <span class="material-symbols-outlined text-primary" style="font-size:26px">document_scanner</span>
            Checking Documents
        </div>

        <div class="scan-item" id="scan_business_permit">
            <div class="scan-icon"><div class="pulse-dot" id="si_business_permit_dot" style="display:none"></div><div class="spinner" id="si_business_permit_spin" style="display:none"></div><span class="material-symbols-outlined pend-icon" id="si_business_permit_wait">pending</span><span class="material-symbols-outlined check-icon" id="si_business_permit_ok" style="display:none">check_circle</span></div>
            <div class="scan-label">Business Permit</div>
            <span class="text-xs text-slate-400 font-medium" id="sl_business_permit">Waiting…</span>
        </div>

        <div class="scan-item" id="scan_bir_2303">
            <div class="scan-icon"><div class="pulse-dot" id="si_bir_2303_dot" style="display:none"></div><div class="spinner" id="si_bir_2303_spin" style="display:none"></div><span class="material-symbols-outlined pend-icon" id="si_bir_2303_wait">pending</span><span class="material-symbols-outlined check-icon" id="si_bir_2303_ok" style="display:none">check_circle</span></div>
            <div class="scan-label">BIR 2303</div>
            <span class="text-xs text-slate-400 font-medium" id="sl_bir_2303">Waiting…</span>
        </div>

        <div class="scan-item" id="scan_government_id">
            <div class="scan-icon"><div class="pulse-dot" id="si_government_id_dot" style="display:none"></div><div class="spinner" id="si_government_id_spin" style="display:none"></div><span class="material-symbols-outlined pend-icon" id="si_government_id_wait">pending</span><span class="material-symbols-outlined check-icon" id="si_government_id_ok" style="display:none">check_circle</span></div>
            <div class="scan-label">Government ID</div>
            <span class="text-xs text-slate-400 font-medium" id="sl_government_id">Waiting…</span>
        </div>

        <p class="text-xs text-slate-400 mt-5 text-center" id="scanHint">Please wait while we scan your documents…</p>
    </div>
</div>

<!-- ════════════════════════════════════════════
     REVIEW MODAL
════════════════════════════════════════════ -->
<div id="reviewModal">
    <div class="review-box">

        <div class="review-header">
            <span class="material-symbols-outlined text-primary" style="font-size:26px">fact_check</span>
            <div>
                <h2 class="text-xl font-black tracking-tight">Review your documents information.</h2>
                <p class="text-sm text-slate-500">Edit any information if needed, this will be later reviewed by our team.</p>
            </div>
        </div>

        <div class="review-body">

            <!-- Business Permit -->
            <div class="review-section">
                <div class="review-section-head">
                    <span class="material-symbols-outlined text-[16px]">storefront</span>
                    Business Permit
                </div>
                <div class="review-fields two">
                    <div class="rfield span2">
                        <label>Shop Name</label>
                        <input type="text" id="r_permit_shop_name">
                    </div>
                    <div class="rfield">
                        <label>Date Issued</label>
                        <input type="text" id="r_permit_issue_date" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="rfield">
                        <label>Expiry Date</label>
                        <input type="text" id="r_permit_expiry_date" placeholder="YYYY-MM-DD">
                    </div>
                </div>
            </div>

            <!-- BIR 2303 -->
            <div class="review-section">
                <div class="review-section-head">
                    <span class="material-symbols-outlined text-[16px]">receipt_long</span>
                    BIR 2303
                </div>
                <div class="review-fields two">
                    <div class="rfield">
                        <label>TIN Number</label>
                        <input type="text" id="r_bir_tin">
                    </div>
                    <div class="rfield">
                        <label>Branch Code</label>
                        <input type="text" id="r_bir_branch">
                    </div>
                    <div class="rfield span2">
                        <label>Name of Taxpayer</label>
                        <input type="text" id="r_bir_owner">
                    </div>
                    <div class="rfield">
                        <label>TIN Issuance Date</label>
                        <input type="text" id="r_bir_tin_date" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="rfield span2">
                        <label>Registering Address</label>
                        <input type="text" id="r_bir_address">
                    </div>
                </div>
            </div>

            <!-- Government ID -->
            <div class="review-section">
                <div class="review-section-head">
                    <span class="material-symbols-outlined text-[16px]">badge</span>
                    Government ID
                </div>
                <div class="review-fields two">
                    <div class="rfield span2">
                        <label>Full Name</label>
                        <input type="text" id="r_id_owner">
                    </div>
                    <div class="rfield">
                        <label>Birthday</label>
                        <input type="text" id="r_id_birthday" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="rfield">
                        <label>ID Number</label>
                        <input type="text" id="r_id_number">
                    </div>
                </div>
            </div>

            <!-- Validity notice -->
            <div id="planNotice" class="rounded-xl bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-800 font-medium mb-2" style="display:none"></div>

        </div><!-- /review-body -->

        <div class="review-footer">
            <button type="button" onclick="closeReview()"
                class="flex-1 py-3 rounded-xl font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-all text-sm">
                Go Back
            </button>
            <button type="button" id="confirmBtn" onclick="confirmAndSubmit()"
                class="flex-1 py-3 rounded-xl font-black text-white bg-primary hover:bg-blue-700 transition-all text-sm flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                Confirm & Continue to Payment
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════
     REJECTION MODAL
════════════════════════════════════════════ -->
<div id="rejectModal">
    <div class="reject-box">
        <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
            <span class="material-symbols-outlined text-red-500" style="font-size:32px">task_alt</span>
        </div>
        <h2 class="text-xl font-black text-slate-900 mb-4">Cannot Proceed</h2>
        <div id="rejectReason" class="text-sm text-slate-700 mb-6">
            <!-- Dynamic content will be inserted here -->
        </div>
        <div class="flex gap-3">
            <button onclick="returnToReview()"
                class="flex-1 py-3 rounded-xl font-bold text-primary bg-primary/10 hover:bg-primary/20 transition-all text-sm flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[18px]">edit</span>
                Edit Details
            </button>
            <button onclick="closeReject()"
                class="flex-1 py-3 rounded-xl font-bold text-white bg-primary hover:bg-blue-700 transition-all text-sm">
                Start Over
            </button>
        </div>
    </div>
</div>

<script>
const BILLING_CYCLE  = <?php echo json_encode($billingCycle ?: 'Monthly'); ?>;
const TENANT_ID      = <?php echo json_encode($tenantID); ?>;

let dummyData = {};   // { document_type: extracted_data }

/* ─── File selection feedback ─── */
document.querySelectorAll('input[type="file"]').forEach(inp => {
    inp.addEventListener('change', function () {
        const cardId = this.getAttribute('data-card');
        const card   = document.getElementById(cardId);
        const badge  = card.querySelector('.doc-badge');

        if (this.files && this.files.length) {
            card.classList.add('ready');
            badge.classList.remove('hidden');
            badge.style.color = '#1152d4';
            badge.innerHTML   = `<span class="material-symbols-outlined text-[14px]">attach_file</span>${this.files[0].name}`;
        } else {
            card.classList.remove('ready', 'success', 'error');
            badge.classList.add('hidden');
        }
    });
});

/* ─── SCAN FLOW ─── */
async function startScan() {
    const inputs = document.querySelectorAll('input[type="file"]');
    let allPresent = true;
    inputs.forEach(i => { if (!i.files || !i.files.length) allPresent = false; });

    if (!allPresent) {
        alert('Please upload all three documents before continuing.');
        return;
    }

    document.getElementById('scanOverlay').classList.add('active');
    dummyData = {};

    const docs = ['business_permit', 'bir_2303', 'government_id'];
    for (const docType of docs) {
        await scanDocument(docType);
    }

    // All done
    document.getElementById('scanHint').textContent = 'All documents scanned successfully!';
    await sleep(600);

    document.getElementById('scanOverlay').classList.remove('active');
    openReview();
}

function setScanState(docType, state, label) {
    const states = {
        dot:  document.getElementById(`si_${docType}_dot`),
        spin: document.getElementById(`si_${docType}_spin`),
        wait: document.getElementById(`si_${docType}_wait`),
        ok:   document.getElementById(`si_${docType}_ok`),
        lbl:  document.getElementById(`sl_${docType}`),
    };

    // Hide all icon elements first
    states.dot.style.display  = 'none';
    states.spin.style.display = 'none';
    states.wait.style.display = 'none';
    states.ok.style.display   = 'none';

    if (state === 'scanning') {
        states.spin.style.display = 'block';
        states.lbl.style.color    = '#1152d4';
    } else if (state === 'done') {
        states.ok.style.display   = 'block';
        states.lbl.style.color    = '#10b981';
    } else {
        states.wait.style.display = 'block';
        states.lbl.style.color    = '#94a3b8';
    }
    states.lbl.textContent = label;
}

async function scanDocument(docType) {
    const fileInput = document.querySelector(`[data-document-type="${docType}"]`);
    setScanState(docType, 'scanning', 'Scanning…');

    // Simulate scan delay (1.2 – 1.8 s)
    await sleep(1200 + Math.random() * 600);

    // Call PHP dummy extraction endpoint
    const formData = new FormData();
    formData.append('action', 'extract_document');
    formData.append('document_type', docType);
    formData.append('file', fileInput.files[0]);

    try {
        const res  = await fetch(window.location.href, { method: 'POST', body: formData });
        const json = await res.json();

        if (json.success) {
            dummyData[docType] = json.extracted_data;
            setScanState(docType, 'done', 'Scanned ✓');

            // Mark card
            const card = document.getElementById(`card_${docType}`);
            card.classList.remove('ready', 'error');
            card.classList.add('success');
        } else {
            throw new Error(json.error || 'Failed');
        }
    } catch (e) {
        setScanState(docType, 'error', 'Error');
        const card = document.getElementById(`card_${docType}`);
        card.classList.add('error');
    }
}

/* ─── REVIEW MODAL ─── */
function openReview() {
    // Populate Business Permit fields
    const bp = dummyData['business_permit'] || {};
    document.getElementById('r_permit_shop_name').value    = bp.shop_name   || '';
    document.getElementById('r_permit_issue_date').value   = bp.issue_date  || '';
    document.getElementById('r_permit_expiry_date').value  = bp.expiry_date || '';

    // Populate BIR fields
    const bir = dummyData['bir_2303'] || {};
    document.getElementById('r_bir_tin').value      = bir.tin_number        || '';
    document.getElementById('r_bir_branch').value   = bir.branch_code       || '';
    document.getElementById('r_bir_owner').value    = bir.owner_name        || '';
    document.getElementById('r_bir_tin_date').value = bir.tin_issuance_date || '';
    document.getElementById('r_bir_address').value  = bir.address           || '';

    // Populate Government ID fields
    const gid = dummyData['government_id'] || {};
    document.getElementById('r_id_owner').value    = gid.owner_name || '';
    document.getElementById('r_id_birthday').value = gid.birthday   || '';
    document.getElementById('r_id_number').value   = gid.id_number  || '';

    // Show plan notice with correct month calculation
    const notice  = document.getElementById('planNotice');
    const months  = BILLING_CYCLE === 'Annual' ? 12 : BILLING_CYCLE === 'Quarterly' ? 3 : 1;
    const req     = new Date();
    req.setMonth(req.getMonth() + months);
    notice.style.display = 'block';
    notice.textContent   = `Your ${BILLING_CYCLE} plan requires documents valid until at least ${req.toISOString().slice(0,10)}.`;

    document.getElementById('reviewModal').classList.add('active');
}

function closeReview() {
    document.getElementById('reviewModal').classList.remove('active');
}

/* ─── CONFIRM & SUBMIT ─── */
function confirmAndSubmit() {
    // Read edited values
    const shopName   = document.getElementById('r_permit_shop_name').value.trim();
    const issueDate  = document.getElementById('r_permit_issue_date').value.trim();
    const expiryDate = document.getElementById('r_permit_expiry_date').value.trim();
    const birTin     = document.getElementById('r_bir_tin').value.trim();
    const birBranch  = document.getElementById('r_bir_branch').value.trim();
    const birOwner   = document.getElementById('r_bir_owner').value.trim();
    const birTinDate = document.getElementById('r_bir_tin_date').value.trim();
    const birAddr    = document.getElementById('r_bir_address').value.trim();
    const idOwner    = document.getElementById('r_id_owner').value.trim();
    const idBday     = document.getElementById('r_id_birthday').value.trim();
    const idNum      = document.getElementById('r_id_number').value.trim();

    // Basic presence check
    if (!birOwner) { 
        alert('Owner name (BIR 2303) is required.'); 
        return; 
    }
    if (!expiryDate) { 
        alert('Business Permit expiry date is required.'); 
        return; 
    }

    // Validate expiry date format (must be YYYY-MM-DD)
    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(expiryDate)) {
        alert('Invalid expiry date format. Use YYYY-MM-DD (e.g., 2028-02-25)');
        return;
    }

    // Parse date safely using YYYY-MM-DD format
    const [year, month, day] = expiryDate.split('-');
    const expTs = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));

    // Get today's date without time component
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Check 1: Document has already expired
    if (expTs < today) {
        const daysAgo = Math.floor((today - expTs) / (1000 * 60 * 60 * 24));
        closeReview();
        showRejectExpired(expiryDate, daysAgo);
        return;
    }

    // Check 2: Document doesn't cover the full subscription period
    const months   = BILLING_CYCLE === 'Annual' ? 12 : BILLING_CYCLE === 'Quarterly' ? 3 : 1;
    const required = new Date(today);
    required.setMonth(required.getMonth() + months);

    if (expTs < required) {
        const requiredDateStr = required.toISOString().slice(0, 10);
        const shortBy = Math.ceil((required - expTs) / (1000 * 60 * 60 * 24));
        closeReview();
        showRejectInvalid(expiryDate, requiredDateStr, BILLING_CYCLE, months, shortBy);
        return;
    }

    // All validations passed – populate hidden fields and submit
    document.getElementById('h_owner_name').value        = birOwner;
    document.getElementById('h_expiry_date').value       = expiryDate;
    document.getElementById('h_permit_shop_name').value  = shopName;
    document.getElementById('h_permit_issue_date').value = issueDate;
    document.getElementById('h_permit_expiry_date').value= expiryDate;
    document.getElementById('h_bir_tin').value           = birTin;
    document.getElementById('h_bir_branch').value        = birBranch;
    document.getElementById('h_bir_owner').value         = birOwner;
    document.getElementById('h_bir_tin_date').value      = birTinDate;
    document.getElementById('h_bir_address').value       = birAddr;
    document.getElementById('h_id_owner').value          = idOwner;
    document.getElementById('h_id_birthday').value       = idBday;
    document.getElementById('h_id_number').value         = idNum;

    closeReview();
    document.getElementById('documentForm').submit();
}

/* ─── REJECTION MODALS ─── */
function showRejectExpired(expiredDate, daysAgo) {
    const rejectBox = document.getElementById('rejectModal');
    const reasonEl = document.getElementById('rejectReason');
    
    reasonEl.innerHTML = `
        <div class="space-y-3">
            <p class="font-semibold">Business Permit has expired</p>
            <div class="bg-red-100 border border-red-300 rounded p-3 text-sm">
                <p><strong>Expiry Date:</strong> ${escapeHtml(expiredDate)}</p>
                <p><strong>Expired:</strong> ${daysAgo} day${daysAgo !== 1 ? 's' : ''} ago</p>
            </div>
            <p class="text-sm">You cannot proceed with an expired Business Permit. Please upload a document with a valid, current expiration date.</p>
        </div>
    `;
    
    rejectBox.classList.add('active');
}

function showRejectInvalid(expiredDate, requiredDate, billingCycle, months, shortBy) {
    const rejectBox = document.getElementById('rejectModal');
    const reasonEl = document.getElementById('rejectReason');
    
    const cycleLabel = billingCycle === 'Annual' ? 'annual' : 
                       billingCycle === 'Quarterly' ? 'quarterly' : 'monthly';
    
    reasonEl.innerHTML = `
        <div class="space-y-3">
            <p class="font-semibold">Document validity does not cover your subscription period</p>
            <div class="bg-amber-100 border border-amber-300 rounded p-3 text-sm space-y-2">
                <div>
                    <p class="font-semibold text-amber-900">Your Subscription Plan:</p>
                    <p>${billingCycle} (${months} month${months !== 1 ? 's' : ''})</p>
                </div>
                <div>
                    <p class="font-semibold text-amber-900">Business Permit Expiry:</p>
                    <p>${escapeHtml(expiredDate)}</p>
                </div>
                <div>
                    <p class="font-semibold text-amber-900">Required Validity Until:</p>
                    <p>${escapeHtml(requiredDate)}</p>
                </div>
                <div class="bg-red-100 border border-red-300 rounded p-2">
                    <p class="text-red-900 font-semibold">Shortfall: ${shortBy} day${shortBy !== 1 ? 's' : ''}</p>
                </div>
            </div>
            <p class="text-sm">Your Business Permit must remain valid for the entire ${billingCycle.toLowerCase()} billing period. Please upload a document that expires on or after <strong>${escapeHtml(requiredDate)}</strong>.</p>
            <p class="text-xs text-slate-600 bg-slate-50 p-2 rounded">💡 Tip: You can go back and edit the expiry date if you believe it's incorrect.</p>
        </div>
    `;
    
    rejectBox.classList.add('active');
}

function returnToReview() {
    document.getElementById('rejectModal').classList.remove('active');
    document.getElementById('reviewModal').classList.add('active');
}

function closeReject() {
    document.getElementById('rejectModal').classList.remove('active');
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

/* ─── Utility ─── */
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
</script>
</body>
</html>