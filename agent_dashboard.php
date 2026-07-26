<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$stmt = $pdo->query("
    SELECT b.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
           COALESCE(s.title, b.service_type) as service_title
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    LEFT JOIN services s ON b.service_id = s.id
    ORDER BY b.created_at DESC
");
$bookings = $stmt->fetchAll();

$stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
$total_bookings = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = CURDATE()");
$today_bookings = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'visitor'");
$total_visitors = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid'");
$total_revenue = $stmt->fetchColumn() ?? 0;
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
        }
        .section-title .gold-line {
            width: 40px;
            height: 2px;
            background: #d4af37;
            margin-top: 6px;
            border-radius: 2px;
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
        }
        td { 
            padding: 14px 16px; 
            font-size: 13px; 
            color: rgba(255,255,255,0.7); 
            border-bottom: 1px solid rgba(255,255,255,0.02);
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
        
        /* ===== FIXED: WhatsApp Button ===== */
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
        
        @media (max-width: 768px) { 
            .stats-grid { grid-template-columns: repeat(2, 1fr); } 
            th, td { padding: 8px 10px; font-size: 12px; }
            .btn-wa { padding: 4px 10px; font-size: 11px; }
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
            All Bookings
            <div class="gold-line"></div>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Booking No</th><th>Customer</th><th>Email</th><th>Phone</th>
                        <th>Service</th><th>Amount</th><th>Travel Date</th><th>Booked On</th><th>Status</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($bookings as $b): ?>
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
                                <option value="cancelled" <?php echo $b['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </td>
                        <td>
                            <!-- ===== FIXED: WhatsApp with correct number format ===== -->
                            <a href="https://wa.me/<?php echo preg_replace('/^0/', '92', $b['user_phone']); ?>" class="btn-wa" target="_blank">WhatsApp</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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