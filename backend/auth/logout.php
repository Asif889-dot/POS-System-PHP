<?php
/**
 * Logout handler
 * Clears session, expires session cookie, redirects to login.
 *
 * Accessible via GET (link click) — safe because logging out has
 * no destructive side-effects that CSRF could exploit.
 */

// Ensure session is available before we start tearing it down
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = [];

// Delete the session cookie from the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,           // 12 hours in the past
            'path'     => $params['path']     ?? '/',
            'domain'   => $params['domain']   ?? '',
            'secure'   => $params['secure']   ?? false,
            'httponly' => $params['httponly'] ?? true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

// Destroy the session on the server
session_destroy();

// Optional: start a fresh anonymous session for flash messages
// (uncomment the two lines below if you want to show "You have been
// logged out." on the login page after redirect)
// session_start();
// $_SESSION['info'] = 'You have been logged out.';

// Redirect to the login page
header('Location: ../../index.php');
exit;