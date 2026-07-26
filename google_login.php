<?php
require_once 'config.php';

// Random "state" value — Google sends it back unchanged. We verify it in
// google_callback.php to make sure the callback really came from a login
// flow we started (prevents CSRF on the OAuth flow itself).
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'prompt'        => 'select_account',
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit();