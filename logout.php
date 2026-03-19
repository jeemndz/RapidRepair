<?php
session_start();

$requestedRedirect = isset($_GET['redirect']) ? basename((string)$_GET['redirect']) : '';
$allowedRedirects = ['login.php', 'tenantlogin.php', 'superaddlogin.php', 'userlogin.php'];

$defaultRedirect = 'tenantlogin.php';
if (isset($_SESSION['superadmin_id'])) {
	$defaultRedirect = 'superaddlogin.php';
} elseif (isset($_SESSION['email']) || isset($_SESSION['user_id'])) {
	$defaultRedirect = 'login.php';
}

$redirectTo = in_array($requestedRedirect, $allowedRedirects, true) ? $requestedRedirect : $defaultRedirect;

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
header("Location: " . $redirectTo);
exit;
?>
