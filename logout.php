<?php
session_start();

function buildTenantLoginUrl($loginSlug)
{
	$loginSlug = trim((string)$loginSlug);
	if ($loginSlug === '') {
		return 'tenantlogin.php';
	}

	$baseDomain = trim((string)(getenv('TENANT_BASE_DOMAIN') ?: ''));
	if ($baseDomain !== '') {
		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		return $scheme . '://' . $loginSlug . '.' . $baseDomain . '/tenantlogin.php';
	}

	return 'tenantlogin.php?shop=' . urlencode($loginSlug);
}

$requestRedirectSource = '';
if (isset($_POST['redirect'])) {
	$requestRedirectSource = (string)$_POST['redirect'];
} elseif (isset($_GET['redirect'])) {
	$requestRedirectSource = (string)$_GET['redirect'];
}

$requestedRedirect = basename($requestRedirectSource);
$allowedRedirects = ['login.php', 'tenantlogin.php', 'superaddlogin.php', 'userlogin.php'];

$tenantLoginSlug = isset($_SESSION['login_slug']) ? (string)$_SESSION['login_slug'] : '';
$defaultRedirect = buildTenantLoginUrl($tenantLoginSlug);
if (isset($_SESSION['superadmin_id'])) {
	$defaultRedirect = 'superaddlogin.php';
} elseif (isset($_SESSION['email']) || isset($_SESSION['user_id'])) {
	$defaultRedirect = 'login.php';
}

$redirectTo = in_array($requestedRedirect, $allowedRedirects, true) ? $requestedRedirect : $defaultRedirect;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = isset($_POST['action']) ? (string)$_POST['action'] : '';

	if ($action === 'cancel') {
		header('Location: ' . $redirectTo);
		exit;
	}

	if ($action === 'confirm') {
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
		header('Location: ' . $redirectTo);
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

		button[name="action"][value="confirm"] {
			background: var(--danger);
			color: #fff;
		}

		button[name="action"][value="confirm"]:hover {
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
			<div class="actions">
				<button type="submit" name="action" value="cancel">Cancel</button>
				<button type="submit" name="action" value="confirm">Yes, logout</button>
			</div>
		</form>
	</div>
</body>
</html>
