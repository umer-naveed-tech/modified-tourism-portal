<?php
// booking_history.php
//
// Past bookings, grouped into time buckets (Yesterday / Last 7 Days /
// Last Month / Older) rather than one long dump. Each bucket is its
// own indexed, paginated query -- so even a customer with years of
// history only ever loads one page's worth of rows at a time.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'visitor') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
date_default_timezone_set('Asia/Riyadh');

$user_id = $_SESSION['user_id'];
$range = $_GET['range'] ?? 'all';
$allowed_ranges = ['all', 'yesterday', '7days', 'month', 'older'];
if (!in_array($range, $allowed_ranges)) $range = 'all';

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$where = "user_id = ? AND hidden_by_user = 0 AND travel_date < CURDATE()";
$params = [$user_id];

if ($range === 'yesterday') {
    $where .= " AND travel_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($range === '7days') {
    $where .= " AND travel_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND travel_date < DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($range === 'month') {
    $where .= " AND travel_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND travel_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($range === 'older') {
    $where .= " AND travel_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}
// 'all' -- no extra filter beyond "already happened"

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE $where");
$stmt->execute($params);
$total_rows = (int)$stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$stmt = $pdo->prepare("
    SELECT id, booking_no, service_type, status, total_amount, travel_date, from_location, to_location, price_breakdown
    FROM bookings
    WHERE $where
    ORDER BY travel_date DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

function service_icon($type) {
    switch ($type) {
        case 'hotel': return 'fa-building';
        case 'taxi': return 'fa-car';
        case 'ziyarat': return 'fa-mosque';
        default: return 'fa-passport';
    }
}
function service_label($b) {
    $details = [];
    if (!empty($b['price_breakdown'])) {
        $d = json_decode($b['price_breakdown'], true);
        if (is_array($d)) $details = $d;
    }
    switch ($b['service_type']) {
        case 'hotel': return 'Hotel — ' . ($details['hotel_name'] ?? 'Booking');
        case 'taxi': return 'Taxi — ' . trim(($b['from_location'] ?? '') . ' to ' . ($b['to_location'] ?? ''));
        case 'ziyarat': return 'Ziyarat — ' . ($b['from_location'] ?: 'Trip');
        default: return ($details['service_title'] ?? ucfirst($b['service_type']));
    }
}

$active_page = 'history';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard_shell.css">
</head>
<body>
<div class="shell-outer">
    <div class="shell">
        <?php include 'dashboard_sidebar.php'; ?>
        <div class="content">
            <div class="headrow">
                <div>
                    <h1>History</h1>
                    <div class="meta">All times shown in Asia/Riyadh (Saudi Arabia time)</div>
                </div>
            </div>

            <div class="tab-row">
                <a href="?range=all" class="tab-link <?php echo $range === 'all' ? 'active' : ''; ?>">All Past</a>
                <a href="?range=yesterday" class="tab-link <?php echo $range === 'yesterday' ? 'active' : ''; ?>">Yesterday</a>
                <a href="?range=7days" class="tab-link <?php echo $range === '7days' ? 'active' : ''; ?>">Last 7 Days</a>
                <a href="?range=month" class="tab-link <?php echo $range === 'month' ? 'active' : ''; ?>">Last Month</a>
                <a href="?range=older" class="tab-link <?php echo $range === 'older' ? 'active' : ''; ?>">Older</a>
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
                        <td><?php echo date('M j, Y', strtotime($b['travel_date'])); ?></td>
                        <td><span class="dot <?php echo $dotClass; ?>"></span><?php echo htmlspecialchars(ucfirst($b['status'])); ?></td>
                        <td style="text-align:right;" class="amt">SAR <?php echo number_format($b['total_amount']); ?></td>
                        <td><a href="booking_detail_view.php?id=<?php echo (int)$b['id']; ?>" class="action">Details →</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pager">
                <a href="?range=<?php echo $range; ?>&page=<?php echo max(1, $page - 1); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">&lsaquo; Prev</a>
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <?php if ($p == $page): ?><span class="current"><?php echo $p; ?></span>
                    <?php else: ?><a href="?range=<?php echo $range; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a><?php endif; ?>
                <?php endfor; ?>
                <a href="?range=<?php echo $range; ?>&page=<?php echo min($total_pages, $page + 1); ?>" class="<?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next &rsaquo;</a>
            </div>
            <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clock-rotate-left" aria-hidden="true"></i>
                    No bookings in this range.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>