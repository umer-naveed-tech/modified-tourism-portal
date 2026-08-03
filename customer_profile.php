<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$user_id = (int)($_GET['user_id'] ?? 0);
if (!$user_id) {
    header('Location: agent_dashboard.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: agent_dashboard.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM bookings WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

$total_bookings_count = count($bookings);

// "Total Spent" matches the same definition already used for the site-wide
// "Total Revenue" stat on agent_dashboard.php (payment_status = 'paid'),
// for consistency rather than inventing a new rule.
$stmt = $pdo->prepare("SELECT SUM(total_amount) FROM bookings WHERE user_id = ? AND payment_status = 'paid'");
$stmt->execute([$user_id]);
$total_spent = $stmt->fetchColumn() ?? 0;

// Status breakdown for this customer
$status_counts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
foreach ($bookings as $b) {
    if (isset($status_counts[$b['status']])) $status_counts[$b['status']]++;
}

// VIP threshold: 5+ total bookings. Adjustable -- just change this number.
$vip_threshold = 5;
$is_vip = $total_bookings_count >= $vip_threshold;

$typeLabels = ['hotel' => 'Hotel', 'taxi' => 'Taxi'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Profile | Ahmed Travels</title>
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
            background: radial-gradient(circle at 20% 15%, rgba(212,175,55,0.10), transparent 40%),
                        radial-gradient(circle at 85% 25%, rgba(212,175,55,0.06), transparent 35%),
                        radial-gradient(circle at 50% 90%, rgba(212,175,55,0.08), transparent 45%);
            animation: driftGlow 24s ease-in-out infinite alternate;
        }
        @keyframes driftGlow { 0% { transform: translate(0,0) scale(1); } 50% { transform: translate(-3%,2%) scale(1.06); } 100% { transform: translate(2%,-2%) scale(1.02); } }
        .grain-overlay {
            position: fixed; inset: 0; z-index: 9997; pointer-events: none; opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        .page-transition {
            position: fixed; inset: 0; z-index: 99999; background: #0a0f1e;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .page-transition.active { opacity: 1; visibility: visible; }
        .pt-spinner { position: relative; width: 64px; height: 64px; }
        .pt-ring { position: absolute; inset: 0; border: 2px solid rgba(212,175,55,0.15); border-top-color: #d4af37; border-radius: 50%; animation: ptSpin 0.9s linear infinite; }
        .pt-icon { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #d4af37; font-size: 20px; animation: ptSpin 0.9s linear infinite reverse; }
        @keyframes ptSpin { to { transform: rotate(360deg); } }

        .page-content { position: relative; z-index: 1; animation: fadeIn 0.5s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        .reveal { opacity: 0; transform: translateY(20px); transition: opacity 0.6s cubic-bezier(.2,.7,.3,1), transform 0.6s cubic-bezier(.2,.7,.3,1); }
        .reveal.in-view { opacity: 1; transform: translateY(0); }

        .navbar { background: rgba(10, 15, 30, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(212, 175, 55, 0.08); padding: 14px 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1000px; margin: 0 auto; padding: 0 24px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-family: 'Playfair Display', serif; color: white; font-size: 22px; font-weight: 800; text-decoration: none; letter-spacing: -0.5px; }
        .logo span { color: #d4af37; }
        .back-link { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 13.5px; display: flex; align-items: center; gap: 6px; transition: all 0.2s ease; }
        .back-link:hover { color: #d4af37; }

        .profile-header {
            padding: 44px 0 30px; position: relative; overflow: hidden;
        }
        .profile-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px; padding: 32px; display: flex; align-items: center; gap: 24px; flex-wrap: wrap;
            opacity: 0; transform: translateY(16px); animation: cardIn 0.6s cubic-bezier(.2,.8,.3,1) forwards;
        }
        @keyframes cardIn { to { opacity: 1; transform: translateY(0); } }
        .avatar {
            width: 74px; height: 74px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, #d4af37, #b8922e); color: #0a0f1e;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 28px; font-weight: 800;
        }
        .profile-info { flex: 1; min-width: 200px; }
        .profile-info h1 { font-family: 'Playfair Display', serif; font-size: 24px; color: white; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .vip-badge {
            display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700;
            background: linear-gradient(135deg, #d4af37, #f4d976); color: #0a0f1e;
            padding: 4px 12px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px;
            box-shadow: 0 4px 14px rgba(212,175,55,0.3);
        }
        .profile-info .meta-row { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 8px; }
        .profile-info .meta-item { font-size: 13px; color: rgba(255,255,255,0.5); display: flex; align-items: center; gap: 6px; }
        .profile-info .meta-item i { color: #d4af37; width: 14px; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin: 28px 0; }
        .stat-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            padding: 20px; border-radius: 14px; text-align: center; transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 14px 34px rgba(0,0,0,0.3); border-color: rgba(212,175,55,0.15); }
        .stat-number { font-size: 26px; font-weight: 700; color: #d4af37; }
        .stat-label { font-size: 11.5px; color: rgba(255,255,255,0.4); margin-top: 4px; text-transform: uppercase; letter-spacing: 0.4px; }

        .status-breakdown { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 28px; }
        .status-pill { padding: 7px 16px; border-radius: 20px; font-size: 12.5px; font-weight: 600; display: flex; align-items: center; gap: 7px; }
        .status-pill .dot { width: 7px; height: 7px; border-radius: 50%; }
        .sp-pending { background: rgba(251,191,36,0.1); color: #fbbf24; } .sp-pending .dot { background: #fbbf24; }
        .sp-confirmed { background: rgba(16,185,129,0.1); color: #34d399; } .sp-confirmed .dot { background: #34d399; }
        .sp-completed { background: rgba(59,130,246,0.1); color: #60a5fa; } .sp-completed .dot { background: #60a5fa; }
        .sp-cancelled { background: rgba(239,68,68,0.1); color: #f87171; } .sp-cancelled .dot { background: #f87171; }

        .section-title { font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; color: white; margin-bottom: 18px; display: flex; align-items: center; gap: 12px; }
        .section-title .gold-line { width: 36px; height: 2px; background: #d4af37; border-radius: 2px; }

        .bookings-list { display: flex; flex-direction: column; gap: 16px; padding-bottom: 50px; }
        .booking-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px; display: flex; align-items: stretch; overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }
        .booking-card:hover { transform: translateY(-3px); box-shadow: 0 14px 34px rgba(0,0,0,0.28); border-color: rgba(212,175,55,0.12); }
        .bk-icon-col {
            flex: 0 0 64px; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(180deg, rgba(212,175,55,0.1), rgba(212,175,55,0.02));
            border-right: 1px dashed rgba(212,175,55,0.2);
        }
        .bk-icon-col i { font-size: 19px; color: #d4af37; }
        .bk-body { flex: 1; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .booking-info h4 { font-size: 14.5px; font-weight: 700; color: white; margin-bottom: 3px; font-family: monospace; }
        .booking-info p { font-size: 12px; color: rgba(255,255,255,0.4); }
        .booking-status { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .booking-status .dot { width: 6px; height: 6px; border-radius: 50%; }
        .status-pending { background: rgba(251,191,36,0.1); color: #fbbf24; } .status-pending .dot { background: #fbbf24; }
        .status-confirmed { background: rgba(16,185,129,0.1); color: #34d399; } .status-confirmed .dot { background: #34d399; }
        .status-completed { background: rgba(59,130,246,0.1); color: #60a5fa; } .status-completed .dot { background: #60a5fa; }
        .status-cancelled { background: rgba(239,68,68,0.1); color: #f87171; } .status-cancelled .dot { background: #f87171; }

        .empty-state { text-align: center; padding: 50px; background: rgba(255,255,255,0.02); border-radius: 16px; border: 1px solid rgba(255,255,255,0.03); }
        .empty-state i { font-size: 32px; color: rgba(212,175,55,0.3); margin-bottom: 12px; display: block; }
        .empty-state p { color: rgba(255,255,255,0.3); }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .profile-card { padding: 24px; }
        }
    </style>
</head>
<body>

<div class="bg-ambient" aria-hidden="true"></div>
<div class="grain-overlay" aria-hidden="true"></div>
<div class="page-transition" id="pageTransition"><div class="pt-spinner"><div class="pt-ring"></div><i class="fas fa-plane pt-icon"></i></div></div>

<div class="page-content">
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">Ahmed<span>Travels</span></a>
            <a href="agent_dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </nav>

    <div class="container">
        <div class="profile-header">
            <div class="profile-card reveal">
                <div class="avatar"><?php echo strtoupper(substr($customer['name'], 0, 1)); ?></div>
                <div class="profile-info">
                    <h1>
                        <?php echo htmlspecialchars($customer['name']); ?>
                        <?php if ($is_vip): ?>
                            <span class="vip-badge"><i class="fas fa-crown"></i> VIP Customer</span>
                        <?php endif; ?>
                    </h1>
                    <div class="meta-row">
                        <span class="meta-item"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($customer['email']); ?></span>
                        <?php if (!empty($customer['phone'])): ?>
                            <span class="meta-item"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($customer['phone']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($customer['phone'])): ?>
                    <a href="https://wa.me/<?php echo preg_replace('/^0/', '92', $customer['phone']); ?>" class="btn-wa-profile" target="_blank"
                       style="background:rgba(37,211,102,0.1); color:#25D366; padding:10px 18px; border-radius:10px; text-decoration:none; font-size:13px; font-weight:600; border:1px solid rgba(37,211,102,0.15); display:flex; align-items:center; gap:8px; transition:all 0.3s ease;">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card reveal">
                <div class="stat-number"><?php echo $total_bookings_count; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-number" style="color:#34d399;">SAR <?php echo number_format($total_spent); ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-number"><?php echo $status_counts['completed']; ?></div>
                <div class="stat-label">Completed Trips</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-number"><?php echo $status_counts['cancelled']; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>

        <div class="status-breakdown reveal">
            <span class="status-pill sp-pending"><span class="dot"></span>Pending: <?php echo $status_counts['pending']; ?></span>
            <span class="status-pill sp-confirmed"><span class="dot"></span>Confirmed: <?php echo $status_counts['confirmed']; ?></span>
            <span class="status-pill sp-completed"><span class="dot"></span>Completed: <?php echo $status_counts['completed']; ?></span>
            <span class="status-pill sp-cancelled"><span class="dot"></span>Cancelled: <?php echo $status_counts['cancelled']; ?></span>
        </div>

        <div class="section-title">
            Booking History
            <div class="gold-line"></div>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <i class="fas fa-suitcase-rolling"></i>
                <p>This customer has no bookings yet.</p>
            </div>
        <?php else: ?>
            <div class="bookings-list">
                <?php foreach ($bookings as $b):
                    $bk_icon = ($b['service_type'] === 'hotel') ? 'fa-hotel' : (($b['service_type'] === 'taxi') ? 'fa-car' : 'fa-passport');
                ?>
                <div class="booking-card reveal">
                    <div class="bk-icon-col"><i class="fas <?php echo $bk_icon; ?>"></i></div>
                    <div class="bk-body">
                        <div class="booking-info">
                            <h4><?php echo htmlspecialchars($b['booking_no']); ?></h4>
                            <p><?php echo htmlspecialchars(ucfirst($b['service_type'])); ?> &middot; Travel: <?php echo htmlspecialchars($b['travel_date']); ?> &middot; Booked: <?php echo date('d M Y', strtotime($b['created_at'])); ?></p>
                        </div>
                        <strong style="color:#d4af37; font-size:14px;">SAR <?php echo number_format($b['total_amount']); ?></strong>
                        <span class="booking-status status-<?php echo htmlspecialchars($b['status']); ?>"><span class="dot"></span><?php echo htmlspecialchars(ucfirst($b['status'])); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.querySelectorAll('.back-link, .logo').forEach(a => {
        a.addEventListener('click', function() {
            document.getElementById('pageTransition').classList.add('active');
        });
    });

    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) { entry.target.classList.add('in-view'); revealObserver.unobserve(entry.target); }
            });
        }, { threshold: 0.1 });
        document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));
    } else {
        document.querySelectorAll('.reveal').forEach(el => el.classList.add('in-view'));
    }

    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            const pt = document.getElementById('pageTransition');
            if (pt) pt.classList.remove('active');
        }
    });
</script>

</body>
</html>