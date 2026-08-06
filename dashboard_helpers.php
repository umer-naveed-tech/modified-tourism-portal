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