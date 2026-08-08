<?php
session_start();
require_once 'config.php';

$type = $_GET['type'] ?? 'hotels';
if($type == 'ziyarat' || $type == 'groups') {
    header('Location: services.php?type=hotels');
    exit();
}
$city = $_GET['city'] ?? 'Mecca';

// NEW: pagination for the hotels list only -- this is the one list on
// the site most likely to keep growing (new hotels get added
// regularly), so it's the one that benefits from not rendering
// everything at once. Taxi/visa lists stay as they were.
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;
$totalHotels = 0;
$totalPages = 1;

if($type == 'hotels') {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM hotels_saudi WHERE city = ?");
    $countStmt->execute([$city]);
    $totalHotels = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalHotels / $per_page));

    $stmt = $pdo->prepare("SELECT * FROM hotels_saudi WHERE city = ? ORDER BY hotel_name ASC LIMIT $per_page OFFSET $offset");
    $stmt->execute([$city]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif($type == 'taxi') {
    $stmt = $pdo->prepare("SELECT * FROM cars");
    $stmt->execute();
    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif($type == 'visa') {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE service_type = 'visa'");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $services = [];
}

// FIX: pulls cities from BOTH from_city and to_city columns, so every
// city that appears anywhere in a route shows up in both the pickup
// and drop-off dropdowns -- previously a city that only ever appeared
// as a destination (never as a starting point) was invisible here.
$stmt = $pdo->query("
    SELECT DISTINCT city FROM (
        SELECT from_city AS city FROM car_fares
        UNION
        SELECT to_city AS city FROM car_fares
    ) all_cities
    WHERE city NOT LIKE '%ZIARAT%'
    ORDER BY city
");
$cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
if(empty($cities)) {
    $cities = ['JEDDAH', 'MAKKAH', 'MADINA', 'JEDDAH ARPT', 'MADINA ARPT', 'MADINAH HTL'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services | Ahmed Travels</title>
    <!-- NEW: preconnect hints -- tells the browser to start the
         DNS/TLS handshake for these external domains immediately,
         instead of waiting until it parses the <link> tags below.
         Purely a network-timing optimization; nothing about the
         page's content or behavior changes. -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #0a0f1e; 
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ===== Shared site theme ===== */
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }
        html { scrollbar-color: #8a6d1f #0a0f1e; scrollbar-width: thin; }

        .bg-ambient { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; background: #0a0f1e; }
        .bg-ambient::before {
            content: '';
            position: absolute; inset: -20%;
            background:
                radial-gradient(circle at 15% 10%, rgba(212,175,55,0.16), transparent 38%),
                radial-gradient(circle at 88% 20%, rgba(212,175,55,0.10), transparent 32%),
                radial-gradient(circle at 50% 100%, rgba(212,175,55,0.13), transparent 42%),
                radial-gradient(circle at 75% 70%, rgba(59,90,130,0.14), transparent 40%);
            animation: driftGlow 26s ease-in-out infinite alternate;
        }
        .bg-ambient::after {
            content: '';
            position: absolute; inset: 0;
            opacity: 0.5;
            background-image: radial-gradient(rgba(212,175,55,0.09) 1px, transparent 1px);
            background-size: 32px 32px;
            mask-image: radial-gradient(ellipse 90% 60% at 50% 0%, #000 40%, transparent 100%);
        }
        @keyframes driftGlow { 0% { transform: translate(0,0) scale(1); } 50% { transform: translate(-3%,2%) scale(1.07); } 100% { transform: translate(2%,-2%) scale(1.03); } }
        @media (prefers-reduced-motion: reduce) { .bg-ambient::before { animation: none; } }

        /* NEW: large, faint, slow-drifting Islamic star/crescent motifs --
           thematically tied to the Hajj/Umrah travel context, tasteful
           and subtle so they never fight with the card content on top. */
        .bg-motif { position: absolute; color: #d4af37; opacity: 0.05; pointer-events: none; animation: motifDrift 30s ease-in-out infinite; }
        .bg-motif.m1 { font-size: 260px; top: -70px; right: -60px; }
        .bg-motif.m2 { font-size: 150px; bottom: 4%; left: -40px; animation-delay: -10s; }
        .bg-motif.m3 { font-size: 90px; top: 40%; right: 6%; animation-delay: -20s; opacity: 0.035; }
        @keyframes motifDrift { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-26px) rotate(8deg); } }
        @media (prefers-reduced-motion: reduce) { .bg-motif { animation: none; } }

        /* NEW: soft vignette so the hero heading area feels a touch more
           spotlighted / less flat */
        .bg-vignette {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background: radial-gradient(ellipse 70% 45% at 50% 8%, rgba(212,175,55,0.05), transparent 65%);
        }

        .grain-overlay {
            position: fixed; inset: 0; z-index: 9997; pointer-events: none; opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* NEW: page-transition loading overlay -- fades in instantly on
           click, giving the user immediate feedback instead of a frozen
           screen while the next page (or a slower query, e.g. switching
           hotel city) loads. */
        .page-transition {
            position: fixed; inset: 0; z-index: 99999;
            background: #0a0f1e;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .page-transition.active { opacity: 1; visibility: visible; }
        .pt-spinner { position: relative; width: 64px; height: 64px; }
        .pt-ring {
            position: absolute; inset: 0;
            border: 2px solid rgba(212,175,55,0.15);
            border-top-color: #d4af37;
            border-radius: 50%;
            animation: ptSpin 0.9s linear infinite;
        }
        .pt-icon {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            color: #d4af37; font-size: 20px;
            animation: ptSpin 0.9s linear infinite reverse;
        }
        @keyframes ptSpin { to { transform: rotate(360deg); } }

        .page-content { position: relative; z-index: 1; animation: fadeIn 0.5s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        /* ===== Reveal-on-scroll (additive utility) ===== */
        .reveal { opacity: 0; transform: translateY(20px); transition: opacity 0.6s cubic-bezier(.2,.7,.3,1), transform 0.6s cubic-bezier(.2,.7,.3,1); }
        .reveal.in-view { opacity: 1; transform: translateY(0); }

        .btn, button, .tab-link, .city-tab, .service-card-btn {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn:hover, button:hover, .service-card-btn:hover { transform: translateY(-2px); }
        .btn:active, button:active { transform: scale(0.97); }

        .service-card { transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease; }
        .service-card:hover { transform: translateY(-6px); box-shadow: 0 18px 45px rgba(0,0,0,0.35); }

        input:focus, select:focus { border-color: #d4af37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.08); outline: none; }

        .navbar { background: rgba(10, 15, 30, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(212, 175, 55, 0.08); padding: 14px 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-family: 'Playfair Display', serif; color: white; font-size: 23px; font-weight: 800; text-decoration: none; letter-spacing: -0.5px; }
        .logo span { color: #d4af37; }
        .nav-links a { position: relative; color: rgba(255,255,255,0.7); text-decoration: none; margin-left: 24px; font-size: 14px; transition: all 0.3s ease; }
        .nav-links a:not(.btn-logout):not(.btn-login)::after { content: ''; position: absolute; left: 0; right: 0; bottom: -4px; height: 1px; background: #d4af37; transform: scaleX(0); transition: transform 0.25s ease; }
        .nav-links a:not(.btn-logout):not(.btn-login):hover::after { transform: scaleX(1); }
        .nav-links a:hover { color: #d4af37; }
        .nav-links .btn-logout { background: rgba(239,68,68,0.1); color: #f87171; padding: 8px 20px; border-radius: 8px; margin-left: 24px; transition: all 0.3s ease; }
        .nav-links .btn-logout:hover { background: #dc2626; color: white; transform: translateY(-2px); }
        .nav-links .btn-login { background: rgba(212,175,55,0.1); color: #d4af37; padding: 8px 20px; border-radius: 8px; margin-left: 24px; transition: all 0.3s ease; }
        .nav-links .btn-login:hover { background: #d4af37; color: #0a0f1e; transform: translateY(-2px); }

        /* ===== Page heading (new, purely additive block above tabs) ===== */
        .page-heading { text-align: center; padding: 44px 0 8px; }
        .page-heading .gold-line { width: 50px; height: 3px; background: #d4af37; margin: 0 auto 14px; border-radius: 2px; }
        .page-heading h1 { font-family: 'Playfair Display', serif; font-size: 30px; color: white; font-weight: 800; }
        .page-heading p { color: rgba(255,255,255,0.4); font-size: 14px; margin-top: 8px; }

        .tabs { display: flex; gap: 8px; border-bottom: 1px solid rgba(255,255,255,0.04); margin: 32px 0 32px; flex-wrap: wrap; justify-content: center; }
        .tab-link { 
            position: relative;
            padding: 12px 28px; 
            font-size: 15px; 
            font-weight: 500; 
            color: rgba(255,255,255,0.4); 
            text-decoration: none; 
            border-radius: 8px 8px 0 0;
        }
        .tab-link:hover { color: #d4af37; background: rgba(255,255,255,0.02); }
        .tab-link.active { 
            color: #d4af37; 
            background: rgba(212,175,55,0.04);
        }
        .tab-link.active::after {
            content: '';
            position: absolute; left: 14px; right: 14px; bottom: -1px; height: 2px;
            background: #d4af37;
            animation: tabIn 0.3s ease;
        }
        @keyframes tabIn { from { transform: scaleX(0); } to { transform: scaleX(1); } }

        .city-tabs { display: flex; gap: 16px; justify-content: center; margin-bottom: 32px; flex-wrap: wrap; }
        .city-tab { 
            padding: 10px 28px; 
            font-size: 14px; 
            font-weight: 500; 
            color: rgba(255,255,255,0.4); 
            text-decoration: none; 
            border-radius: 30px; 
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            transition: all 0.3s ease; 
        }
        .city-tab:hover { border-color: rgba(212, 175, 55, 0.2); color: #d4af37; transform: translateY(-2px); }
        .city-tab.active { 
            background: #d4af37; 
            color: #0a0f1e; 
            border-color: #d4af37; 
            box-shadow: 0 6px 20px rgba(212,175,55,0.2);
        }
        
        .services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 30px; margin-top: 20px; }
        .service-card { 
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 16px; 
            overflow: hidden; 
            cursor: pointer; 
        }
        .service-card:hover { border-color: rgba(212, 175, 55, 0.2); }
        .service-card-img-wrap { position: relative; overflow: hidden; }
        .service-card-img { width: 100%; height: 200px; object-fit: cover; background: rgba(255,255,255,0.02); display: block; transition: transform 0.5s ease; }
        .service-card:hover .service-card-img { transform: scale(1.07); }
        .service-card-img-wrap::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, transparent 55%, rgba(10,15,30,0.75) 100%); opacity: 0; transition: opacity 0.35s ease; }
        .service-card:hover .service-card-img-wrap::after { opacity: 1; }
        .service-card-body { padding: 20px; }
        .service-card-title { font-size: 18px; font-weight: 700; color: white; margin-bottom: 8px; }
        .service-card-location { color: rgba(255,255,255,0.4); font-size: 13px; margin-bottom: 8px; }
        .service-card-stars { color: #d4af37; font-size: 13px; margin-bottom: 12px; }
        .service-card-price { font-size: 20px; font-weight: 700; color: #d4af37; margin: 10px 0; }
        .service-card-duration { 
            display: inline-block; 
            background: rgba(255,255,255,0.02);
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 12px; 
            color: rgba(255,255,255,0.3); 
        }
        
        .hotel-details { 
            background: rgba(255,255,255,0.02);
            padding: 12px; 
            border-radius: 12px; 
            margin: 12px 0; 
        }
        .detail-item { 
            display: flex; 
            align-items: baseline; 
            gap: 8px; 
            font-size: 12px; 
            color: rgba(255,255,255,0.5); 
            margin-bottom: 6px; 
        }
        .detail-label { font-weight: 500; color: rgba(255,255,255,0.7); min-width: 70px; }
        .service-value { color: #34d399; font-weight: 500; }
        
        .service-card-btn { 
            background: rgba(212, 175, 55, 0.1);
            color: #d4af37; 
            border: 1px solid rgba(212, 175, 55, 0.1);
            padding: 10px 20px; 
            border-radius: 10px; 
            font-weight: 500; 
            width: 100%; 
            font-size: 14px; 
            cursor: pointer; 
            transition: all 0.3s ease;
        }
        .service-card-btn:hover { background: #d4af37; color: #0a0f1e; box-shadow: 0 8px 22px rgba(212,175,55,0.2); }
        
        .car-dropdown-container { max-width: 500px; margin: 0 auto 40px auto; }
        .car-select { 
            width: 100%; 
            padding: 15px 20px; 
            font-size: 15px; 
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; 
            background: rgba(255,255,255,0.03);
            color: white;
            cursor: pointer; 
            transition: all 0.3s ease;
        }
        .car-select option { background: #0a0f1e; color: white; }
        .car-select:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.08);
        }
        .car-select:hover { border-color: rgba(212, 175, 55, 0.2); }
        
        .car-details-card { 
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px; 
            overflow: hidden; 
            margin-top: 20px; 
            opacity: 0;
            transform: translateY(16px);
            animation: cardIn 0.5s cubic-bezier(.2,.8,.3,1) forwards;
        }
        @keyframes cardIn { to { opacity: 1; transform: translateY(0); } }
        .car-header { 
            background: rgba(255,255,255,0.02);
            color: white; 
            padding: 25px; 
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .car-header h2 { font-family: 'Playfair Display', serif; }
        .car-category { 
            display: inline-block; 
            padding: 4px 15px; 
            border-radius: 20px; 
            font-size: 12px; 
            margin-top: 8px; 
        }
        .car-category.luxury { background: rgba(212, 175, 55, 0.1); color: #d4af37; }
        .car-category.premium { background: rgba(8, 145, 178, 0.1); color: #22d3ee; }
        .car-category.standard { background: rgba(255,255,255,0.03); color: rgba(255,255,255,0.5); }
        .car-category.economy { background: rgba(16,185,129,0.1); color: #34d399; }
        .car-image-wrap { width: 100%; height: 250px; background: rgba(255,255,255,0.015); border-radius: 12px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .car-image { width: 100%; height: 100%; object-fit: contain; transition: transform 0.5s ease; }
        .car-image-wrap:hover .car-image { transform: scale(1.04); }
        .fare-table { width: 100%; border-collapse: collapse; margin: 15px 0; border-radius: 10px; overflow: hidden; }
        .fare-table th, .fare-table td { padding: 11px; text-align: center; border: 1px solid rgba(255,255,255,0.04); }
        .fare-table th { background: rgba(212,175,55,0.05); font-weight: 600; color: rgba(255,255,255,0.75); }
        .fare-table td { color: rgba(255,255,255,0.5); }
        .fare-table tr:hover td { background: rgba(255,255,255,0.02); }
        .city-select { 
            width: 100%; 
            padding: 13px; 
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; 
            margin-bottom: 15px;
            background: rgba(255,255,255,0.03);
            color: white;
            transition: all 0.3s ease;
        }
        .city-select:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.08);
        }
        .city-select option { background: #0a0f1e; color: white; }
        .city-select:hover { border-color: rgba(212, 175, 55, 0.2); }
        .fare-display { 
            background: rgba(16,185,129,0.06);
            padding: 13px; 
            border-radius: 12px; 
            text-align: center; 
            font-weight: 600; 
            color: #34d399; 
            margin: 15px 0; 
            border: 1px solid rgba(16,185,129,0.08);
            transition: all 0.3s ease;
        }
        
        .empty-state { 
            text-align: center; 
            padding: 60px; 
            background: rgba(255,255,255,0.02);
            border-radius: 16px; 
            border: 1px solid rgba(255,255,255,0.03);
        }
        .empty-state h3 { color: white; margin-bottom: 8px; font-family: 'Playfair Display', serif; }
        .empty-state p { color: rgba(255,255,255,0.3); }

        /* NEW: hotel-list pagination */
        .hotels-pagination { display: flex; gap: 6px; justify-content: center; align-items: center; padding: 30px 0 10px; flex-wrap: wrap; }
        .hotels-pagination a, .hotels-pagination span { padding: 8px 13px; border-radius: 6px; font-size: 13px; text-decoration: none; color: rgba(255,255,255,0.5); border: 1px solid rgba(255,255,255,0.06); }
        .hotels-pagination a:hover { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.9); }
        .hotels-pagination .current { background: #d4af37; color: #0a0f1e; border-color: #d4af37; font-weight: 600; }
        .hotels-pagination .disabled { opacity: 0.3; pointer-events: none; }
        
        @media (max-width: 768px) { 
            .services-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="bg-ambient" aria-hidden="true">
    <i class="fas fa-star-and-crescent bg-motif m1"></i>
    <i class="fas fa-star-and-crescent bg-motif m2"></i>
    <i class="fas fa-star-and-crescent bg-motif m3"></i>
</div>
<div class="bg-vignette" aria-hidden="true"></div>
<div class="grain-overlay" aria-hidden="true"></div>

<!-- NEW: page-transition overlay -- shown immediately on any internal
     navigation click so the wait for the next page never feels like a
     "hang". Pure UX addition; does not change where any link goes. -->
<div class="page-transition" id="pageTransition">
    <div class="pt-spinner">
        <div class="pt-ring"></div>
        <i class="fas fa-plane pt-icon"></i>
    </div>
</div>

<div class="page-content">
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">Ahmed<span>Travels</span></a>
            <div class="nav-links">
                <a href="services.php">Services</a>
                <a href="dashboard.php">Dashboard</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="btn-logout">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn-login">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-heading reveal">
            <div class="gold-line"></div>
            <h1><?php echo $type == 'hotels' ? 'Find Your Stay' : ($type == 'taxi' ? 'Book a Taxi' : 'Visa Services'); ?></h1>
            <p><?php echo $type == 'hotels' ? 'Handpicked hotels near the Haram' : ($type == 'taxi' ? 'Comfortable rides, transparent fares' : 'Fast, guided visa processing'); ?></p>
        </div>

        <div class="tabs">
            <a href="?type=hotels&city=Mecca" class="tab-link <?php echo $type == 'hotels' ? 'active' : ''; ?>">Hotels</a>
            <a href="?type=taxi" class="tab-link <?php echo $type == 'taxi' ? 'active' : ''; ?>">Airport Taxi</a>
            <a href="?type=visa" class="tab-link <?php echo $type == 'visa' ? 'active' : ''; ?>">Visa Services</a>
        </div>
        
        <?php if($type == 'hotels'): ?>
            <div class="city-tabs">
                <a href="?type=hotels&city=Mecca" class="city-tab <?php echo $city == 'Mecca' ? 'active' : ''; ?>">Mecca Hotels</a>
                <a href="?type=hotels&city=Madinah" class="city-tab <?php echo $city == 'Madinah' ? 'active' : ''; ?>">Madinah Hotels</a>
            </div>
            <div class="services-grid">
                <?php if(count($services) > 0): ?>
                    <?php foreach($services as $hotel): ?>
                        <div class="service-card reveal" onclick="goTo('hotel_rooms.php?hotel_id=<?php echo $hotel['id']; ?>')">
                            <div class="service-card-img-wrap">
                                <img class="service-card-img" src="<?php echo htmlspecialchars(!empty($hotel['image_url']) ? $hotel['image_url'] : 'https://placehold.co/400x250/1a1a2e/333?text=Hotel'); ?>" alt="<?php echo htmlspecialchars($hotel['hotel_name'] ?? 'Hotel'); ?>" onerror="this.onerror=null;this.src='https://placehold.co/400x250/1a1a2e/333?text=Hotel';">
                            </div>
                            <div class="service-card-body">
                                <h3 class="service-card-title"><?php echo htmlspecialchars($hotel['hotel_name'] ?? 'Hotel Name'); ?></h3>
                                <div class="service-card-location"><?php echo htmlspecialchars($hotel['city'] ?? 'Mecca'); ?></div>
                                <div class="service-card-stars"><?php echo str_repeat('★', $hotel['rating'] ?? 4); ?></div>
                                <div class="hotel-details">
                                    <?php if(!empty($hotel['location'])): ?>
                                        <div class="detail-item"><span class="detail-label">Location:</span><span><?php echo htmlspecialchars($hotel['location']); ?></span></div>
                                    <?php endif; ?>
                                    <?php if(!empty($hotel['distance_meters'])): ?>
                                        <div class="detail-item"><span class="detail-label">Distance:</span><span><?php echo $hotel['distance_meters']; ?> meters</span></div>
                                    <?php endif; ?>
                                    <?php if(!empty($hotel['shuttle_service']) && $hotel['shuttle_service'] == 'Yes'): ?>
                                        <div class="detail-item"><span class="detail-label">Shuttle:</span><span class="service-value">Free Shuttle</span></div>
                                    <?php elseif(!empty($hotel['shuttle_service']) && $hotel['shuttle_service'] == 'Star Shuttle Service'): ?>
                                        <div class="detail-item"><span class="detail-label">Service:</span><span class="service-value">Star Shuttle</span></div>
                                    <?php endif; ?>
                                </div>
                                <button class="service-card-btn">View Rooms</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state"><h3>No Hotels Found</h3><p>Hotels in <?php echo htmlspecialchars($city); ?> will be added soon.</p></div>
                <?php endif; ?>
            </div>

            <?php if($totalPages > 1): ?>
            <div class="hotels-pagination">
                <a href="?type=hotels&city=<?php echo urlencode($city); ?>&page=<?php echo max(1, $page - 1); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">&lsaquo; Prev</a>
                <?php
                    $startP = max(1, $page - 2);
                    $endP = min($totalPages, $page + 2);
                    if ($startP > 1) echo '<a href="?type=hotels&city=' . urlencode($city) . '&page=1">1</a><span>&hellip;</span>';
                    for ($p = $startP; $p <= $endP; $p++) {
                        if ($p == $page) echo '<span class="current">' . $p . '</span>';
                        else echo '<a href="?type=hotels&city=' . urlencode($city) . '&page=' . $p . '">' . $p . '</a>';
                    }
                    if ($endP < $totalPages) echo '<span>&hellip;</span><a href="?type=hotels&city=' . urlencode($city) . '&page=' . $totalPages . '">' . $totalPages . '</a>';
                ?>
                <a href="?type=hotels&city=<?php echo urlencode($city); ?>&page=<?php echo min($totalPages, $page + 1); ?>" class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">Next &rsaquo;</a>
            </div>
            <?php endif; ?>

        <?php elseif($type == 'taxi' && isset($cars)): ?>
            <div class="car-dropdown-container reveal">
                <select id="carSelect" class="car-select">
                    <option value="" style="color:rgba(255,255,255,0.3);">— Select a Car —</option>
                    <?php foreach($cars as $car): ?>
                        <option value="<?php echo $car['id']; ?>">
                            <?php echo htmlspecialchars($car['car_name']); ?> <?php echo htmlspecialchars($car['car_model']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="carDetailsContainer">
                <div class="empty-state">
                    <h3>Select a Car</h3>
                    <p>Please choose a car from the dropdown above to view fares and book</p>
                </div>
            </div>
            
            <script>
            const carsData = <?php 
                $cars_array = [];
                foreach($cars as $car) {
                    $stmt = $pdo->prepare("SELECT from_city, to_city, price_sar FROM car_fares WHERE car_id = ? ORDER BY from_city, to_city");
                    $stmt->execute([$car['id']]);
                    $fares = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $cars_array[$car['id']] = [
                        'id' => $car['id'],
                        'name' => $car['car_name'] ?? '',
                        'model' => $car['car_model'] ?? '',
                        'capacity' => $car['capacity'] ?? 4,
                        'image_url' => $car['image_url'] ?? '',
                        'fares' => $fares
                    ];
                }
                echo json_encode($cars_array);
            ?>;
            
            const cities = <?php echo json_encode($cities); ?>;
            
            function showCarDetails(carId) {
                const car = carsData[carId];
                if(!car) return;
                
                let categoryClass = '', categoryName = '';
                if(car.name == 'Hyundai Sonata') { categoryClass = 'luxury'; categoryName = 'Luxury'; }
                else if(car.name == 'Honda Civic') { categoryClass = 'premium'; categoryName = 'Premium'; }
                else if(car.name == 'Toyota Corolla') { categoryClass = 'standard'; categoryName = 'Standard'; }
                else { categoryClass = 'economy'; categoryName = 'Economy'; }
                
                let faresHtml = '<table class="fare-table"><thead><tr><th>Route</th><th>Fare (SAR)</th></tr></thead><tbody>';
                car.fares.forEach(fare => {
                    // Consistent Title Case display regardless of how the
                    // agent originally typed the city name.
                    const fromDisp = fare.from_city.trim().toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
                    const toDisp = fare.to_city.trim().toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
                    faresHtml += '<tr><td style="padding: 10px;">'+fromDisp+' → '+toDisp+'</td><td style="font-weight: bold; color: #d4af37;">SAR '+fare.price_sar+'</td></tr>';
                });
                faresHtml += '</tbody></table>';
                
                let html = `
                    <div class="car-details-card">
                        <div class="car-header">
                            <h2 style="color:white;">${car.name} ${car.model}</h2>
                            <span class="car-category ${categoryClass}">${categoryName} Class</span>
                        </div>
                        <div class="car-image-wrap">
                            <img class="car-image" src="${car.image_url}" onerror="this.src='https://placehold.co/600x300/1a1a2e/333?text=${car.name}'">
                        </div>
                        <div style="padding: 25px;">
                            <p style="margin-bottom: 15px; color:rgba(255,255,255,0.5);"><strong style="color:rgba(255,255,255,0.7);">Capacity:</strong> ${car.capacity} persons &nbsp;|&nbsp; <strong style="color:rgba(255,255,255,0.7);">Air Conditioning:</strong> Yes</p>
                            ${faresHtml}
                            <div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: 16px; margin-top: 20px; border: 1px solid rgba(255,255,255,0.04);">
                                <select id="fromCity" class="city-select"><option value="" style="color:rgba(255,255,255,0.3);">Select Pickup City</option>${cities.map(c => `<option value="${c}">${c}</option>`).join('')}</select>
                                <select id="toCity" class="city-select"><option value="" style="color:rgba(255,255,255,0.3);">Select Drop City</option>${cities.map(c => `<option value="${c}">${c}</option>`).join('')}</select>
                                <div id="fareDisplay" class="fare-display">Select cities to see fare</div>
                                <button id="bookNowBtn" class="service-card-btn" disabled>Book Now</button>
                            </div>
                        </div>
                    </div>
                `;
                
                document.getElementById('carDetailsContainer').innerHTML = html;
                
                setTimeout(() => {
                    const fromCity = document.getElementById('fromCity');
                    const toCity = document.getElementById('toCity');
                    const fareDisplay = document.getElementById('fareDisplay');
                    const bookBtn = document.getElementById('bookNowBtn');

                    // FIX: dropdowns now only show cities that actually
                    // have a route on THIS car (previously they listed
                    // every city ever entered for every car, so picking
                    // a real-looking combination could still say "no
                    // route" if this specific car didn't serve it).
                    // Case is also normalized for display/matching here,
                    // so "JEDDAH" and "jeddah" are treated as the same
                    // city instead of showing as two separate options.
                    function titleCase(s) {
                        return s.trim().toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
                    }
                    function canon(s) { return s.trim().toUpperCase(); }

                    // Build a canon-key -> display-label map from every
                    // city that appears (either side) in this car's fares.
                    const cityMap = {};
                    car.fares.forEach(f => {
                        cityMap[canon(f.from_city)] = titleCase(f.from_city);
                        cityMap[canon(f.to_city)] = titleCase(f.to_city);
                    });
                    const allCarCities = Object.keys(cityMap).sort();

                    function fillSelect(select, options, placeholder) {
                        const current = select.value;
                        select.innerHTML = `<option value="" style="color:rgba(255,255,255,0.3);">${placeholder}</option>` +
                            options.map(k => `<option value="${k}">${cityMap[k]}</option>`).join('');
                        if (options.includes(current)) select.value = current;
                    }

                    fillSelect(fromCity, allCarCities, 'Select Pickup City');
                    fillSelect(toCity, allCarCities, 'Select Drop City');

                    // Cascading: once a pickup city is picked, only show
                    // drop-off cities this car actually has a route to
                    // (checked both directions, same as the fare lookup).
                    fromCity.addEventListener('change', function() {
                        const fromKey = fromCity.value;
                        if (!fromKey) {
                            fillSelect(toCity, allCarCities, 'Select Drop City');
                        } else {
                            const reachable = new Set();
                            car.fares.forEach(f => {
                                const ff = canon(f.from_city), ft = canon(f.to_city);
                                if (ff === fromKey) reachable.add(ft);
                                if (ft === fromKey) reachable.add(ff);
                            });
                            fillSelect(toCity, Array.from(reachable).sort(), 'Select Drop City');
                        }
                        updateFare();
                    });

                    function updateFare() {
                        const from = fromCity.value, to = toCity.value;
                        if(from && to && from !== to && car.fares) {
                            // Case-insensitive AND bidirectional -- "makkah"
                            // matches "MAKKAH", and a route entered as
                            // "Jeddah -> Makkah" also matches a customer
                            // selecting "Makkah -> Jeddah".
                            const fare = car.fares.find(f => {
                                const ffU = canon(f.from_city), ftU = canon(f.to_city);
                                return (ffU === from && ftU === to) || (ffU === to && ftU === from);
                            });
                            if(fare) { 
                                fareDisplay.innerHTML = 'Total Fare: SAR '+fare.price_sar; 
                                bookBtn.disabled = false; 
                                bookBtn.setAttribute('data-from', from); 
                                bookBtn.setAttribute('data-to', to); 
                            } else { 
                                fareDisplay.innerHTML = 'No route from '+cityMap[from]+' to '+cityMap[to]; 
                                bookBtn.disabled = true; 
                            }
                        } else if(from === to && from) { 
                            fareDisplay.innerHTML = 'Cities cannot be same'; 
                            bookBtn.disabled = true; 
                        } else { 
                            fareDisplay.innerHTML = 'Select cities to see fare'; 
                            bookBtn.disabled = true; 
                        }
                    }
                    toCity.addEventListener('change', updateFare);
                    bookBtn.addEventListener('click', function() {
                        const from = fromCity.value, to = toCity.value;
                        if(from && to) goTo('booking_taxi.php?car_id='+car.id+'&car_name='+encodeURIComponent(car.name)+'&from='+encodeURIComponent(cityMap[from])+'&to='+encodeURIComponent(cityMap[to]));
                    });
                }, 100);
            }
            
            document.getElementById('carSelect').addEventListener('change', function() {
                const carId = this.value;
                if(carId) showCarDetails(carId);
                else document.getElementById('carDetailsContainer').innerHTML = '<div class="empty-state"><h3>Select a Car</h3><p>Please choose a car from the dropdown above to view fares and book</p></div>';
            });
            </script>
        
        <?php elseif($type == 'visa' && isset($services)): ?>
            <div class="services-grid">
                <?php foreach($services as $service): ?>
                    <div class="service-card reveal" onclick="goTo('booking.php?type=<?php echo $type; ?>&id=<?php echo $service['id']; ?>')">
                        <div class="service-card-img-wrap">
                            <img class="service-card-img" src="https://placehold.co/400x200/1a1a2e/333?text=<?php echo urlencode($service['title'] ?? 'Service'); ?>" alt="<?php echo htmlspecialchars($service['title'] ?? 'Service'); ?>">
                        </div>
                        <div class="service-card-body">
                            <h3 class="service-card-title"><?php echo htmlspecialchars($service['title'] ?? 'Service Name'); ?></h3>
                            <div class="service-card-location"><?php echo htmlspecialchars($service['description'] ?? 'No description available'); ?></div>
                            <div class="service-card-price">SAR <?php echo number_format($service['price'] ?? 0); ?></div>
                            <button class="service-card-btn">Apply Now</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        
        <?php else: ?>
            <div class="empty-state"><h3>No Services Available</h3><p>Please check back later.</p></div>
        <?php endif; ?>
    </div>
</div>

<script>
    /* ---------- NEW: page-transition overlay helpers ---------- */
    function goTo(url) {
        document.getElementById('pageTransition').classList.add('active');
        setTimeout(() => { window.location.href = url; }, 180);
    }
    // For plain <a href> tabs (Hotels/Taxi/Visa, Mecca/Madinah), the
    // browser's own navigation already begins asynchronously, so we
    // just show the overlay immediately without touching the href or
    // preventing default -- normal navigation (incl. middle-click /
    // open-in-new-tab) keeps working exactly as before.
    document.querySelectorAll('.tab-link, .city-tab, .hotels-pagination a:not(.disabled)').forEach(a => {
        a.addEventListener('click', function() {
            if (!this.classList.contains('active')) {
                document.getElementById('pageTransition').classList.add('active');
            }
        });
    });

    /* ---------- NEW: scroll-reveal, purely additive ---------- */
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