<?php
// cleanup_abandoned_bookings.php
//
// Cancels bookings that were started but completely abandoned -- no
// payment proof ever submitted, and more than 48 hours old. This is
// intentionally VERY conservative:
//   - Only touches status = 'pending' bookings (never confirmed/
//     completed/already-cancelled ones)
//   - Only touches bookings with ZERO rows in `payments` (if a
//     customer submitted ANYTHING, even a since-rejected one, this
//     leaves it alone -- that's an active case for the agent to
//     review, not an abandoned one)
//   - Only touches bookings older than 48 hours
//
// Can be run two ways:
//   1. Automatically -- included by agent_dashboard.php, which only
//      actually runs the cleanup query once every few hours (tracked
//      via a small marker file), so it doesn't add a query to every
//      single page load.
//   2. As a real cron job, if the hosting account supports one --
//      point a cron entry directly at this file's URL or run it via
//      PHP CLI for more precise, guaranteed timing than the lazy
//      trigger above can offer.

function cleanupAbandonedBookings($pdo) {
    $stmt = $pdo->prepare("
        UPDATE bookings b
        SET b.status = 'cancelled'
        WHERE b.status = 'pending'
          AND b.created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
          AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.id)
    ");
    $stmt->execute();
    return $stmt->rowCount();
}

// Only run standalone logic (marker file + output) when this file is
// hit directly -- when included by agent_dashboard.php, only the
// function above is used, nothing below this line runs.
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once 'config.php';
    $cancelled_count = cleanupAbandonedBookings($pdo);
    echo "Cleanup complete. $cancelled_count abandoned booking(s) cancelled.";
}