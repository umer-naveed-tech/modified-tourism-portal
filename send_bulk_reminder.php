<?php
// send_bulk_reminder.php
//
// Agent bulk-action: sends a "check-in reminder" email to every customer
// behind the selected booking IDs. Uses the same PHPMailer/SMTP pattern
// already used in forgot_password.php -- no new mail library or config.

session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

if (function_exists('csrf_valid')) {
    if (!csrf_valid($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token, please refresh the page']);
        exit();
    }
}

$raw_ids = json_decode($_POST['booking_ids'] ?? '[]', true);
if (!is_array($raw_ids) || empty($raw_ids)) {
    echo json_encode(['success' => false, 'message' => 'No bookings selected']);
    exit();
}

// Sanitize to a clean list of positive integers -- never trust IDs from
// the client directly in a query, even though they end up as bound params.
$ids = array_values(array_unique(array_filter(array_map('intval', $raw_ids), fn($v) => $v > 0)));
if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No valid bookings selected']);
    exit();
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT b.*, u.name as user_name, u.email as user_email FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id IN ($placeholders)");
$stmt->execute($ids);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($bookings)) {
    echo json_encode(['success' => false, 'message' => 'None of the selected bookings could be found']);
    exit();
}

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$sent = 0;
$failed = 0;

foreach ($bookings as $b) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($b['user_email'], $b['user_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Reminder: Your upcoming ' . ucfirst($b['service_type']) . ' with Ahmed Travels';
        $mail->Body =
            '<h2>Booking Reminder</h2>'
            . '<p>Dear ' . htmlspecialchars($b['user_name']) . ',</p>'
            . '<p>This is a friendly reminder about your upcoming booking with Ahmed Travels:</p>'
            . '<p><strong>Booking ID:</strong> ' . htmlspecialchars($b['booking_no']) . '<br>'
            . '<strong>Service:</strong> ' . htmlspecialchars(ucfirst($b['service_type'])) . '<br>'
            . '<strong>Travel Date:</strong> ' . htmlspecialchars($b['travel_date']) . '</p>'
            . '<p>If you have any questions or need to make changes, please contact us.</p>'
            . '<p>We look forward to serving you.<br>-- Ahmed Travels</p>';

        $mail->send();
        $sent++;
    } catch (Exception $e) {
        $failed++;
    }
}

$message = "$sent reminder" . ($sent == 1 ? '' : 's') . ' sent successfully';
if ($failed > 0) {
    $message .= ", $failed failed";
}

echo json_encode(['success' => true, 'sent' => $sent, 'failed' => $failed, 'message' => $message]);