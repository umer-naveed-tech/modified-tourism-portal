<?php
/**
 * csrf.php — lightweight CSRF protection helper.
 *
 * Usage in a form page:
 *   <?php echo csrf_field(); ?>   // inside the <form> ... </form>
 *
 * Usage in the handler that processes the POST:
 *   csrf_verify();   // put this as the very first line inside the
 *                    // `if ($_SERVER['REQUEST_METHOD'] == 'POST')` block
 *
 * For AJAX (fetch/XHR) requests, read the token from a hidden field or a
 * data attribute in JS and send it as a normal POST field named csrf_token,
 * then call csrf_verify() the same way in the PHP endpoint.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Returns the current token, generating one if none exists yet.
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Convenience helper to print a ready-to-use hidden <input> for forms.
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// Call at the top of any POST handler. Stops execution with a 403 if the
// token is missing, expired, or doesn't match.
function csrf_verify() {
    $submitted = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !is_string($submitted) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die('Security check failed (invalid or expired form). Please go back, refresh the page, and try again.');
    }
}

// Same idea but returns true/false instead of stopping execution — useful
// for AJAX endpoints that need to respond with JSON instead of a die().
function csrf_valid() {
    $submitted = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && is_string($submitted) && hash_equals($_SESSION['csrf_token'], $submitted);
}