<?php
/**
 * auth_helpers.php
 * Shared functions used by signup.php, login.php, and google_callback.php.
 * require_once this AFTER config.php (needs $pdo).
 */

// --------------------------------------------------------------------------
// Validation
// --------------------------------------------------------------------------

function validate_name($name) {
    $name = trim($name);
    if ($name === '') return 'Full name is required.';
    if (mb_strlen($name) > 100) return 'Full name is too long.';
    return null;
}

function validate_email_address($email) {
    if (trim($email) === '') return 'Email is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Please enter a valid email address.';
    return null;
}

function validate_phone_optional($phone) {
    $phone = trim($phone);
    if ($phone === '') return null; // optional field
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) return 'Please enter a valid phone number.';
    return null;
}

/**
 * Password rule: 8-20 chars, at least one uppercase, one lowercase, one digit.
 */
function validate_password_strength($password) {
    if (strlen($password) < 8 || strlen($password) > 20) {
        return 'Password must be between 8 and 20 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) return 'Password must include at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password)) return 'Password must include at least one lowercase letter.';
    if (!preg_match('/[0-9]/', $password)) return 'Password must include at least one number.';
    return null;
}

// --------------------------------------------------------------------------
// Session login (used by password login, remember-me auto-login, and Google login)
// --------------------------------------------------------------------------

function log_user_into_session(array $user) {
    session_regenerate_id(true); // prevent session fixation on every login
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
}

function redirect_after_login(array $user) {
    if (!empty($_SESSION['redirect_after_login'])) {
        $target = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $target);
        exit();
    }
    if ($user['role'] === 'agent') {
        header('Location: agent_dashboard.php');
    } else {
        header('Location: visitor_dashboard.php');
    }
    exit();
}

// --------------------------------------------------------------------------
// Remember Me (selector/validator token pattern)
// --------------------------------------------------------------------------

const REMEMBER_COOKIE_NAME = 'remember_me';
const REMEMBER_TTL_DAYS = 30;

/**
 * Call after a successful password (or Google) login when "remember me" was checked.
 * Matches remember_me_check.php's schema: remember_selector, remember_validator_hash,
 * remember_expires stored directly on the users table (not a separate table).
 */
function issue_remember_me(PDO $pdo, $user_id) {
    $selector  = bin2hex(random_bytes(9));   // 18 hex chars, safe to store/index
    $validator = bin2hex(random_bytes(32));  // secret half, never stored raw
    $hashed    = hash('sha256', $validator);
    $expires   = date('Y-m-d H:i:s', time() + REMEMBER_TTL_DAYS * 86400);

    $stmt = $pdo->prepare(
        "UPDATE users SET remember_selector = ?, remember_validator_hash = ?, remember_expires = ? WHERE id = ?"
    );
    $stmt->execute([$selector, $hashed, $expires, $user_id]);

    set_remember_cookie($selector, $validator);
}

function set_remember_cookie($selector, $validator) {
    setcookie(
        REMEMBER_COOKIE_NAME,
        $selector . ':' . $validator,
        [
            'expires'  => time() + REMEMBER_TTL_DAYS * 86400,
            'path'     => '/',
            'secure'   => true,     // only sent over HTTPS
            'httponly' => true,     // not readable by JS — blocks XSS token theft
            'samesite' => 'Lax',
        ]
    );
}

function clear_remember_cookie() {
    setcookie(REMEMBER_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// NOTE: The actual auto-login check lives in your own remember_me_check.php
// (included by config.php) — that's the single source of truth for reading
// the cookie back on each request, so it isn't duplicated here.

/**
 * Call this from logout.php.
 */
function forget_remember_me(PDO $pdo, $user_id = null) {
    if ($user_id) {
        $pdo->prepare(
            "UPDATE users SET remember_selector = NULL, remember_validator_hash = NULL, remember_expires = NULL WHERE id = ?"
        )->execute([$user_id]);
    }
    clear_remember_cookie();
}