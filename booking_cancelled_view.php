<?php
// booking_cancelled_view.php
//
// Included by booking_payment.php when a booking has been cancelled
// (currently only happens when an agent rejects a payment proof --
// see reject_payment.php). Shows why, and points the customer to
// start a brand new booking rather than trying to pay for this one
// again.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Cancelled | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; min-height: 100vh; color: white; }
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }

        .wrap { max-width: 560px; margin: 0 auto; padding: 60px 20px 80px; }
        .logo { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 800; text-align: center; margin-bottom: 40px; }
        .logo span { color: #d4af37; }

        .card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 18px; padding: 36px; text-align: center; }
        .icon-wrap { width: 70px; height: 70px; margin: 0 auto 20px; border-radius: 50%; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); display: flex; align-items: center; justify-content: center; }
        .icon-wrap i { font-size: 28px; color: #f87171; }
        h2 { font-family: 'Playfair Display', serif; font-size: 22px; margin-bottom: 8px; }
        .booking-no { color: rgba(255,255,255,0.4); font-size: 13px; margin-bottom: 20px; }
        .reason-box { background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.15); border-radius: 12px; padding: 16px 18px; text-align: left; margin-bottom: 26px; font-size: 13.5px; color: rgba(255,255,255,0.7); line-height: 1.6; }
        .reason-box strong { color: #f87171; display: block; margin-bottom: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.3px; }

        .btn-new { display: inline-block; width: 100%; padding: 15px; background: #d4af37; color: #0a0f1e; border: none; border-radius: 12px;
            font-weight: 700; font-size: 15px; text-decoration: none; margin-bottom: 12px; transition: all 0.25s ease; }
        .btn-new:hover { background: #b8922e; }
        .btn-back { display: block; text-align: center; color: rgba(255,255,255,0.4); font-size: 12.5px; text-decoration: none; }
        .btn-back:hover { color: #d4af37; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="logo">Ahmed<span>Travels</span></div>

        <div class="card">
            <div class="icon-wrap"><i class="fas fa-circle-xmark"></i></div>
            <h2>This Booking Was Cancelled</h2>
            <div class="booking-no">Booking No. <?php echo htmlspecialchars($booking['booking_no']); ?></div>

            <?php if (!empty($cancel_reason)): ?>
            <div class="reason-box">
                <strong>Reason</strong>
                <?php echo htmlspecialchars($cancel_reason); ?>
            </div>
            <?php else: ?>
            <div class="reason-box">
                <strong>Reason</strong>
                We were unable to verify the payment for this booking.
            </div>
            <?php endif; ?>

            <a href="services.php" class="btn-new">Create a New Booking</a>
            <a href="dashboard.php" class="btn-back">Back to My Bookings</a>
        </div>
    </div>
</body>
</html>