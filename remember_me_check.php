<?php
/**
 * Auto-login via "Remember Me" cookie.
 *
 * Include this AFTER session_start() and AFTER $pdo is available
 * (e.g. require it near the top of config.php, right after the session
 * and PDO setup, or at the top of every protected page before you check
 * $_SESSION['user_id']).
 *
 * It only runs when there is no active session yet, so it won't interfere
 * with normal logged-in requests.
 */

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {

    $parts = explode(':', $_COOKIE['remember_me']);

    if (count($parts) === 2) {
        [$selector, $validator] = $parts;

        $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_selector = ? AND remember_expires > NOW()");
        $stmt->execute([$selector]);
        $user = $stmt->fetch();

        if ($user && hash_equals($user['remember_validator_hash'], hash('sha256', $validator))) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];
        } else {
            // Invalid or expired token: clear the bad cookie
            setcookie('remember_me', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}