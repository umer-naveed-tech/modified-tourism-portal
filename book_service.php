<?php
// book_service.php
//
// The full "choose a service" experience -- opened when a customer
// clicks Book a Service on the dashboard. Each of the 3 services gets
// its own full-size photo (agent-configurable via
// agent_theme_settings.php), with a staggered entrance animation.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'visitor') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$stmt = $pdo->query("SELECT setting_key, image_path FROM site_theme_images");
$theme_images = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
function svcBg($path, $fallback) {
    return $path ? "background-image: url('" . htmlspecialchars($path) . "');" : "background: $fallback;";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Service | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #faf7f1; color: #2b2620; min-height: 100vh; }
        .top-bar { display: flex; align-items: center; justify-content: space-between; padding: 24px 40px; }
        .brand { font-family: 'Playfair Display', serif; font-size: 18px; font-weight: 700; color: #2b2620; text-decoration: none; }
        .brand span { color: #b8912f; }
        .back-link { color: #8a7f6a; text-decoration: none; font-size: 13px; }
        .back-link:hover { color: #2b2620; }

        .intro { text-align: center; padding: 20px 24px 44px; }
        .intro h1 { font-family: 'Playfair Display', serif; font-size: 34px; margin-bottom: 10px; opacity: 0; animation: fadeUp 0.7s ease 0.1s forwards; }
        .intro p { color: #9b8f78; font-size: 14px; opacity: 0; animation: fadeUp 0.7s ease 0.25s forwards; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

        .services-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; max-width: 1280px; margin: 0 auto; padding: 0 40px 60px; }
        .svc-card {
            position: relative; border-radius: 20px; overflow: hidden; min-height: 460px;
            display: flex; flex-direction: column; justify-content: flex-end; padding: 32px;
            background-size: cover; background-position: center; text-decoration: none; color: #fff;
            box-shadow: 0 16px 40px rgba(120,95,40,0.15); transition: transform 0.35s ease, box-shadow 0.35s ease;
            opacity: 0; transform: translateY(30px); animation: cardIn 0.7s ease forwards;
        }
        .svc-card:nth-child(1) { animation-delay: 0.15s; }
        .svc-card:nth-child(2) { animation-delay: 0.3s; }
        .svc-card:nth-child(3) { animation-delay: 0.45s; }
        @keyframes cardIn { to { opacity: 1; transform: translateY(0); } }
        .svc-card::before { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(20,16,8,0.05) 25%, rgba(15,12,6,0.85) 100%); transition: background 0.35s ease; }
        .svc-card:hover { transform: translateY(-10px); box-shadow: 0 26px 56px rgba(120,95,40,0.28); }
        .svc-card:hover::before { background: linear-gradient(180deg, rgba(20,16,8,0.15) 15%, rgba(15,12,6,0.92) 100%); }
        .svc-card > * { position: relative; z-index: 1; }
        .svc-eyebrow { font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: #e8d9a8; margin-bottom: 8px; }
        .svc-card h2 { font-family: 'Playfair Display', serif; font-size: 30px; font-weight: 700; margin-bottom: 10px; }
        .svc-card p { font-size: 13.5px; color: rgba(255,255,255,0.8); line-height: 1.6; margin-bottom: 18px; }
        .svc-cta { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 13px; color: #241f14; background: #e2bd63; padding: 11px 20px; border-radius: 10px; width: fit-content; }
        .svc-card:hover .svc-cta { background: #fff; }

        @media (max-width: 900px) {
            .services-grid { grid-template-columns: 1fr; padding: 0 20px 40px; }
            .top-bar { padding: 18px 20px; }
            .intro h1 { font-size: 26px; }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="dashboard.php" class="brand">Ahmed<span>Travels</span></a>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>

    <div class="intro">
        <h1>Where would you like to go?</h1>
        <p>Choose a service to get started</p>
    </div>

    <div class="services-grid">
        <a href="services.php?type=hotels" class="svc-card" style="<?php echo svcBg($theme_images['service_hotel'] ?? null, 'linear-gradient(135deg,#2b2416,#5c4a24)'); ?>">
            <div class="svc-eyebrow">Stay</div>
            <h2>Hotels</h2>
            <p>Mecca &amp; Madinah, every star rating, seasonal pricing built in.</p>
            <span class="svc-cta">Browse Hotels <i class="fas fa-arrow-right"></i></span>
        </a>
        <a href="services.php?type=taxi" class="svc-card" style="<?php echo svcBg($theme_images['service_taxi'] ?? null, 'linear-gradient(135deg,#1e2a3d,#3a5170)'); ?>">
            <div class="svc-eyebrow">Move</div>
            <h2>Taxi &amp; Transfers</h2>
            <p>City-to-city routes with fixed, transparent fares.</p>
            <span class="svc-cta">Browse Taxis <i class="fas fa-arrow-right"></i></span>
        </a>
        <a href="services.php?type=visa" class="svc-card" style="<?php echo svcBg($theme_images['service_visa'] ?? null, 'linear-gradient(135deg,#3d2416,#6b4526)'); ?>">
            <div class="svc-eyebrow">Explore</div>
            <h2>Visa &amp; Tours</h2>
            <p>Guided trips and visa services, handled end to end.</p>
            <span class="svc-cta">Browse Tours <i class="fas fa-arrow-right"></i></span>
        </a>
    </div>
</body>
</html>