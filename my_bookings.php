<?php
// my_bookings.php
//
// "My Bookings" -- per Umer's spec, this defaults to COMPLETED
// bookings only (not a dump of everything -- that's what History is
// for). An "Upcoming" tab covers the full upcoming list (the
// dashboard only ever shows a 3-row preview of this). Paginated with
// LIMIT/OFFSET so this stays fast regardless of how many bookings a
// customer accumulates over time.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'visitor') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'dashboard_helpers.php';
date_default_timezone_set('Asia/Riyadh');

$user_id = $_SESSION['user_id'];

// Quick summary stats, moved here from the dashboard so the dashboard
// stays focused on booking a new service.
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND hidden_by_user = 0");
$stmt->execute([$user_id]);
$total_bookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND hidden_by_user = 0 AND status != 'cancelled' AND travel_date >= CURDATE() AND travel_date > '1970-01-02'");
$stmt->execute([$user_id]);
$upcoming_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT travel_date FROM bookings WHERE user_id = ? AND hidden_by_user = 0 AND status != 'cancelled' AND travel_date >= CURDATE() AND travel_date > '1970-01-02' ORDER BY travel_date ASC LIMIT 1");
$stmt->execute([$user_id]);
$next_trip_date = $stmt->fetchColumn();
$next_trip_label = $next_trip_date ? date('M j', strtotime($next_trip_date)) : '--';

$tab = $_GET['tab'] ?? 'completed';
if (!in_array($tab, ['completed', 'upcoming', 'cancelled'])) $tab = 'completed';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

if ($tab === 'upcoming') {
    $where = "user_id = ? AND hidden_by_user = 0 AND status != 'cancelled' AND travel_date >= CURDATE() AND travel_date > '1970-01-02'";
    $order = "travel_date ASC";
} elseif ($tab === 'cancelled') {
    $where = "user_id = ? AND hidden_by_user = 0 AND status = 'cancelled'";
    $order = "created_at DESC";
} else {
    $where = "user_id = ? AND hidden_by_user = 0 AND status = 'completed'";
    $order = "travel_date DESC";
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE $where");
$stmt->execute([$user_id]);
$total_rows = (int)$stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$stmt = $pdo->prepare("
    SELECT id, booking_no, service_type, status, total_amount, travel_date, from_location, to_location, price_breakdown
    FROM bookings
    WHERE $where
    ORDER BY $order
    LIMIT $per_page OFFSET $offset
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$active_page = 'bookings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard_shell.css?v=2">
</head>
<body>
<div class="bg-ambient" aria-hidden="true"></div>
<div class="grain-overlay" aria-hidden="true"></div>
<div class="shell-outer">
    <div class="shell">
        <?php include 'dashboard_sidebar.php'; ?>
        <div class="content">
            <div class="headrow">
                <div>
                    <h1>My Bookings</h1>
                    <div class="meta">Your completed and upcoming bookings</div>
                </div>
                <a href="services.php" class="btn-book-service"><i class="fas fa-plus" aria-hidden="true"></i>Book a Service</a>
            </div>

            <div class="stat-strip">
                <div class="cell"><div class="lbl">Total Bookings</div><div class="val"><?php echo $total_bookings; ?></div></div>
                <div class="cell"><div class="lbl">Upcoming</div><div class="val gold"><?php echo $upcoming_count; ?></div></div>
                <div class="cell"><div class="lbl">Next Trip</div><div class="val"><?php echo htmlspecialchars($next_trip_label); ?></div></div>
            </div>

            <?php if (isset($_GET['removed'])): ?>
            <div style="background:rgba(52,211,153,0.06); border:1px solid rgba(52,211,153,0.15); color:#34d399; padding:12px 16px; border-radius:10px; margin-bottom:18px; font-size:13px;"><i class="fas fa-circle-check"></i> Booking removed from your list.</div>
            <?php endif; ?>

            <div class="tab-row">
                <a href="my_bookings.php?tab=completed" class="tab-link <?php echo $tab === 'completed' ? 'active' : ''; ?>">Completed</a>
                <a href="my_bookings.php?tab=upcoming" class="tab-link <?php echo $tab === 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
                <a href="my_bookings.php?tab=cancelled" class="tab-link <?php echo $tab === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
            </div>

            <?php if (count($bookings) > 0): ?>
            <table>
                <thead>
                    <tr><th>Service</th><th>Date</th><th>Status</th><th style="text-align:right;">Amount</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b):
                        $dotClass = $b['status'] === 'confirmed' ? 'g' : ($b['status'] === 'pending' ? 'y' : ($b['status'] === 'completed' ? 'b' : 'r'));
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
                        <td>
                            <a href="booking_detail_view.php?id=<?php echo (int)$b['id']; ?>" class="action">Details →</a>
                            <a href="hide_booking.php?id=<?php echo (int)$b['id']; ?>" class="action" style="margin-left:10px; color:rgba(255,255,255,0.25);" title="Remove from my bookings"><i class="fas fa-trash-can"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pager">
                <a href="?tab=<?php echo $tab; ?>&page=<?php echo max(1, $page - 1); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">&lsaquo; Prev</a>
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <?php if ($p == $page): ?><span class="current"><?php echo $p; ?></span>
                    <?php else: ?><a href="?tab=<?php echo $tab; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a><?php endif; ?>
                <?php endfor; ?>
                <a href="?tab=<?php echo $tab; ?>&page=<?php echo min($total_pages, $page + 1); ?>" class="<?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next &rsaquo;</a>
            </div>
            <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-suitcase-rolling" aria-hidden="true"></i>
                    <?php echo $tab === 'completed' ? 'No completed bookings yet.' : ($tab === 'cancelled' ? 'No cancelled bookings.' : 'No upcoming bookings.'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>