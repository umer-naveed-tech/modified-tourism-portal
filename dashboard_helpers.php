<?php
// dashboard_helpers.php
//
// Shared helper functions for the visitor dashboard pages. Kept in one
// place instead of copy-pasted into visitor_dashboard.php,
// my_bookings.php, booking_history.php, and booking_detail_view.php --
// so a fix (like the safe_date() one below) only has to happen once.

// Formats a date safely -- returns "—" instead of "Jan 1, 1970" when
// travel_date is empty/null, which strtotime('') would otherwise
// silently turn into the Unix epoch.
function safe_date($date_str, $format = 'M j, Y') {
    if (empty($date_str)) return '—';
    $ts = strtotime($date_str);
    if ($ts === false) return '—';
    // Bookings without a real travel date store '1970-01-01' as a
    // placeholder (see the `travel_date > '1970-01-02'` filters used
    // elsewhere) -- without this check, that placeholder would render
    // as a real-looking (but meaningless) "Jan 1, 1970" date.
    if ($ts < strtotime('1970-01-02')) return '—';
    return date($format, $ts);
}

function service_icon($type) {
    switch ($type) {
        case 'hotel': return 'fa-building';
        case 'taxi': return 'fa-car';
        case 'ziyarat': return 'fa-mosque';
        default: return 'fa-passport';
    }
}

function service_label($b) {
    $details = [];
    if (!empty($b['price_breakdown'])) {
        $d = json_decode($b['price_breakdown'], true);
        if (is_array($d)) $details = $d;
    }
    switch ($b['service_type']) {
        case 'hotel':
            return 'Hotel — ' . ($details['hotel_name'] ?? 'Booking');
        case 'taxi':
            return 'Taxi — ' . trim(($b['from_location'] ?? '') . ' to ' . ($b['to_location'] ?? ''));
        case 'ziyarat':
            return 'Ziyarat — ' . ($b['from_location'] ?: 'Trip');
        default:
            return ($details['service_title'] ?? ucfirst($b['service_type']));
    }
}

// NEW: the database only ever stores 3 statuses now -- pending,
// cancelled, completed (see simplify_booking_status_migration.sql).
// "Completed" covers both "paid, trip is still upcoming" and "trip
// already happened" -- this tells the two apart for DISPLAY only, so
// a customer with a paid but upcoming trip sees "Confirmed" instead
// of the confusing "Completed", without needing a 4th status in the
// database itself. Everywhere that shows a booking's status to
// someone should call this instead of using $status directly.
function display_status($status, $travel_date) {
    if ($status === 'completed') {
        $ts = !empty($travel_date) ? strtotime($travel_date) : false;
        if ($ts !== false && $ts >= strtotime(date('Y-m-d'))) {
            return ['label' => 'Confirmed', 'dot' => 'g'];
        }
        return ['label' => 'Completed', 'dot' => 'b'];
    }
    if ($status === 'pending') return ['label' => 'Pending', 'dot' => 'y'];
    if ($status === 'cancelled') return ['label' => 'Cancelled', 'dot' => 'r'];
    return ['label' => ucfirst($status), 'dot' => 'y'];
}