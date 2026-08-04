<?php
session_start();
header('Content-Type: application/json');

// ------------------------------------------------------------------
// JSON safety net: PHP warnings/notices/fatal errors printed as raw
// text (instead of being caught) corrupt the JSON response, which is
// exactly what makes the frontend show "Error calculating price" —
// fetch()'s response.json() fails to parse a response that has extra
// text mixed into it. This block makes sure that never happens: any
// warning gets logged instead of printed, and any fatal error still
// results in a clean JSON error instead of a broken page.
// ------------------------------------------------------------------
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("get_hotel_room_price.php warning: $errstr in $errfile on line $errline");
    return true; // stop PHP's default output, we've logged it already
});
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("get_hotel_room_price.php FATAL: {$err['message']} in {$err['file']} on line {$err['line']}");
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'error' => 'Server error while calculating price. Please try again.']);
    }
});

require_once 'config.php';
require_once 'hotel_handlers/handler_factory.php';
// Single source of truth for the actual per-hotel pricing logic --
// book_hotel_room.php calls the exact same function, so a preview and
// the booking it leads to can never disagree on price again.
require_once 'hotel_handlers/price_calculator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$raw = json_decode(file_get_contents('php://input'), true);

if (!$raw || !($raw['hotel_id'] ?? 0) || !($raw['room_type'] ?? '') || !($raw['check_in'] ?? '') || !($raw['check_out'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$result = calculateHotelStayPrice($pdo, [
    'hotel_id'    => $raw['hotel_id'] ?? 0,
    'room_type'   => $raw['room_type'] ?? '',
    'bed_type'    => $raw['bed_type'] ?? '',
    'meal_type'   => $raw['meal_type'] ?? '',
    'extra_bed'   => $raw['extra_bed'] ?? 0,
    'supplement'  => $raw['supplement'] ?? null,
    'supplements' => $raw['supplements'] ?? [],
    'meals'       => $raw['meals'] ?? [],
    'guests'      => $raw['guests'] ?? 2,
    'check_in'    => $raw['check_in'] ?? '',
    'check_out'   => $raw['check_out'] ?? '',
]);

echo json_encode($result);