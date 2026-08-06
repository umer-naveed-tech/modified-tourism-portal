<?php
// payments_history.php
//
// All payment-proof submissions this customer has made, across all
// their bookings. Paginated for the same performance reason as the
// other new pages.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'visitor') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
date_default_timezone_set('Asia/Riyadh');

$user_id = $_SESSION['user_id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM payments p JOIN bookings b ON p.booking_id = b.id WHERE b.user_id = ?");
$stmt->execute([$user_id]);
$total_rows = (int)$stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$stmt = $pdo->prepare("
    SELECT p.*, b.booking_no, b.service_type, b.total_amount
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    WHERE b.user_id = ?
    ORDER BY p.submitted_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute([$user_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lifetime total of VERIFIED payments only -- a pending/rejected
// submission isn't actually money received yet.
$stmt = $pdo->prepare("SELECT COALESCE(SUM(b.total_amount), 0) FROM payments p JOIN bookings b ON p.booking_id = b.id WHERE b.user_id = ? AND p.status = 'verified'");
$stmt->execute([$user_id]);
$verified_total = (float)$stmt->fetchColumn();

$active_page = 'payments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard_shell.css">
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
                    <h1>Payments</h1>
                    <div class="meta">Every payment proof you've submitted, and its verification status</div>
                </div>
                <a href="services.php" class="btn-book-service"><i class="fas fa-plus" aria-hidden="true"></i>Book a Service</a>
            </div>

            <div class="stat-strip">
                <div class="cell"><div class="lbl">Total Submissions</div><div class="val"><?php echo $total_rows; ?></div></div>
                <div class="cell"><div class="lbl">Verified Total</div><div class="val gold">SAR <?php echo number_format($verified_total); ?></div></div>
            </div>

            <?php if (count($payments) > 0): ?>
            <table>
                <thead>
                    <tr><th>Booking</th><th>Payer</th><th>Reference</th><th>Submitted</th><th>Status</th><th style="text-align:right;">Amount</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td>
                            <div class="svc">
                                <?php if (!empty($p['screenshot_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($p['screenshot_path']); ?>" target="_blank" class="thumb-link">
                                        <img src="<?php echo htmlspecialchars($p['screenshot_path']); ?>" alt="Payment proof">
                                    </a>
                                <?php endif; ?>
                                <div>
                                    <div class="svc-name"><?php echo htmlspecialchars($p['booking_no']); ?></div>
                                    <div class="svc-sub"><?php echo htmlspecialchars(ucfirst($p['service_type'])); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($p['payer_name']); ?></td>
                        <td><?php echo htmlspecialchars($p['payment_reference']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($p['submitted_at'])); ?></td>
                        <td><span class="pill <?php echo htmlspecialchars($p['status']); ?>"><?php echo htmlspecialchars(ucfirst($p['status'])); ?></span></td>
                        <td style="text-align:right;" class="amt">SAR <?php echo number_format($p['total_amount']); ?></td>
                        <td><a href="booking_detail_view.php?id=<?php echo (int)$p['booking_id']; ?>" class="action">Details →</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pager">
                <a href="?page=<?php echo max(1, $page - 1); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">&lsaquo; Prev</a>
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <?php if ($p == $page): ?><span class="current"><?php echo $p; ?></span>
                    <?php else: ?><a href="?page=<?php echo $p; ?>"><?php echo $p; ?></a><?php endif; ?>
                <?php endfor; ?>
                <a href="?page=<?php echo min($total_pages, $page + 1); ?>" class="<?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next &rsaquo;</a>
            </div>
            <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-credit-card" aria-hidden="true"></i>
                    No payments submitted yet.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>