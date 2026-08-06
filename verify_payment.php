<?php
// verify_payment.php
//
// Agent-only AJAX endpoint. Marks the most recent payment for a
// booking as verified and moves the booking itself to 'confirmed' --
// this is the manual step where an agent has looked at the uploaded
// screenshot/reference and is satisfied the payment actually came in.
// Nothing here is automatic; the agent's click is the only thing that
// confirms a booking through this new flow.

session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

if (!csrf_valid()) {
    echo json_encode(['success' => false, 'error' => 'Security check failed, please refresh the page']);
    exit();
}

$booking_id = (int)($_POST['booking_id'] ?? 0);
if (!$booking_id) {
    echo json_encode(['success' => false, 'error' => 'Missing booking id']);
    exit();
}

$stmt = $pdo->prepare("SELECT b.*, u.name AS user_name, u.email AS user_email FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ?");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    echo json_encode(['success' => false, 'error' => 'Booking not found']);
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$booking_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    echo json_encode(['success' => false, 'error' => 'No payment proof has been submitted for this booking yet']);
    exit();
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE payments SET status = 'verified', verified_at = NOW(), verified_by = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $payment['id']]);

    $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', payment_status = 'verified' WHERE id = ?");
    $stmt->execute([$booking_id]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error while verifying payment']);
    exit();
}

// Best-effort confirmation email to the customer -- failing to send
// this never rolls back the verification itself, since the payment
// and booking are already correctly updated above.
$to_email = $booking['customer_email'] ?: $booking['user_email'];
$to_name = $booking['customer_name'] ?: $booking['user_name'];
if ($to_email) {
    try {
        require_once 'PHPMailer/src/PHPMailer.php';
        require_once 'PHPMailer/src/SMTP.php';
        require_once 'PHPMailer/src/Exception.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = 'Payment Verified - Booking Confirmed - ' . $booking['booking_no'] . ' - Ahmed Travels';
        $mail->Body = "<h2>Your Booking Is Confirmed</h2>"
            . "<p>Dear " . htmlspecialchars($to_name) . ",</p>"
            . "<p>We have verified your payment for booking <strong>" . htmlspecialchars($booking['booking_no']) . "</strong>. Your booking is now confirmed.</p>"
            . "<p>Thank you for choosing Ahmed Travels.</p>";
        $mail->send();
    } catch (Exception $e) {
        error_log('verify_payment.php mail error: ' . $e->getMessage());
    }
}

echo json_encode(['success' => true]);