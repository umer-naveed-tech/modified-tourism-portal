<?php
require_once 'config.php';
require_once 'auth_helpers.php';

// ---- Validate the state param first (blocks CSRF against the OAuth flow) ----
if (empty($_GET['state']) || empty($_SESSION['google_oauth_state'])
    || !hash_equals($_SESSION['google_oauth_state'], $_GET['state'])) {
    unset($_SESSION['google_oauth_state']);
    header('Location: login.php?google_error=1');
    exit();
}
unset($_SESSION['google_oauth_state']);

if (empty($_GET['code'])) {
    header('Location: login.php?google_error=1');
    exit();
}

// ---- Exchange the authorization code for an access token ----
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code'          => $_GET['code'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]),
]);
$token_response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error || !$token_response) {
    header('Location: login.php?google_error=1');
    exit();
}
$token_data = json_decode($token_response, true);
if (empty($token_data['access_token'])) {
    header('Location: login.php?google_error=1');
    exit();
}

// ---- Fetch the user's Google profile ----
$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token_data['access_token']],
]);
$profile_response = curl_exec($ch);
curl_close($ch);

$profile = json_decode($profile_response, true);

// Only trust the email if Google itself verified it.
if (empty($profile['email']) || empty($profile['email_verified'])) {
    header('Location: login.php?google_error=1');
    exit();
}

$email = $profile['email'];
$name = $profile['name'] ?? explode('@', $email)[0];

// ---- Find existing account, or create one (matched by email, no schema change) ----
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Random, unusable password hash — this account can only ever be
    // accessed via Google sign-in, not via the password login form.
    $unusable_password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, '', 'visitor')"
    );
    $stmt->execute([$name, $email, $unusable_password]);
    $user = [
        'id' => $pdo->lastInsertId(),
        'name' => $name,
        'email' => $email,
        'role' => 'visitor',
    ];
}

log_user_into_session($user);
redirect_after_login($user);