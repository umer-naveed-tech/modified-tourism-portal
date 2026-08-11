<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

// 🔴 NEW: opportunistic booking-lifecycle maintenance -- auto-completes
// confirmed bookings whose stay/trip has passed, sends a one-time 24h
// payment reminder, and cancels truly-abandoned bookings (pending, no
// payment ever submitted, older than 48 hours). Runs at most once
// every 6 hours via a small marker file, so this never adds real cost
// to normal page loads. Safe even without true cron access on shared
// hosting; if cron IS available, cleanup_abandoned_bookings.php can
// also be pointed at directly for more precise timing.
require_once 'cleanup_abandoned_bookings.php';
$marker_file = __DIR__ . '/uploads/.last_abandoned_cleanup';
if (!file_exists($marker_file) || (time() - filemtime($marker_file)) > 6 * 3600) {
    runBookingMaintenance($pdo);
    @touch($marker_file);
}

// ==================== FILTERS FROM QUERY STRING ====================
$status = $_GET['status'] ?? 'pending';   // pending | confirmed | completed | cancelled | all
$search = trim($_GET['search'] ?? '');
$type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_col = $_GET['sort'] ?? 'created_at';
// 🔴 FIX: default was always "newest first" -- for the Pending tab
// specifically that buries old, still-unreviewed payments under a
// stream of new ones. If the agent hasn't explicitly clicked a sort
// column, Pending defaults to oldest-first instead; clicking any
// column header still works exactly as before and overrides this.
$default_dir = ($status === 'pending' && !isset($_GET['sort']) && !isset($_GET['dir'])) ? 'asc' : 'desc';
$sort_dir = (strtolower($_GET['dir'] ?? $default_dir) === 'asc') ? 'ASC' : 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$allowed_sort_cols = ['created_at', 'total_amount', 'booking_date', 'id'];
if (!in_array($sort_col, $allowed_sort_cols)) $sort_col = 'created_at';

// ==================== BUILD WHERE CLAUSE ====================
$where = [];
$params = [];

// Bookings the agent removed from their own view stay in the database
// (and still show for the customer, and still count toward revenue/
// analytics elsewhere) -- they're just excluded from this list.
$where[] = "b.hidden_by_agent = 0";

