<?php
// tamara_checkout.php
//
// Triggered by the new "Pay with Tamara" button on booking_payment.php
// (additive only -- the existing manual bank-transfer flow is
// completely untouched). Creates a Checkout Session with Tamara and
// redirects the customer to Tamara's hosted checkout page.

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$booking_id = (int)($_GET['booking_id'] ?? 0);
if (!$booking_id) {
    header('Location: dashboard.php');
    exit();
}

$stmt = $pdo->prepare("SELECT b.*, u.email AS account_email FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ? AND b.user_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: dashboard.php');
    exit();
}

// Only block payment for a booking that's already been paid or
// cancelled -- matches booking_payment.php's own (lenient) access
// rule, so anything reachable there is reachable here too.
if (in_array($booking['status'], ['completed', 'cancelled'], true)) {
    header('Location: booking_payment.php?booking_id=' . $booking_id);
    exit();
}

// Split the customer's full name into first/last -- Tamara requires both.
$name_parts = preg_split('/\s+/', trim($booking['customer_name'] ?: 'Guest'), 2);
$first_name = $name_parts[0];
$last_name = $name_parts[1] ?? $name_parts[0];

$site_base_url = 'https://' . $_SERVER['HTTP_HOST'];

$payload = [
    'total_amount' => [
        'amount' => number_format((float)$booking['total_amount'], 2, '.', ''),
        'currency' => 'SAR',
    ],
    'shipping_amount' => ['amount' => '0.00', 'currency' => 'SAR'],
    'tax_amount' => ['amount' => '0.00', 'currency' => 'SAR'],
    'order_reference_id' => $booking['booking_no'],
    'order_number' => $booking['booking_no'],
    'items' => [[
        'reference_id' => (string)$booking['id'],
        'type' => 'Digital',
        'name' => ucfirst($booking['service_type']) . ' Booking - ' . $booking['booking_no'],
        'sku' => $booking['service_type'] . '-' . $booking['id'],
        'quantity' => 1,
        'unit_price' => [
            'amount' => number_format((float)$booking['total_amount'], 2, '.', ''),
            'currency' => 'SAR',
        ],
        'total_amount' => [
            'amount' => number_format((float)$booking['total_amount'], 2, '.', ''),
            'currency' => 'SAR',
        ],
    ]],
    'consumer' => [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone_number' => $booking['customer_phone'] ?: '',
        'email' => $booking['customer_email'] ?: $booking['account_email'],
    ],
    'country_code' => 'SA',
    'description' => 'Ahmed Travels booking ' . $booking['booking_no'],
    'merchant_url' => [
        'success' => $site_base_url . '/tamara_return.php?booking_id=' . $booking_id . '&result=success',
        'failure' => $site_base_url . '/tamara_return.php?booking_id=' . $booking_id . '&result=failure',
        'cancel'  => $site_base_url . '/tamara_return.php?booking_id=' . $booking_id . '&result=cancel',
        'notification' => $site_base_url . '/tamara_webhook.php',
    ],
    'platform' => 'custom',
    'is_mobile' => false,
];

$ch = curl_init(rtrim(TAMARA_API_URL, '/') . '/checkout');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . TAMARA_API_TOKEN,
    ],
    CURLOPT_TIMEOUT => 20,
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error || $http_code < 200 || $http_code >= 300) {
    error_log('Tamara checkout creation failed: ' . $curl_error . ' | HTTP ' . $http_code . ' | ' . $response);
    header('Location: booking_payment.php?booking_id=' . $booking_id . '&tamara_error=1');
    exit();
}

$data = json_decode($response, true);
if (empty($data['checkout_url']) || empty($data['order_id'])) {
    error_log('Tamara checkout response missing checkout_url/order_id: ' . $response);
    header('Location: booking_payment.php?booking_id=' . $booking_id . '&tamara_error=1');
    exit();
}

// Store Tamara's order_id against this booking so the webhook can
// find its way back to the right row later.
$stmt = $pdo->prepare("UPDATE bookings SET tamara_order_id = ? WHERE id = ?");
$stmt->execute([$data['order_id'], $booking_id]);

header('Location: ' . $data['checkout_url']);
exit();