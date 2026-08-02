<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=booking');
    exit();
}
require_once 'config.php';

$service_id = $_GET['id'] ?? 0;
$service_type = $_GET['type'] ?? 'hotel';

$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if(!$service) {
    header('Location: services.php?type=' . $service_type);
    exit();
}

$error = '';
$success = '';
$booking_no = '';
$wa_link = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $travel_date = $_POST['travel_date'];
    $from_location = $_POST['from_location'] ?? '';
    $to_location = $_POST['to_location'] ?? '';
    $guests = $_POST['guests'] ?? 1;
    
    $booking_no = 'TRV-' . date('Ymd') . '-' . rand(1000, 9999);
    $total_amount = $service['price'] * $guests;
    
    $stmt = $pdo->prepare("INSERT INTO bookings (booking_no, user_id, service_type, service_id, booking_date, travel_date, from_location, to_location, guests, total_amount, status) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'pending')");
    
    if($stmt->execute([$booking_no, $_SESSION['user_id'], $service_type, $service_id, $travel_date, $from_location, $to_location, $guests, $total_amount])) {
        $success = true;
        $wa_msg = "Hi! I have booked {$service['title']} for $guests person(s) on $travel_date. Booking ID: $booking_no. Total: Rs. $total_amount";
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
    <title>Book <?php echo htmlspecialchars($service['title']); ?> - Ahmed Travels</title>
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
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .navbar-brand { font-family: 'Playfair Display', serif; color: white; font-size: 21px; font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .navbar-brand i { color: #d4af37; }
        .btn-dashboard { background: rgba(212,175,55,0.1); color: #d4af37; border: 1px solid rgba(212,175,55,0.15); padding: 8px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.3s ease; }
        .btn-dashboard:hover { background: #d4af37; color: #0a0f1e; }

        .booking-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px; padding: 36px; max-width: 600px; margin: 50px auto;
            opacity: 0; transform: translateY(16px); animation: cardIn 0.6s cubic-bezier(.2,.8,.3,1) forwards;
        }
        @keyframes cardIn { to { opacity: 1; transform: translateY(0); } }
        .booking-card h2 { font-family: 'Playfair Display', serif; color: white; text-align: center; margin-bottom: 28px; font-size: 24px; }

        .field-row { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 4px; }
        .field-col { flex: 1; min-width: 200px; margin-bottom: 16px; }
        .field-col label { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 500; color: rgba(255,255,255,0.5); margin-bottom: 7px; }
        .field-col label i { color: #d4af37; font-size: 11px; }
        .field-col input {
            width: 100%; padding: 12px 14px; font-size: 14px; border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03); border-radius: 10px; color: white; font-family: inherit; transition: all 0.25s ease;
        }
        .field-col input:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 4px rgba(212,175,55,0.07); background: rgba(255,255,255,0.05); }
        .field-col input[readonly] { color: rgba(255,255,255,0.4); cursor: not-allowed; }
        .field-col input::placeholder { color: rgba(255,255,255,0.2); }

        .price-summary { text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.05); }
        .price-summary h4 { color: rgba(255,255,255,0.6); font-size: 14px; font-weight: 500; }
        .price-large { font-size: 34px; font-weight: 800; color: #d4af37; display: block; margin-top: 4px; }
        .price-summary p { color: rgba(255,255,255,0.3); font-size: 12.5px; margin-top: 4px; }

        .btn-confirm {
            position: relative; overflow: hidden;
            background: #d4af37; color: #0a0f1e; padding: 14px; border: none; border-radius: 50px;
            width: 100%; font-weight: 700; font-size: 15px; margin-top: 20px; cursor: pointer; transition: all 0.3s ease;
        }
        .btn-confirm:hover { background: #b8922e; transform: translateY(-2px); box-shadow: 0 12px 32px rgba(212,175,55,0.25); }
        .btn-confirm .btn-shine { position: absolute; top: 0; left: -60%; width: 40%; height: 100%; background: linear-gradient(120deg, transparent, rgba(255,255,255,0.35), transparent); transform: skewX(-20deg); transition: left 0.6s ease; }
        .btn-confirm:hover .btn-shine { left: 130%; }

        .error-message { background: rgba(239,68,68,0.07); color: #f87171; padding: 13px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 20px; border: 1px solid rgba(239,68,68,0.1); display: flex; align-items: center; gap: 10px; }

        /* Success state */
        .success-block { text-align: center; }
        .check-wrap { width: 76px; height: 76px; margin: 0 auto 18px; border-radius: 50%; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); display: flex; align-items: center; justify-content: center; opacity: 0; transform: scale(0.5); animation: popIn 0.5s cubic-bezier(.34,1.56,.64,1) forwards; animation-delay: 0.15s; }
        .check-wrap i { font-size: 32px; color: #34d399; }
        @keyframes popIn { to { opacity: 1; transform: scale(1); } }
        .success-block h4 { font-family: 'Playfair Display', serif; color: white; font-size: 22px; margin-bottom: 18px; }
        .detail-box { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; padding: 18px 20px; text-align: left; margin-bottom: 20px; }
        .detail-row { display: flex; justify-content: space-between; padding: 7px 0; font-size: 13.5px; }
        .detail-row span:first-child { color: rgba(255,255,255,0.4); }
        .detail-row span:last-child { color: white; font-weight: 600; }

        .btn-wa { display: flex; align-items: center; justify-content: center; gap: 8px; background: #25D366; color: white; padding: 12px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; margin-bottom: 14px; transition: all 0.3s ease; }
        .btn-wa:hover { background: #128C7E; transform: translateY(-2px); }
        .btn-row { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-primary2, .btn-secondary2 { flex: 1; padding: 12px; border-radius: 10px; font-weight: 600; font-size: 13.5px; text-decoration: none; text-align: center; transition: all 0.3s ease; }
        .btn-primary2 { background: rgba(212,175,55,0.1); color: #d4af37; border: 1px solid rgba(212,175,55,0.15); }
        .btn-primary2:hover { background: #d4af37; color: #0a0f1e; }
        .btn-secondary2 { background: rgba(255,255,255,0.03); color: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,0.05); }
        .btn-secondary2:hover { background: rgba(255,255,255,0.07); }

        @media (max-width: 500px) { .btn-row { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="bg-ambient" aria-hidden="true"></div>
    <div class="grain-overlay" aria-hidden="true"></div>

<div class="page-content">
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-plane"></i> Ahmed Travels</a>
            <a href="dashboard.php" class="btn-dashboard">My Dashboard</a>
        </div>
    </nav>

    <div class="container">
        <div class="booking-card">
            <h2>Book <?php echo htmlspecialchars($service['title']); ?></h2>
            
            <?php if($success): ?>
                <div class="success-block">
                    <div class="check-wrap"><i class="fas fa-check"></i></div>
                    <h4>Booking Confirmed!</h4>
                    <div class="detail-box">
                        <div class="detail-row"><span>Booking ID</span><span><?php echo htmlspecialchars($booking_no); ?></span></div>
                        <div class="detail-row"><span>Service</span><span><?php echo htmlspecialchars($service['title']); ?></span></div>
                        <div class="detail-row"><span>Total Amount</span><span style="color:#d4af37;">Rs. <?php echo number_format($service['price'] * ($_POST['guests'] ?? 1)); ?></span></div>
                    </div>
                    <a href="<?php echo htmlspecialchars($wa_link); ?>" class="btn-wa" target="_blank"><i class="fab fa-whatsapp"></i> Send WhatsApp Confirmation</a>
                    <div class="btn-row">
                        <a href="dashboard.php" class="btn-primary2">View My Bookings</a>
                        <a href="services.php?type=<?php echo urlencode($service_type); ?>" class="btn-secondary2">Book More</a>
                    </div>
                </div>
            <?php else: ?>
                <?php if($error): ?>
                    <div class="error-message"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="field-row">
                        <div class="field-col">
                            <label><i class="fas fa-calendar"></i> Travel Date *</label>
                            <input type="date" name="travel_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="field-col">
                            <label><i class="fas fa-users"></i> Number of Guests/Persons</label>
                            <input type="number" name="guests" value="1" min="1" max="10">
                        </div>
                    </div>
                    
                    <?php if($service_type == 'taxi'): ?>
                    <div class="field-row">
                        <div class="field-col">
                            <label><i class="fas fa-map-marker-alt"></i> Pickup Location *</label>
                            <input type="text" name="from_location" placeholder="e.g., Lahore Airport" required>
                        </div>
                        <div class="field-col">
                            <label><i class="fas fa-flag-checkered"></i> Drop Location *</label>
                            <input type="text" name="to_location" placeholder="e.g., Islamabad" required>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="field-row">
                        <div class="field-col" style="flex-basis:100%;">
                            <label><i class="fas fa-map-marker-alt"></i> Location</label>
                            <input type="text" name="from_location" value="<?php echo htmlspecialchars($service['location']); ?>" readonly>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="price-summary">
                        <h4>Total Amount</h4>
                        <span class="price-large">Rs. <?php echo number_format($service['price']); ?></span>
                        <p>*Per <?php echo $service_type == 'hotel' ? 'night' : ($service_type == 'taxi' ? 'trip' : 'person'); ?></p>
                        <button type="submit" class="btn-confirm"><span class="btn-shine"></span>Confirm Booking</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>