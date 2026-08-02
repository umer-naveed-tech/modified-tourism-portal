<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$booking_no = $_GET['booking_no'] ?? '';
$hotel_name = $_GET['hotel'] ?? '';
$room_name = $_GET['room'] ?? '';
$check_in = $_GET['check_in'] ?? '';
$check_out = $_GET['check_out'] ?? '';
$total = $_GET['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; overflow-x: hidden; }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }
        html { scrollbar-color: #8a6d1f #0a0f1e; scrollbar-width: thin; }

        .bg-ambient { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; background: #0a0f1e; }
        .bg-ambient::before {
            content: ''; position: absolute; inset: -20%;
            background: radial-gradient(circle at 50% 30%, rgba(16,185,129,0.10), transparent 45%),
                        radial-gradient(circle at 20% 80%, rgba(212,175,55,0.08), transparent 40%);
            animation: driftGlow 22s ease-in-out infinite alternate;
        }
        @keyframes driftGlow { 0% { transform: translate(0,0) scale(1); } 100% { transform: translate(2%,-2%) scale(1.05); } }
        .grain-overlay {
            position: fixed; inset: 0; z-index: 9997; pointer-events: none; opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        .success-card {
            position: relative; z-index: 1;
            background: rgba(255,255,255,0.04); backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.05); border-radius: 24px;
            padding: 48px 40px; max-width: 500px; width: 100%; text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            opacity: 0; transform: translateY(20px); animation: cardIn 0.6s cubic-bezier(.2,.8,.3,1) forwards;
        }
        @keyframes cardIn { to { opacity: 1; transform: translateY(0); } }

        .check-wrap { width: 84px; height: 84px; margin: 0 auto 24px; border-radius: 50%; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); display: flex; align-items: center; justify-content: center; opacity: 0; transform: scale(0.5); animation: popIn 0.5s cubic-bezier(.34,1.56,.64,1) forwards; animation-delay: 0.2s; }
        .check-wrap i { font-size: 36px; color: #34d399; }
        @keyframes popIn { to { opacity: 1; transform: scale(1); } }

        .success-card h2 { font-family: 'Playfair Display', serif; color: white; font-size: 26px; margin-bottom: 8px; }
        .success-card > p { color: rgba(255,255,255,0.5); font-size: 14px; margin-bottom: 26px; }

        .detail-box { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; padding: 18px 20px; text-align: left; margin-bottom: 26px; }
        .detail-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 13.5px; border-bottom: 1px solid rgba(255,255,255,0.04); gap: 12px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-row span:first-child { color: rgba(255,255,255,0.4); flex-shrink: 0; }
        .detail-row span:last-child { color: white; font-weight: 600; text-align: right; }
        .detail-row .amt { color: #d4af37; font-size: 15px; }

        .note { font-size: 12.5px; color: rgba(255,255,255,0.35); margin-bottom: 24px; display: flex; align-items: center; justify-content: center; gap: 6px; }

        .btn-row { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-primary, .btn-secondary { flex: 1; padding: 13px; border-radius: 12px; font-weight: 600; font-size: 14px; text-decoration: none; text-align: center; transition: all 0.3s ease; }
        .btn-primary { background: #d4af37; color: #0a0f1e; }
        .btn-primary:hover { background: #b8922e; transform: translateY(-2px); box-shadow: 0 10px 25px rgba(212,175,55,0.2); }
        .btn-secondary { background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.8); border: 1px solid rgba(255,255,255,0.06); }
        .btn-secondary:hover { background: rgba(255,255,255,0.08); transform: translateY(-2px); }

        @media (max-width: 500px) { .btn-row { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="bg-ambient" aria-hidden="true"></div>
    <div class="grain-overlay" aria-hidden="true"></div>

    <div class="success-card">
        <div class="check-wrap"><i class="fas fa-check"></i></div>
        <h2>Booking Confirmed!</h2>
        <p>Your hotel booking has been successfully confirmed.</p>

        <div class="detail-box">
            <div class="detail-row"><span>Booking ID</span><span><?php echo htmlspecialchars($booking_no); ?></span></div>
            <div class="detail-row"><span>Hotel</span><span><?php echo htmlspecialchars($hotel_name); ?></span></div>
            <div class="detail-row"><span>Room</span><span><?php echo htmlspecialchars($room_name); ?></span></div>
            <div class="detail-row"><span>Check-in</span><span><?php echo htmlspecialchars($check_in); ?></span></div>
            <div class="detail-row"><span>Check-out</span><span><?php echo htmlspecialchars($check_out); ?></span></div>
            <div class="detail-row"><span>Total Amount</span><span class="amt">SAR <?php echo number_format($total); ?></span></div>
        </div>

        <p class="note"><i class="fas fa-envelope"></i> A confirmation email has been sent to your registered email address.</p>

        <div class="btn-row">
            <a href="dashboard.php" class="btn-primary">Go to Dashboard</a>
            <a href="services.php?type=hotels" class="btn-secondary">Book Another Hotel</a>
        </div>
    </div>
</body>
</html>