if ($status !== 'all') {
    $where[] = "b.status = ?";
    $params[] = $status;
    // A "pending" booking must be one the customer actually confirmed
    // (see customer_confirmed_at) -- an abandoned draft never reached
    // that point and isn't a real booking yet, so it shouldn't show
    // up in the agent's Pending list either.
    if ($status === 'pending') {
        $where[] = "b.customer_confirmed_at IS NOT NULL";
    }
} else {
    $where[] = "(b.status != 'pending' OR b.customer_confirmed_at IS NOT NULL)";
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
// Pending only counts bookings the customer actually confirmed (see
// customer_confirmed_at) -- an abandoned draft from before they
// reached the Review step was never a real booking, so it shouldn't
// inflate this count.
$countStmt = $pdo->query("
    SELECT status, COUNT(*) as cnt FROM bookings
    WHERE status != 'pending' OR customer_confirmed_at IS NOT NULL
    GROUP BY status
");
$statusCounts = ['pending' => 0, 'completed' => 0, 'cancelled' => 0];
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
        /* 🔴 FIX: native <select> dropdown options were showing white
           background with unreadable text -- browsers render the
           OPEN dropdown list of an <option> separately from the closed
           select box, and mostly ignore the select's own background/
           color for it unless the <option> elements are styled
           directly. This fixes it everywhere on this page. */
        select { background-color: rgba(255,255,255,0.03); color: white; }
        select option { background-color: #10182c; color: white; }

        /* NEW: highlight pending bookings older than 24h so they stand
           out from newer ones instead of getting lost in the list. */
        .stale-pending-row td { background: rgba(251,191,36,0.03); }
        .stale-pending-row:hover td { background: rgba(251,191,36,0.06) !important; }
        .stale-badge {
            display: inline-block; margin-left: 6px; padding: 2px 8px; border-radius: 20px;
            background: rgba(251,191,36,0.12); color: #fbbf24; font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.3px; vertical-align: middle;
        }

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
        .container { max-width: 1560px; margin: 0 auto; padding: 0 24px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; max-width: 1280px; }
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

        /* NEW: sticky Action column -- the Details button is the
           most-used part of each row, so it stays visible on the right
           edge even when the table needs to scroll horizontally on
           narrower screens, instead of getting cut off out of view. */
        table th:last-child, table td:last-child {
            position: sticky; right: 0; z-index: 2;
            background: #0d1424;
            box-shadow: -6px 0 10px -6px rgba(0,0,0,0.5);
            white-space: nowrap;
        }
        thead th:last-child { z-index: 3; }
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

        /* NEW: Details button + booking-details modal -- shows the
           agent exactly what was booked (hotel/room/car/route/etc)
           instead of just the service type. */
        .btn-details {
            background: rgba(212,175,55,0.1); color: #d4af37; padding: 6px 14px; border-radius: 8px;
            font-size: 12px; font-weight: 600; transition: all 0.3s ease; border: 1px solid rgba(212,175,55,0.15);
            display: inline-block; white-space: nowrap; cursor: pointer; font-family: inherit; margin-right: 6px;
        }
        .btn-details:hover { background: #d4af37; color: #0a0f1e; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(212,175,55,0.25); }

        .btn-remove-booking {
            background: rgba(255,255,255,0.03); color: rgba(255,255,255,0.35); width: 26px; height: 26px; border-radius: 8px;
            font-size: 15px; line-height: 1; border: 1px solid rgba(255,255,255,0.06); cursor: pointer; font-family: inherit;
            vertical-align: middle;
        }
        .btn-remove-booking:hover { background: rgba(239,68,68,0.12); color: #f87171; border-color: rgba(239,68,68,0.2); }

        .modal-overlay {
            position: fixed; inset: 0; z-index: 9999; background: rgba(5,8,16,0.75); backdrop-filter: blur(6px);
            display: none; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.active { display: flex; animation: modalFadeIn 0.2s ease; }
        @keyframes modalFadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal-box {
            position: relative; background: #0d1220; border: 1px solid rgba(212,175,55,0.15); border-radius: 18px;
            padding: 32px; max-width: 480px; width: 100%; max-height: 85vh; overflow-y: auto;
            box-shadow: 0 25px 70px rgba(0,0,0,0.5);
            opacity: 0; transform: translateY(16px) scale(0.98); animation: modalBoxIn 0.25s cubic-bezier(.2,.8,.3,1) forwards;
        }
        @keyframes modalBoxIn { to { opacity: 1; transform: translateY(0) scale(1); } }
        .modal-close {
            position: absolute; top: 18px; right: 18px; background: rgba(255,255,255,0.05); border: none; color: rgba(255,255,255,0.6);
            width: 32px; height: 32px; border-radius: 50%; font-size: 18px; cursor: pointer; transition: all 0.2s ease;
            display: flex; align-items: center; justify-content: center;
        }
        .modal-close:hover { background: rgba(239,68,68,0.15); color: #f87171; }
        .modal-box h3 { font-family: 'Playfair Display', serif; color: white; font-size: 20px; margin-bottom: 4px; }
        .modal-subtitle { color: rgba(255,255,255,0.35); font-size: 12.5px; margin-bottom: 20px; }
        .legacy-note {
            background: rgba(251,191,36,0.08); border: 1px solid rgba(251,191,36,0.15); color: #fbbf24;
            padding: 10px 14px; border-radius: 10px; font-size: 12px; margin-bottom: 18px; line-height: 1.5;
        }
        .detail-section-title {
            font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #d4af37; font-weight: 700;
            margin: 18px 0 8px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.05);
        }
        .detail-section-title:first-of-type { margin-top: 0; padding-top: 0; border-top: none; }
        .detail-row {
            display: flex; justify-content: space-between; gap: 16px; padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 13.5px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row span:first-child { color: rgba(255,255,255,0.4); flex-shrink: 0; }
        .detail-row span:last-child { color: white; font-weight: 500; text-align: right; }
        .detail-row .amt { color: #d4af37; font-weight: 700; font-size: 15px; }
        .modal-loading { text-align: center; padding: 40px 0; color: rgba(255,255,255,0.4); font-size: 14px; }

        .empty-row td { text-align: center; padding: 60px 20px; color: rgba(255,255,255,0.3); }

        .pagination { display: flex; gap: 6px; justify-content: center; align-items: center; padding: 24px 0 8px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 13px; border-radius: 6px; font-size: 13px; text-decoration: none; color: rgba(255,255,255,0.5); border: 1px solid rgba(255,255,255,0.06); }
        .pagination a:hover { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.9); }
        .pagination .current { background: #d4af37; color: #0a0f1e; border-color: #d4af37; font-weight: 600; }
        .pagination .disabled { opacity: 0.3; pointer-events: none; }

        /* NEW: Bulk actions bar -- hidden until at least one row checkbox
           is checked; shown/hidden purely by JS toggling .active, doesn't
           touch table markup or the per-row status-select logic at all. */
        .bulk-checkbox { width: 16px; height: 16px; accent-color: #d4af37; cursor: pointer; }
        .bulk-bar {
            display: none;
            align-items: center; gap: 14px; flex-wrap: wrap;
            background: rgba(212,175,55,0.06); border: 1px solid rgba(212,175,55,0.15);
            padding: 12px 18px; border-radius: 12px; margin-bottom: 16px;
        }
        .bulk-bar.active { display: flex; animation: bulkBarIn 0.25s ease; }
        @keyframes bulkBarIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
        .bulk-bar #bulkCount { color: #d4af37; font-weight: 600; font-size: 13px; margin-right: 4px; }
        .bulk-btn {
            padding: 8px 16px; border-radius: 8px; font-size: 12.5px; font-weight: 600;
            border: none; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 6px;
        }
        .bulk-btn-confirm { background: rgba(16,185,129,0.12); color: #34d399; }
        .bulk-btn-confirm:hover { background: #10b981; color: white; transform: translateY(-2px); }
        .bulk-btn-reminder { background: rgba(59,130,246,0.12); color: #60a5fa; }
        .bulk-btn-reminder:hover { background: #3b82f6; color: white; transform: translateY(-2px); }
        .bulk-btn-clear { background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.5); margin-left: auto; }
        .bulk-btn-clear:hover { background: rgba(255,255,255,0.08); color: white; }
        .bulk-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            th, td { padding: 8px 10px; font-size: 12px; }
            .btn-wa { padding: 4px 10px; font-size: 11px; }
            .filters-bar input[type="text"] { min-width: 100%; }
        }
        /* NEW: sidebar layout -- scoped with an "agent-" prefix so
           these classes can never collide with any of the existing
           CSS above (stat-card, container, etc. are all untouched). */
        .agent-shell { display: flex; min-height: 100vh; }
        .agent-sidebar { width: 220px; flex-shrink: 0; background: rgba(10,15,30,0.95); border-right: 1px solid rgba(212,175,55,0.08); padding: 24px 0; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .agent-sidebar .brand { display: block; font-family: 'Playfair Display', serif; font-size: 19px; font-weight: 800; color: white; text-decoration: none; padding: 0 22px 26px; }
        .agent-sidebar .brand span { color: #d4af37; }
        .agent-sidebar a.side-link { display: flex; align-items: center; gap: 11px; padding: 11px 22px; color: rgba(255,255,255,0.6); text-decoration: none; font-size: 13.5px; transition: all 0.2s ease; position: relative; }
        .agent-sidebar a.side-link:hover { color: #d4af37; background: rgba(255,255,255,0.02); }
        .agent-sidebar a.side-link.on { color: white; background: rgba(212,175,55,0.06); }
        .agent-sidebar a.side-link.on::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 2px; background: #d4af37; }
        .agent-sidebar a.side-link i { width: 16px; font-size: 13px; }
        .agent-sidebar .side-div { height: 1px; background: rgba(255,255,255,0.06); margin: 14px 22px; }
        .agent-sidebar a.side-logout { color: #f87171; }
        .agent-sidebar a.side-logout:hover { background: rgba(239,68,68,0.08); }
        .agent-main { flex: 1; min-width: 0; }
        @media (max-width: 900px) {
            .agent-shell { flex-direction: column; }
            .agent-sidebar { width: 100%; height: auto; position: relative; padding: 14px 0; display: flex; flex-wrap: wrap; align-items: center; }
            .agent-sidebar .brand { width: 100%; padding-bottom: 10px; }
            .agent-sidebar .side-div { display: none; }
        }
    </style>
</head>
<body>

<div class="bg-ambient" aria-hidden="true"></div>
<div class="grain-overlay" aria-hidden="true"></div>
<div class="page-transition" id="pageTransition"><div class="pt-spinner"><div class="pt-ring"></div><i class="fas fa-plane pt-icon"></i></div></div>

<div class="page-content">
    <div class="agent-shell">
        <div class="agent-sidebar">
            <a href="agent_dashboard.php" class="brand">Ahmed<span>Travels</span></a>
            <a href="agent_dashboard.php" class="side-link on"><i class="fas fa-gauge" aria-hidden="true"></i>Dashboard</a>
            <a href="agent_manage_hotels.php" class="side-link"><i class="fas fa-hotel" aria-hidden="true"></i>Manage Hotels</a>
            <a href="agent_manage_taxis.php" class="side-link"><i class="fas fa-car" aria-hidden="true"></i>Manage Taxis</a>
            <a href="agent_panel.php" class="side-link"><i class="fas fa-passport" aria-hidden="true"></i>Manage Visas</a>
            <a href="agent_price_management.php" class="side-link"><i class="fas fa-tags" aria-hidden="true"></i>Manage Prices</a>
            <a href="agent_payments.php" class="side-link"><i class="fas fa-credit-card" aria-hidden="true"></i>Payments</a>
            <a href="agent_revenue.php" class="side-link"><i class="fas fa-chart-line" aria-hidden="true"></i>Revenue</a>
            <a href="agent_theme_settings.php" class="side-link"><i class="fas fa-image" aria-hidden="true"></i>User Panel View</a>
            <a href="agent_bank_details.php" class="side-link"><i class="fas fa-building-columns" aria-hidden="true"></i>Bank Details</a>
            <div class="side-div"></div>
            <a href="services.php" class="side-link"><i class="fas fa-globe" aria-hidden="true"></i>View Site</a>
            <a href="logout.php" class="side-link side-logout" onclick="return confirm('Are you sure you want to log out?');"><i class="fas fa-right-from-bracket" aria-hidden="true"></i>Logout</a>
        </div>

        <div class="agent-main">
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
        </div>

        <div id="ajaxContent">
        <div class="section-title">
            <span>All Bookings <span class="result-count">(<?php echo $totalFiltered; ?> found)</span></span>
            <div class="gold-line"></div>
        </div>

        <!-- STATUS TABS -->
        <div class="status-tabs">
            <a href="<?php echo qs(['status' => 'pending', 'page' => null]); ?>" class="status-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">
                Pending <span class="count"><?php echo $statusCounts['pending']; ?></span>
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

        <!-- BULK ACTIONS BAR -->
        <div class="bulk-bar" id="bulkBar">
            <span id="bulkCount">0 selected</span>
            <button type="button" id="bulkConfirmBtn" class="bulk-btn bulk-btn-confirm"><i class="fas fa-check"></i> Mark as Completed</button>
            <button type="button" id="bulkReminderBtn" class="bulk-btn bulk-btn-reminder"><i class="fas fa-bell"></i> Send Check-in Reminder</button>
            <button type="button" id="bulkClearBtn" class="bulk-btn bulk-btn-clear">Clear</button>
        </div>

        <div class="table-container reveal">
            <table>
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="selectAll" class="bulk-checkbox"></th>
                        <th>Booking No</th><th>Customer</th><th>Email</th><th>Phone</th>
                        <th>Service</th>
                        <th><a href="<?php echo qs(['sort' => 'total_amount', 'dir' => ($sort_col === 'total_amount' && $sort_dir === 'DESC') ? 'asc' : 'desc']); ?>">Amount<?php echo sortIndicator('total_amount', $sort_col, $sort_dir); ?></a></th>
                        <th><a href="<?php echo qs(['sort' => 'created_at', 'dir' => ($sort_col === 'created_at' && $sort_dir === 'DESC') ? 'asc' : 'desc']); ?>">Booked On<?php echo sortIndicator('created_at', $sort_col, $sort_dir); ?></a></th>
                        <th>Status</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                    <tr class="empty-row"><td colspan="10">No bookings found matching these filters.</td></tr>
                    <?php else: foreach($bookings as $b):
                        // NEW: flag pending bookings older than 24 hours
                        // so they visually stand out instead of getting
                        // buried once newer ones come in.
                        $is_stale_pending = ($b['status'] === 'pending') && (strtotime($b['created_at']) < strtotime('-24 hours'));
                    ?>
                    <tr class="table-row <?php echo $is_stale_pending ? 'stale-pending-row' : ''; ?>">
                        <td><input type="checkbox" class="row-check bulk-checkbox" value="<?php echo (int)$b['id']; ?>"></td>
                        <td>
                            <?php echo htmlspecialchars($b['booking_no']); ?>
                            <?php if ($is_stale_pending): ?>
                                <span class="stale-badge" title="Pending for more than 24 hours">Waiting <?php echo (int)floor((time() - strtotime($b['created_at'])) / 3600); ?>h</span>
                            <?php endif; ?>
                        </td>
                        <td><a href="customer_profile.php?user_id=<?php echo (int)$b['user_id']; ?>" class="customer-link"><?php echo htmlspecialchars($b['user_name']); ?></a></td>
                        <td><?php echo htmlspecialchars($b['user_email']); ?></td>
                        <td><?php echo htmlspecialchars($b['user_phone']); ?></td>
                        <td>
                            <?php echo htmlspecialchars(ucfirst($b['service_type'])); ?>
                        </td>
                        <td><strong style="color:#d4af37;">SAR <?php echo number_format($b['total_amount']); ?></strong></td>
                        <td><?php echo date('d M Y h:i A', strtotime($b['created_at'])); ?></td>
                        <td>
                            <select class="status-select" data-id="<?php echo (int)$b['id']; ?>">
                                <option value="pending" <?php echo $b['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $b['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $b['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="btn-details" data-id="<?php echo (int)$b['id']; ?>">Details</button>
                            <button type="button" class="btn-remove-booking" data-id="<?php echo (int)$b['id']; ?>" title="Remove from my view">&times;</button>
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
        </div>
        </div>
</div>

<!-- NEW: booking-details modal -->
<div id="bookingDetailsModal" class="modal-overlay">
    <div class="modal-box">
        <button type="button" class="modal-close" id="bookingModalClose" aria-label="Close">&times;</button>
        <div id="bookingDetailsContent"><div class="modal-loading">Loading booking details...</div></div>
    </div>
</div>

<script>
const csrfToken = '<?php echo csrf_token(); ?>';
const ajaxContentEl = document.getElementById('ajaxContent');
let revealObserver;

/* ---------- Status-select change handler (same request/logic as
   before -- only the "what happens after success" changed: instead of
   location.reload() it now does a partial AJAX refresh so scroll
   position isn't lost). Rebindable so it also works on content that
   was just swapped in via AJAX. ---------- */
function bindStatusSelects(root) {
    root.querySelectorAll('.status-select').forEach(select => {
        // 🔴 FIX: capture the value BEFORE it changes, so if the agent
        // declines the confirmation below, the dropdown visually snaps
        // back to what it actually still is in the database -- before
        // this, a declined confirm left the dropdown showing the new
        // status even though nothing was saved, which is misleading.
        select.addEventListener('focus', function() {
            this.dataset.prevValue = this.value;
        });

        select.addEventListener('change', function() {
            const bookingId = this.dataset.id;
            const newStatus = this.value;
            const prevValue = this.dataset.prevValue || this.value;
            const selectEl = this;

            if(confirm('Change booking status to ' + newStatus.toUpperCase() + '? Customer will be notified.')) {
                fetch('update_booking_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(bookingId) + '&status=' + encodeURIComponent(newStatus) + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        refreshAjaxContent();
                    } else {
                        alert('Error updating status');
                        selectEl.value = prevValue;
                    }
                });
            } else {
                selectEl.value = prevValue;
            }
        });
    });
}

/* ---------- Bulk actions -- identical logic to before, only the final
   location.reload() calls became refreshAjaxContent(). Rebindable. ---------- */
function bindBulkActions(root) {
    const selectAll = root.querySelector('#selectAll');
    const rowChecks = () => root.querySelectorAll('.row-check');
    const bulkBar = root.querySelector('#bulkBar');
    const bulkCount = root.querySelector('#bulkCount');
    const confirmBtn = root.querySelector('#bulkConfirmBtn');
    const reminderBtn = root.querySelector('#bulkReminderBtn');
    const clearBtn = root.querySelector('#bulkClearBtn');
    if (!bulkBar) return;

    function selectedIds() {
        return Array.from(root.querySelectorAll('.row-check:checked')).map(cb => cb.value);
    }

    function refreshBar() {
        const n = selectedIds().length;
        if (n > 0) {
            bulkBar.classList.add('active');
            bulkCount.textContent = n + ' selected';
        } else {
            bulkBar.classList.remove('active');
        }
        if (selectAll) {
            const all = rowChecks();
            selectAll.checked = all.length > 0 && n === all.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            rowChecks().forEach(cb => { cb.checked = this.checked; });
            refreshBar();
        });
    }
    rowChecks().forEach(cb => cb.addEventListener('change', refreshBar));

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            rowChecks().forEach(cb => { cb.checked = false; });
            if (selectAll) selectAll.checked = false;
            refreshBar();
        });
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            const ids = selectedIds();
            if (ids.length === 0) return;
            if (!confirm('Mark ' + ids.length + ' booking(s) as Completed? Customers will be notified by email.')) return;

            confirmBtn.disabled = true;
            confirmBtn.innerHTML = 'Processing...';

            Promise.all(ids.map(id => fetch('update_booking_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id) + '&status=completed&csrf_token=' + encodeURIComponent(csrfToken)
            }).then(r => r.json())))
            .then(results => {
                const failedCount = results.filter(r => !r.success).length;
                alert(failedCount > 0
                    ? (ids.length - failedCount) + ' updated, ' + failedCount + ' failed. Please review.'
                    : ids.length + ' booking(s) marked as Completed.');
                refreshAjaxContent();
            })
            .catch(() => {
                alert('Something went wrong updating some bookings. Refreshing -- please check statuses.');
                refreshAjaxContent();
            });
        });
    }

    if (reminderBtn) {
        reminderBtn.addEventListener('click', function() {
            const ids = selectedIds();
            if (ids.length === 0) return;
            if (!confirm('Send a check-in reminder email to ' + ids.length + ' customer(s)?')) return;

            reminderBtn.disabled = true;
            const originalText = reminderBtn.innerHTML;
            reminderBtn.innerHTML = 'Sending...';

            fetch('send_bulk_reminder.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'booking_ids=' + encodeURIComponent(JSON.stringify(ids)) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message || 'Reminders sent.');
            })
            .catch(() => {
                alert('Failed to send reminders. Please check your connection and try again.');
            })
            .finally(() => {
                reminderBtn.disabled = false;
                reminderBtn.innerHTML = originalText;
            });
        });
    }
}

/* ---------- Scroll-reveal, rebindable for freshly-swapped-in content ---------- */
function bindReveal(root) {
    if ('IntersectionObserver' in window) {
        if (!revealObserver) {
            revealObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) { entry.target.classList.add('in-view'); revealObserver.unobserve(entry.target); }
                });
            }, { threshold: 0.1 });
        }
        root.querySelectorAll('.reveal:not(.in-view)').forEach(el => revealObserver.observe(el));
    } else {
        root.querySelectorAll('.reveal').forEach(el => el.classList.add('in-view'));
    }
}

/* ---------- Customer-profile link: this goes to a genuinely different
   page (customer_profile.php), not a filtered view of this same
   dashboard -- so it keeps the original full-navigation + loading
   overlay behavior, same as before. Rebindable for swapped-in rows. ---------- */
function bindCustomerLinks(root) {
    root.querySelectorAll('.customer-link').forEach(a => {
        a.addEventListener('click', function() {
            document.getElementById('pageTransition').classList.add('active');
        });
    });
}

/* ---------- NEW: AJAX partial-refresh engine ----------
   Only intercepts links that filter/sort/paginate THIS SAME dashboard
   (status tabs, column-sort headers, pagination, clear-filter) and the
   filter form's own submit. Fetches the same URL, pulls out just the
   #ajaxContent portion of the response, and swaps it in -- so the
   navbar, stat cards, and (most importantly) the page's scroll
   position never move. If anything about the fetch looks unexpected
   (e.g. session expired and the response is a login page with no
   #ajaxContent), it falls back to a normal full navigation so the
   action is never silently lost. */
let ajaxLoading = false;

function loadAjaxContent(url) {
    if (ajaxLoading || !ajaxContentEl) { window.location.href = url; return; }
    ajaxLoading = true;
    ajaxContentEl.style.opacity = '0.45';
    ajaxContentEl.style.pointerEvents = 'none';

    fetch(url)
        .then(r => r.text())
        .then(html => {
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const newContent = parsed.getElementById('ajaxContent');
            if (!newContent) { window.location.href = url; return; }

            ajaxContentEl.innerHTML = newContent.innerHTML;
            const isSameUrl = (url === window.location.pathname + window.location.search);
            if (isSameUrl) history.replaceState({ ajaxUrl: url }, '', url);
            else history.pushState({ ajaxUrl: url }, '', url);

            bindStatusSelects(ajaxContentEl);
            bindBulkActions(ajaxContentEl);
            bindReveal(ajaxContentEl);
            bindCustomerLinks(ajaxContentEl);
            bindAjaxLinks(ajaxContentEl);
            bindAjaxForm(ajaxContentEl);
            bindDetailsButtons(ajaxContentEl);
            bindRemoveBookingButtons(ajaxContentEl);
        })
        .catch(() => { window.location.href = url; })
        .finally(() => {
            ajaxLoading = false;
            if (ajaxContentEl) {
                ajaxContentEl.style.opacity = '';
                ajaxContentEl.style.pointerEvents = '';
            }
        });
}

function refreshAjaxContent() {
    loadAjaxContent(window.location.pathname + window.location.search);
}

function bindAjaxLinks(root) {
    root.querySelectorAll('.status-tab, th a, .pagination a:not(.disabled), .btn-clear-filter').forEach(a => {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            loadAjaxContent(this.getAttribute('href'));
        });
    });
}

function bindAjaxForm(root) {
    const form = root.querySelector('form.filters-bar');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const params = new URLSearchParams(new FormData(form)).toString();
        loadAjaxContent(window.location.pathname + '?' + params);
    });
}

window.addEventListener('popstate', function() {
    loadAjaxContent(window.location.pathname + window.location.search);
});

/* ---------- Page-transition overlay for links OUTSIDE #ajaxContent
   (navbar, Manage Hotels/Taxis/Visas/Prices) -- unchanged from before,
   these are genuine page changes and should still show the overlay. ---------- */
document.querySelectorAll('.action-btn, .logo, .nav-links a:not(.btn-logout)').forEach(a => {
    a.addEventListener('click', function() {
        document.getElementById('pageTransition').classList.add('active');
    });
});

/* ---------- Initial bind (covers both the static parts of the page and
   the server-rendered #ajaxContent on first load) ---------- */
bindReveal(document.body);
if (ajaxContentEl) {
    bindStatusSelects(ajaxContentEl);
    bindBulkActions(ajaxContentEl);
    bindCustomerLinks(ajaxContentEl);
    bindAjaxLinks(ajaxContentEl);
    bindAjaxForm(ajaxContentEl);
    bindDetailsButtons(ajaxContentEl);
    bindRemoveBookingButtons(ajaxContentEl);
}

