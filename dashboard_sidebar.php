<?php
// dashboard_sidebar.php
//
// Shared sidebar for the new editorial-style visitor dashboard pages
// (visitor_dashboard.php, my_bookings.php, booking_history.php,
// payments_history.php). Included by each page with $active_page set
// beforehand (one of: 'dashboard', 'bookings', 'history', 'payments',
// 'profile', 'support').
//
// Kept as a single shared file so all 4 pages always look identical --
// changing the sidebar once here updates it everywhere, instead of
// having to edit 4 separate copies and risking them drifting apart.

if (!isset($active_page)) $active_page = '';
?>
<div class="side">
    <a href="visitor_dashboard.php" class="brand">Ahmed<span>Travels</span></a>
    <a href="visitor_dashboard.php" class="<?php echo $active_page === 'dashboard' ? 'on' : ''; ?>"><i class="fas fa-gauge" aria-hidden="true"></i>Dashboard</a>
    <a href="my_bookings.php" class="<?php echo $active_page === 'bookings' ? 'on' : ''; ?>"><i class="fas fa-ticket" aria-hidden="true"></i>My Bookings</a>
    <a href="booking_history.php" class="<?php echo $active_page === 'history' ? 'on' : ''; ?>"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i>History</a>
    <a href="payments_history.php" class="<?php echo $active_page === 'payments' ? 'on' : ''; ?>"><i class="fas fa-credit-card" aria-hidden="true"></i>Payments</a>
    <div class="div"></div>
    <a href="edit_profile.php" class="<?php echo $active_page === 'profile' ? 'on' : ''; ?>"><i class="fas fa-user" aria-hidden="true"></i>Profile</a>
    <a href="https://wa.me/923001234567" target="_blank"><i class="fas fa-headset" aria-hidden="true"></i>Support</a>
    <a href="logout.php"><i class="fas fa-right-from-bracket" aria-hidden="true"></i>Logout</a>
</div>