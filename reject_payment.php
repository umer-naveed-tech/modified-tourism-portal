<?php
// reject_payment.php
//
// Agent-only. Rejects a payment with a reason the customer will see,
// and moves their booking back to "awaiting payment" so
// booking_payment.php lets them submit a fresh payment proof --
// instead of being stuck seeing "Payment Submitted -- Awaiting
// Confirmation" forever with no way to fix a mistake.

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

$payment_id = (int)($_POST['payment_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if (!$payment_id) {
    echo json_encode(['success' => false, 'error' => 'Missing payment id']);
    exit();
}
if ($reason === '') {
    echo json_encode(['success' => false, 'error' => 'Please provide a reason for the customer']);
    exit();
}

$stmt = $pdo->prepare("SELECT p.*, b.booking_no, b.customer_name, b.customer_email, u.name AS user_name, u.email AS user_email FROM payments p JOIN bookings b ON p.booking_id = b.id JOIN users u ON b.user_id = u.id WHERE p.id = ?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    echo json_encode(['success' => false, 'error' => 'Payment not found']);
    exit();
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE payments SET status = 'rejected', rejection_reason = ?, verified_at = NOW(), verified_by = ? WHERE id = ?");
    $stmt->execute([$reason, $_SESSION['user_id'], $payment_id]);

    // Send the booking back to "awaiting payment" so booking_payment.php
    // shows the reason and a fresh upload form instead of blocking the
    // customer with "already submitted".
    $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'awaiting_payment' WHERE id = ?");
    $stmt->execute([$payment['booking_id']]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error while rejecting payment']);
    exit();
}

// Best-effort email letting the customer know why, and that they can
// resubmit.
$to_email = $payment['customer_email'] ?: $payment['user_email'];
$to_name = $payment['customer_name'] ?: $payment['user_name'];
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
        $mail->Subject = 'Payment Could Not Be Verified - ' . $payment['booking_no'] . ' - Ahmed Travels';
        $mail->Body = "<h2>We Couldn't Verify Your Payment</h2>"
            . "<p>Dear " . htmlspecialchars($to_name) . ",</p>"
            . "<p>We were unable to verify your payment for booking <strong>" . htmlspecialchars($payment['booking_no']) . "</strong>.</p>"
            . "<p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>"
            . "<p>Please log in and submit your payment proof again with the correct details.</p>"
            . "<p>-- Ahmed Travels</p>";
        $mail->send();
    } catch (Exception $e) {
        error_log('reject_payment.php mail error: ' . $e->getMessage());
    }
}

echo json_encode(['success' => true]);