/* NEW: booking-details modal -- fetches get_booking_details.php and
   renders whatever is known about that booking (hotel/room/car/route/
   dates/meal/extra bed etc). Falls back gracefully with a clear note
   when data had to be reconstructed for a pre-existing booking. */
function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = (str === null || str === undefined) ? '' : str;
    return div.innerHTML;
}

function openBookingDetails(id) {
    const overlay = document.getElementById('bookingDetailsModal');
    const content = document.getElementById('bookingDetailsContent');
    overlay.classList.add('active');
    content.innerHTML = '<div class="modal-loading">Loading booking details...</div>';

    fetch('get_booking_details.php?id=' + encodeURIComponent(id))
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                content.innerHTML = '<h3>Booking Details</h3><p style="color:#f87171;">' + escHtml(data.error || 'Could not load details.') + '</p>';
                return;
            }
            renderBookingDetails(data);
        })
        .catch(() => {
            content.innerHTML = '<h3>Booking Details</h3><p style="color:#f87171;">Network error. Please try again.</p>';
        });
}

function closeBookingDetails() {
    document.getElementById('bookingDetailsModal').classList.remove('active');
}

function renderBookingDetails(data) {
    const b = data.booking;
    const d = data.details || {};
    let html = '<h3>Booking ' + escHtml(b.booking_no) + '</h3>';
    html += '<div class="modal-subtitle">' + escHtml(b.service_type ? (b.service_type.charAt(0).toUpperCase() + b.service_type.slice(1)) : '') + ' booking</div>';

    html += '<div class="detail-section-title">Customer</div>';
    html += row('Name', b.customer_name);
    html += row('Email', b.customer_email);
    html += row('Phone', b.customer_phone);
    html += row('Country', b.customer_country);
    if (b.id_type) html += row(b.id_type === 'passport' ? 'Passport No.' : 'ID Card No.', b.id_number);

    html += '<div class="detail-section-title">What Was Booked</div>';
    if (b.service_type === 'hotel') {
        html += row('Hotel', d.hotel_name);
        html += row('Room Type', d.room_type);
        if (d.bed_type) html += row('Bed Type', d.bed_type);
        if (d.meal_type) html += row('Meal Plan', d.meal_type);
        if (d.check_in) html += row('Check-in', d.check_in);
        if (d.check_out) html += row('Check-out', d.check_out);
        if (d.nights) html += row('Nights', d.nights);
        if (b.extra_bed) html += row('Extra Bed', 'Yes (SAR ' + escHtml(b.extra_bed_price) + ')');
        if (d.supplement) html += row('Supplement', d.supplement);
        if (!d.room_type && d.raw_info) html += row('Details', d.raw_info);
    } else if (b.service_type === 'taxi') {
        html += row('Vehicle', [d.car_name, d.car_model].filter(Boolean).join(' '));
        html += row('Capacity', d.capacity ? (d.capacity + ' persons') : null);
        html += row('Route', (d.from_city || b.from_location) + ' → ' + (d.to_city || b.to_location));
        html += row('Total Fare', 'SAR ' + escHtml(Number(b.total_amount).toLocaleString()));
    } else if (b.service_type === 'ziyarat') {
        html += row('Route', d.ziyarat_route);
        html += row('Pickup', d.pickup_location);
        html += row('Notes', d.special_requests);
    } else {
        html += row('Service', d.service_title);
        html += row('Description', d.service_description);
        if (!d.service_title && d.raw_info) html += row('Details', d.raw_info);
    }

    html += '<div class="detail-section-title">Booking Info</div>';
    html += row('Guests', b.guests);
    html += row('Travel Date', b.travel_date);
    html += row('Booked On', b.created_at);
    html += row('Status', b.status ? (b.status.charAt(0).toUpperCase() + b.status.slice(1)) : null);
    html += '<div class="detail-row"><span>Total Amount</span><span class="amt">SAR ' + escHtml(Number(b.total_amount).toLocaleString()) + '</span></div>';

    // NEW: payment proof section -- only appears once the customer has
    // reached the payment step of the new booking flow.
    const p = data.payment;
    if (p) {
        html += '<div class="detail-section-title">Payment Proof</div>';
        html += row('Payer Name', p.payer_name);
        html += row('Payment ID / Reference', p.payment_reference);
        html += row('Submitted', p.submitted_at);
        html += row('Status', p.status.charAt(0).toUpperCase() + p.status.slice(1));
        if (p.screenshot_url) {
            html += '<div style="margin:10px 0;"><a href="' + escHtml(p.screenshot_url) + '" target="_blank"><img src="' + escHtml(p.screenshot_url) + '" alt="Payment screenshot" style="max-width:100%; border-radius:10px; border:1px solid rgba(255,255,255,0.08);"></a></div>';
        }
        if (p.status === 'pending') {
            html += '<button type="button" class="btn-verify-payment" data-id="' + b.id + '" style="width:100%; margin-top:10px; padding:12px; background:#34d399; color:#0a0f1e; border:none; border-radius:10px; font-weight:700; font-size:13.5px; cursor:pointer;">Verify Payment &amp; Confirm Booking</button>';
        } else if (p.status === 'verified') {
            html += '<div style="margin-top:10px; padding:10px 14px; background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.15); border-radius:10px; color:#34d399; font-size:12.5px;">Payment verified on ' + escHtml(p.verified_at) + '. Booking is confirmed.</div>';
        }
    } else if (b.payment_status && b.payment_status !== 'awaiting_details') {
        html += '<div class="detail-section-title">Payment</div>';
        html += '<div style="padding:10px 14px; background:rgba(251,191,36,0.08); border:1px solid rgba(251,191,36,0.15); border-radius:10px; color:#fbbf24; font-size:12.5px;">Customer has not submitted payment proof yet.</div>';
    }

    document.getElementById('bookingDetailsContent').innerHTML = html;

    if (p && p.status === 'pending') {
        document.querySelector('.btn-verify-payment').addEventListener('click', function() {
            verifyPayment(this.dataset.id, this);
        });
    }
}

