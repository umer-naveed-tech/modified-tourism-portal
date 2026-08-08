<?php
// agent_payments.php
//
// Agent-facing Payments management: every payment proof submitted by
// customers, filterable by when it came in, with full Approve/Reject
// (with a reason shown to the customer) from one click.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
date_default_timezone_set('Asia/Riyadh');

$range = $_GET['range'] ?? 'all';
$allowed_ranges = ['all', 'yesterday', '7days', 'month', 'older'];
if (!in_array($range, $allowed_ranges)) $range = 'all';

$status_filter = $_GET['status'] ?? 'all';
$allowed_statuses = ['all', 'pending', 'verified', 'rejected'];
if (!in_array($status_filter, $allowed_statuses)) $status_filter = 'all';

$where = "1=1";
if ($range === 'yesterday') {
    $where .= " AND DATE(p.submitted_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($range === '7days') {
    $where .= " AND p.submitted_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND p.submitted_at < DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($range === 'month') {
    $where .= " AND p.submitted_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND p.submitted_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($range === 'older') {
    $where .= " AND p.submitted_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}
if ($status_filter !== 'all') {
    $where .= " AND p.status = " . $pdo->quote($status_filter);
}

// Quick counts for the status pills at the top (unfiltered by date, so
// the agent always sees the true total pending count regardless of
// which date-range tab they're on).
$counts = $pdo->query("SELECT status, COUNT(*) c FROM payments GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$pending_count = $counts['pending'] ?? 0;
$verified_count = $counts['verified'] ?? 0;
$rejected_count = $counts['rejected'] ?? 0;

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->query("
    SELECT COUNT(*) FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    WHERE $where
");
$total_rows = (int)$stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$stmt = $pdo->query("
    SELECT p.*, b.booking_no, b.service_type, b.total_amount, b.travel_date,
           u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
           b.customer_name, b.customer_phone
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN users u ON b.user_id = u.id
    WHERE $where
    ORDER BY p.submitted_at DESC
    LIMIT $per_page OFFSET $offset
");
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments | Agent Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: white; min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px 24px 60px; }
        .headrow { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 14px; }
        h1 { font-family: 'Playfair Display', serif; font-size: 26px; }
        .btn-back { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13px; }

        .stat-strip { display: flex; border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; margin-bottom: 24px; overflow: hidden; }
        .stat-cell { flex: 1; padding: 16px 22px; border-right: 1px solid rgba(255,255,255,0.06); }
        .stat-cell:last-child { border-right: none; }
        .stat-cell .lbl { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255,255,255,0.4); }
        .stat-cell .val { font-size: 20px; font-weight: 700; margin-top: 5px; }
        .stat-cell.pending .val { color: #fbbf24; }
        .stat-cell.verified .val { color: #34d399; }
        .stat-cell.rejected .val { color: #f87171; }

        .filter-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; }
        .tab-row { display: flex; gap: 4px; flex-wrap: wrap; }
        .tab-link { padding: 8px 16px; border-radius: 8px; font-size: 12.5px; text-decoration: none; color: rgba(255,255,255,0.5); background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); }
        .tab-link.active { background: #d4af37; color: #0a0f1e; font-weight: 700; border-color: #d4af37; }
        .status-select { padding: 8px 14px; border-radius: 8px; font-size: 12.5px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); color: white; font-family: inherit; }

        table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; overflow: hidden; }
        th { text-align: left; padding: 13px 16px; background: rgba(255,255,255,0.02); font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255,255,255,0.4); border-bottom: 1px solid rgba(255,255,255,0.05); }
        td { padding: 13px 16px; font-size: 13px; color: rgba(255,255,255,0.8); border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tbody tr { cursor: pointer; transition: background 0.15s ease; }
        tbody tr:hover td { background: rgba(212,175,55,0.03); }
        .thumb { width: 42px; height: 42px; border-radius: 8px; object-fit: cover; border: 1px solid rgba(255,255,255,0.08); }
        .pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .pill.pending { background: rgba(251,191,36,0.1); color: #fbbf24; }
        .pill.verified { background: rgba(52,211,153,0.1); color: #34d399; }
        .pill.rejected { background: rgba(248,113,113,0.1); color: #f87171; }
        .cust-name { color: white; font-weight: 500; }
        .cust-sub { color: rgba(255,255,255,0.35); font-size: 11px; margin-top: 1px; }
        .amt { color: #d4af37; font-weight: 700; }
        .empty-state { text-align: center; padding: 60px 20px; color: rgba(255,255,255,0.4); }

        .pager { display: flex; justify-content: center; gap: 6px; margin-top: 24px; }
        .pager a, .pager span { padding: 7px 12px; border-radius: 6px; font-size: 12px; text-decoration: none; color: rgba(255,255,255,0.5); border: 1px solid rgba(255,255,255,0.06); }
        .pager .current { background: #d4af37; color: #0a0f1e; border-color: #d4af37; font-weight: 700; }
        .pager .disabled { opacity: 0.3; pointer-events: none; }

        /* Detail modal */
        .modal-overlay { position: fixed; inset: 0; background: rgba(5,8,16,0.8); z-index: 999; display: none; align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: #10182c; border: 1px solid rgba(212,175,55,0.15); border-radius: 18px; padding: 30px; max-width: 520px; width: 100%; max-height: 88vh; overflow-y: auto; position: relative; }
        .modal-close { position: absolute; top: 18px; right: 18px; background: rgba(255,255,255,0.05); border: none; color: rgba(255,255,255,0.6); width: 32px; height: 32px; border-radius: 50%; font-size: 16px; cursor: pointer; }
        .modal-close:hover { background: rgba(239,68,68,0.15); color: #f87171; }
        .modal-box h3 { font-family: 'Playfair Display', serif; color: white; font-size: 20px; margin-bottom: 4px; }
        .modal-sub { color: rgba(255,255,255,0.35); font-size: 12px; margin-bottom: 20px; }
        .modal-section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #d4af37; font-weight: 700; margin: 18px 0 8px; }
        .modal-section-title:first-of-type { margin-top: 0; }
        .modal-row { display: flex; justify-content: space-between; gap: 16px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 13px; }
        .modal-row:last-child { border-bottom: none; }
        .modal-row span:first-child { color: rgba(255,255,255,0.4); }
        .modal-row span:last-child { color: white; font-weight: 500; text-align: right; }
        .modal-row .amt { font-size: 15px; }
        .proof-img { width: 100%; border-radius: 10px; margin: 10px 0; border: 1px solid rgba(255,255,255,0.08); cursor: zoom-in; }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn-approve, .btn-reject { flex: 1; padding: 12px; border-radius: 10px; font-weight: 700; font-size: 13.5px; border: none; cursor: pointer; }
        .btn-approve { background: #34d399; color: #0a0f1e; }
        .btn-approve:hover { background: #22c55e; }
        .btn-reject { background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2); }
        .btn-reject:hover { background: #dc2626; color: white; }
        .reject-box { display: none; margin-top: 14px; }
        .reject-box textarea { width: 100%; padding: 12px; border-radius: 10px; background: rgba(255,255,255,0.03); border: 1px solid rgba(239,68,68,0.2); color: white; font-family: inherit; font-size: 13px; resize: vertical; min-height: 70px; }
        .reject-box .hint { font-size: 11.5px; color: rgba(255,255,255,0.35); margin: 6px 0 10px; }
        .already-note { padding: 12px 16px; border-radius: 10px; font-size: 12.5px; margin-top: 16px; }
        .already-note.verified { background: rgba(52,211,153,0.08); border: 1px solid rgba(52,211,153,0.15); color: #34d399; }
        .already-note.rejected { background: rgba(248,113,113,0.08); border: 1px solid rgba(248,113,113,0.15); color: #f87171; }
    </style>
</head>
<body>
<div class="container">
    <a href="agent_dashboard.php" class="btn-back">← Back to Dashboard</a>
    <div class="headrow">
        <h1>Payments</h1>
    </div>

    <div class="stat-strip">
        <div class="stat-cell pending"><div class="lbl">Pending Review</div><div class="val"><?php echo $pending_count; ?></div></div>
        <div class="stat-cell verified"><div class="lbl">Verified</div><div class="val"><?php echo $verified_count; ?></div></div>
        <div class="stat-cell rejected"><div class="lbl">Rejected</div><div class="val"><?php echo $rejected_count; ?></div></div>
    </div>

    <div class="filter-row">
        <div class="tab-row">
            <a href="?range=all&status=<?php echo $status_filter; ?>" class="tab-link <?php echo $range === 'all' ? 'active' : ''; ?>">Recent</a>
            <a href="?range=yesterday&status=<?php echo $status_filter; ?>" class="tab-link <?php echo $range === 'yesterday' ? 'active' : ''; ?>">Yesterday</a>
            <a href="?range=7days&status=<?php echo $status_filter; ?>" class="tab-link <?php echo $range === '7days' ? 'active' : ''; ?>">Last Week</a>
            <a href="?range=month&status=<?php echo $status_filter; ?>" class="tab-link <?php echo $range === 'month' ? 'active' : ''; ?>">Last Month</a>
            <a href="?range=older&status=<?php echo $status_filter; ?>" class="tab-link <?php echo $range === 'older' ? 'active' : ''; ?>">Older</a>
        </div>
        <select class="status-select" onchange="window.location.href = '?range=<?php echo $range; ?>&status=' + this.value;">
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
    </div>

    <?php if (count($payments) > 0): ?>
    <table>
        <thead>
            <tr><th></th><th>Customer</th><th>Booking</th><th>Reference</th><th>Submitted</th><th>Status</th><th style="text-align:right;">Amount</th></tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $p): ?>
            <tr onclick="openPaymentDetails(<?php echo (int)$p['id']; ?>)">
                <td><?php if (!empty($p['screenshot_path'])): ?><img class="thumb" src="<?php echo htmlspecialchars($p['screenshot_path']); ?>" alt=""><?php endif; ?></td>
                <td>
                    <div class="cust-name"><?php echo htmlspecialchars($p['customer_name'] ?: $p['user_name']); ?></div>
                    <div class="cust-sub"><?php echo htmlspecialchars($p['payer_name']); ?> (payer)</div>
                </td>
                <td><?php echo htmlspecialchars($p['booking_no']); ?><div class="cust-sub"><?php echo htmlspecialchars(ucfirst($p['service_type'])); ?></div></td>
                <td><?php echo htmlspecialchars($p['payment_reference']); ?></td>
                <td><?php echo date('M j, Y g:i A', strtotime($p['submitted_at'])); ?></td>
                <td><span class="pill <?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                <td style="text-align:right;" class="amt">SAR <?php echo number_format($p['total_amount']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div class="pager">
        <a href="?range=<?php echo $range; ?>&status=<?php echo $status_filter; ?>&page=<?php echo max(1,$page-1); ?>" class="<?php echo $page<=1?'disabled':''; ?>">&lsaquo; Prev</a>
        <?php for ($p2=1; $p2<=$total_pages; $p2++): ?>
            <?php if ($p2==$page): ?><span class="current"><?php echo $p2; ?></span>
            <?php else: ?><a href="?range=<?php echo $range; ?>&status=<?php echo $status_filter; ?>&page=<?php echo $p2; ?>"><?php echo $p2; ?></a><?php endif; ?>
        <?php endfor; ?>
        <a href="?range=<?php echo $range; ?>&status=<?php echo $status_filter; ?>&page=<?php echo min($total_pages,$page+1); ?>" class="<?php echo $page>=$total_pages?'disabled':''; ?>">Next &rsaquo;</a>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state"><i class="fas fa-credit-card" style="font-size:26px; display:block; margin-bottom:10px; opacity:0.4;"></i>No payments in this range.</div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="detailsModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <div id="detailsContent"></div>
    </div>
</div>

<script>
const csrfToken = '<?php echo csrf_token(); ?>';

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = (s === null || s === undefined) ? '' : s;
    return d.innerHTML;
}
function row(label, value) {
    if (value === null || value === undefined || value === '') return '';
    return '<div class="modal-row"><span>' + escHtml(label) + '</span><span>' + escHtml(value) + '</span></div>';
}

function openPaymentDetails(paymentId) {
    document.getElementById('detailsModal').classList.add('active');
    document.getElementById('detailsContent').innerHTML = '<div style="text-align:center; padding:40px 0; color:rgba(255,255,255,0.4);">Loading...</div>';

    fetch('get_payment_details.php?id=' + paymentId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('detailsContent').innerHTML = '<p style="color:#f87171;">' + escHtml(data.error) + '</p>';
                return;
            }
            renderDetails(data);
        })
        .catch(() => {
            document.getElementById('detailsContent').innerHTML = '<p style="color:#f87171;">Network error.</p>';
        });
}

function closeModal() {
    document.getElementById('detailsModal').classList.remove('active');
}
document.getElementById('detailsModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

function renderDetails(data) {
    const p = data.payment, b = data.booking, d = data.details;
    let html = '<h3>' + escHtml(b.booking_no) + '</h3>';
    html += '<div class="modal-sub">' + escHtml(b.service_type ? b.service_type.charAt(0).toUpperCase() + b.service_type.slice(1) : '') + ' booking</div>';

    html += '<div class="modal-section-title">Customer</div>';
    html += row('Name', b.customer_name);
    html += row('Email', b.customer_email);
    html += row('Phone', b.customer_phone);

    html += '<div class="modal-section-title">Booking</div>';
    if (d.hotel_name) html += row('Hotel', d.hotel_name);
    if (d.room_type) html += row('Room Type', d.room_type);
    if (d.car_name) html += row('Vehicle', d.car_name);
    if (d.from_city || d.to_city) html += row('Route', (d.from_city || '') + ' → ' + (d.to_city || ''));
    html += row('Travel Date', b.travel_date);
    html += '<div class="modal-row"><span>Total Amount</span><span class="amt" style="color:#d4af37;">SAR ' + escHtml(Number(b.total_amount).toLocaleString()) + '</span></div>';

    html += '<div class="modal-section-title">Payment Proof</div>';
    html += row('Payer Name', p.payer_name);
    html += row('Reference', p.payment_reference);
    html += row('Submitted', p.submitted_at);
    if (p.screenshot_url) {
        html += '<a href="' + escHtml(p.screenshot_url) + '" target="_blank"><img class="proof-img" src="' + escHtml(p.screenshot_url) + '" alt="Payment screenshot"></a>';
    }

    if (p.status === 'pending') {
        html += `
            <div class="modal-actions">
                <button class="btn-approve" onclick="approvePayment(${p.id})">Approve</button>
                <button class="btn-reject" onclick="showRejectBox()">Reject</button>
            </div>
            <div class="reject-box" id="rejectBox">
                <div class="modal-section-title" style="margin-top:14px;">Reason for Rejection</div>
                <textarea id="rejectReason" placeholder="e.g. Amount doesn't match the booking total, or screenshot is unclear -- this will be shown to the customer."></textarea>
                <div class="hint">The customer will see this reason and be able to submit a new payment.</div>
                <button class="btn-reject" style="width:100%;" onclick="rejectPayment(${p.id})">Confirm Rejection</button>
            </div>
        `;
    } else if (p.status === 'verified') {
        html += '<div class="already-note verified"><i class="fas fa-circle-check"></i> Verified on ' + escHtml(p.verified_at) + '. Booking is confirmed.</div>';
    } else if (p.status === 'rejected') {
        html += '<div class="already-note rejected"><i class="fas fa-circle-xmark"></i> Rejected' + (p.rejection_reason ? ': ' + escHtml(p.rejection_reason) : '') + '</div>';
    }

    document.getElementById('detailsContent').innerHTML = html;
}

function showRejectBox() {
    document.getElementById('rejectBox').style.display = 'block';
}

function approvePayment(paymentId) {
    if (!confirm('Approve this payment and confirm the booking?')) return;
    fetch('verify_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'payment_id=' + encodeURIComponent(paymentId) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { location.reload(); }
        else { alert('Could not approve: ' + (data.error || 'unknown error')); }
    })
    .catch(() => alert('Network error.'));
}

function rejectPayment(paymentId) {
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) { alert('Please enter a reason for the customer.'); return; }
    if (!confirm('Reject this payment? The customer will be asked to submit again.')) return;

    fetch('reject_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'payment_id=' + encodeURIComponent(paymentId) + '&reason=' + encodeURIComponent(reason) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { location.reload(); }
        else { alert('Could not reject: ' + (data.error || 'unknown error')); }
    })
    .catch(() => alert('Network error.'));
}
</script>
</body>
</html>