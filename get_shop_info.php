<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

function respond($statusCode, array $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeLogoUrl($logoPath)
{
    $logoPath = trim((string) $logoPath);

    if ($logoPath === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $logoPath)) {
        return $logoPath;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';

    if ($host === '') {
        return $logoPath;
    }

    return $scheme . '://' . $host . '/' . ltrim($logoPath, '/');
}

$tenantID = isset($_GET['tenantID']) ? (int) $_GET['tenantID'] : 0;

if ($tenantID <= 0) {
    respond(400, [
        'success' => false,
        'message' => 'Missing tenantID.',
    ]);
}

$stmt = $conn->prepare("
    SELECT 
        o.tenantID,
        o.shopName,
        o.shopAddress,
        o.contactNumber,
        tc.shop_name AS custom_shop_name,
        tc.shop_address AS custom_shop_address,
        tc.logo_path
    FROM owners o
    LEFT JOIN tenant_customizations tc 
        ON tc.tenantID = o.tenantID
    WHERE o.tenantID = ?
    LIMIT 1
");

if (!$stmt) {
    respond(500, [
        'success' => false,
        'message' => 'Failed to prepare query.',
    ]);
}

$stmt->bind_param('i', $tenantID);
$stmt->execute();
$result = $stmt->get_result();
$shop = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$shop) {
    respond(404, [
        'success' => false,
        'message' => 'Shop not found.',
    ]);
}

$logoPath = $shop['logo_path'] ?? '';

respond(200, [
    'success' => true,
    'tenantID' => (int) $shop['tenantID'],

    'shopName' => $shop['custom_shop_name'] ?: ($shop['shopName'] ?? ''),
    'shopAddress' => $shop['custom_shop_address'] ?: ($shop['shopAddress'] ?? ''),
    'contactNumber' => $shop['contactNumber'] ?? '',

    'logo_path' => $logoPath,
    'logoUrl' => normalizeLogoUrl($logoPath),
]);
?>