<?php
session_start();

function buildTenantLoginUrl($loginSlug)
{
	$loginSlug = trim((string)$loginSlug);
	if ($loginSlug === '') {
		return '../tenant/tenantlogin.php';
	}

	$baseDomain = trim((string)(getenv('TENANT_BASE_DOMAIN') ?: ''));
	if ($baseDomain !== '') {
		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		return $scheme . '://' . $loginSlug . '.' . $baseDomain . '/tenant/tenantlogin.php';
	}

	return '../tenant/tenantlogin.php?shop=' . urlencode($loginSlug);
}

function buildSafeInAppPath($candidate)
{
	$candidate = trim((string)$candidate);
	if ($candidate === '') {
		return '';
	}

	$parts = parse_url($candidate);
	if ($parts === false) {
		return '';
	}

	if (isset($parts['scheme']) || isset($parts['host'])) {
		return '';
	}

	$path = isset($parts['path']) ? (string)$parts['path'] : '';
	if ($path === '') {
		return '';
	}

	if (strpos($path, '/RapidRepair/') === 0) {
		$path = substr($path, strlen('/RapidRepair/'));
	} elseif (strpos($path, '/RapidRepair') === 0) {
		$path = substr($path, strlen('/RapidRepair'));
	}

	$path = ltrim($path, '/');
	if ($path === '') {
		return '';
	}

	$segments = explode('/', $path);
	foreach ($segments as $segment) {
		if ($segment === '' || $segment === '.' || $segment === '..') {
			return '';
		}
	}

	if (strtolower($path) === 'logout/logout.php') {
		return '';
	}

	$query = isset($parts['query']) ? (string)$parts['query'] : '';
	$fragment = isset($parts['fragment']) ? (string)$parts['fragment'] : '';

	$result = '../' . $path;
	if ($query !== '') {
		$result .= '?' . $query;
	}
	if ($fragment !== '') {
		$result .= '#' . $fragment;
	}

	return $result;
}

function buildAuthenticatedHomeUrl()
{
	if (isset($_SESSION['superadmin_id'])) {
		return '../superadmin/subscriptionmanage.php';
	}

	if (isset($_SESSION['shop_id']) || isset($_SESSION['tenant_id']) || isset($_SESSION['tenantID'])) {
		return '../tenant/dashboardadmin.php';
	}

	if (isset($_SESSION['client_id']) || isset($_SESSION['email']) || isset($_SESSION['user_id'])) {
		return '../clientapplication/clientlanding.php';
	}

	return '../index.php';
}

$requestRedirectSource = '';
if (isset($_POST['redirect'])) {
	$requestRedirectSource = (string)$_POST['redirect'];
} elseif (isset($_GET['redirect'])) {
	$requestRedirectSource = (string)$_GET['redirect'];
}

$requestedRedirect = basename($requestRedirectSource);
$allowedRedirects = [
	'tenantlogin.php' => '../tenant/tenantlogin.php',
	'superaddlogin.php' => '../superadmin/superaddlogin.php',
	'clientlogin.php' => '../clientapplication/clientlogin.php',
	'clientlanding.php' => '../clientapplication/clientlanding.php',
	'index.php' => '../index.php'
];

$requestedShopSlug = '';
if (isset($_POST['shop'])) {
	$requestedShopSlug = trim((string)$_POST['shop']);
} elseif (isset($_GET['shop'])) {
	$requestedShopSlug = trim((string)$_GET['shop']);
}

$tenantLoginSlug = $requestedShopSlug !== '' ? $requestedShopSlug : (isset($_SESSION['login_slug']) ? (string)$_SESSION['login_slug'] : '');
$logoutRedirect = buildTenantLoginUrl($tenantLoginSlug);

if (isset($_SESSION['superadmin_id'])) {
	$logoutRedirect = '../superadmin/superaddlogin.php';
} elseif (isset($_SESSION['client_id']) || isset($_SESSION['email']) || isset($_SESSION['user_id'])) {
	$logoutRedirect = '../clientapplication/clientlanding.php';
}

$logoutRedirectTo = isset($allowedRedirects[$requestedRedirect]) ? $allowedRedirects[$requestedRedirect] : $logoutRedirect;

$cancelRedirectTo = '';
if (isset($_POST['return_to'])) {
	$cancelRedirectTo = buildSafeInAppPath($_POST['return_to']);
}

if ($cancelRedirectTo === '' && isset($_GET['return_to'])) {
	$cancelRedirectTo = buildSafeInAppPath($_GET['return_to']);
}

if ($cancelRedirectTo === '' && isset($_SERVER['HTTP_REFERER'])) {
	$cancelRedirectTo = buildSafeInAppPath($_SERVER['HTTP_REFERER']);
}

