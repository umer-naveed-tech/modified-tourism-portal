<?php
// booking_detail_view.php
//
// Customer-facing "Details" page, linked from the Dashboard, My
// Bookings, and History pages. Ownership-checked (same pattern as
// cancel_booking.php) so a customer can only ever view their OWN
// booking, never someone else's by guessing an id in the URL.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'visitor') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
date_default_timezone_set('Asia/Riyadh');

$booking_id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$b = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$b) {
    header('Location: my_bookings.php');
    exit();
}

$details = [];
if (!empty($b['price_breakdown'])) {
    $d = json_decode($b['price_breakdown'], true);
    if (is_array($d)) $details = $d;
}

$stmt = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$booking_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

$active_page = 'bookings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($b['booking_no']); ?> | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard_shell.css">
    <style>
        .detail-row { display: flex; justify-content: space-between; gap: 16px; padding: 12px 4px; border-bottom: 1px solid #141a2b; font-family: 'Helvetica Neue', sans-serif; font-size: 13px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-row span:first-child { color: #5c6684; }
        .detail-row span:last-child { color: #f4f4f2; font-weight: 500; text-align: right; }
        .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #c9a24b; font-weight: 700; margin: 22px 0 6px; font-family: 'Helvetica Neue', sans-serif; }
        .section-title:first-of-type { margin-top: 0; }
        .box { background: #0d1220; border: 1px solid #1c2436; border-radius: 12px; padding: 4px 18px; margin-bottom: 6px; }
    </style>
</head>
<body>
<div class="shell-outer">
    <div class="shell">
        <?php include 'dashboard_sidebar.php'; ?>
        <div class="content">
            <div class="headrow">
                <div>
                    <h1><?php echo htmlspecialchars($b['booking_no']); ?></h1>
                    <div class="meta"><?php echo htmlspecialchars(ucfirst($b['service_type'])); ?> booking</div>
                </div>
                <a href="my_bookings.php" class="a" style="font-family:'Helvetica Neue',sans-serif; color:#5c6684; font-size:12.5px; text-decoration:none;">← Back to My Bookings</a>
            </div>

            <div class="section-title">Booking Info</div>
            <div class="box">
                <div class="detail-row"><span>Status</span><span><span class="dot <?php echo $b['status']==='confirmed'?'g':($b['status']==='pending'?'y':($b['status']==='cancelled'?'r':'b')); ?>"></span><?php echo htmlspecialchars(ucfirst($b['status'])); ?></span></div>
                <div class="detail-row"><span>Travel Date</span><span><?php echo date('M j, Y', strtotime($b['travel_date'])); ?></span></div>
                <div class="detail-row"><span>Guests</span><span><?php echo (int)$b['guests']; ?></span></div>
                <div class="detail-row"><span>Booked On</span><span><?php echo date('M j, Y g:i A', strtotime($b['created_at'])); ?></span></div>
                <div class="detail-row"><span>Total Amount</span><span style="color:#c9a24b; font-weight:700;">SAR <?php echo number_format($b['total_amount']); ?></span></div>
            </div>

            <?php if ($b['service_type'] === 'hotel'): ?>
                <div class="section-title">Hotel</div>
                <div class="box">
                    <?php if (!empty($details['hotel_name'])): ?><div class="detail-row"><span>Hotel</span><span><?php echo htmlspecialchars($details['hotel_name']); ?></span></div><?php endif; ?>
                    <?php if (!empty($details['room_type'])): ?><div class="detail-row"><span>Room Type</span><span><?php echo htmlspecialchars($details['room_type']); ?></span></div><?php endif; ?>
                    <?php if (!empty($details['check_in'])): ?><div class="detail-row"><span>Check-in</span><span><?php echo htmlspecialchars($details['check_in']); ?></span></div><?php endif; ?>
                    <?php if (!empty($details['check_out'])): ?><div class="detail-row"><span>Check-out</span><span><?php echo htmlspecialchars($details['check_out']); ?></span></div><?php endif; ?>
                    <?php if (!$details): ?><div class="detail-row"><span>Details</span><span><?php echo htmlspecialchars($b['from_location']); ?></span></div><?php endif; ?>
                </div>
            <?php elseif ($b['service_type'] === 'taxi'): ?>
                <div class="section-title">Trip</div>
                <div class="box">
                    <?php if (!empty($details['car_name'])): ?><div class="detail-row"><span>Vehicle</span><span><?php echo htmlspecialchars($details['car_name']); ?></span></div><?php endif; ?>
                    <div class="detail-row"><span>From</span><span><?php echo htmlspecialchars($b['from_location']); ?></span></div>
                    <div class="detail-row"><span>To</span><span><?php echo htmlspecialchars($b['to_location']); ?></span></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($b['customer_name'])): ?>
            <div class="section-title">Traveler</div>
            <div class="box">
                <div class="detail-row"><span>Name</span><span><?php echo htmlspecialchars($b['customer_name']); ?></span></div>
                <div class="detail-row"><span>Phone</span><span><?php echo htmlspecialchars($b['customer_phone']); ?></span></div>
                <?php if (!empty($b['customer_country'])): ?><div class="detail-row"><span>Country</span><span><?php echo htmlspecialchars($b['customer_country']); ?></span></div><?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="section-title">Payment</div>
            <div class="box">
                <?php if ($payment): ?>
                    <div class="detail-row"><span>Status</span><span class="pill <?php echo htmlspecialchars($payment['status']); ?>"><?php echo htmlspecialchars(ucfirst($payment['status'])); ?></span></div>
                    <div class="detail-row"><span>Reference</span><span><?php echo htmlspecialchars($payment['payment_reference']); ?></span></div>
                    <div class="detail-row"><span>Submitted</span><span><?php echo date('M j, Y g:i A', strtotime($payment['submitted_at'])); ?></span></div>
                <?php elseif (!empty($b['customer_name'])): ?>
                    <div class="detail-row"><span>Status</span><span>Awaiting payment</span></div>
                    <div class="detail-row"><span></span><span><a href="booking_payment.php?booking_id=<?php echo $booking_id; ?>" style="color:#c9a24b; text-decoration:none;">Complete payment →</a></span></div>
                <?php else: ?>
                    <div class="detail-row"><span>Status</span><span>Not started</span></div>
                <?php endif; ?>
            </div>

            <?php
                // Preserve the existing 60-minute cancellation window
                // (cancel_booking.php itself is unchanged) -- only shown
                // while it's still actually available.
                $created_at = new DateTime($b['created_at']);
                $cancel_deadline = (clone $created_at)->modify('+60 minutes');
                $can_cancel = (new DateTime() <= $cancel_deadline) && ($b['status'] == 'pending');
            ?>
            <?php if ($can_cancel): ?>
            <a href="cancel_booking.php?id=<?php echo $booking_id; ?>" style="display:block; text-align:center; margin-top:20px; padding:13px; background:rgba(201,98,92,0.1); color:#c9625c; border:1px solid rgba(201,98,92,0.2); border-radius:10px; font-family:'Helvetica Neue',sans-serif; font-size:13px; font-weight:600; text-decoration:none;">Cancel This Booking</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>