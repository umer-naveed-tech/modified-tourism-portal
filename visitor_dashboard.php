<?php
// visitor_dashboard.php
//
// True full-viewport photo landing page -- the sidebar overlays
// directly on top of the hero photo (translucent panel, always
// readable regardless of the photo behind it) instead of sitting in
// its own separate boxed column. Matches the index.php-style landing
// page look the client asked for. The other 3 dashboard pages
// (my_bookings.php, booking_history.php, payments_history.php) keep
// the normal boxed sidebar layout from dashboard_shell.css -- they're
// functional list pages, not the dramatic landing page, so only this
// file changes structure.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'visitor') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'dashboard_helpers.php';
date_default_timezone_set('Asia/Riyadh');

$user_id = $_SESSION['user_id'];

if (!isset($_SESSION['user_email'])) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();
    $_SESSION['user_email'] = $u['email'];
}

// ---- Only the 3 soonest upcoming bookings -- deliberately capped so
// this page never has to render a long list, even for a customer with
// many bookings. "View all" goes to my_bookings.php for the rest. ----
$stmt = $pdo->prepare("
    SELECT id, booking_no, service_type, service_id, status, total_amount, travel_date, from_location, to_location, price_breakdown
    FROM bookings
    WHERE user_id = ? AND hidden_by_user = 0 AND status != 'cancelled' AND travel_date >= CURDATE() AND travel_date > '1970-01-02'
    ORDER BY travel_date ASC
    LIMIT 3
");
$stmt->execute([$user_id]);
$upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

$name_parts = preg_split('/\s+/', trim($_SESSION['user_name']));
$initials = strtoupper(substr($name_parts[0], 0, 1) . (count($name_parts) > 1 ? substr(end($name_parts), 0, 1) : ''));

// ---- Theme image (agent-uploaded via agent_theme_settings.php) --
// falls back to an elegant gradient (no broken/missing-image look) if
// the agent hasn't uploaded a photo yet. ----
$stmt = $pdo->query("SELECT image_path FROM site_theme_images WHERE setting_key = 'dashboard_hero'");
$hero_image = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT bg_color FROM site_theme_slot_colors WHERE setting_key = 'dashboard_hero'");
$hero_color = $stmt->fetchColumn();
$hero_style = $hero_image
    ? "background-image: url('" . htmlspecialchars($hero_image) . "');"
    : ($hero_color ? "background:" . htmlspecialchars($hero_color) . ";" : "background: linear-gradient(135deg, #2b2416 0%, #4a3d22 45%, #7a6530 100%);");

$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-body, 'Inter', sans-serif); background: var(--c-bg); color: var(--c-text); }

        /* ============ TRUE full-viewport hero ============ */
        .hero-full {
            position: relative; width: 100%; min-height: 100vh; overflow: hidden;
            background-size: cover; background-position: center;
            display: flex;
        }
        .hero-bg-video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0; }
        .hero-full::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(180deg, rgba(10,8,4,0.15) 0%, rgba(10,8,4,0.05) 30%, rgba(10,8,4,0.55) 100%),
                        linear-gradient(90deg, rgba(10,8,4,0.55) 0%, rgba(10,8,4,0.1) 300px, transparent 320px);
            pointer-events: none;
        }

        /* Sidebar overlaid directly on the photo -- own subtle
           gradient behind it so links stay readable no matter what
           the photo looks like there. */
        .hero-nav {
            position: relative; z-index: 2; width: 250px; flex-shrink: 0;
            padding: 28px 0; display: flex; flex-direction: column;
        }
        .hero-nav .brand { font-family: var(--font-heading); font-size: 18px; font-weight: 700; color: #fff; padding: 0 28px 36px; text-decoration: none; }
        .hero-nav .brand span { color: #ecc873; }
        .hero-nav a.link { display: flex; align-items: center; gap: 12px; padding: 11px 28px; color: rgba(255,255,255,0.75); font-size: 13px; font-weight: 500; text-decoration: none; transition: all 0.2s ease; position: relative; }
        .hero-nav a.link:hover { color: #fff; }
        .hero-nav a.link.on { color: #fff; }
        .hero-nav a.link.on::before { content: ''; position: absolute; left: 0; top: 4px; bottom: 4px; width: 3px; background: #ecc873; border-radius: 2px; }
        .hero-nav a.link i { width: 15px; font-size: 13px; }
        .hero-nav .div { height: 1px; background: rgba(255,255,255,0.15); margin: 16px 28px; }
        .hero-nav a.logout { color: rgba(255,255,255,0.5); }
        .hero-nav a.logout:hover { color: #f2a5a0; }

        /* Main hero text/CTA content, bottom-left of the photo area */
        .hero-main { position: relative; z-index: 2; flex: 1; display: flex; flex-direction: column; justify-content: flex-end; padding: 50px 60px; }
        .hero-eyebrow { font-size: 11.5px; letter-spacing: 2px; text-transform: uppercase; color: #ecc873; margin-bottom: 12px; opacity: 0; animation: heroUp 0.8s ease 0.1s forwards; }
        .hero-main h1 { font-family: var(--font-heading); font-size: 48px; font-weight: 800; color: #fff; line-height: 1.1; margin-bottom: 10px; opacity: 0; animation: heroUp 0.8s ease 0.25s forwards; text-shadow: 0 4px 24px rgba(0,0,0,0.3); }
        .hero-main .sub { font-size: 14.5px; color: rgba(255,255,255,0.85); margin-bottom: 28px; opacity: 0; animation: heroUp 0.8s ease 0.4s forwards; }
        .hero-cta-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; opacity: 0; animation: heroUp 0.8s ease 0.55s forwards; }
        @keyframes heroUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .btn-book-hero {
            display: inline-flex; align-items: center; gap: 11px;
            background: linear-gradient(135deg, var(--c-accent-grad-1), var(--c-accent-2));
            color: var(--c-accent-ink); font-weight: 800; font-size: 15.5px;
            padding: 18px 34px; border-radius: 12px; text-decoration: none; border: 2px solid rgba(255,255,255,0.25); cursor: pointer;
            box-shadow: 0 12px 34px rgba(0,0,0,0.35);
            transition: all 0.25s ease;
        }
        .btn-book-hero:hover { transform: translateY(-2px); box-shadow: 0 16px 40px rgba(0,0,0,0.4); }
        .hero-secondary-link {
            font-weight: 700; font-size: 13.5px; color: #fff; text-decoration: none;
            padding: 16px 24px; border-radius: 12px; background: rgba(0,0,0,0.35); border: 2px solid rgba(255,255,255,0.35);
            backdrop-filter: blur(4px); transition: all 0.25s ease;
        }
        .hero-secondary-link:hover { background: rgba(0,0,0,0.5); border-color: #fff; }
        .avatar { width: 38px; height: 38px; border-radius: 50%; background: rgba(255,255,255,0.15); border: 2px solid rgba(255,255,255,0.35); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; margin-left: auto; }

        /* ============ Below-the-fold content, normal bright page ============ */
        .below-hero { max-width: 1200px; margin: 0 auto; padding: 44px 40px 70px; }
        .row-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .row-head .t { font-family: 'Playfair Display', serif; color: var(--c-text); font-size: 19px; font-weight: 700; }
        .row-head .a { color: var(--c-accent); font-size: 12.5px; text-decoration: none; font-weight: 600; }
        .row-head .a:hover { text-decoration: underline; }

        table { width: 100%; border-collapse: collapse; background: var(--c-card-bg); border: 1px solid var(--c-border); border-radius: 14px; overflow: hidden; }
        th { text-align: left; color: var(--c-muted); font-size: 10.5px; letter-spacing: 0.5px; text-transform: uppercase; font-weight: 600; padding: 14px 16px; border-bottom: 1px solid var(--c-border); background: #f8f4ec; }
        td { padding: 15px 16px; border-bottom: 1px solid #f2ece0; font-size: 12.5px; color: #4a4335; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .svc { display: flex; align-items: center; gap: 10px; }
        .svc-icon { width: 30px; height: 30px; border-radius: 8px; background: #f6f0e3; display: flex; align-items: center; justify-content: center; color: var(--c-accent); font-size: 12px; flex-shrink: 0; }
        .svc-name { color: var(--c-text); font-size: 12.5px; font-weight: 600; }
        .svc-sub { color: var(--c-muted); font-size: 11px; margin-top: 1px; }
        .dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .dot.g { background: #2e9e6a; } .dot.y { background: #c9a24b; } .dot.b { background: #5b8fd6; }
        .amt { color: var(--c-text); font-weight: 700; }
        .action { color: var(--c-muted); text-decoration: none; font-weight: 600; }
        .action:hover { color: var(--c-accent); }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--c-muted); font-size: 13px; background: var(--c-card-bg); border: 1px solid var(--c-border); border-radius: 14px; }
        .empty-state i { font-size: 30px; color: #d9cdb8; margin-bottom: 14px; display: block; }
        .empty-state a { color: var(--c-accent); text-decoration: none; font-weight: 600; }

        @media (max-width: 900px) {
            .hero-full { flex-direction: column; }
            .hero-nav { width: 100%; flex-direction: row; flex-wrap: wrap; align-items: center; padding: 16px 20px; background: rgba(0,0,0,0.4); }
            .hero-nav .brand { padding: 0 16px 0 0; }
            .hero-nav .div { display: none; }
            .hero-nav a.link { padding: 8px 12px; font-size: 12px; }
            .hero-main { padding: 30px 24px; }
            .hero-main h1 { font-size: 32px; }
            .below-hero { padding: 30px 20px 50px; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
    <?php include 'dynamic_theme.php'; ?>
</head>
<body>

<div class="hero-full" style="<?php echo $hero_style; ?>">
    <video class="hero-bg-video" autoplay muted loop playsinline<?php echo $hero_image ? ' poster="' . htmlspecialchars($hero_image) . '"' : ''; ?>>
        <source src="videos/hero-taxi.mp4" type="video/mp4">
    </video>
    <nav class="hero-nav">
        <a href="visitor_dashboard.php" class="brand">Ahmed<span>Travels</span></a>
        <a href="visitor_dashboard.php" class="link on"><i class="fas fa-globe" aria-hidden="true"></i>Dashboard</a>
        <a href="my_bookings.php" class="link"><i class="fas fa-ticket" aria-hidden="true"></i>My Bookings</a>
        <a href="booking_history.php" class="link"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i>History</a>
        <a href="payments_history.php" class="link"><i class="fas fa-credit-card" aria-hidden="true"></i>Payments</a>
        <div class="div"></div>
        <a href="edit_profile.php" class="link"><i class="fas fa-user" aria-hidden="true"></i>Profile</a>
        <a href="contact_us.php" class="link"><i class="fas fa-headset" aria-hidden="true"></i>Support</a>
        <a href="logout.php" class="link logout" onclick="return confirm('Are you sure you want to log out?');"><i class="fas fa-right-from-bracket" aria-hidden="true"></i>Logout</a>
    </nav>

    <div class="hero-main">
        <div class="hero-eyebrow">Ahmed Travels</div>
        <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></h1>
        <div class="sub"><?php echo date('l, F j'); ?> — where would you like to go next?</div>
        <div class="hero-cta-row">
            <a href="book_service.php" class="btn-book-hero"><i class="fas fa-plus" aria-hidden="true"></i>Book a Service</a>
            <a href="my_bookings.php" class="hero-secondary-link">View My Bookings</a>
            <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
        </div>
    </div>
</div>

<div class="below-hero">
    <div class="row-head">
        <div class="t">Upcoming Bookings</div>
        <a href="my_bookings.php?tab=upcoming" class="a">View all →</a>
    </div>

    <?php if (count($upcoming) > 0): ?>
    <table>
        <thead>
            <tr><th>Service</th><th>Date</th><th>Status</th><th style="text-align:right;">Amount</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($upcoming as $b):
                $ds = display_status($b['status'], $b['travel_date']); $dotClass = $ds['dot'];
            ?>
            <tr>
                <td>
                    <div class="svc">
                        <div class="svc-icon"><i class="fas <?php echo service_icon($b['service_type']); ?>" aria-hidden="true"></i></div>
                        <div>
                            <div class="svc-name"><?php echo htmlspecialchars(service_label($b)); ?></div>
                            <div class="svc-sub"><?php echo htmlspecialchars($b['booking_no']); ?></div>
                        </div>
                    </div>
                </td>
                <td><?php echo safe_date($b['travel_date']); ?></td>
                <td><span class="dot <?php echo $dotClass; ?>"></span><?php echo htmlspecialchars($ds['label']); ?></td>
                <td style="text-align:right;" class="amt">SAR <?php echo number_format($b['total_amount']); ?></td>
                <td><a href="booking_detail_view.php?id=<?php echo (int)$b['id']; ?>" class="action">Details →</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-suitcase-rolling" aria-hidden="true"></i>
            No upcoming bookings. <a href="services.php">Book a service</a> to get started.
        </div>
    <?php endif; ?>
</div>

</body>
</html>