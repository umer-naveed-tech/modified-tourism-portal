<?php
// tamara_webhook.php
//
// Tamara calls this URL directly (server-to-server, no browser
// involved) whenever an order's status changes. This is the ONLY
// place a booking is actually marked paid/completed for a Tamara
// payment -- the customer-facing redirect (tamara_return.php) never
// does this itself, since redirects can be interrupted or spoofed.
//
// Every incoming request is verified against TAMARA_NOTIFICATION_TOKEN
// before anything in the database changes -- an unverified request is
// rejected outright, so nobody can fake a "payment approved" call.

require_once 'config.php';

function verifyTamaraToken($jwt, $secret) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return false;
    [$header_b64, $payload_b64, $sig_b64] = $parts;

    $expected_sig = hash_hmac('sha256', $header_b64 . '.' . $payload_b64, $secret, true);
    $expected_sig_b64 = rtrim(strtr(base64_encode($expected_sig), '+/', '-_'), '=');

    if (!hash_equals($expected_sig_b64, $sig_b64)) return false;

    $payload_json = base64_decode(strtr($payload_b64, '-_', '+/'));
    return json_decode($payload_json, true);
}

// Tamara sends the JWT in the Authorization header as "Bearer <token>".
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$jwt = trim(str_ireplace('Bearer', '', $auth_header));

$verified_claims = $jwt ? verifyTamaraToken($jwt, TAMARA_NOTIFICATION_TOKEN) : false;

if (!$verified_claims) {
    error_log('Tamara webhook: signature verification failed or missing token.');
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or missing notification token']);
    exit();
}

$raw_body = file_get_contents('php://input');
$payload = json_decode($raw_body, true);

if (!$payload || empty($payload['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Malformed payload']);
    exit();
}

$tamara_order_id = $payload['order_id'];
$order_reference_id = $payload['order_reference_id'] ?? null; // our booking_no
$event_type = $payload['event_type'] ?? ($payload['status'] ?? '');

// Find the matching booking -- prefer the Tamara order_id we stored
// when the checkout session was created, fall back to booking_no.
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE tamara_order_id = ? LIMIT 1");
$stmt->execute([$tamara_order_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking && $order_reference_id) {
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_no = ? LIMIT 1");
    $stmt->execute([$order_reference_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$booking) {
    error_log('Tamara webhook: no matching booking for order_id ' . $tamara_order_id);
    http_response_code(404);
    echo json_encode(['error' => 'Booking not found']);
    exit();
}

// Only "approved" (however Tamara's payload names it) triggers
// confirmation -- other events (declined, expired, canceled) are
// logged but never mark a booking as paid.
$is_approved = (stripos($event_type, 'approved') !== false);

if ($is_approved) {
    // Required step per Tamara's integration rules: acknowledge
    // receipt by calling their Authorise Order API before treating
    // the order as final.
    $ch = curl_init(rtrim(TAMARA_API_URL, '/') . '/orders/' . $tamara_order_id . '/authorise');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '{}',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . TAMARA_API_TOKEN,
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $auth_response = curl_exec($ch);
    $auth_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($auth_http_code >= 200 && $auth_http_code < 300) {
        // Mirrors exactly what verify_payment.php does for a manually
        // verified payment -- same end state, so every other part of
        // the site (agent dashboard, customer status labels) treats a
        // Tamara payment identically to an agent-approved one.
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'completed', payment_status = 'verified' WHERE id = ?");
        $stmt->execute([$booking['id']]);

        // Record it in the payments table too, so the agent's payment
        // history shows Tamara payments alongside manual ones.
        $stmt = $pdo->prepare("SELECT id FROM payments WHERE booking_id = ? AND payment_reference = ?");
        $stmt->execute([$booking['id'], 'TAMARA-' . $tamara_order_id]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO payments (booking_id, payment_reference, payer_name, status, verified_at)
                VALUES (?, ?, ?, 'verified', NOW())
            ");
            $stmt->execute([$booking['id'], 'TAMARA-' . $tamara_order_id, $booking['customer_name'] ?? 'Tamara Customer']);
        }
    } else {
        error_log('Tamara order authorisation failed for order_id ' . $tamara_order_id . ' | HTTP ' . $auth_http_code . ' | ' . $auth_response);
    }
} else {
    error_log('Tamara webhook: non-approved event "' . $event_type . '" for order_id ' . $tamara_order_id . ' -- no booking change made.');
}

http_response_code(200);
echo json_encode(['success' => true]);