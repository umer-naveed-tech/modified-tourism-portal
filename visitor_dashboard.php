<?php
// visitor_dashboard.php
//
// NEW editorial-style dashboard home page. Shows a quick stat strip
// and only the 3 soonest upcoming bookings -- the full lists live on
// their own pages now (my_bookings.php, booking_history.php,
// payments_history.php), so this page stays light and fast no matter
// how many bookings a customer has built up over time.

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

// ---- Stat strip (each a single, indexed, cheap query -- no full table scans) ----
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND hidden_by_user = 0");
$stmt->execute([$user_id]);
$total_bookings = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND hidden_by_user = 0 AND status != 'cancelled' AND travel_date >= CURDATE() AND travel_date > '1970-01-02'");
$stmt->execute([$user_id]);
$upcoming_count = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE user_id = ? AND status IN ('confirmed', 'completed')");
$stmt->execute([$user_id]);
$total_spent = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT MIN(travel_date) FROM bookings WHERE user_id = ? AND hidden_by_user = 0 AND status != 'cancelled' AND travel_date >= CURDATE() AND travel_date > '1970-01-02'");
$stmt->execute([$user_id]);
$next_trip_date = $stmt->fetchColumn();
$next_trip_label = '--';
if ($next_trip_date) {
    $days = (int)((strtotime($next_trip_date) - strtotime(date('Y-m-d'))) / 86400);
    $next_trip_label = $days <= 0 ? 'Today' : ($days . ' day' . ($days > 1 ? 's' : ''));
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

// ---- Theme images (agent-uploaded via agent_theme_settings.php) --
// falls back to an elegant gradient (no broken/missing-image look) if
// the agent hasn't uploaded photos yet. ----
$stmt = $pdo->query("SELECT setting_key, image_path FROM site_theme_images");
$theme_images = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$fallback_gradient = 'linear-gradient(135deg, #2b2416 0%, #4a3d22 45%, #7a6530 100%)';
function heroBg($path, $fallback) {
    return $path ? "background-image: url('" . htmlspecialchars($path) . "');" : "background: $fallback;";
}

$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard_shell.css">
</head>
<body>
<div class="bg-ambient" aria-hidden="true"></div>
<div class="grain-overlay" aria-hidden="true"></div>
<div class="shell-outer">
    <div class="shell">
        <?php include 'dashboard_sidebar.php'; ?>

        <div class="content">
            <div class="dash-hero" style="<?php echo heroBg($theme_images['dashboard_hero'] ?? null, $fallback_gradient); ?>">
                <div class="eyebrow">Ahmed Travels</div>
                <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></h1>
                <div class="sub"><?php echo date('l, F j'); ?> — where would you like to go next?</div>
                <div class="hero-cta-row">
                    <button type="button" class="btn-book-hero" id="btnRevealServices"><i class="fas fa-plus" aria-hidden="true"></i>Book a Service</button>
                    <a href="my_bookings.php" class="hero-secondary-link">View My Bookings</a>
                    <div class="avatar" style="margin-left:auto;"><?php echo htmlspecialchars($initials); ?></div>
                </div>
            </div>

            <div class="service-reveal" id="serviceRevealSection">
                <div class="service-reveal-grid">
                    <a href="services.php?type=hotels" class="service-card" style="<?php echo heroBg($theme_images['service_hotel'] ?? null, 'linear-gradient(135deg,#2b2416,#5c4a24)'); ?>">
                        <div class="sc-label">Stay</div>
                        <h3>Hotels</h3>
                        <p>Mecca & Madinah, every star rating, seasonal pricing.</p>
                    </a>
                    <a href="services.php?type=taxi" class="service-card" style="<?php echo heroBg($theme_images['service_taxi'] ?? null, 'linear-gradient(135deg,#1e2a3d,#3a5170)'); ?>">
                        <div class="sc-label">Move</div>
                        <h3>Taxi & Transfers</h3>
                        <p>City-to-city routes with fixed, transparent fares.</p>
                    </a>
                    <a href="services.php?type=visa" class="service-card" style="<?php echo heroBg($theme_images['service_visa'] ?? null, 'linear-gradient(135deg,#3d2416,#6b4526)'); ?>">
                        <div class="sc-label">Explore</div>
                        <h3>Visa & Tours</h3>
                        <p>Guided trips and visa services, handled end to end.</p>
                    </a>
                </div>
            </div>

            <div class="stat-strip">
                <div class="cell"><div class="lbl">Total Bookings</div><div class="val"><?php echo $total_bookings; ?></div></div>
                <div class="cell"><div class="lbl">Upcoming</div><div class="val gold"><?php echo $upcoming_count; ?></div></div>
                <a href="payments_history.php" class="cell" style="text-decoration:none; cursor:pointer;"><div class="lbl">My Spending</div><div class="val gold">View →</div></a>
                <div class="cell"><div class="lbl">Next Trip</div><div class="val"><?php echo htmlspecialchars($next_trip_label); ?></div></div>
            </div>

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
                        $dotClass = $b['status'] === 'confirmed' ? 'g' : ($b['status'] === 'pending' ? 'y' : 'b');
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
                        <td><span class="dot <?php echo $dotClass; ?>"></span><?php echo htmlspecialchars(ucfirst($b['status'])); ?></td>
                        <td style="text-align:right;" class="amt">SAR <?php echo number_format($b['total_amount']); ?></td>
                        <td><a href="booking_detail_view.php?id=<?php echo (int)$b['id']; ?>" class="action">Details →</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-suitcase-rolling" aria-hidden="true"></i>
                    No upcoming bookings. <a href="services.php" style="color:#c9a24b;">Book a service</a> to get started.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
document.getElementById('btnRevealServices').addEventListener('click', function() {
    const section = document.getElementById('serviceRevealSection');
    const isOpen = section.classList.contains('open');
    if (isOpen) {
        section.classList.remove('open');
    } else {
        section.classList.add('open');
        section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
});
</script>
</body>
</html>