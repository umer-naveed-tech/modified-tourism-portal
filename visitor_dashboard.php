<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'visitor') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$user_id = $_SESSION['user_id'];

if(!isset($_SESSION['user_email'])) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $_SESSION['user_email'] = $user['email'];
}

// ==================== Upcoming / Past view + date-range filter ====================
// travel_date is stored as a string but always starts with YYYY-MM-DD
// (confirmed against real data for both hotel and taxi bookings), so a
// plain string comparison against today's date sorts correctly without
// needing to parse/convert the column.
$view = ($_GET['view'] ?? 'upcoming') === 'past' ? 'past' : 'upcoming';
$range = $_GET['range'] ?? 'all';
$allowed_ranges = ['all', 'yesterday', '7days', '30days'];
if (!in_array($range, $allowed_ranges)) $range = 'all';

$today = date('Y-m-d');
$where = ['user_id = ?', 'hidden_by_user = 0'];
$params = [$user_id];

if ($view === 'upcoming') {
    $where[] = 'travel_date >= ?';
    $params[] = $today;
} else {
    $where[] = 'travel_date < ?';
    $params[] = $today;
    if ($range === 'yesterday') {
        $where[] = 'travel_date >= ?';
        $params[] = date('Y-m-d', strtotime('-1 day'));
    } elseif ($range === '7days') {
        $where[] = 'travel_date >= ?';
        $params[] = date('Y-m-d', strtotime('-7 days'));
    } elseif ($range === '30days') {
        $where[] = 'travel_date >= ?';
        $params[] = date('Y-m-d', strtotime('-30 days'));
    }
}
$whereSql = implode(' AND ', $where);

