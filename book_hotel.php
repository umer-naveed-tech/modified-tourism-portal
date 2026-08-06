<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$hotel_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM hotels_saudi WHERE id = ?");
$stmt->execute([$hotel_id]);
$hotel = $stmt->fetch();

if(!$hotel) {
    header('Location: services.php?type=hotels');
    exit();
}

$error = '';
$success = '';
$booking_no = '';
$wa_link = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $guests = $_POST['guests'] ?? 1;
    
    // Calculate nights
    $date1 = new DateTime($check_in);
    $date2 = new DateTime($check_out);
    $nights = $date1->diff($date2)->days;
    
    $total_amount = $hotel['price_per_night_sar'] * $nights;
    $booking_no = 'HOTEL-' . date('Ymd') . '-' . rand(1000, 9999);
    
    // price_breakdown -- lets the agent panel show which hotel/dates
    // this actually was, instead of just the city name.
    $price_breakdown = json_encode([
        'hotel_name' => $hotel['hotel_name'],
        'city' => $hotel['city'],
        'check_in' => $check_in,
        'check_out' => $check_out,
        'nights' => $nights,
        'guests' => $guests,
        'price_per_night' => $hotel['price_per_night_sar'],
    ]);

    $stmt = $pdo->prepare("INSERT INTO bookings (booking_no, user_id, service_type, service_id, booking_date, travel_date, from_location, guests, total_amount, price_breakdown, status) VALUES (?, ?, 'hotel', ?, CURDATE(), ?, ?, ?, ?, ?, 'pending')");
    
    if($stmt->execute([$booking_no, $_SESSION['user_id'], $hotel_id, $check_in, mb_substr($hotel['hotel_name'] . ' (' . $hotel['city'] . ')', 0, 100), $guests, $total_amount, $price_breakdown])) {
        $success = true;
        $wa_msg = "Hi! I have booked {$hotel['hotel_name']} in {$hotel['city']} from $check_in to $check_out ($nights nights). Booking ID: $booking_no. Total: SAR $total_amount";
        $wa_link = "https://wa.me/923001234567?text=" . urlencode($wa_msg);
    } else {
        $error = "Booking failed. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book <?php echo htmlspecialchars($hotel['hotel_name']); ?> - Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; min-height: 100vh; overflow-x: hidden; }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }
        html { scrollbar-color: #8a6d1f #0a0f1e; scrollbar-width: thin; }

        .bg-ambient { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; background: #0a0f1e; }
        .bg-ambient::before {
            content: ''; position: absolute; inset: -20%;
            background: radial-gradient(circle at 20% 15%, rgba(212,175,55,0.09), transparent 40%),
                        radial-gradient(circle at 85% 80%, rgba(212,175,55,0.06), transparent 40%);
            animation: driftGlow 24s ease-in-out infinite alternate;
        }
        @keyframes driftGlow { 0% { transform: translate(0,0) scale(1); } 100% { transform: translate(2%,-2%) scale(1.05); } }
        .grain-overlay {
            position: fixed; inset: 0; z-index: 9997; pointer-events: none; opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        .page-content { position: relative; z-index: 1; }

        .navbar { background: rgba(10, 15, 30, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(212, 175, 55, 0.08); padding: 14px 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .navbar .container-inner { display: flex; justify-content: space-between; align-items: center; }
        .navbar-brand { font-family: 'Playfair Display', serif; color: white; font-size: 21px; font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .navbar-brand i { color: #d4af37; }

        .booking-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px; padding: 36px; max-width: 560px; margin: 50px auto;
            opacity: 0; transform: translateY(16px); animation: cardIn 0.6s cubic-bezier(.2,.8,.3,1) forwards;
        }
        @keyframes cardIn { to { opacity: 1; transform: translateY(0); } }
        .booking-card h2 { font-family: 'Playfair Display', serif; color: white; text-align: center; margin-bottom: 24px; font-size: 24px; }

        .hotel-preview { text-align: center; margin-bottom: 24px; }
        .hotel-preview img { width: 100%; border-radius: 14px; height: 200px; object-fit: cover; margin-bottom: 14px; }
        .hotel-preview h4 { font-family: 'Playfair Display', serif; color: white; font-size: 19px; margin-bottom: 6px; }
        .hotel-preview .stars { color: #d4af37; margin-bottom: 6px; }
        .hotel-preview .amenities-text { color: rgba(255,255,255,0.4); font-size: 12.5px; }

        .field-col { margin-bottom: 16px; }
        .field-col label { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 500; color: rgba(255,255,255,0.5); margin-bottom: 7px; }
        .field-col label i { color: #d4af37; font-size: 11px; }
        .field-col input {
            width: 100%; padding: 12px 14px; font-size: 14px; border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03); border-radius: 10px; color: white; font-family: inherit; transition: all 0.25s ease;
        }
        .field-col input:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 4px rgba(212,175,55,0.07); background: rgba(255,255,255,0.05); }

        .price-note { text-align: center; color: rgba(255,255,255,0.6); font-size: 14px; margin: 18px 0; }
        .price-note strong { color: #d4af37; }

        .btn-confirm {
            position: relative; overflow: hidden;
            background: #d4af37; color: #0a0f1e; padding: 14px; border: none; border-radius: 50px;
            width: 100%; font-weight: 700; font-size: 15px; cursor: pointer; transition: all 0.3s ease;
        }
        .btn-confirm:hover { background: #b8922e; transform: translateY(-2px); box-shadow: 0 12px 32px rgba(212,175,55,0.25); }

        .success-block { text-align: center; }
        .check-wrap { width: 76px; height: 76px; margin: 0 auto 18px; border-radius: 50%; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); display: flex; align-items: center; justify-content: center; opacity: 0; transform: scale(0.5); animation: popIn 0.5s cubic-bezier(.34,1.56,.64,1) forwards; animation-delay: 0.15s; }
        .check-wrap i { font-size: 32px; color: #34d399; }
        @keyframes popIn { to { opacity: 1; transform: scale(1); } }
        .success-block h4 { font-family: 'Playfair Display', serif; color: white; font-size: 22px; margin-bottom: 18px; }
        .detail-box { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; padding: 18px 20px; text-align: left; margin-bottom: 20px; }
        .detail-row { display: flex; justify-content: space-between; padding: 7px 0; font-size: 13.5px; }
        .detail-row span:first-child { color: rgba(255,255,255,0.4); }
        .detail-row span:last-child { color: white; font-weight: 600; text-align: right; }

        .btn-wa { display: flex; align-items: center; justify-content: center; gap: 8px; background: #25D366; color: white; padding: 12px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; margin-bottom: 14px; transition: all 0.3s ease; }
        .btn-wa:hover { background: #128C7E; transform: translateY(-2px); }
        .btn-row { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-primary2, .btn-secondary2 { flex: 1; padding: 12px; border-radius: 10px; font-weight: 600; font-size: 13.5px; text-decoration: none; text-align: center; transition: all 0.3s ease; }
        .btn-primary2 { background: rgba(212,175,55,0.1); color: #d4af37; border: 1px solid rgba(212,175,55,0.15); }
        .btn-primary2:hover { background: #d4af37; color: #0a0f1e; }
        .btn-secondary2 { background: rgba(255,255,255,0.03); color: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,0.05); }
        .btn-secondary2:hover { background: rgba(255,255,255,0.07); }

        .error-message { background: rgba(239,68,68,0.07); color: #f87171; padding: 13px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 20px; border: 1px solid rgba(239,68,68,0.1); display: flex; align-items: center; gap: 10px; }

        @media (max-width: 500px) { .btn-row { flex-direction: column; } }
    
        .btn-spinner {
            display: inline-block; width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,0.35); border-top-color: currentColor;
            border-radius: 50%; animation: btnSpin 0.6s linear infinite;
            margin-right: 8px; vertical-align: -2px;
        }
        @keyframes btnSpin { to { transform: rotate(360deg); } }
    
        /* NEW: compact trust badges strip */
        .mini-trust-strip { display: flex; justify-content: center; gap: 16px; margin-top: 18px; flex-wrap: wrap; }
        .mini-trust-item { display: flex; align-items: center; gap: 6px; font-size: 11px; color: rgba(255,255,255,0.4); }
        .mini-trust-item i { color: #d4af37; font-size: 12px; }
    </style>
</head>
<body>
    <div class="bg-ambient" aria-hidden="true"></div>
    <div class="grain-overlay" aria-hidden="true"></div>

<div class="page-content">
    <nav class="navbar">
        <div class="container container-inner">
            <a class="navbar-brand" href="index.php"><i class="fas fa-plane"></i> Ahmed Travels - Saudi</a>
        </div>
    </nav>

    <div class="container">
        <div class="booking-card">
            <h2>Book <?php echo htmlspecialchars($hotel['hotel_name']); ?></h2>
            
            <?php if($success): ?>
                <div class="success-block">
                    <div class="check-wrap"><i class="fas fa-check"></i></div>
                    <h4>Booking Confirmed!</h4>
                    <div class="detail-box">
                        <div class="detail-row"><span>Booking ID</span><span><?php echo htmlspecialchars($booking_no); ?></span></div>
                        <div class="detail-row"><span>Hotel</span><span><?php echo htmlspecialchars($hotel['hotel_name']); ?></span></div>
                        <div class="detail-row"><span>Total Amount</span><span style="color:#d4af37;">SAR <?php echo number_format($total_amount); ?></span></div>
                    </div>
                    <a href="<?php echo htmlspecialchars($wa_link); ?>" class="btn-wa" target="_blank"><i class="fab fa-whatsapp"></i> Send WhatsApp Confirmation</a>
                    <div class="btn-row">
                        <a href="dashboard.php" class="btn-primary2">View My Bookings</a>
                        <a href="services.php?type=hotels" class="btn-secondary2">Book Another</a>
                    </div>
                </div>
            <?php else: ?>
                <?php if($error): ?>
                    <div class="error-message"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="hotel-preview">
                    <img src="<?php echo htmlspecialchars($hotel['image_url']); ?>" alt="<?php echo htmlspecialchars($hotel['hotel_name']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/600x300/0a0f1e/d4af37?text=Hotel';">
                    <h4><?php echo htmlspecialchars($hotel['hotel_name']); ?></h4>
                    <p class="stars"><?php echo str_repeat('★', (int)$hotel['rating']); ?></p>
                    <p class="amenities-text"><?php echo htmlspecialchars($hotel['amenities']); ?></p>
                </div>

                <form method="POST">
                    <div class="field-col">
                        <label><i class="fas fa-calendar-check"></i> Check-in Date *</label>
                        <input type="date" name="check_in" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="field-col">
                        <label><i class="fas fa-calendar-times"></i> Check-out Date *</label>
                        <input type="date" name="check_out" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>
                    <div class="field-col">
                        <label><i class="fas fa-users"></i> Number of Guests</label>
                        <input type="number" name="guests" value="2" min="1" max="10">
                    </div>

                    <p class="price-note">Price per night: <strong>SAR <?php echo number_format($hotel['price_per_night_sar']); ?></strong></p>

                    <button type="submit" class="btn-confirm">Confirm Booking</button>
                </form>
                <div class="mini-trust-strip">
                    <span class="mini-trust-item"><i class="fas fa-shield-halved"></i> Secure Payment</span>
                    <span class="mini-trust-item"><i class="fas fa-headset"></i> 24/7 Support</span>
                    <span class="mini-trust-item"><i class="fas fa-rotate-left"></i> Free Cancellation</span>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>


<script>
    /* NEW: disable the submit button and show a spinner while the form
       is submitting, so double-clicking never fires a second (duplicate)
       booking request. Skips entirely if an earlier listener already
       cancelled the submit (e.g. client-side validation failing) --
       never leaves a valid form stuck showing "Processing...". */
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (e.defaultPrevented) return;
            const btn = this.querySelector('button[type="submit"]');
            if (btn && !btn.disabled) {
                btn.dataset.originalHtml = btn.innerHTML;
                btn.innerHTML = '<span class="btn-spinner"></span>Processing...';
                btn.disabled = true;
            }
        });
    });
</script>

</body>
</html>