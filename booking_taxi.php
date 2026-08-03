<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'send_admin_email.php';  // Add this

$car_id = $_GET['car_id'] ?? 0;
$car_name = $_GET['car_name'] ?? '';
$from_city = $_GET['from'] ?? '';
$to_city = $_GET['to'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM cars WHERE id = ?");
$stmt->execute([$car_id]);
$car = $stmt->fetch();

if(!$car) {
    header('Location: services.php?type=taxi');
    exit();
}

$fare = null;
if($from_city && $to_city) {
    $stmt = $pdo->prepare("SELECT price_sar FROM car_fares WHERE car_id = ? AND from_city = ? AND to_city = ?");
    $stmt->execute([$car_id, $from_city, $to_city]);
    $fare = $stmt->fetch();
}

$error = '';
$success = '';
$booking_no = '';
$wa_link = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $from = $_POST['from'];
    $to = $_POST['to'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    // Note: fare is NOT trusted from the client anymore — it is looked up
    // fresh from car_fares using the car/route, same as the page load above.
    $fare_stmt = $pdo->prepare("SELECT price_sar FROM car_fares WHERE car_id = ? AND from_city = ? AND to_city = ?");
    $fare_stmt->execute([$car_id, $from, $to]);
    $verified_fare = $fare_stmt->fetch();

    if(!$verified_fare) {
        $error = "Invalid route selected. Please try again.";
    } else {
    $fare_amount = $verified_fare['price_sar'];
    
    $booking_no = 'TAXI-' . date('Ymd') . '-' . rand(1000, 9999);
    $travel_datetime = $date . ($time ? ' at ' . $time : '');
    
    $stmt = $pdo->prepare("INSERT INTO bookings (booking_no, user_id, service_type, service_id, booking_date, travel_date, from_location, to_location, guests, total_amount, status, payment_status, can_cancel_until) VALUES (?, ?, 'taxi', ?, CURDATE(), ?, ?, ?, ?, ?, 'pending', 'pending', DATE_ADD(NOW(), INTERVAL 1 HOUR))");
    
    if($stmt->execute([$booking_no, $_SESSION['user_id'], $car_id, $travel_datetime, $from, $to, $car['capacity'], $fare_amount])) {
        $success = true;
        
        // Send email to admin
        sendAdminEmail(
            'booking',
            $booking_no,
            $_SESSION['user_name'],
            $_SESSION['user_email'],
            'Taxi - ' . $car['car_name'] . ' (' . $from . ' to ' . $to . ')',
            $travel_datetime,
            $fare_amount,
            'pending'
        );
        
        $wa_msg = "New Booking: $car_name from $from to $to on $date at $time. Booking ID: $booking_no. Total: SAR $fare_amount";
        $wa_link = "https://wa.me/923001234567?text=" . urlencode($wa_msg);
    } else {
        $error = "Booking failed. Please try again.";
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Taxi - Ahmed Travels</title>
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
        .navbar-brand { font-family: 'Playfair Display', serif; color: white; font-size: 21px; font-weight: 800; text-decoration: none; }
        .btn-dashboard { background: rgba(212,175,55,0.1); color: #d4af37; border: 1px solid rgba(212,175,55,0.15); padding: 8px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.3s ease; }
        .btn-dashboard:hover { background: #d4af37; color: #0a0f1e; }

        .booking-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px; padding: 36px; max-width: 520px; margin: 50px auto;
            opacity: 0; transform: translateY(16px); animation: cardIn 0.6s cubic-bezier(.2,.8,.3,1) forwards;
        }
        @keyframes cardIn { to { opacity: 1; transform: translateY(0); } }
        .booking-card h2 { font-family: 'Playfair Display', serif; color: white; text-align: center; margin-bottom: 24px; font-size: 22px; }

        .car-preview { text-align: center; margin-bottom: 20px; }
        .car-preview img { width: 100%; border-radius: 14px; height: 180px; object-fit: cover; margin-bottom: 10px; }
        .car-preview p { color: rgba(255,255,255,0.4); font-size: 13px; }

        .field-row { display: flex; gap: 14px; flex-wrap: wrap; }
        .field-col { flex: 1; min-width: 180px; margin-bottom: 16px; }
        .field-col label { display: block; font-size: 12.5px; font-weight: 500; color: rgba(255,255,255,0.5); margin-bottom: 7px; }
        .field-col input {
            width: 100%; padding: 12px 14px; font-size: 14px; border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03); border-radius: 10px; color: white; font-family: inherit; transition: all 0.25s ease;
        }
        .field-col input:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 4px rgba(212,175,55,0.07); background: rgba(255,255,255,0.05); }
        .field-col input[readonly] { color: rgba(255,255,255,0.4); cursor: not-allowed; }
        .field-col input[type="date"], .field-col input[type="time"] { color-scheme: dark; }

        .fare-display-box { text-align: center; margin: 8px 0 20px; }
        .fare-display-box .price-large { font-size: 30px; font-weight: 800; color: #d4af37; display: block; }
        .fare-display-box label { font-size: 12.5px; color: rgba(255,255,255,0.4); }

        .btn-confirm {
            position: relative; overflow: hidden;
            background: #d4af37; color: #0a0f1e; padding: 14px; border: none; border-radius: 12px;
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
    </style>
</head>
<body>
    <div class="bg-ambient" aria-hidden="true"></div>
    <div class="grain-overlay" aria-hidden="true"></div>

<div class="page-content">
    <nav class="navbar">
        <div class="container container-inner">
            <a class="navbar-brand" href="index.php">Ahmed Travels - Saudi</a>
            <a href="dashboard.php" class="btn-dashboard">My Dashboard</a>
        </div>
    </nav>

    <div class="container">
        <div class="booking-card">
            <h2>Book <?php echo htmlspecialchars($car['car_name']); ?> <?php echo htmlspecialchars($car['car_model']); ?></h2>
            
            <?php if($success): ?>
                <div class="success-block">
                    <div class="check-wrap"><i class="fas fa-check"></i></div>
                    <h4>Booking Confirmed!</h4>
                    <div class="detail-box">
                        <div class="detail-row"><span>Booking ID</span><span><?php echo htmlspecialchars($booking_no); ?></span></div>
                        <div class="detail-row"><span>Route</span><span><?php echo htmlspecialchars($from_city); ?> &rarr; <?php echo htmlspecialchars($to_city); ?></span></div>
                        <div class="detail-row"><span>Total Fare</span><span style="color:#d4af37;">SAR <?php echo number_format($fare['price_sar'] ?? 0); ?></span></div>
                    </div>
                    <a href="<?php echo htmlspecialchars($wa_link); ?>" class="btn-wa" target="_blank"><i class="fab fa-whatsapp"></i> Send WhatsApp</a>
                    <div class="btn-row">
                        <a href="dashboard.php" class="btn-primary2">View My Bookings</a>
                        <a href="services.php?type=taxi" class="btn-secondary2">Book Another</a>
                    </div>
                </div>
            <?php else: ?>
                <?php if($error): ?>
                    <div class="error-message"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="car-preview">
                    <img src="<?php echo htmlspecialchars($car['image_url']); ?>" alt="<?php echo htmlspecialchars($car['car_name']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/600x300/0a0f1e/d4af37?text=Car';">
                    <p>Capacity: <?php echo (int)$car['capacity']; ?> persons | Air Conditioning: Yes</p>
                </div>
                
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="field-row">
                        <div class="field-col">
                            <label>Pickup City</label>
                            <input type="text" name="from" value="<?php echo htmlspecialchars($from_city); ?>" readonly>
                        </div>
                        <div class="field-col">
                            <label>Drop City</label>
                            <input type="text" name="to" value="<?php echo htmlspecialchars($to_city); ?>" readonly>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-col">
                            <label>Travel Date</label>
                            <input type="date" name="date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="field-col">
                            <label>Travel Time</label>
                            <input type="time" name="time">
                        </div>
                    </div>

                    <div class="fare-display-box">
                        <label>Total Fare</label>
                        <span class="price-large">SAR <?php echo number_format($fare['price_sar'] ?? 0); ?></span>
                    </div>
                    <input type="hidden" name="fare" value="<?php echo $fare['price_sar'] ?? 0; ?>">
                    
                    <button type="submit" class="btn-confirm">Confirm Booking</button>
                </form>
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