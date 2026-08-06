<?php
// booking_review.php
//
// STEP 3 of the new booking flow. Shows the full booking summary and
// asks "Are you sure?" before the customer confirms. On confirm, sends
// a confirmation email (new, additive -- does not touch or replace the
// existing "booking received" email already sent when the booking was
// first created) and moves to the payment step.

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$booking_id = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
if (!$booking_id) {
    header('Location: dashboard.php');
    exit();
}

$stmt = $pdo->prepare("SELECT b.*, u.name AS account_name, u.email AS account_email FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ? AND b.user_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: dashboard.php');
    exit();
}

// If personal details haven't been filled in yet, send them back to
// that step first rather than showing an incomplete summary.
if (empty($booking['customer_name']) || empty($booking['id_number'])) {
    header('Location: booking_details.php?booking_id=' . $booking_id);
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'awaiting_payment' WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);

    // New, additive confirmation email -- separate from whatever email
    // was already sent at booking-creation time.
    if (!empty($booking['customer_email']) || !empty($booking['account_email'])) {
        $to_email = $booking['customer_email'] ?: $booking['account_email'];
        $to_name = $booking['customer_name'] ?: $booking['account_name'];
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
            $mail->Subject = 'Booking Confirmed - ' . $booking['booking_no'] . ' - Ahmed Travels';
            $mail->Body = "<h2>Booking Confirmed</h2>"
                . "<p>Dear " . htmlspecialchars($to_name) . ",</p>"
                . "<p>Thank you for confirming your booking with Ahmed Travels.</p>"
                . "<p><strong>Booking ID:</strong> " . htmlspecialchars($booking['booking_no']) . "<br>"
                . "<strong>Total Amount:</strong> SAR " . number_format($booking['total_amount']) . "</p>"
                . "<p>Please complete the payment step to finalize your booking. Our team will confirm your booking once payment is received.</p>"
                . "<p>-- Ahmed Travels</p>";
            $mail->send();
        } catch (Exception $e) {
            // Non-fatal -- the booking still proceeds to payment even
            // if the email couldn't be sent.
            error_log('booking_review.php mail error: ' . $e->getMessage());
        }
    }

    header('Location: booking_payment.php?booking_id=' . $booking_id);
    exit();
}

