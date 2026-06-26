<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

// Get all bookings with user details
$stmt = $pdo->query("
    SELECT b.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
           COALESCE(s.title, b.service_type) as service_title
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    LEFT JOIN services s ON b.service_id = s.id
    ORDER BY b.created_at DESC
");
$bookings = $stmt->fetchAll();

// Stats
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        
        .navbar { background: #0f172a; padding: 16px 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { color: white; font-size: 22px; font-weight: 600; text-decoration: none; letter-spacing: -0.5px; }
        .logo span { color: #d4af37; }
        .nav-links a { color: #cbd5e1; text-decoration: none; margin-left: 24px; font-size: 14px; transition: color 0.3s ease; }
        .nav-links a:hover { color: #d4af37; }
        .nav-links .btn-logout { background: #ef4444; color: white; padding: 8px 20px; border-radius: 8px; margin-left: 24px; }
        
        .dashboard-header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; padding: 48px 0; }
        .dashboard-header h1 { font-size: 32px; font-weight: 600; margin-bottom: 8px; }
        .dashboard-header p { color: #94a3b8; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-top: -30px; margin-bottom: 32px; }
        .stat-card { background: white; padding: 24px; border-radius: 16px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .stat-number { font-size: 32px; font-weight: 700; color: #0f172a; }
        .stat-label { font-size: 13px; color: #64748b; margin-top: 8px; }
        
        .actions-section { background: white; padding: 16px 24px; border-radius: 16px; margin-bottom: 32px; display: flex; gap: 16px; flex-wrap: wrap; align-items: center; border: 1px solid #e2e8f0; }
        .action-btn { padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 500; text-decoration: none; }
        .action-btn-primary { background: #0f172a; color: white; }
        .action-btn-primary:hover { background: #d4af37; color: #0f172a; }
        
        .section-title { font-size: 20px; font-weight: 600; color: #0f172a; margin-bottom: 20px; }
        .table-container { background: white; border-radius: 16px; overflow-x: auto; border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 16px; background: #f8fafc; font-weight: 600; font-size: 13px; color: #0f172a; border-bottom: 1px solid #e2e8f0; }
        td { padding: 14px 16px; font-size: 13px; color: #334155; border-bottom: 1px solid #e2e8f0; }
        tr:hover td { background: #f8fafc; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-confirmed { background: #d1fae5; color: #059669; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        .status-select { padding: 6px 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 12px; }
        .btn-wa { background: #10b981; color: white; padding: 6px 12px; border-radius: 8px; text-decoration: none; font-size: 12px; }
        .btn-wa:hover { background: #059669; }
        
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } th, td { padding: 10px; } }
    </style>
</head>
<body>

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
        <h1>Agent Panel</h1>
        <p>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
    </div>
</div>

<div class="container">
    <!-- Stats -->
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
            <div class="stat-number">SAR <?php echo number_format($total_revenue); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="actions-section">
        <a href="services.php?type=hotels" class="action-btn action-btn-primary">Manage Hotels</a>
        <a href="services.php?type=taxi" class="action-btn action-btn-primary">Manage Taxis</a>
        <a href="services.php?type=visa" class="action-btn action-btn-primary">Manage Visas</a>
        <a href="services.php?type=groups" class="action-btn action-btn-primary">Manage Tours</a>
    </div>
    
    <!-- All Bookings Table (SAR Currency) -->
    <div class="section-title">All Bookings</div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Booking No</th><th>Customer</th><th>Email</th><th>Phone</th>
                    <th>Service</th><th>Amount</th><th>Travel Date</th><th>Booked On</th><th>Status</th><th>Action</th>
                 '</thead>
            <tbody>
                <?php foreach($bookings as $b): ?>
                <tr>
                    <td><?php echo $b['id']; ?></td>
                    <td><?php echo htmlspecialchars($b['booking_no']); ?></td>
                    <td><?php echo htmlspecialchars($b['user_name']); ?></td>
                    <td><?php echo $b['user_email']; ?></td>
                    <td><?php echo $b['user_phone']; ?></td>
                    <td>
                        <?php echo ucfirst($b['service_type']); ?>
                        <?php if(!empty($b['service_title'])): ?>
                            <br><small style="color:#64748b;"><?php echo htmlspecialchars($b['service_title']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><strong>SAR <?php echo number_format($b['total_amount']); ?></strong></td>
                    <td><?php echo $b['travel_date']; ?></td>
                    <td><?php echo date('d M Y h:i A', strtotime($b['created_at'])); ?></td>
                    <td>
                        <select class="status-select" data-id="<?php echo $b['id']; ?>">
                            <option value="pending" <?php echo $b['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $b['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="cancelled" <?php echo $b['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </td>
                    <td>
                        <a href="https://wa.me/92<?php echo $b['user_phone']; ?>" class="btn-wa" target="_blank">WhatsApp</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', function() {
        const bookingId = this.dataset.id;
        const newStatus = this.value;
        
        if(confirm('Change booking status to ' + newStatus.toUpperCase() + '? Customer will be notified.')) {
            fetch('update_booking_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + bookingId + '&status=' + newStatus
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