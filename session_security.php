<?php
/**
 * Session Security Handler
 * Manages session security including back button handling and cache control
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Set headers to prevent caching of authenticated pages
 * This ensures the browser won't restore the page from cache when using back button
 */
function setSecurityHeaders() {
    // Prevent all caching
    header("Cache-Control: no-store, no-cache, no-revalidate, must-revalidate, max-age=0, private");
    header("Pragma: no-cache");
    header("Expires: 0");
    header("Surrogate-Control: no-store");
    
    // Additional security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

/**
 * Check if user is authenticated
 * Returns true if session is valid, false otherwise
 */
function isAuthenticated() {
    return isset($_SESSION['tenantID']) && !empty($_SESSION['tenantID']);
}

/**
 * Destroy session and redirect to login
 */
function destroySessionAndLogout($loginPage = 'tenantlogin.php') {
    if (isset($_SESSION['tenantID'])) {
        $tenantID = $_SESSION['tenantID'];
        // Optional: Log the logout
        // You can add logging here if needed
    }
    
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    // Redirect to login page
    header("Location: " . $loginPage);
    exit;
}

/**
 * Get JavaScript snippet for back button detection
 * Runs only on dashboardadmin.php
 */
function getBackButtonDetectionScript() {
    return <<<'JS'
<script>
(function() {
    // Run back button detection only on dashboardadmin.php
    const currentPage = window.location.pathname;
    if (!currentPage.includes('dashboardadmin.php')) {
        return;
    }
    
    // Extract login_slug from URL or document
    function getLoginSlug() {
        // Try to get from URL query parameter
        const params = new URLSearchParams(window.location.search);
        if (params.has('shop')) {
            return params.get('shop');
        }
        
        // Try to get from data attribute
        const slugAttr = document.documentElement.getAttribute('data-login-slug');
        if (slugAttr) {
            return slugAttr;
        }
        
        // Default empty
        return '';
    }
    
    let loginSlug = getLoginSlug();
    
    let hasDestroyed = false;

    // Detect when page is shown (restoration from back/forward cache)
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            destroySessionImmediately();
        }
    });

    // Force a history state and catch browser back button while on dashboard.
    // When back is pressed, destroy session and redirect to login.
    if (window.history && window.history.pushState) {
        window.history.pushState({ dashboard_guard: true }, '', window.location.href);
        window.addEventListener('popstate', function() {
            destroySessionImmediately();
        });
    }
    
    // Function to destroy session immediately
    function destroySessionImmediately() {
        if (hasDestroyed) {
            return;
        }
        hasDestroyed = true;

        // Clear all stored data
        sessionStorage.clear();
        localStorage.clear();
        
        // Build login redirect URL with shop slug
        let loginUrl = '/RapidRepair/tenant/tenantlogin.php';
        if (loginSlug) {
            loginUrl += '?shop=' + encodeURIComponent(loginSlug);
        }
        
        // Make synchronous request to destroy session server-side
        let xhr = new XMLHttpRequest();
        xhr.open('POST', '/RapidRepair/logout/logout.php', false);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        try {
            xhr.send('destroy_session=1');
            // Redirect immediately after session is destroyed
            window.location.href = loginUrl;
        } catch (error) {
            // If request fails, still redirect for safety
            window.location.href = loginUrl;
        }
    }
})();
</script>
JS;
}

// Set security headers for all sessions
setSecurityHeaders();
?>
