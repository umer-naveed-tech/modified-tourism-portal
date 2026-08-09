<?php
// agent_revenue.php
//
// Real revenue analytics -- ONLY counts money from bookings the agent
// has actually verified payment for (payments.status = 'verified').
// This replaces the old "Total Revenue" stat card, which checked
// bookings.payment_status = 'paid' -- a value nothing in this system
// ever actually sets, so that number was always wrong/stale.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
date_default_timezone_set('Asia/Riyadh');

$period = $_GET['period'] ?? 'monthly';
if (!in_array($period, ['daily', 'monthly', 'yearly'])) $period = 'monthly';

// ---- Summary cards ----
$stmt = $pdo->query("
    SELECT COALESCE(SUM(b.total_amount), 0) AS total, COUNT(*) AS cnt
    FROM payments p JOIN bookings b ON p.booking_id = b.id
    WHERE p.status = 'verified'
");
$all_time = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT COALESCE(SUM(b.total_amount), 0)
    FROM payments p JOIN bookings b ON p.booking_id = b.id
    WHERE p.status = 'verified' AND DATE(p.verified_at) = CURDATE()
");
$today_revenue = (float)$stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COALESCE(SUM(b.total_amount), 0)
    FROM payments p JOIN bookings b ON p.booking_id = b.id
    WHERE p.status = 'verified' AND YEAR(p.verified_at) = YEAR(CURDATE()) AND MONTH(p.verified_at) = MONTH(CURDATE())
");
$month_revenue = (float)$stmt->fetchColumn();

$avg_order = $all_time['cnt'] > 0 ? $all_time['total'] / $all_time['cnt'] : 0;

// ---- Chart data, grouped by the selected period ----
if ($period === 'daily') {
    // Last 30 days
    $stmt = $pdo->query("
        SELECT DATE(p.verified_at) AS label, SUM(b.total_amount) AS revenue
        FROM payments p JOIN bookings b ON p.booking_id = b.id
        WHERE p.status = 'verified' AND p.verified_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(p.verified_at)
        ORDER BY label
    ");
} elseif ($period === 'yearly') {
    // Last 6 years
    $stmt = $pdo->query("
        SELECT YEAR(p.verified_at) AS label, SUM(b.total_amount) AS revenue
        FROM payments p JOIN bookings b ON p.booking_id = b.id
        WHERE p.status = 'verified' AND p.verified_at >= DATE_SUB(CURDATE(), INTERVAL 6 YEAR)
        GROUP BY YEAR(p.verified_at)
        ORDER BY label
    ");
} else {
    // monthly -- last 12 months
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(p.verified_at, '%Y-%m') AS label, SUM(b.total_amount) AS revenue
        FROM payments p JOIN bookings b ON p.booking_id = b.id
        WHERE p.status = 'verified' AND p.verified_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(p.verified_at, '%Y-%m')
        ORDER BY label
    ");
}
$chart_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$chart_labels = array_map(function($r) use ($period) {
    if ($period === 'daily') return date('M j', strtotime($r['label']));
    if ($period === 'yearly') return (string)$r['label'];
    return date('M Y', strtotime($r['label'] . '-01'));
}, $chart_rows);
$chart_values = array_map(fn($r) => (float)$r['revenue'], $chart_rows);

// ---- Breakdown by service type (all-time, verified only) ----
$stmt = $pdo->query("
    SELECT b.service_type, COALESCE(SUM(b.total_amount), 0) AS revenue
    FROM payments p JOIN bookings b ON p.booking_id = b.id
    WHERE p.status = 'verified'
    GROUP BY b.service_type
");
$by_service = $stmt->fetchAll(PDO::FETCH_ASSOC);
$service_labels = array_map(fn($r) => ucfirst($r['service_type']), $by_service);
$service_values = array_map(fn($r) => (float)$r['revenue'], $by_service);

// ---- Recent verified payments (small table) ----
$stmt = $pdo->query("
    SELECT b.booking_no, b.total_amount, b.service_type, p.verified_at,
           b.customer_name, u.name AS user_name
    FROM payments p JOIN bookings b ON p.booking_id = b.id JOIN users u ON b.user_id = u.id
    WHERE p.status = 'verified'
    ORDER BY p.verified_at DESC
    LIMIT 10
");
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue | Agent Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: white; min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px 24px 60px; }
        .headrow { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px; }
        h1 { font-family: 'Playfair Display', serif; font-size: 26px; }
        .btn-back { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13px; }

        .stat-strip { display: flex; border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; margin-bottom: 24px; overflow: hidden; flex-wrap: wrap; }
        .stat-cell { flex: 1; min-width: 160px; padding: 18px 22px; border-right: 1px solid rgba(255,255,255,0.06); }
        .stat-cell:last-child { border-right: none; }
        .stat-cell .lbl { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255,255,255,0.4); }
        .stat-cell .val { font-size: 21px; font-weight: 700; margin-top: 6px; color: #34d399; }

        .card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 24px; margin-bottom: 20px; }
        .card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; }
        .card-head h3 { font-size: 15px; font-weight: 600; }

        .tab-row { display: flex; gap: 4px; }
        .tab-link { padding: 7px 16px; border-radius: 8px; font-size: 12.5px; text-decoration: none; color: rgba(255,255,255,0.5); background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); }
        .tab-link.active { background: #d4af37; color: #0a0f1e; font-weight: 700; border-color: #d4af37; }

        .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        @media (max-width: 900px) { .charts-row { grid-template-columns: 1fr; } }
        .chart-wrap { position: relative; height: 320px; }
        .chart-wrap-sm { position: relative; height: 260px; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px 12px; font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255,255,255,0.4); border-bottom: 1px solid rgba(255,255,255,0.06); }
        td { padding: 11px 12px; font-size: 13px; color: rgba(255,255,255,0.8); border-bottom: 1px solid rgba(255,255,255,0.03); }
        tr:last-child td { border-bottom: none; }
        .amt { color: #34d399; font-weight: 700; }
        .empty-note { text-align: center; padding: 40px 20px; color: rgba(255,255,255,0.35); font-size: 13px; }
    </style>
</head>
<body>
<div class="container">
    <a href="agent_dashboard.php" class="btn-back">← Back to Dashboard</a>
    <div class="headrow">
        <h1>Revenue</h1>
    </div>

    <div class="stat-strip">
        <div class="stat-cell"><div class="lbl">Total Revenue (Verified)</div><div class="val">SAR <?php echo number_format($all_time['total']); ?></div></div>
        <div class="stat-cell"><div class="lbl">This Month</div><div class="val">SAR <?php echo number_format($month_revenue); ?></div></div>
        <div class="stat-cell"><div class="lbl">Today</div><div class="val">SAR <?php echo number_format($today_revenue); ?></div></div>
        <div class="stat-cell"><div class="lbl">Verified Bookings</div><div class="val" style="color:#d4af37;"><?php echo (int)$all_time['cnt']; ?></div></div>
        <div class="stat-cell"><div class="lbl">Avg. Order Value</div><div class="val" style="color:#d4af37;">SAR <?php echo number_format($avg_order); ?></div></div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3>Revenue Over Time</h3>
            <div class="tab-row">
                <a href="?period=daily" class="tab-link <?php echo $period === 'daily' ? 'active' : ''; ?>">Daily (30d)</a>
                <a href="?period=monthly" class="tab-link <?php echo $period === 'monthly' ? 'active' : ''; ?>">Monthly (12m)</a>
                <a href="?period=yearly" class="tab-link <?php echo $period === 'yearly' ? 'active' : ''; ?>">Yearly</a>
            </div>
        </div>
        <?php if (empty($chart_values)): ?>
            <div class="empty-note">No verified revenue in this range yet.</div>
        <?php else: ?>
            <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
        <?php endif; ?>
    </div>

    <div class="charts-row">
        <div class="card">
            <div class="card-head"><h3>Recent Verified Payments</h3></div>
            <?php if (empty($recent)): ?>
                <div class="empty-note">No verified payments yet.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Booking</th><th>Customer</th><th>Verified</th><th style="text-align:right;">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['booking_no']); ?><br><span style="color:rgba(255,255,255,0.35); font-size:11px;"><?php echo htmlspecialchars(ucfirst($r['service_type'])); ?></span></td>
                        <td><?php echo htmlspecialchars($r['customer_name'] ?: $r['user_name']); ?></td>
                        <td><?php echo date('M j, g:i A', strtotime($r['verified_at'])); ?></td>
                        <td style="text-align:right;" class="amt">SAR <?php echo number_format($r['total_amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-head"><h3>By Service Type</h3></div>
            <?php if (empty($service_values)): ?>
                <div class="empty-note">No data yet.</div>
            <?php else: ?>
                <div class="chart-wrap-sm"><canvas id="serviceChart"></canvas></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const chartLabels = <?php echo json_encode($chart_labels); ?>;
const chartValues = <?php echo json_encode($chart_values); ?>;
const serviceLabels = <?php echo json_encode($service_labels); ?>;
const serviceValues = <?php echo json_encode($service_values); ?>;

Chart.defaults.color = 'rgba(255,255,255,0.5)';
Chart.defaults.font.family = 'Inter';

if (chartValues.length > 0) {
    new Chart(document.getElementById('revenueChart'), {
        type: '<?php echo $period === "yearly" ? "bar" : "line"; ?>',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Revenue (SAR)',
                data: chartValues,
                borderColor: '#d4af37',
                backgroundColor: 'rgba(212,175,55,0.12)',
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#d4af37',
                pointRadius: 3,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.04)' } },
                y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { callback: v => 'SAR ' + v.toLocaleString() } }
            }
        }
    });
}

if (serviceValues.length > 0) {
    new Chart(document.getElementById('serviceChart'), {
        type: 'doughnut',
        data: {
            labels: serviceLabels,
            datasets: [{
                data: serviceValues,
                backgroundColor: ['#d4af37', '#34d399', '#5b8fd6', '#f87171', '#c9a24b'],
                borderColor: '#0a0f1e',
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14, font: { size: 11.5 } } } }
        }
    });
}
</script>
</body>
</html>