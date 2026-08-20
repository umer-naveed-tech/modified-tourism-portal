<?php
// tamara_return.php
//
// Where the customer lands after leaving Tamara's checkout page
// (success, failure, or cancel). Per Tamara's own integration rules,
// this redirect is NOT the authoritative payment confirmation --
// tamara_webhook.php is. This page just tells the customer what's
// happening and checks the booking's current status, which the
// webhook may or may not have already updated by the time they land
// back here (webhooks can arrive a few seconds after the redirect).

session_start();
require_once 'config.php';

$booking_id = (int)($_GET['booking_id'] ?? 0);
$result = $_GET['result'] ?? '';

$booking = null;
if ($booking_id && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

$is_paid = $booking && $booking['status'] === 'completed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #faf7f1; color: #2b2620; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { max-width: 440px; width: 100%; margin: 24px; background: #fffdfa; border: 1px solid #ece4d4; border-radius: 20px; padding: 40px 32px; text-align: center; box-shadow: 0 20px 50px rgba(120,95,40,0.08); }
        .icon { width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 30px; }
        .icon.ok { background: rgba(46,158,106,0.12); color: #2e9e6a; }
        .icon.wait { background: rgba(212,175,55,0.12); color: #b8912f; }
        .icon.fail { background: rgba(220,38,38,0.1); color: #dc2626; }
        h1 { font-family: 'Playfair Display', serif; font-size: 22px; margin-bottom: 10px; }
        p { color: rgba(43,38,32,0.65); font-size: 13.5px; line-height: 1.6; margin-bottom: 24px; }
        .btn { display: inline-block; background: #d4af37; color: #201a0d; font-weight: 700; padding: 13px 28px; border-radius: 10px; text-decoration: none; font-size: 14px; }
        .btn:hover { background: #b8922e; }
        .btn-secondary { display: block; margin-top: 12px; color: rgba(43,38,32,0.5); font-size: 12.5px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($is_paid): ?>
            <div class="icon ok"><i class="fas fa-check"></i></div>
            <h1>Payment Confirmed</h1>
            <p>Your payment via Tamara was successful and your booking is now confirmed. A confirmation has been sent to your email.</p>
            <a href="booking_detail_view.php?id=<?php echo $booking_id; ?>" class="btn">View Booking</a>
        <?php elseif ($result === 'cancel'): ?>
            <div class="icon fail"><i class="fas fa-xmark"></i></div>
            <h1>Payment Cancelled</h1>
            <p>You cancelled the Tamara checkout before completing payment. Your booking is still pending -- you can try again anytime.</p>
            <a href="booking_payment.php?booking_id=<?php echo $booking_id; ?>" class="btn">Back to Payment Options</a>
        <?php elseif ($result === 'failure'): ?>
            <div class="icon fail"><i class="fas fa-triangle-exclamation"></i></div>
            <h1>Payment Failed</h1>
            <p>Tamara couldn't complete this payment. No charge was made. You can try again or use manual bank transfer instead.</p>
            <a href="booking_payment.php?booking_id=<?php echo $booking_id; ?>" class="btn">Back to Payment Options</a>
        <?php else: ?>
            <div class="icon wait"><i class="fas fa-clock"></i></div>
            <h1>Confirming Your Payment</h1>
            <p>We're finalizing confirmation with Tamara -- this usually takes just a few seconds. Refresh this page shortly, or check My Bookings.</p>
            <a href="booking_payment.php?booking_id=<?php echo $booking_id; ?>" class="btn">Refresh Status</a>
            <a href="dashboard.php" class="btn-secondary">Go to Dashboard</a>
        <?php endif; ?>
    </div>
</body>
</html>