function verifyPayment(bookingId, btn) {
    if (!confirm('Confirm that you have checked this payment and want to mark the booking as confirmed?')) return;
    btn.disabled = true;
    btn.textContent = 'Verifying...';

    fetch('verify_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'booking_id=' + encodeURIComponent(bookingId) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            openBookingDetails(bookingId); // refresh the modal to show the verified state
        } else {
            alert('Could not verify payment: ' + (data.error || 'unknown error'));
            btn.disabled = false;
            btn.textContent = 'Verify Payment & Confirm Booking';
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Verify Payment & Confirm Booking';
    });
}

function row(label, value) {
    if (value === null || value === undefined || value === '') return '';
    return '<div class="detail-row"><span>' + escHtml(label) + '</span><span>' + escHtml(value) + '</span></div>';
}

function bindDetailsButtons(root) {
    root.querySelectorAll('.btn-details').forEach(btn => {
        btn.addEventListener('click', function() {
            openBookingDetails(this.dataset.id);
        });
    });
}
bindDetailsButtons(document);

function bindRemoveBookingButtons(root) {
    root.querySelectorAll('.btn-remove-booking').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Remove this booking from your view? It stays in the system and the customer can still see it -- this only removes it from your list.')) return;
            const id = this.dataset.id;
            fetch('hide_booking_agent.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) { refreshAjaxContent(); }
                else { alert('Could not remove: ' + (data.error || 'unknown error')); }
            })
            .catch(() => alert('Network error. Please try again.'));
        });
    });
}
bindRemoveBookingButtons(document);

document.getElementById('bookingModalClose').addEventListener('click', closeBookingDetails);
document.getElementById('bookingDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) closeBookingDetails(); // click outside the box closes it
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeBookingDetails();
});

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