// NEW: LEFT JOIN to hotels_saudi / cars so booking cards can show a real
// thumbnail image where one exists (visa bookings simply have no image
// and fall back to the icon, same as before).
$stmt = $pdo->prepare("
    SELECT b.*, h.image_url as hotel_image, h.hotel_name as hotel_name, c.image_url as car_image, c.car_name as car_name
    FROM bookings b
    LEFT JOIN hotels_saudi h ON b.service_type = 'hotel' AND b.service_id = h.id
    LEFT JOIN cars c ON b.service_type = 'taxi' AND b.service_id = c.id
    WHERE $whereSql
    ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Total bookings count for the stat card -- excludes bookings the
// customer has removed from their own view (hidden_by_user), same as
// the list below, so the number always matches what's actually visible.
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND hidden_by_user = 0");
$stmt->execute([$user_id]);
$total_bookings_all = $stmt->fetchColumn();

// Initials for the profile avatar circle, e.g. "Ahmed Khan" -> "AK"
$name_parts = preg_split('/\s+/', trim($_SESSION['user_name']));
$initials = strtoupper(substr($name_parts[0], 0, 1) . (count($name_parts) > 1 ? substr(end($name_parts), 0, 1) : ''));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Ahmed Travels</title>
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
            background:
                radial-gradient(circle at 20% 15%, rgba(212,175,55,0.10), transparent 40%),
                radial-gradient(circle at 85% 25%, rgba(212,175,55,0.06), transparent 35%),
                radial-gradient(circle at 50% 90%, rgba(212,175,55,0.08), transparent 45%);
            animation: driftGlow 24s ease-in-out infinite alternate;
        }
        .bg-ambient::after {
            content: ''; position: absolute; inset: 0; opacity: 0.4;
            background-image: radial-gradient(rgba(212,175,55,0.07) 1px, transparent 1px);
            background-size: 34px 34px;
            mask-image: radial-gradient(ellipse 80% 50% at 50% 0%, #000 40%, transparent 100%);
        }
        @keyframes driftGlow { 0% { transform: translate(0,0) scale(1); } 50% { transform: translate(-3%,2%) scale(1.06); } 100% { transform: translate(2%,-2%) scale(1.02); } }
        .bg-shape { position: absolute; color: #d4af37; opacity: 0.05; pointer-events: none; animation: motifDrift 28s ease-in-out infinite; }
        .bg-shape.s1 { font-size: 200px; top: -50px; right: -40px; }
        .bg-shape.s2 { font-size: 110px; top: 30%; left: -30px; animation-delay: -12s; opacity: 0.04; }
        @keyframes motifDrift { 0%,100% { transform: translateY(0) rotate(0); } 50% { transform: translateY(-22px) rotate(8deg); } }
        @media (prefers-reduced-motion: reduce) { .bg-ambient::before, .bg-shape { animation: none; } }

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

        .btn, button { transition: all 0.3s ease; cursor: pointer; }
        .btn:active, button:active { transform: scale(0.97); }

        input:focus { border-color: #d4af37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.08); outline: none; }

        .navbar { background: rgba(10, 15, 30, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(212, 175, 55, 0.08); padding: 14px 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-family: 'Playfair Display', serif; color: white; font-size: 23px; font-weight: 800; text-decoration: none; letter-spacing: -0.5px; }
        .logo span { color: #d4af37; }
        .nav-links a { position: relative; color: rgba(255,255,255,0.7); text-decoration: none; margin-left: 24px; font-size: 14px; transition: all 0.3s ease; }
        .nav-links a:not(.btn-logout)::after { content: ''; position: absolute; left: 0; right: 0; bottom: -4px; height: 1px; background: #d4af37; transform: scaleX(0); transition: transform 0.25s ease; }
        .nav-links a:not(.btn-logout):hover::after { transform: scaleX(1); }
        .nav-links a:hover { color: #d4af37; }
        .nav-links .btn-logout { background: rgba(239,68,68,0.1); color: #f87171; padding: 8px 20px; border-radius: 8px; margin-left: 24px; transition: all 0.3s ease; }
        .nav-links .btn-logout:hover { background: #dc2626; color: white; transform: translateY(-2px); }

        .dashboard-header { 
            position: relative; overflow: hidden;
            background: linear-gradient(180deg, #0a0f1e 0%, #0d1a2d 50%, #0a0f1e 100%);
            color: white; padding: 56px 0 64px; border-bottom: 1px solid rgba(212, 175, 55, 0.05);
        }
        .dashboard-header .gold-line { width: 60px; height: 3px; background: #d4af37; margin-bottom: 14px; border-radius: 2px; opacity: 0; animation: fadeSlideIn 0.6s ease forwards; }
        .greeting-tag { display: inline-block; font-size: 12px; color: #d4af37; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 8px; opacity: 0; animation: fadeSlideIn 0.6s ease forwards; }
        .dashboard-header h1 { font-family: 'Playfair Display', serif; font-size: 38px; font-weight: 800; margin-bottom: 8px; opacity: 0; transform: translateY(10px); animation: fadeSlideIn 0.6s ease forwards; animation-delay: 0.08s; }
        .dashboard-header p { color: rgba(255,255,255,0.5); opacity: 0; transform: translateY(10px); animation: fadeSlideIn 0.6s ease forwards; animation-delay: 0.16s; }
        @keyframes fadeSlideIn { to { opacity: 1; transform: translateY(0); } }

        /* NEW: profile summary card */
        .profile-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 18px; padding: 22px 26px; margin-top: -34px; margin-bottom: 26px;
            display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
            position: relative; z-index: 1;
        }
        .profile-avatar {
            width: 58px; height: 58px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, #d4af37, #b8922e); color: #0a0f1e;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 800;
        }
        .profile-details { flex: 1; min-width: 160px; }
        .profile-details h2 { font-family: 'Playfair Display', serif; font-size: 18px; color: white; margin-bottom: 3px; }
        .profile-details p { font-size: 12.5px; color: rgba(255,255,255,0.4); display: flex; align-items: center; gap: 6px; }
        .profile-details p i { color: #d4af37; }
        .btn-edit-profile {
            background: rgba(212,175,55,0.1); color: #d4af37; border: 1px solid rgba(212,175,55,0.15);
            padding: 9px 20px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 600;
            display: inline-flex; align-items: center; gap: 7px; transition: all 0.3s ease;
        }
        .btn-edit-profile:hover { background: #d4af37; color: #0a0f1e; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(212,175,55,0.2); }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 22px; margin-bottom: 50px; }
        .stat-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            padding: 24px 20px; border-radius: 16px; text-align: center; cursor: default;
            position: relative; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-6px); box-shadow: 0 18px 42px rgba(0,0,0,0.35); border-color: rgba(212, 175, 55, 0.2); }
        .stat-card[onclick] { cursor: pointer; }
        .stat-icon { width: 38px; height: 38px; border-radius: 11px; background: rgba(212,175,55,0.1); border: 1px solid rgba(212,175,55,0.15); display: flex; align-items: center; justify-content: center; color: #d4af37; font-size: 15px; margin: 0 auto 12px; }
        .stat-number { font-size: 30px; font-weight: 700; color: #d4af37; font-variant-numeric: tabular-nums; }
        .stat-label { font-size: 12.5px; color: rgba(255,255,255,0.4); margin-top: 6px; text-transform: uppercase; letter-spacing: 0.4px; }

        .section-title { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700; color: white; margin: 36px 0 22px; }
        .section-title .gold-line { width: 40px; height: 2px; background: #d4af37; margin-top: 6px; border-radius: 2px; }

        .services-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 22px; margin-bottom: 52px; }
        .service-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            padding: 26px 22px; border-radius: 16px; text-align: center; cursor: pointer;
            position: relative; overflow: hidden;
            transform: perspective(700px) rotateX(var(--rx,0deg)) rotateY(var(--ry,0deg));
            transition: transform 0.25s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .service-card:hover { border-color: rgba(212, 175, 55, 0.25); box-shadow: 0 20px 45px rgba(0,0,0,0.4); }
        .service-card .glare { position: absolute; inset: 0; background: radial-gradient(circle at var(--gx,50%) var(--gy,50%), rgba(212,175,55,0.12), transparent 55%); opacity: 0; transition: opacity 0.3s ease; pointer-events: none; }
        .service-card:hover .glare { opacity: 1; }
        .service-card .s-icon { position: relative; z-index: 1; width: 50px; height: 50px; margin: 0 auto 14px; border-radius: 14px; background: rgba(212,175,55,0.1); border: 1px solid rgba(212,175,55,0.15); display: flex; align-items: center; justify-content: center; color: #d4af37; font-size: 20px; transition: transform 0.35s ease; }
        .service-card:hover .s-icon { transform: scale(1.1) rotate(-5deg); }
        .service-card h3 { position: relative; z-index: 1; font-size: 18px; font-weight: 600; color: white; margin: 12px 0 4px; }
        .service-card p { position: relative; z-index: 1; font-size: 13px; color: rgba(255,255,255,0.4); margin-bottom: 18px; }
        .service-card button { position: relative; z-index: 1; background: rgba(212, 175, 55, 0.1); color: #d4af37; border: 1px solid rgba(212, 175, 55, 0.1); padding: 9px 22px; border-radius: 9px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .service-card button:hover { background: #d4af37; color: #0a0f1e; box-shadow: 0 8px 22px rgba(212,175,55,0.25); }

        /* ===== Ticket-style booking cards ===== */
        .bookings-list { display: flex; flex-direction: column; gap: 18px; margin-top: 20px; }
        .booking-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px; display: flex; align-items: stretch; overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }
        .booking-card:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,0.3); border-color: rgba(212,175,55,0.15); }
        .bk-icon-col {
            flex: 0 0 76px; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(180deg, rgba(212,175,55,0.1), rgba(212,175,55,0.02));
            border-right: 1px dashed rgba(212,175,55,0.2);
            position: relative;
        }
        /* NEW: when a real hotel/car photo is available, it fills this
           column instead of just the icon -- the icon still renders as a
           small badge over the corner so the "ticket stub" feel stays. */
        .bk-icon-col.has-image { background: none; padding: 0; }
        .bk-icon-col.has-image img { width: 100%; height: 100%; object-fit: cover; }
        .bk-icon-col.has-image .img-badge {
            position: absolute; bottom: 6px; left: 50%; transform: translateX(-50%);
            width: 26px; height: 26px; border-radius: 50%; background: rgba(10,15,30,0.75);
            display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px);
        }
        .bk-icon-col.has-image .img-badge i { font-size: 12px; }
        .bk-icon-col::before, .bk-icon-col::after {
            content: ''; position: absolute; width: 16px; height: 16px; border-radius: 50%;
            background: #0a0f1e; right: -8px;
        }
        .bk-icon-col::before { top: -8px; }
        .bk-icon-col::after { bottom: -8px; }
        .bk-icon-col i { font-size: 22px; color: #d4af37; }
        .bk-body { flex: 1; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; }
        .booking-info h4 { font-size: 15.5px; font-weight: 700; color: white; margin-bottom: 4px; font-family: monospace; letter-spacing: 0.3px; }
        .booking-info p { font-size: 12.5px; color: rgba(255,255,255,0.4); }
        .booking-status { display: inline-flex; align-items: center; gap: 6px; padding: 5px 13px; border-radius: 20px; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .booking-status .dot { width: 6px; height: 6px; border-radius: 50%; }
        .status-pending { background: rgba(251,191,36,0.1); color: #fbbf24; }
        .status-pending .dot { background: #fbbf24; animation: pulseDot 1.6s ease infinite; }
        .status-confirmed { background: rgba(16,185,129,0.1); color: #34d399; }
        .status-confirmed .dot { background: #34d399; }
        .status-completed { background: rgba(59,130,246,0.1); color: #60a5fa; }
        .status-completed .dot { background: #60a5fa; }
        .status-cancelled { background: rgba(239,68,68,0.1); color: #f87171; }
        .status-cancelled .dot { background: #f87171; }
        @keyframes pulseDot { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
        .booking-actions { display: flex; gap: 10px; }
        .btn-cancel, .btn-support, .btn-remove { padding: 7px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; transition: all 0.3s ease; font-family: inherit; }
        .btn-cancel { background: rgba(239,68,68,0.1); color: #f87171; }
        .btn-cancel:hover { background: #dc2626; color: white; transform: translateY(-2px); }
        .btn-remove { background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.5); border: 1px solid rgba(255,255,255,0.06); }
        .btn-remove:hover { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.85); transform: translateY(-2px); }
        .btn-support { background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,0.05); }
        .btn-support:hover { background: #d4af37; color: #0a0f1e; transform: translateY(-2px); }

        /* NEW: Upcoming / Past tabs + quick date-range pills */
        .booking-tabs { display: flex; gap: 6px; margin-top: 20px; border-bottom: 1px solid rgba(255,255,255,0.06); flex-wrap: wrap; }
        .booking-tab {
            position: relative; padding: 10px 20px; color: rgba(255,255,255,0.5); text-decoration: none;
            font-size: 14px; font-weight: 500; border-bottom: 2px solid transparent; transition: all 0.2s ease;
        }
        .booking-tab:hover { color: rgba(255,255,255,0.85); }
        .booking-tab.active { color: #d4af37; border-bottom-color: #d4af37; }
        .range-pills { display: flex; gap: 8px; flex-wrap: wrap; margin: 16px 0 6px; }
        .range-pill {
            padding: 6px 16px; border-radius: 20px; font-size: 12.5px; font-weight: 500; text-decoration: none;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); color: rgba(255,255,255,0.5);
            transition: all 0.25s ease;
        }
        .range-pill:hover { border-color: rgba(212,175,55,0.2); color: #d4af37; }
        .range-pill.active { background: #d4af37; color: #0a0f1e; border-color: #d4af37; }

        .empty-state { background: rgba(255,255,255,0.02); padding: 56px; text-align: center; border-radius: 16px; border: 1px solid rgba(255,255,255,0.03); }
        .empty-state i { font-size: 36px; color: rgba(212,175,55,0.3); margin-bottom: 14px; display: block; }
        .empty-state p { color: rgba(255,255,255,0.3); }
        .empty-state a { color: #d4af37; text-decoration: none; font-weight: 600; }
        .empty-state a:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            .stats-grid, .services-grid { grid-template-columns: repeat(2, 1fr); }
            .bk-icon-col { flex-basis: 56px; }
            .bk-icon-col i { font-size: 18px; }
        }
    </style>
</head>
<body>

<div class="bg-ambient" aria-hidden="true">
    <i class="fas fa-star-and-crescent bg-shape s1"></i>
    <i class="fas fa-star-and-crescent bg-shape s2"></i>
</div>
<div class="grain-overlay" aria-hidden="true"></div>
<div class="page-transition" id="pageTransition"><div class="pt-spinner"><div class="pt-ring"></div><i class="fas fa-plane pt-icon"></i></div></div>

<div class="page-content">
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">Ahmed<span>Travels</span></a>
            <div class="nav-links">
                <a href="services.php">Services</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
    </nav>

    <div class="dashboard-header">
        <div class="container">
            <div class="greeting-tag" id="greetingTag">Welcome back</div>
            <div class="gold-line"></div>
            <h1>Hello, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
            <p>Manage your bookings and explore new destinations</p>
        </div>
    </div>

    <div class="container">
        <div class="profile-card reveal">
            <div class="profile-avatar"><?php echo htmlspecialchars($initials); ?></div>
            <div class="profile-details">
                <h2><?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
            </div>
            <a href="edit_profile.php" class="btn-edit-profile"><i class="fas fa-pen"></i> Edit Profile</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card reveal">
                <div class="stat-icon"><i class="fas fa-ticket"></i></div>
                <div class="stat-number" data-count="<?php echo $total_bookings_all; ?>">0</div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-icon"><i class="fas fa-earth-asia"></i></div>
                <div class="stat-number">10+</div>
                <div class="stat-label">Destinations</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-icon"><i class="fas fa-headset"></i></div>
                <div class="stat-number">24/7</div>
                <div class="stat-label">Support</div>
            </div>
            <div class="stat-card reveal" onclick="window.open('https://wa.me/923001234567', '_blank')" style="cursor:pointer;">
                <div class="stat-icon" style="background:rgba(37,211,102,0.12); border-color:rgba(37,211,102,0.2); color:#25D366;"><i class="fab fa-whatsapp"></i></div>
                <div class="stat-number" style="color:#25D366; font-size:20px;">WhatsApp</div>
                <div class="stat-label">Chat Now</div>
            </div>
        </div>
        
        <div class="section-title reveal">
            Quick Booking
            <div class="gold-line"></div>
        </div>
        <div class="services-grid">
            <div class="service-card reveal" onclick="goTo('services.php?type=hotels&city=Mecca')">
                <div class="glare"></div>
                <div class="s-icon"><i class="fas fa-hotel"></i></div>
                <h3>Hotels</h3>
                <p>Luxury stays in Mecca</p>
                <button>Book Hotel</button>
            </div>
            <div class="service-card reveal" onclick="goTo('services.php?type=taxi')">
                <div class="glare"></div>
                <div class="s-icon"><i class="fas fa-car"></i></div>
                <h3>Airport Taxi</h3>
                <p>Rent a car with driver</p>
                <button>Book Taxi</button>
            </div>
            <div class="service-card reveal" onclick="goTo('services.php?type=visa')">
                <div class="glare"></div>
                <div class="s-icon"><i class="fas fa-passport"></i></div>
                <h3>Visa Services</h3>
                <p>Fast processing</p>
                <button>Apply Now</button>
            </div>
        </div>
        
        <div id="ajaxContent">
        <div class="section-title reveal">
            My Bookings
            <div class="gold-line"></div>
        </div>

        <div class="booking-tabs">
            <a href="?view=upcoming" class="booking-tab <?php echo $view === 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
            <a href="?view=past" class="booking-tab <?php echo $view === 'past' ? 'active' : ''; ?>">Past</a>
        </div>

        <?php if ($view === 'past'): ?>
        <div class="range-pills">
            <a href="?view=past&range=all" class="range-pill <?php echo $range === 'all' ? 'active' : ''; ?>">All Past</a>
            <a href="?view=past&range=yesterday" class="range-pill <?php echo $range === 'yesterday' ? 'active' : ''; ?>">Yesterday</a>
            <a href="?view=past&range=7days" class="range-pill <?php echo $range === '7days' ? 'active' : ''; ?>">Last 7 Days</a>
            <a href="?view=past&range=30days" class="range-pill <?php echo $range === '30days' ? 'active' : ''; ?>">Last Month</a>
        </div>
        <?php endif; ?>

        <?php if(count($bookings) > 0): ?>
            <div class="bookings-list">
                <?php foreach($bookings as $b): 
                    $created_at = new DateTime($b['created_at']);
                    $cancel_deadline = clone $created_at;
                    $cancel_deadline->modify('+60 minutes');
                    $now = new DateTime();
                    $can_cancel = ($now <= $cancel_deadline) && ($b['status'] == 'pending');
                    $bk_icon = ($b['service_type'] === 'hotel') ? 'fa-hotel' : (($b['service_type'] === 'taxi') ? 'fa-car' : 'fa-passport');
                    $bk_image = $b['hotel_image'] ?? $b['car_image'] ?? null;
                ?>
                <div class="booking-card reveal">
                    <div class="bk-icon-col<?php echo $bk_image ? ' has-image' : ''; ?>">
                        <?php if ($bk_image): ?>
                            <img src="<?php echo htmlspecialchars($bk_image); ?>" alt="" onerror="this.parentElement.classList.remove('has-image'); this.remove();">
                            <span class="img-badge"><i class="fas <?php echo $bk_icon; ?>"></i></span>
                        <?php else: ?>
                            <i class="fas <?php echo $bk_icon; ?>"></i>
                        <?php endif; ?>
                    </div>
                    <div class="bk-body">
                        <div class="booking-info">
                            <h4><?php echo htmlspecialchars($b['booking_no']); ?></h4>
                            <p><?php echo htmlspecialchars(ucfirst($b['service_type'])); ?> | Travel Date: <?php echo htmlspecialchars($b['travel_date']); ?></p>
                        </div>
                        <div>
                            <span class="booking-status status-<?php echo htmlspecialchars($b['status']); ?>"><span class="dot"></span><?php echo htmlspecialchars(ucfirst($b['status'])); ?></span>
                        </div>
                        <div class="booking-info">
                            <p>Amount: <strong style="color:#d4af37; font-size:14px;">SAR <?php echo number_format($b['total_amount']); ?></strong></p>
                        </div>
                        <div class="booking-actions">
                            <?php if($can_cancel): ?>
                                <a href="cancel_booking.php?id=<?php echo (int)$b['id']; ?>" class="btn-cancel" onclick="return confirm('Cancel this booking?')">Cancel</a>
                            <?php else: ?>
                                <button type="button" class="btn-remove" data-id="<?php echo (int)$b['id']; ?>">Remove</button>
                            <?php endif; ?>
                            <a href="https://wa.me/923001234567?text=<?php echo urlencode('Help with booking ' . $b['booking_no']); ?>" class="btn-support" target="_blank">Support</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-suitcase-rolling"></i>
                <p><?php echo $view === 'upcoming' ? 'No upcoming bookings.' : 'No past bookings in this range.'; ?> <a href="services.php">Book a service</a> to get started!</p>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function goTo(url) {
        document.getElementById('pageTransition').classList.add('active');
        setTimeout(() => { window.location.href = url; }, 180);
    }

    /* edit-profile goes to a genuinely different page, so it keeps the
       original full-navigation + loading-overlay behavior. */
    document.querySelectorAll('.btn-edit-profile').forEach(a => {
        a.addEventListener('click', function() {
            document.getElementById('pageTransition').classList.add('active');
        });
    });

    /* NEW: time-based greeting (purely additive, doesn't touch the
       server-rendered name/heading at all) */
    (function() {
        const h = new Date().getHours();
        const tag = document.getElementById('greetingTag');
        if (!tag) return;
        if (h < 12) tag.textContent = 'Good morning';
        else if (h < 17) tag.textContent = 'Good afternoon';
        else tag.textContent = 'Good evening';
    })();

    /* NEW: animated count-up for stat numbers with data-count */
    document.querySelectorAll('.stat-number[data-count]').forEach(el => {
        const target = parseInt(el.getAttribute('data-count'), 10) || 0;
        let current = 0;
        const step = Math.max(1, Math.ceil(target / 24));
        const timer = setInterval(() => {
            current += step;
            if (current >= target) { current = target; clearInterval(timer); }
            el.textContent = current;
        }, 35);
    });

    /* NEW: 3D tilt + glare on service cards (fine-pointer only) */
    if (window.matchMedia('(pointer: fine)').matches) {
        document.querySelectorAll('.service-card').forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const r = card.getBoundingClientRect();
                const px = (e.clientX - r.left) / r.width;
                const py = (e.clientY - r.top) / r.height;
                card.style.setProperty('--rx', ((0.5 - py) * 8).toFixed(2) + 'deg');
                card.style.setProperty('--ry', ((px - 0.5) * 8).toFixed(2) + 'deg');
                card.style.setProperty('--gx', (px * 100).toFixed(1) + '%');
                card.style.setProperty('--gy', (py * 100).toFixed(1) + '%');
            });
            card.addEventListener('mouseleave', () => {
                card.style.setProperty('--rx', '0deg');
                card.style.setProperty('--ry', '0deg');
            });
        });
    }

    let revealObserver;
    function bindReveal(root) {
        if ('IntersectionObserver' in window) {
            if (!revealObserver) {
                revealObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) { entry.target.classList.add('in-view'); revealObserver.unobserve(entry.target); }
                    });
                }, { threshold: 0.1 });
            }
            root.querySelectorAll('.reveal:not(.in-view)').forEach(el => revealObserver.observe(el));
        } else {
            root.querySelectorAll('.reveal').forEach(el => el.classList.add('in-view'));
        }
    }
    bindReveal(document.body);

    /* NEW: AJAX partial-refresh for the Upcoming/Past tabs and date-range
       pills -- these only change which bookings are listed inside
       #ajaxContent, so instead of a full page navigation (which resets
       scroll position back to the top) we fetch the same page, pull out
       just the #ajaxContent portion, and swap it in. Falls back to a
       normal navigation if anything looks unexpected (e.g. session
       expired), so the action is never silently lost. */
    const ajaxContentEl = document.getElementById('ajaxContent');
    const csrfToken = <?php echo json_encode(csrf_token()); ?>;
    let ajaxLoading = false;

    function loadAjaxContent(url) {
        if (ajaxLoading || !ajaxContentEl) { window.location.href = url; return; }
        ajaxLoading = true;
        ajaxContentEl.style.opacity = '0.45';
        ajaxContentEl.style.pointerEvents = 'none';

        fetch(url)
            .then(r => r.text())
            .then(html => {
                const parsed = new DOMParser().parseFromString(html, 'text/html');
                const newContent = parsed.getElementById('ajaxContent');
                if (!newContent) { window.location.href = url; return; }

                ajaxContentEl.innerHTML = newContent.innerHTML;
                const isSameUrl = (url === window.location.pathname + window.location.search);
                if (isSameUrl) history.replaceState({ ajaxUrl: url }, '', url);
                else history.pushState({ ajaxUrl: url }, '', url);

                bindReveal(ajaxContentEl);
                bindAjaxLinks(ajaxContentEl);
                bindRemoveButtons(ajaxContentEl);
            })
            .catch(() => { window.location.href = url; })
            .finally(() => {
                ajaxLoading = false;
                if (ajaxContentEl) {
                    ajaxContentEl.style.opacity = '';
                    ajaxContentEl.style.pointerEvents = '';
                }
            });
    }

    function bindAjaxLinks(root) {
        root.querySelectorAll('.booking-tab, .range-pill').forEach(a => {
            a.addEventListener('click', function(e) {
                e.preventDefault();
                loadAjaxContent(this.getAttribute('href'));
            });
        });
    }

    /* NEW: "Remove" button -- lets the customer take a booking off
       their own dashboard (hidden_by_user=1 server-side). Does NOT
       touch the booking's status and does NOT delete anything -- the
       agent still sees it exactly as before. After a successful
       remove, refreshes just #ajaxContent so the card disappears
       without a full page reload. */
    function bindRemoveButtons(root) {
        root.querySelectorAll('.btn-remove').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                if (!confirm('Remove this booking from your dashboard? You can still see it if you contact support later.')) return;

                this.disabled = true;
                const originalText = this.textContent;
                this.textContent = 'Removing...';

                fetch('hide_booking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        loadAjaxContent(window.location.pathname + window.location.search);
                    } else {
                        alert('Could not remove this booking: ' + (data.error || 'unknown error'));
                        this.disabled = false;
                        this.textContent = originalText;
                    }
                })
                .catch(() => {
                    alert('Network error. Please try again.');
                    this.disabled = false;
                    this.textContent = originalText;
                });
            });
        });
    }

    window.addEventListener('popstate', function() {
        loadAjaxContent(window.location.pathname + window.location.search);
    });

    if (ajaxContentEl) {
        bindAjaxLinks(ajaxContentEl);
        bindRemoveButtons(ajaxContentEl);
    }

    /* NEW BUG FIX: when a page is restored from the browser's
       back-forward cache (bfcache) via the Back/Forward button, the
       page-transition overlay can still be showing (frozen in the
       "active" state it was in right when the user clicked away) since
       no fresh page-load JS runs on a bfcache restore. This listens for
       that restore and clears the overlay so the page is visible again. */
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            const pt = document.getElementById('pageTransition');
            if (pt) pt.classList.remove('active');
        }
    });
</script>

<?php include 'chatbot_widget.php'; ?>
</body>
</html>