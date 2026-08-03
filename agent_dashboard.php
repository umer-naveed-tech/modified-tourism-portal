<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

// ==================== FILTERS FROM QUERY STRING ====================
$status = $_GET['status'] ?? 'pending';   // pending | confirmed | completed | cancelled | all
$search = trim($_GET['search'] ?? '');
$type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_col = $_GET['sort'] ?? 'created_at';
$sort_dir = (strtolower($_GET['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$allowed_sort_cols = ['created_at', 'total_amount', 'booking_date', 'id'];
if (!in_array($sort_col, $allowed_sort_cols)) $sort_col = 'created_at';

// ==================== BUILD WHERE CLAUSE ====================
$where = [];
$params = [];

if ($status !== 'all') {
    $where[] = "b.status = ?";
    $params[] = $status;
}
if ($search !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR b.booking_no LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}
if ($type_filter !== '') {
    $where[] = "b.service_type = ?";
    $params[] = $type_filter;
}
if ($date_from !== '') {
    $where[] = "b.booking_date >= ?";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where[] = "b.booking_date <= ?";
    $params[] = $date_to;
}
$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// ==================== STATUS TAB COUNTS ====================
$countStmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status");
$statusCounts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($statusCounts[$row['status']])) $statusCounts[$row['status']] = (int)$row['cnt'];
}
$totalAllCount = array_sum($statusCounts);

// ==================== FILTERED COUNT (for pagination) ====================
$countQuery = "SELECT COUNT(*) FROM bookings b JOIN users u ON b.user_id = u.id $whereSql";
$countStmt2 = $pdo->prepare($countQuery);
$countStmt2->execute($params);
$totalFiltered = (int)$countStmt2->fetchColumn();
$totalPages = max(1, ceil($totalFiltered / $per_page));

// ==================== MAIN QUERY (unchanged JOIN, now with filters/sort/pagination) ====================
$query = "
    SELECT b.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
           COALESCE(s.title, b.service_type) as service_title
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    LEFT JOIN services s ON b.service_id = s.id
    $whereSql
    ORDER BY b.$sort_col $sort_dir
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// ==================== STAT CARDS (unchanged -- global totals, not affected by filters) ====================
$stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
$total_bookings = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = CURDATE()");
$today_bookings = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'visitor'");
$total_visitors = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid'");
$total_revenue = $stmt->fetchColumn() ?? 0;

function qs($overrides = []) {
    $current = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($current[$k]);
        else $current[$k] = $v;
    }
    return htmlspecialchars('?' . http_build_query($current));
}
function sortIndicator($col, $currentSort, $currentDir) {
    if ($col !== $currentSort) return '';
    return $currentDir === 'ASC' ? ' &uarr;' : ' &darr;';
}
$typeLabels = ['hotel' => 'Hotel', 'taxi' => 'Taxi'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard | Ahmed Travels</title>
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
        @media (prefers-reduced-motion: reduce) { .bg-ambient::before { animation: none; } }

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

        .btn, button, .action-btn, .status-select {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn:active, button:active { transform: scale(0.97); }

        .stat-card { transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 16px 40px rgba(0,0,0,0.3); border-color: rgba(212,175,55,0.15); }

        .table-row { transition: all 0.2s ease; }
        .table-row:hover { background: rgba(255,255,255,0.02); }

        input:focus, select:focus { border-color: #d4af37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.08); outline: none; }

        .navbar { background: rgba(10, 15, 30, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(212, 175, 55, 0.08); padding: 14px 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-family: 'Playfair Display', serif; color: white; font-size: 22px; font-weight: 800; text-decoration: none; letter-spacing: -0.5px; }
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
            color: white; padding: 52px 0 60px; border-bottom: 1px solid rgba(212, 175, 55, 0.05);
        }
        .dashboard-header .gold-line { width: 60px; height: 3px; background: #d4af37; margin-bottom: 12px; border-radius: 2px; opacity: 0; animation: fadeSlideIn 0.6s ease forwards; }
        .dashboard-header h1 { font-family: 'Playfair Display', serif; font-size: 34px; font-weight: 800; margin-bottom: 8px; opacity: 0; transform: translateY(10px); animation: fadeSlideIn 0.6s ease forwards; animation-delay: 0.08s; }
        .dashboard-header p { color: rgba(255,255,255,0.5); opacity: 0; transform: translateY(10px); animation: fadeSlideIn 0.6s ease forwards; animation-delay: 0.16s; }
        @keyframes fadeSlideIn { to { opacity: 1; transform: translateY(0); } }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 22px; margin-top: -34px; margin-bottom: 32px; }
        .stat-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            padding: 24px; border-radius: 16px; text-align: center; position: relative; overflow: hidden;
        }
        .stat-icon { width: 38px; height: 38px; border-radius: 11px; background: rgba(212,175,55,0.1); border: 1px solid rgba(212,175,55,0.15); display: flex; align-items: center; justify-content: center; color: #d4af37; font-size: 15px; margin: 0 auto 12px; }
        .stat-number { font-size: 30px; font-weight: 700; color: #d4af37; }
        .stat-label { font-size: 12.5px; color: rgba(255,255,255,0.4); margin-top: 6px; text-transform: uppercase; letter-spacing: 0.4px; }

        .actions-section {
            background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04);
            padding: 16px 24px; border-radius: 16px; margin-bottom: 32px;
            display: flex; gap: 16px; flex-wrap: wrap; align-items: center;
        }
        .action-btn { padding: 9px 22px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.3s ease; }
        .action-btn-primary { background: rgba(212, 175, 55, 0.1); color: #d4af37; border: 1px solid rgba(212, 175, 55, 0.08); }
        .action-btn-primary:hover { background: #d4af37; color: #0a0f1e; transform: translateY(-2px); box-shadow: 0 8px 22px rgba(212,175,55,0.2); }

        .section-title {
            font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700; color: white; margin-bottom: 20px;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
        }
        .section-title .gold-line { width: 40px; height: 2px; background: #d4af37; margin-top: 6px; border-radius: 2px; }
        .result-count { font-size: 13px; font-weight: 400; color: rgba(255,255,255,0.4); font-family: 'Inter', sans-serif; }

        .status-tabs { display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.06); flex-wrap: wrap; }
        .status-tab { position: relative; padding: 10px 18px; color: rgba(255,255,255,0.5); text-decoration: none; font-size: 14px; font-weight: 500; border-bottom: 2px solid transparent; transition: all 0.2s ease; }
        .status-tab:hover { color: rgba(255,255,255,0.85); }
        .status-tab.active { color: #d4af37; border-bottom-color: #d4af37; }
        .status-tab .count { display: inline-block; margin-left: 6px; padding: 1px 8px; background: rgba(255,255,255,0.06); border-radius: 10px; font-size: 12px; }
        .status-tab.active .count { background: rgba(212,175,55,0.15); color: #d4af37; }

        .filters-bar {
            display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-bottom: 24px;
            padding: 16px 20px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); border-radius: 16px;
        }
        .filters-bar input[type="text"], .filters-bar input[type="date"], .filters-bar select {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); color: rgba(255,255,255,0.85);
            padding: 9px 12px; border-radius: 8px; font-size: 13px; font-family: inherit;
        }
        .filters-bar input[type="text"] { min-width: 240px; flex: 1; }
        .filters-bar label { font-size: 12px; color: rgba(255,255,255,0.35); margin-right: 4px; white-space: nowrap; }
        .filter-group { display: flex; align-items: center; gap: 6px; }
        .btn-apply-filter { background: #d4af37; color: #0a0f1e; border: none; padding: 9px 22px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; }
        .btn-apply-filter:hover { background: #b8922e; }
        .btn-clear-filter { background: transparent; color: rgba(255,255,255,0.4); border: 1px solid rgba(255,255,255,0.06); padding: 9px 16px; border-radius: 8px; font-size: 13px; text-decoration: none; }
        .btn-clear-filter:hover { color: #d4af37; border-color: rgba(212,175,55,0.2); }

        .table-container { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); border-radius: 16px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left; padding: 16px; background: rgba(255,255,255,0.02); font-weight: 600; font-size: 11.5px;
            color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.04); white-space: nowrap;
        }
        th a { color: inherit; text-decoration: none; }
        th a:hover { color: #d4af37; }
        td { padding: 14px 16px; font-size: 13px; color: rgba(255,255,255,0.7); border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: top; }
        tr:hover td { background: rgba(255,255,255,0.02); }

        .status-select {
            padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06);
            background: rgba(255,255,255,0.02); color: rgba(255,255,255,0.7); font-size: 12px; font-family: inherit; transition: all 0.3s ease;
        }
        .status-select:hover { border-color: rgba(212, 175, 55, 0.2); }

        /* NEW: clickable customer name -> profile history page */
        .customer-link { color: rgba(255,255,255,0.85); text-decoration: none; font-weight: 500; border-bottom: 1px dashed rgba(212,175,55,0.3); transition: all 0.2s ease; }
        .customer-link:hover { color: #d4af37; border-bottom-color: #d4af37; }

        .btn-wa {
            background: rgba(37, 211, 102, 0.1); color: #25D366; padding: 6px 14px; border-radius: 8px; text-decoration: none;
            font-size: 12px; font-weight: 600; transition: all 0.3s ease; border: 1px solid rgba(37, 211, 102, 0.08); display: inline-block; white-space: nowrap;
        }
        .btn-wa:hover { background: #25D366; color: white; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(37, 211, 102, 0.25); }

        .empty-row td { text-align: center; padding: 60px 20px; color: rgba(255,255,255,0.3); }

        .pagination { display: flex; gap: 6px; justify-content: center; align-items: center; padding: 24px 0 8px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 13px; border-radius: 6px; font-size: 13px; text-decoration: none; color: rgba(255,255,255,0.5); border: 1px solid rgba(255,255,255,0.06); }
        .pagination a:hover { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.9); }
        .pagination .current { background: #d4af37; color: #0a0f1e; border-color: #d4af37; font-weight: 600; }
        .pagination .disabled { opacity: 0.3; pointer-events: none; }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            th, td { padding: 8px 10px; font-size: 12px; }
            .btn-wa { padding: 4px 10px; font-size: 11px; }
            .filters-bar input[type="text"] { min-width: 100%; }
        }
    </style>
</head>
<body>

<div class="bg-ambient" aria-hidden="true"></div>
<div class="grain-overlay" aria-hidden="true"></div>
<div class="page-transition" id="pageTransition"><div class="pt-spinner"><div class="pt-ring"></div><i class="fas fa-plane pt-icon"></i></div></div>

<div class="page-content">
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">Ahmed<span>Travels</span></a>
            <div class="nav-links">
                <a href="services.php">Services</a>
                <a href="agent_dashboard.php">Dashboard</a>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
    </nav>

    <div class="dashboard-header">
        <div class="container">
            <div class="gold-line"></div>
            <h1>Agent Panel</h1>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
        </div>
    </div>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card reveal">
                <div class="stat-icon"><i class="fas fa-ticket"></i></div>
                <div class="stat-number"><?php echo $total_bookings; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-number"><?php echo $today_bookings; ?></div>
                <div class="stat-label">Today's Bookings</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $total_visitors; ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-icon" style="background:rgba(52,211,153,0.1); border-color:rgba(52,211,153,0.2); color:#34d399;"><i class="fas fa-sack-dollar"></i></div>
                <div class="stat-number" style="color:#34d399;">SAR <?php echo number_format($total_revenue); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <div class="actions-section reveal">
            <a href="services.php?type=hotels" class="action-btn action-btn-primary">Manage Hotels</a>
            <a href="services.php?type=taxi" class="action-btn action-btn-primary">Manage Taxis</a>
            <a href="services.php?type=visa" class="action-btn action-btn-primary">Manage Visas</a>
            <a href="agent_price_management.php" class="action-btn action-btn-primary">Manage Prices</a>
        </div>

        <div class="section-title">
            <span>All Bookings <span class="result-count">(<?php echo $totalFiltered; ?> found)</span></span>
            <div class="gold-line"></div>
        </div>

        <!-- STATUS TABS -->
        <div class="status-tabs">
            <a href="<?php echo qs(['status' => 'pending', 'page' => null]); ?>" class="status-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">
                Pending <span class="count"><?php echo $statusCounts['pending']; ?></span>
            </a>
            <a href="<?php echo qs(['status' => 'confirmed', 'page' => null]); ?>" class="status-tab <?php echo $status === 'confirmed' ? 'active' : ''; ?>">
                Confirmed <span class="count"><?php echo $statusCounts['confirmed']; ?></span>
            </a>
            <a href="<?php echo qs(['status' => 'completed', 'page' => null]); ?>" class="status-tab <?php echo $status === 'completed' ? 'active' : ''; ?>">
                Completed <span class="count"><?php echo $statusCounts['completed']; ?></span>
            </a>
            <a href="<?php echo qs(['status' => 'cancelled', 'page' => null]); ?>" class="status-tab <?php echo $status === 'cancelled' ? 'active' : ''; ?>">
                Cancelled <span class="count"><?php echo $statusCounts['cancelled']; ?></span>
            </a>
            <a href="<?php echo qs(['status' => 'all', 'page' => null]); ?>" class="status-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
                All <span class="count"><?php echo $totalAllCount; ?></span>
            </a>
        </div>

        <!-- FILTERS -->
        <form method="GET" class="filters-bar">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
            <input type="text" name="search" placeholder="Search customer name, email, or booking #" value="<?php echo htmlspecialchars($search); ?>">
            <div class="filter-group">
                <label>Type</label>
                <select name="type">
                    <option value="">All types</option>
                    <?php foreach ($typeLabels as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $type_filter === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="filter-group">
                <label>To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <button type="submit" class="btn-apply-filter">Apply</button>
            <a href="<?php echo qs(['search' => null, 'type' => null, 'date_from' => null, 'date_to' => null, 'page' => null]); ?>" class="btn-clear-filter">Clear</a>
        </form>

        <div class="table-container reveal">
            <table>
                <thead>
                    <tr>
                        <th><a href="<?php echo qs(['sort' => 'id', 'dir' => ($sort_col === 'id' && $sort_dir === 'DESC') ? 'asc' : 'desc']); ?>">ID<?php echo sortIndicator('id', $sort_col, $sort_dir); ?></a></th>
                        <th>Booking No</th><th>Customer</th><th>Email</th><th>Phone</th>
                        <th>Service</th>
                        <th><a href="<?php echo qs(['sort' => 'total_amount', 'dir' => ($sort_col === 'total_amount' && $sort_dir === 'DESC') ? 'asc' : 'desc']); ?>">Amount<?php echo sortIndicator('total_amount', $sort_col, $sort_dir); ?></a></th>
                        <th><a href="<?php echo qs(['sort' => 'booking_date', 'dir' => ($sort_col === 'booking_date' && $sort_dir === 'DESC') ? 'asc' : 'desc']); ?>">Travel Date<?php echo sortIndicator('booking_date', $sort_col, $sort_dir); ?></a></th>
                        <th><a href="<?php echo qs(['sort' => 'created_at', 'dir' => ($sort_col === 'created_at' && $sort_dir === 'DESC') ? 'asc' : 'desc']); ?>">Booked On<?php echo sortIndicator('created_at', $sort_col, $sort_dir); ?></a></th>
                        <th>Status</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                    <tr class="empty-row"><td colspan="10">No bookings found matching these filters.</td></tr>
                    <?php else: foreach($bookings as $b): ?>
                    <tr class="table-row">
                        <td><?php echo $b['id']; ?></td>
                        <td><?php echo htmlspecialchars($b['booking_no']); ?></td>
                        <td><a href="customer_profile.php?user_id=<?php echo (int)$b['user_id']; ?>" class="customer-link"><?php echo htmlspecialchars($b['user_name']); ?></a></td>
                        <td><?php echo htmlspecialchars($b['user_email']); ?></td>
                        <td><?php echo htmlspecialchars($b['user_phone']); ?></td>
                        <td>
                            <?php echo htmlspecialchars(ucfirst($b['service_type'])); ?>
                            <?php if(!empty($b['service_title'])): ?>
                                <br><small style="color:rgba(255,255,255,0.3);"><?php echo htmlspecialchars($b['service_title']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><strong style="color:#d4af37;">SAR <?php echo number_format($b['total_amount']); ?></strong></td>
                        <td><?php echo htmlspecialchars($b['travel_date']); ?></td>
                        <td><?php echo date('d M Y h:i A', strtotime($b['created_at'])); ?></td>
                        <td>
                            <select class="status-select" data-id="<?php echo (int)$b['id']; ?>">
                                <option value="pending" <?php echo $b['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $b['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $b['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $b['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </td>
                        <td>
                            <a href="https://wa.me/<?php echo preg_replace('/^0/', '92', $b['user_phone']); ?>" class="btn-wa" target="_blank">WhatsApp</a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <a href="<?php echo qs(['page' => max(1, $page - 1)]); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">&lsaquo; Prev</a>
            <?php
                $startP = max(1, $page - 2);
                $endP = min($totalPages, $page + 2);
                if ($startP > 1) echo '<a href="' . qs(['page' => 1]) . '">1</a><span>&hellip;</span>';
                for ($p = $startP; $p <= $endP; $p++) {
                    if ($p == $page) echo '<span class="current">' . $p . '</span>';
                    else echo '<a href="' . qs(['page' => $p]) . '">' . $p . '</a>';
                }
                if ($endP < $totalPages) echo '<span>&hellip;</span><a href="' . qs(['page' => $totalPages]) . '">' . $totalPages . '</a>';
            ?>
            <a href="<?php echo qs(['page' => min($totalPages, $page + 1)]); ?>" class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">Next &rsaquo;</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const csrfToken = '<?php echo csrf_token(); ?>';
document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', function() {
        const bookingId = this.dataset.id;
        const newStatus = this.value;
        
        if(confirm('Change booking status to ' + newStatus.toUpperCase() + '? Customer will be notified.')) {
            fetch('update_booking_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(bookingId) + '&status=' + encodeURIComponent(newStatus) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Status updated successfully!');
                    location.reload();
                } else {
                    alert('Error updating status');
                }
            });
        }
    });
});

/* NEW: page-transition overlay for internal navigation links */
document.querySelectorAll('.action-btn, .logo, .nav-links a:not(.btn-logout), .customer-link').forEach(a => {
    a.addEventListener('click', function() {
        document.getElementById('pageTransition').classList.add('active');
    });
});

/* NEW: scroll-reveal */
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

/* NEW BUG FIX: when a page is restored from the browser's back-forward
   cache (bfcache) via the Back/Forward button, the page-transition
   overlay can still be showing (frozen in the "active" state it was in
   right when the user clicked away) since no fresh page-load JS runs
   on a bfcache restore. This listens for that restore and clears the
   overlay so the page is visible again. */
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        const pt = document.getElementById('pageTransition');
        if (pt) pt.classList.remove('active');
    }
});
</script>

</body>
</html>