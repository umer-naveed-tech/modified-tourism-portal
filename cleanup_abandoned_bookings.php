<?php
// cleanup_abandoned_bookings.php
//
// Automated booking-lifecycle maintenance, run opportunistically (see
// the marker-file trigger in agent_dashboard.php) roughly every few
// hours, or as a real cron job if the hosting account supports one.
// All three functions below are intentionally conservative -- each
// only touches bookings that unambiguously match its specific case.

// ============================================================
// 1. Cancel truly-abandoned bookings.
//    - Only status = 'pending' (never confirmed/completed/cancelled)
//    - Only bookings with ZERO rows in `payments` -- if a customer
//      submitted anything, even a since-rejected one, this leaves it
//      alone; that's an active case for the agent, not an abandoned one
//    - Only older than 48 hours
// ============================================================
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

// ============================================================
// 2. Auto-complete confirmed bookings whose stay/trip has clearly
//    finished. Only ever touches status = 'confirmed' bookings --
//    never pending/cancelled ones, and never re-touches something
//    already 'completed'.
//
//    For hotel bookings, uses the actual check-out date from
//    price_breakdown when available (a multi-night stay shouldn't be
//    marked "completed" the day after check-in) -- falls back to
//    travel_date for every other service type (taxi/visa/ziyarat,
//    which are single-day events where travel_date IS the whole event).
// ============================================================
function autoCompleteBookings($pdo) {
    $stmt = $pdo->query("
        SELECT id, service_type, travel_date, price_breakdown
        FROM bookings
        WHERE status = 'confirmed' AND travel_date < CURDATE()
    ");
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $today = date('Y-m-d');
    $completed_ids = [];

    foreach ($candidates as $b) {
        $cutoff_date = $b['travel_date'];

        if ($b['service_type'] === 'hotel' && !empty($b['price_breakdown'])) {
            $decoded = json_decode($b['price_breakdown'], true);
            if (is_array($decoded) && !empty($decoded['check_out'])) {
                $cutoff_date = $decoded['check_out'];
            }
        }

        if ($cutoff_date < $today) {
            $completed_ids[] = (int)$b['id'];
        }
    }

    if (!empty($completed_ids)) {
        $placeholders = implode(',', array_fill(0, count($completed_ids), '?'));
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id IN ($placeholders) AND status = 'confirmed'");
        $stmt->execute($completed_ids);
    }

    return count($completed_ids);
}

// ============================================================
// 3. Send a one-time payment reminder to customers who started a
//    booking but haven't submitted payment proof after 24 hours --
//    before the 48-hour auto-cancel in cleanupAbandonedBookings()
//    above. reminder_sent_at guarantees this only ever fires once per
//    booking, no matter how often this script runs.
// ============================================================
function sendPaymentReminders($pdo) {
    $stmt = $pdo->query("
        SELECT b.id, b.booking_no, b.total_amount, b.customer_name, b.customer_email,
               u.name AS user_name, u.email AS user_email
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.status = 'pending'
          AND b.reminder_sent_at IS NULL
          AND b.created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          AND b.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
          AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.id)
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sent = 0;
    foreach ($rows as $b) {
        $to_email = $b['customer_email'] ?: $b['user_email'];
        $to_name = $b['customer_name'] ?: $b['user_name'];

        // Always mark as attempted, even if there's no email address or
        // sending fails -- otherwise a booking with a bad email would
        // get retried every single cleanup run forever.
        $mark_attempted = $pdo->prepare("UPDATE bookings SET reminder_sent_at = NOW() WHERE id = ?");

        if (!$to_email) {
            $mark_attempted->execute([$b['id']]);
            continue;
        }

        try {
            require_once 'PHPMailer/src/PHPMailer.php';
            require_once 'PHPMailer/src/SMTP.php';
            require_once 'PHPMailer/src/Exception.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_APP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
            $mail->addAddress($to_email, $to_name);
            $mail->isHTML(true);
            $mail->Subject = 'Reminder: Complete Your Payment - ' . $b['booking_no'] . ' - Ahmed Travels';
            $mail->Body = "<h2>Your Booking Is Waiting</h2>"
                . "<p>Dear " . htmlspecialchars($to_name) . ",</p>"
                . "<p>We noticed you haven't completed payment yet for booking <strong>" . htmlspecialchars($b['booking_no']) . "</strong> (SAR " . number_format($b['total_amount']) . ").</p>"
                . "<p>Please log in and complete your payment soon -- unpaid bookings are automatically cancelled after 48 hours.</p>"
                . "<p>-- Ahmed Travels</p>";
            $mail->send();
            $sent++;
        } catch (Exception $e) {
            error_log('sendPaymentReminders mail error: ' . $e->getMessage());
        }

        $mark_attempted->execute([$b['id']]);
    }

    return $sent;
}

// Runs all three maintenance tasks together -- this is what
// agent_dashboard.php's lazy trigger calls.
function runBookingMaintenance($pdo) {
    return [
        'completed' => autoCompleteBookings($pdo),
        'reminders_sent' => sendPaymentReminders($pdo),
        'cancelled' => cleanupAbandonedBookings($pdo),
    ];
}

// Only run standalone logic (marker file + output) when this file is
// hit directly -- when included by agent_dashboard.php, only the
// functions above are used, nothing below this line runs.
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once 'config.php';
    $result = runBookingMaintenance($pdo);
    echo "Maintenance complete. {$result['completed']} booking(s) auto-completed, "
       . "{$result['reminders_sent']} reminder(s) sent, "
       . "{$result['cancelled']} abandoned booking(s) cancelled.";
}