// ---- Reconstruct a readable summary the same way the agent panel does ----
$details = [];
if (!empty($booking['price_breakdown'])) {
    $decoded = json_decode($booking['price_breakdown'], true);
    if (is_array($decoded)) $details = $decoded;
}
if (empty($details) && $booking['service_type'] === 'hotel') {
    $stmt2 = $pdo->prepare("SELECT hotel_name FROM hotels_saudi WHERE id = ?");
    $stmt2->execute([$booking['service_id']]);
    $h = $stmt2->fetch(PDO::FETCH_ASSOC);
    $details['hotel_name'] = $h['hotel_name'] ?? null;
} elseif (empty($details) && $booking['service_type'] === 'taxi') {
    $stmt2 = $pdo->prepare("SELECT car_name, car_model FROM cars WHERE id = ?");
    $stmt2->execute([$booking['service_id']]);
    $c = $stmt2->fetch(PDO::FETCH_ASSOC);
    $details['car_name'] = trim(($c['car_name'] ?? '') . ' ' . ($c['car_model'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Your Booking | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; min-height: 100vh; color: white; }
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }

        .wrap { max-width: 640px; margin: 0 auto; padding: 40px 20px 80px; }
        .logo { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 800; text-align: center; margin-bottom: 8px; }
        .logo span { color: #d4af37; }

        .steps { display: flex; justify-content: center; gap: 10px; margin: 24px 0 36px; }
        .step { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: rgba(255,255,255,0.3); }
        .step .num { width: 24px; height: 24px; border-radius: 50%; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; }
        .step.active { color: #d4af37; }
        .step.active .num { background: #d4af37; color: #0a0f1e; }
        .step.done .num { background: rgba(212,175,55,0.2); color: #d4af37; }
        .step-sep { width: 24px; height: 1px; background: rgba(255,255,255,0.08); align-self: center; }

        .card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 18px; padding: 32px; }
        .card h2 { font-family: 'Playfair Display', serif; font-size: 22px; margin-bottom: 6px; }
        .card .sub { color: rgba(255,255,255,0.4); font-size: 13px; margin-bottom: 26px; }

        .summary-box { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; padding: 6px 20px; margin-bottom: 24px; }
        .row { display: flex; justify-content: space-between; gap: 16px; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 13.5px; }
        .row:last-child { border-bottom: none; }
        .row span:first-child { color: rgba(255,255,255,0.4); }
        .row span:last-child { color: white; font-weight: 500; text-align: right; }
        .row .amt { color: #d4af37; font-weight: 700; font-size: 16px; }

        .confirm-note { background: rgba(212,175,55,0.06); border: 1px solid rgba(212,175,55,0.15); border-radius: 12px; padding: 16px 18px; margin-bottom: 22px; font-size: 13.5px; color: rgba(255,255,255,0.7); line-height: 1.6; }

        .btn-confirm { width: 100%; padding: 15px; background: #d4af37; color: #0a0f1e; border: none; border-radius: 12px;
            font-weight: 700; font-size: 15px; cursor: pointer; transition: all 0.25s ease; }
        .btn-confirm:hover { background: #b8922e; }
        .btn-back { display: block; text-align: center; margin-top: 14px; color: rgba(255,255,255,0.35); font-size: 12.5px; text-decoration: none; }
        .btn-back:hover { color: #d4af37; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="logo">Ahmed<span>Travels</span></div>

        <div class="steps">
            <div class="step done"><div class="num"><i class="fas fa-check" style="font-size:10px;"></i></div>Service</div>
            <div class="step-sep"></div>
            <div class="step done"><div class="num"><i class="fas fa-check" style="font-size:10px;"></i></div>Details</div>
            <div class="step-sep"></div>
            <div class="step active"><div class="num">3</div>Confirm</div>
            <div class="step-sep"></div>
            <div class="step"><div class="num">4</div>Payment</div>
        </div>

        <div class="card">
            <h2>Are You Sure?</h2>
            <p class="sub">Please review your booking details before confirming.</p>

            <div class="summary-box">
                <div class="row"><span>Booking No.</span><span><?php echo htmlspecialchars($booking['booking_no']); ?></span></div>
                <div class="row"><span>Service</span><span><?php echo htmlspecialchars(ucfirst($booking['service_type'])); ?></span></div>
                <?php if (!empty($details['hotel_name'])): ?>
                    <div class="row"><span>Hotel</span><span><?php echo htmlspecialchars($details['hotel_name']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($details['room_type'])): ?>
                    <div class="row"><span>Room Type</span><span><?php echo htmlspecialchars($details['room_type']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($details['car_name'])): ?>
                    <div class="row"><span>Vehicle</span><span><?php echo htmlspecialchars($details['car_name']); ?></span></div>
                <?php endif; ?>
                <div class="row"><span>Travel Date</span><span><?php echo htmlspecialchars($booking['travel_date']); ?></span></div>
                <div class="row"><span>Guests</span><span><?php echo (int)$booking['guests']; ?></span></div>
                <div class="row"><span>Traveler Name</span><span><?php echo htmlspecialchars($booking['customer_name']); ?></span></div>
                <div class="row"><span>Phone</span><span><?php echo htmlspecialchars($booking['customer_phone']); ?></span></div>
                <div class="row"><span>Country</span><span><?php echo htmlspecialchars($booking['customer_country']); ?></span></div>
                <div class="row"><span><?php echo $booking['id_type'] === 'passport' ? 'Passport No.' : 'ID Card No.'; ?></span><span><?php echo htmlspecialchars($booking['id_number']); ?></span></div>
                <div class="row"><span>Total Amount</span><span class="amt">SAR <?php echo number_format($booking['total_amount']); ?></span></div>
            </div>

            <div class="confirm-note">By confirming, you agree that the details above are correct. You will be taken to the payment step next, and this booking will remain pending until our team verifies your payment.</div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                <button type="submit" class="btn-confirm">Yes, Confirm Booking</button>
            </form>
            <a href="booking_details.php?booking_id=<?php echo $booking_id; ?>" class="btn-back">Go back and edit details</a>
        </div>
    </div>
</body>
</html>