if ($cancelRedirectTo === '') {
	$cancelRedirectTo = buildAuthenticatedHomeUrl();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
	$destroySession = isset($_POST['destroy_session']) ? (int)$_POST['destroy_session'] : 0;

	// Handle back button session destruction (AJAX request)
	if ($destroySession === 1) {
		require_once __DIR__ . '/../db.php';

		$tenantID = isset($_SESSION['tenantID']) ? (int)$_SESSION['tenantID'] : null;
		$user_id = $tenantID;
		$user_name = isset($_SESSION['shopName']) ? $_SESSION['shopName'] : '';
		$user_role = 'admin';
		$actionLog = 'LOGOUT';
		$entity_type = 'tenant';
		$entity_id = $tenantID;
		$details = 'Session destroyed - back button detected';
		$ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

		if ($tenantID) {
			$stmt = $conn->prepare("INSERT INTO system_logs (tenantID, user_id, user_name, user_role, action, entity_type, entity_id, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
			$stmt->bind_param('iissssisss', $tenantID, $user_id, $user_name, $user_role, $actionLog, $entity_type, $entity_id, $details, $ip_address, $user_agent);
			$stmt->execute();
			$stmt->close();
		}

		$_SESSION = [];

		if (ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				time() - 42000,
				$params['path'],
				$params['domain'],
				$params['secure'],
				$params['httponly']
			);
		}

		session_destroy();
		
		header('Content-Type: application/json');
		echo json_encode(['status' => 'success', 'message' => 'Session destroyed']);
		exit;
	}

	if ($action === 'cancel') {
		header('Location: ' . $cancelRedirectTo);
		exit;
	}

	// First click from admin pages: open logout confirmation template.
	if ($action === 'confirm') {
		$query = [];
		if ($requestedRedirect !== '') {
			$query['redirect'] = $requestedRedirect;
		}
		if ($requestedShopSlug !== '') {
			$query['shop'] = $requestedShopSlug;
		}
		if ($cancelRedirectTo !== '') {
			$query['return_to'] = $cancelRedirectTo;
		}

		$target = 'logout.php';
		if (!empty($query)) {
			$target .= '?' . http_build_query($query);
		}

		header('Location: ' . $target);
		exit;
	}

	if ($action === 'do_logout') {
		require_once __DIR__ . '/../db.php';

		$tenantID = isset($_SESSION['tenantID']) ? (int)$_SESSION['tenantID'] : null;
		$user_id = $tenantID;
		$user_name = isset($_SESSION['shopName']) ? $_SESSION['shopName'] : '';
		$user_role = 'admin';
		$actionLog = 'LOGOUT';
		$entity_type = 'tenant';
		$entity_id = $tenantID;
		$details = 'Tenant logged out';
		$ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

		if ($tenantID) {
			$stmt = $conn->prepare("INSERT INTO system_logs (tenantID, user_id, user_name, user_role, action, entity_type, entity_id, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
			$stmt->bind_param('iissssisss', $tenantID, $user_id, $user_name, $user_role, $actionLog, $entity_type, $entity_id, $details, $ip_address, $user_agent);
			$stmt->execute();
			$stmt->close();
		}

		$_SESSION = [];

		if (ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				time() - 42000,
				$params['path'],
				$params['domain'],
				$params['secure'],
				$params['httponly']
			);
		}

		session_destroy();
		header('Location: ' . $logoutRedirectTo);
		exit;
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Confirm Logout</title>
	<style>
		:root {
			--bg: #f4f6fb;
			--card: #ffffff;
			--text: #1c2430;
			--muted: #6b7686;
			--danger: #cf2f2f;
			--danger-hover: #b12424;
			--neutral: #e7ebf2;
			--neutral-hover: #d8deea;
		}

		* {
			box-sizing: border-box;
		}

		body {
			margin: 0;
			font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
			background: radial-gradient(circle at top, #ffffff 0%, var(--bg) 60%);
			min-height: 100vh;
			display: grid;
			place-items: center;
			padding: 20px;
		}

		.modal {
			width: 100%;
			max-width: 440px;
			background: var(--card);
			border-radius: 14px;
			box-shadow: 0 18px 38px rgba(20, 31, 52, 0.15);
			padding: 24px;
			animation: fadeIn 0.2s ease-out;
		}

		h1 {
			margin: 0 0 8px;
			font-size: 1.28rem;
			color: var(--text);
		}

		p {
			margin: 0;
			line-height: 1.5;
			color: var(--muted);
		}

		.actions {
			display: flex;
			justify-content: flex-end;
			gap: 10px;
			margin-top: 20px;
		}

		button {
			border: 0;
			border-radius: 9px;
			padding: 10px 16px;
			font-size: 0.95rem;
			font-weight: 600;
			cursor: pointer;
		}

		button[name="action"][value="do_logout"] {
			background: var(--danger);
			color: #fff;
		}

		button[name="action"][value="do_logout"]:hover {
			background: var(--danger-hover);
		}

		button[name="action"][value="cancel"] {
			background: var(--neutral);
			color: var(--text);
		}

		button[name="action"][value="cancel"]:hover {
			background: var(--neutral-hover);
		}

		@keyframes fadeIn {
			from {
				opacity: 0;
				transform: translateY(8px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}
	</style>
</head>
<body>
	<div class="modal" role="dialog" aria-modal="true" aria-labelledby="logout-title">
		<h1 id="logout-title">Are you sure you want to logout?</h1>
		<p>You will need to sign in again to continue using your account.</p>
		<form method="post">
			<input type="hidden" name="redirect" value="<?php echo htmlspecialchars($requestedRedirect, ENT_QUOTES, 'UTF-8'); ?>">
			<input type="hidden" name="return_to" value="<?php echo htmlspecialchars($cancelRedirectTo, ENT_QUOTES, 'UTF-8'); ?>">
			<div class="actions">
				<button type="submit" name="action" value="cancel">Cancel</button>
				<button type="submit" name="action" value="do_logout">Yes, logout</button>
			</div>
		</form>
	</div>
</body>
</html>