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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #0a0f1e; 
            min-height: 100vh;
        }
        
        .page-content {
            animation: fadeIn 0.5s ease forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .btn, button, .action-btn, .status-select {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn:hover, button:hover, .action-btn:hover { transform: translateY(-2px); }
        .btn:active, button:active { transform: scale(0.97); }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.3);
        }
        
        .table-row {
            transition: all 0.2s ease;
        }
        .table-row:hover {
            background: rgba(255,255,255,0.02);
        }
        
        .navbar { background: rgba(10, 15, 30, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(212, 175, 55, 0.08); padding: 14px 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { color: white; font-size: 22px; font-weight: 800; text-decoration: none; letter-spacing: -0.5px; }
        .logo span { color: #d4af37; }
        .nav-links a { color: rgba(255,255,255,0.7); text-decoration: none; margin-left: 24px; font-size: 14px; transition: all 0.3s ease; }
        .nav-links a:hover { color: #d4af37; }
        .nav-links .btn-logout { background: rgba(239,68,68,0.1); color: #f87171; padding: 8px 20px; border-radius: 8px; margin-left: 24px; transition: all 0.3s ease; }
        .nav-links .btn-logout:hover { background: #dc2626; color: white; transform: translateY(-2px); }
        
        .dashboard-header { 
            background: linear-gradient(180deg, #0a0f1e 0%, #0d1a2d 50%, #0a0f1e 100%);
            color: white; 
            padding: 48px 0;
            border-bottom: 1px solid rgba(212, 175, 55, 0.05);
        }
        .dashboard-header .gold-line {
            width: 60px;
            height: 3px;
            background: #d4af37;
            margin-bottom: 12px;
            border-radius: 2px;
        }
        .dashboard-header h1 { font-size: 32px; font-weight: 800; margin-bottom: 8px; }
        .dashboard-header p { color: rgba(255,255,255,0.5); }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-top: -30px; margin-bottom: 32px; }
        .stat-card { 
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.04);
            padding: 24px; 
            border-radius: 16px; 
            text-align: center; 
        }
        .stat-card:hover { border-color: rgba(212, 175, 55, 0.1); }
        .stat-number { font-size: 32px; font-weight: 700; color: #d4af37; }
        .stat-label { font-size: 13px; color: rgba(255,255,255,0.4); margin-top: 8px; }
        
        .actions-section { 
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            padding: 16px 24px; 
            border-radius: 16px; 
            margin-bottom: 32px; 
            display: flex; 
            gap: 16px; 
            flex-wrap: wrap; 
            align-items: center; 
        }
        .action-btn { 
            padding: 8px 20px; 
            border-radius: 8px; 
            font-size: 13px; 
            font-weight: 500; 
            text-decoration: none; 
            transition: all 0.3s ease;
        }
        .action-btn-primary { 
            background: rgba(212, 175, 55, 0.1); 
            color: #d4af37; 
            border: 1px solid rgba(212, 175, 55, 0.05);
        }
        .action-btn-primary:hover { background: #d4af37; color: #0a0f1e; transform: translateY(-2px); }
        
        .section-title { 
            font-size: 20px; 
            font-weight: 700; 
            color: white; 
            margin-bottom: 20px; 
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .section-title .gold-line {
            width: 40px;
            height: 2px;
            background: #d4af37;
            margin-top: 6px;
            border-radius: 2px;
        }
        .result-count { font-size: 13px; font-weight: 400; color: rgba(255,255,255,0.4); }

        /* ===== NEW: Status Tabs ===== */
        .status-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            flex-wrap: wrap;
        }
        .status-tab {
            padding: 10px 18px;
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }
        .status-tab:hover { color: rgba(255,255,255,0.85); }
        .status-tab.active { color: #d4af37; border-bottom-color: #d4af37; }
        .status-tab .count {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 8px;
            background: rgba(255,255,255,0.06);
            border-radius: 10px;
            font-size: 12px;
        }
        .status-tab.active .count { background: rgba(212,175,55,0.15); color: #d4af37; }

        /* ===== NEW: Filters bar ===== */
        .filters-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 24px;
            padding: 16px 20px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 16px;
        }
        .filters-bar input[type="text"],
        .filters-bar input[type="date"],
        .filters-bar select {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.85);
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-family: inherit;
        }
        .filters-bar input[type="text"] { min-width: 240px; flex: 1; }
        .filters-bar label { font-size: 12px; color: rgba(255,255,255,0.35); margin-right: 4px; white-space: nowrap; }
        .filter-group { display: flex; align-items: center; gap: 6px; }
        .btn-apply-filter {
            background: #d4af37;
            color: #0a0f1e;
            border: none;
            padding: 9px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-clear-filter {
            background: transparent;
            color: rgba(255,255,255,0.4);
            border: 1px solid rgba(255,255,255,0.06);
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 13px;
            text-decoration: none;
        }

        .table-container { 
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 16px; 
            overflow-x: auto; 
        }
        table { width: 100%; border-collapse: collapse; }
        th { 
            text-align: left; 
            padding: 16px; 
            background: rgba(255,255,255,0.02);
            font-weight: 600; 
            font-size: 12px; 
            color: rgba(255,255,255,0.5); 
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            white-space: nowrap;
        }
        th a { color: inherit; text-decoration: none; }
        th a:hover { color: #d4af37; }
        td { 
            padding: 14px 16px; 
            font-size: 13px; 
            color: rgba(255,255,255,0.7); 
            border-bottom: 1px solid rgba(255,255,255,0.02);
            vertical-align: top;
        }
        tr:hover td { background: rgba(255,255,255,0.02); }
        .status-badge { 
            display: inline-block; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 11px; 
            font-weight: 500; 
        }
        .status-pending { background: rgba(251,191,36,0.1); color: #fbbf24; }
        .status-confirmed { background: rgba(16,185,129,0.1); color: #34d399; }
        .status-completed { background: rgba(59,130,246,0.1); color: #60a5fa; }
        .status-cancelled { background: rgba(239,68,68,0.1); color: #f87171; }
        .status-select { 
            padding: 6px 10px; 
            border-radius: 8px; 
            border: 1px solid rgba(255,255,255,0.06);
            background: rgba(255,255,255,0.02);
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            transition: all 0.3s ease;
        }
        .status-select:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.05);
        }
        .status-select:hover { border-color: rgba(212, 175, 55, 0.1); }
        
        .btn-wa { 
            background: rgba(37, 211, 102, 0.1);
            color: #25D366;
            padding: 6px 14px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(37, 211, 102, 0.05);
            display: inline-block;
            white-space: nowrap;
        }
        .btn-wa:hover { 
            background: #25D366; 
            color: white; 
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.2);
        }

        .empty-row td { text-align: center; padding: 60px 20px; color: rgba(255,255,255,0.3); }

        /* ===== NEW: Pagination ===== */
        .pagination {
            display: flex;
            gap: 6px;
            justify-content: center;
            align-items: center;
            padding: 24px 0 8px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 8px 13px;
            border-radius: 6px;
            font-size: 13px;
            text-decoration: none;
            color: rgba(255,255,255,0.5);
            border: 1px solid rgba(255,255,255,0.06);
        }
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
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_bookings; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $today_bookings; ?></div>
                <div class="stat-label">Today's Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_visitors; ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color:#34d399;">SAR <?php echo number_format($total_revenue); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>
        
        <div class="actions-section">
            <a href="services.php?type=hotels" class="action-btn action-btn-primary">Manage Hotels</a>
            <a href="services.php?type=taxi" class="action-btn action-btn-primary">Manage Taxis</a>
            <a href="services.php?type=visa" class="action-btn action-btn-primary">Manage Visas</a>
            <a href="agent_price_management.php" class="action-btn action-btn-primary" style="background:#d4af37; color:#0a0f1e; border-color:#d4af37;">Manage Prices</a>
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

        <div class="table-container">
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
                        <td><?php echo htmlspecialchars($b['user_name']); ?></td>
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
</script>

</body>
</html>