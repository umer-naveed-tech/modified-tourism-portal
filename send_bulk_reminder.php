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
        $mail->Subject = 'Reminder: Your Upcoming ' . ucfirst($b['service_type']) . ' with Ahmed Travels';

        // NEW: same placeholder-date guard as the other emails -- a
        // booking with no real travel_date shouldn't show "Jan 1, 1970".
        $travel_date_display = (!empty($b['travel_date']) && strtotime($b['travel_date']) > strtotime('1970-01-02'))
            ? htmlspecialchars($b['travel_date']) : 'To be confirmed';

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f1ea; margin: 0; padding: 0; }
                .container { max-width: 560px; margin: 20px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(60,45,20,0.1); }
                .header { background: linear-gradient(135deg, #2b2416, #4a3d22); padding: 30px; text-align: center; }
                .header h1 { color: #d4af37; margin: 0; font-size: 24px; font-family: Georgia, serif; }
                .header p { color: rgba(255,255,255,0.7); margin: 6px 0 0; font-size: 13px; letter-spacing: 0.5px; }
                .content { padding: 28px; }
                .greeting { font-size: 16px; color: #2b2620; margin-bottom: 14px; }
                .booking-box { background: #faf7f1; border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid #ece4d4; }
                .booking-box h3 { margin: 0 0 14px; color: #2b2620; font-size: 14px; font-family: Georgia, serif; }
                .detail-row { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #ece4d4; font-size: 13.5px; }
                .detail-row:last-child { border-bottom: none; }
                .detail-label { color: rgba(43,38,32,0.55); }
                .detail-value { color: #2b2620; font-weight: 600; text-align: right; }
                .btn-wa { display: inline-block; background: #25D366; color: white; padding: 11px 26px; text-decoration: none; border-radius: 25px; font-weight: 600; font-size: 13px; }
                .contact-line { margin-top: 10px; font-size: 12px; color: rgba(43,38,32,0.5); }
                .footer { background: #2b2416; padding: 22px; text-align: center; color: rgba(255,255,255,0.5); font-size: 11.5px; line-height: 1.8; }
                .footer a { color: #d4af37; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ahmed Travels</h1>
                    <p>SAUDI ARABIA &bull; BOOKING REMINDER</p>
                </div>
                <div class='content'>
                    <div class='greeting'>Dear " . htmlspecialchars($b['user_name']) . ",</div>
                    <p style='color: rgba(43,38,32,0.75); font-size: 13.5px; line-height: 1.6;'>This is a friendly reminder about your upcoming booking with Ahmed Travels:</p>
                    <div class='booking-box'>
                        <h3>Booking Details</h3>
                        <div class='detail-row'><span class='detail-label'>Booking ID</span><span class='detail-value'>" . htmlspecialchars($b['booking_no']) . "</span></div>
                        <div class='detail-row'><span class='detail-label'>Service</span><span class='detail-value'>" . htmlspecialchars(ucfirst($b['service_type'])) . "</span></div>
                        <div class='detail-row'><span class='detail-label'>Travel Date</span><span class='detail-value'>$travel_date_display</span></div>
                    </div>
                    <p style='color: rgba(43,38,32,0.75); font-size: 13.5px; line-height: 1.6;'>If you have any questions or need to make changes, our team is happy to help.</p>
                    <div style='text-align: center; margin: 22px 0;'>
                        <a href='https://wa.me/923134830023' class='btn-wa' target='_blank'>Chat on WhatsApp</a>
                        <div class='contact-line'>+966 51 036 1841 &bull; ahmedtvl606@gmail.com</div>
                    </div>
                </div>
                <div class='footer'>
                    <p>Ahmed Travels &bull; Saudi Arabia</p>
                    <p><a href='mailto:ahmedtvl606@gmail.com'>ahmedtvl606@gmail.com</a></p>
                </div>
            </div>
        </body>
        </html>";

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