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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$raw = json_decode(file_get_contents('php://input'), true);
$hotel_id = $raw['hotel_id'] ?? 0;
$room_type = $raw['room_type'] ?? '';
$bed_type = $raw['bed_type'] ?? '';
$extra_bed = $raw['extra_bed'] ?? 0;
$supplement = $raw['supplement'] ?? null;
$check_in = $raw['check_in'] ?? '';
$check_out = $raw['check_out'] ?? '';
$hotel_type = $raw['hotel_type'] ?? '';

if (!$hotel_id || !$room_type || !$check_in || !$check_out) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

function isWeekend($date) {
    $day = date('N', strtotime($date));
    return ($day == 4 || $day == 5);
}

// ============================================================
// MAKKAH HOTEL (hotel_id = 43)
// ============================================================
if ($hotel_id == 43) {
    $meal_type = $raw['meal_type'] ?? 'breakfast';
    $extra_bed = $raw['extra_bed'] ?? 0;
    $supplements = $raw['supplements'] ?? [];
    $guests = $raw['guests'] ?? 2;
    
    $start = new DateTime($check_in);
    $end = new DateTime($check_out);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    $total = 0;
    $extra_bed_total = 0;
    $supplements_total = 0;
    $nights = 0;
    $breakdown = [];
    
    $supplement_prices = [
        'renovated' => 125,
        'junior_suite' => 250,
        'kaaba_view' => 600,
        'suite' => 2450
    ];
    
    foreach ($supplements as $supp) {
        if (isset($supplement_prices[$supp])) {
            $supplements_total += $supplement_prices[$supp];
        }
    }
    
    $meal_prices = [
        'breakfast' => 80,
        'halfboard' => 250,
        'fullboard' => 420
    ];
    $meal_price_per_night = $meal_prices[$meal_type] ?? 80;
    
    foreach ($period as $date) {
        $current_date = $date->format('Y-m-d');
        $is_weekend_val = isWeekend($current_date) ? 1 : 0;
        $found = false;
        
        $stmt = $pdo->prepare("
            SELECT * FROM hotel_seasonal_pricing 
            WHERE hotel_id = ? AND room_type_code = ? AND is_weekend = ? 
            AND ? BETWEEN start_date AND end_date
        ");
        $stmt->execute([$hotel_id, $room_type, $is_weekend_val, $current_date]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rule) {
            $night_price = $rule['base_price_sar'];
            $total += $night_price;
            $nights++;
            
            if ($extra_bed) {
                $extra_bed_price = $rule['extra_bed_base'] ?? 0;
                $extra_bed_total += $extra_bed_price;
            }
            
            $breakdown[] = [
                'date' => $current_date,
                'price' => $night_price,
                'is_weekend' => $is_weekend_val,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))
            ];
            
            $found = true;
        }
        
        if (!$found) {
            echo json_encode([
                'success' => false,
                'error' => "No pricing available for date: $current_date (Room: $room_type, Weekend: $is_weekend_val)"
            ]);
            exit();
        }
    }
    
    $meal_total = $meal_price_per_night * $guests * $nights;
    $grand_total = $total + $meal_total + $extra_bed_total + $supplements_total;
    
    echo json_encode([
        'success' => true,
        'room_total' => $total,
        'meal_total' => $meal_total,
        'extra_bed_total' => $extra_bed_total,
        'supplements_total' => $supplements_total,
        'grand_total' => $grand_total,
        'nights' => $nights,
        'breakdown' => $breakdown,
        'meal_type' => $meal_type,
        'guest_count' => $guests
    ]);
    exit();
}

// ============================================================
// MOVENPICK (hotel_id = 63)
// ============================================================
if ($hotel_id == 63) {
    $meal_type = $raw['meal_type'] ?? 'breakfast';
    $extra_bed = $raw['extra_bed'] ?? 0;
    
    if ($meal_type === 'fullboard') {
        $extra_bed = 0;
    }
    
    $start = new DateTime($check_in);
    $end = new DateTime($check_out);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    $total = 0;
    $extra_bed_total = 0;
    $nights = 0;
    $breakdown = [];
    $is_full_board = false;
    
    foreach ($period as $date) {
        $current_date = $date->format('Y-m-d');
        $is_weekend_val = isWeekend($current_date) ? 1 : 0;
        $found = false;
        
        $stmt = $pdo->prepare("
            SELECT * FROM hotel_seasonal_pricing 
            WHERE hotel_id = ? AND room_type = ? AND is_weekend = ? 
            AND ? BETWEEN start_date AND end_date
            AND (meal_type = ? OR is_full_board = 1)
        ");
        $stmt->execute([$hotel_id, strtolower($room_type), $is_weekend_val, $current_date, $meal_type]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rule) {
            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            $nights++;
            
            if ($extra_bed && !$rule['is_full_board']) {
                $extra_bed_price = ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
                $extra_bed_total += $extra_bed_price;
            }
            
            if ($rule['is_full_board']) {
                $is_full_board = true;
            }
            
            $breakdown[] = [
                'date' => $current_date,
                'price' => $night_price,
                'is_weekend' => $is_weekend_val,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))
            ];
            
            $found = true;
        }
        
        if (!$found) {
            echo json_encode([
                'success' => false,
                'error' => "No pricing available for date: $current_date"
            ]);
            exit();
        }
    }
    
    $grand_total = $total + $extra_bed_total;
    
    echo json_encode([
        'success' => true,
        'room_total' => $total,
        'extra_bed_total' => $extra_bed_total,
        'grand_total' => $grand_total,
        'nights' => $nights,
        'breakdown' => $breakdown,
        'is_full_board' => $is_full_board,
        'meal_type' => $meal_type
    ]);
    exit();
}

// ============================================================
// MARRIOT JABAL OMER (hotel_id = 41)
// ============================================================
if ($hotel_id == 41) {
    $meals = $raw['meals'] ?? [];
    
    $start = new DateTime($check_in);
    $end = new DateTime($check_out);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    $total = 0;
    $meal_total = 0;
    $nights = 0;
    $breakdown = [];
    $is_full_board = false;
    $db_room_type = strtolower($room_type);
    
    foreach ($period as $date) {
        $current_date = $date->format('Y-m-d');
        $found = false;
        
        $stmt = $pdo->prepare("
            SELECT * FROM hotel_seasonal_pricing 
            WHERE hotel_id = ? AND room_type = ? 
            AND ? BETWEEN start_date AND end_date
        ");
        $stmt->execute([$hotel_id, $db_room_type, $current_date]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rule) {
            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            $nights++;
            
            if (!$rule['is_full_board']) {
                if (in_array('breakfast', $meals) && $rule['breakfast_price_sar'] !== null) {
                    $meal_total += $rule['breakfast_price_sar'];
                }
                if (in_array('lunch', $meals) && $rule['lunch_price_sar'] !== null) {
                    $meal_total += $rule['lunch_price_sar'];
                }
                if (in_array('dinner', $meals) && $rule['dinner_price_sar'] !== null) {
                    $meal_total += $rule['dinner_price_sar'];
                }
            } else {
                $is_full_board = true;
            }
            
            $breakdown[] = [
                'date' => $current_date,
                'price' => $night_price,
                'is_weekend' => 0,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))
            ];
            
            $found = true;
        }
        
        if (!$found) {
            echo json_encode([
                'success' => false,
                'error' => "No pricing available for date: $current_date"
            ]);
            exit();
        }
    }
    
    $grand_total = $total + $meal_total;
    
    echo json_encode([
        'success' => true,
        'room_total' => $total,
        'meal_total' => $meal_total,
        'grand_total' => $grand_total,
        'nights' => $nights,
        'breakdown' => $breakdown,
        'is_full_board' => $is_full_board
    ]);
    exit();
}

// ============================================================
// MAKKAH TOWERS (hotel_id = 44) — SIRF ROOMS + EXTRA BED
// ============================================================
if ($hotel_id == 44) {
    $extra_bed = $raw['extra_bed'] ?? 0;
    
    $start = new DateTime($check_in);
    $end = new DateTime($check_out);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    $total = 0;
    $extra_bed_total = 0;
    $nights = 0;
    $breakdown = [];
    
    foreach ($period as $date) {
        $current_date = $date->format('Y-m-d');
        $is_weekend_val = isWeekend($current_date) ? 1 : 0;
        $found = false;
        
        // Pehle room_type se try karein
        $stmt = $pdo->prepare("
            SELECT * FROM hotel_seasonal_pricing 
            WHERE hotel_id = ? AND room_type = ? AND is_weekend = ? 
            AND ? BETWEEN start_date AND end_date
        ");
        $stmt->execute([$hotel_id, $room_type, $is_weekend_val, $current_date]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        // Agar na mile to room_type_code se try karein — book_hotel_room.php
        // isi tarah fallback karta hai, isliye preview aur final booking
        // hamesha same price dikhayenge.
        if (!$rule) {
            $stmt = $pdo->prepare("
                SELECT * FROM hotel_seasonal_pricing 
                WHERE hotel_id = ? AND room_type_code = ? AND is_weekend = ? 
                AND ? BETWEEN start_date AND end_date
            ");
            $stmt->execute([$hotel_id, $room_type, $is_weekend_val, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($rule) {
            $night_price = $rule['base_price_sar'];
            $total += $night_price;
            $nights++;
            
            if ($extra_bed) {
                $extra_bed_price = $rule['extra_bed_base'] ?? 0;
                $extra_bed_total += $extra_bed_price;
            }
            
            $breakdown[] = [
                'date' => $current_date,
                'price' => $night_price,
                'is_weekend' => $is_weekend_val,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))
            ];
            
            $found = true;
        }
        
        if (!$found) {
            echo json_encode([
                'success' => false,
                'error' => "No pricing for date: $current_date (Room: $room_type, Weekend: $is_weekend_val)"
            ]);
            exit();
        }
    }
    
    $grand_total = $total + $extra_bed_total;
    
    echo json_encode([
        'success' => true,
        'room_total' => $total,
        'extra_bed_total' => $extra_bed_total,
        'grand_total' => $grand_total,
        'nights' => $nights,
        'breakdown' => $breakdown
    ]);
    exit();
}

// ============================================================
// AL SAFWAH TOWER 3 HOTEL -- single room type, extra bed + supplement
// ============================================================
if ($hotel_id == ALSAFWAH_HOTEL_ID) {
    $supplement_prices = ['standard_hv' => 110, 'junior_suite' => 350];

    $start = new DateTime($check_in);
    $end = new DateTime($check_out);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    $total = 0;
    $extra_bed_total = 0;
    $nights = 0;
    $breakdown = [];

    foreach ($period as $date) {
        $current_date = $date->format('Y-m-d');
        $is_weekend_val = isWeekend($current_date) ? 1 : 0;

        $stmt = $pdo->prepare("
            SELECT * FROM hotel_seasonal_pricing 
            WHERE hotel_id = ? AND room_type = 'double' AND is_weekend = ? 
            AND ? BETWEEN start_date AND end_date
        ");
        $stmt->execute([$hotel_id, $is_weekend_val, $current_date]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rule) {
            echo json_encode(['success' => false, 'error' => "No pricing available for date: $current_date"]);
            exit();
        }

        $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
        $total += $night_price;
        $nights++;

        if ($extra_bed) {
            $extra_bed_total += ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
        }

        $breakdown[] = [
            'date' => $current_date,
            'price' => $night_price,
            'is_weekend' => $is_weekend_val,
            'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))
        ];
    }

    $supplement_total = ($supplement && isset($supplement_prices[$supplement])) ? $supplement_prices[$supplement] : 0;
    $grand_total = $total + $extra_bed_total + $supplement_total;

    echo json_encode([
        'success' => true,
        'room_total' => $total,
        'extra_bed_total' => $extra_bed_total,
        'supplement_total' => $supplement_total,
        'grand_total' => $grand_total,
        'nights' => $nights,
        'breakdown' => $breakdown
    ]);
    exit();
}

// ============================================================
// CONRAD HOTEL MAKKAH -- single room type, supplement only (no extra bed)
// ============================================================
if ($hotel_id == CONRAD_HOTEL_ID) {
    $supplement_prices = [
        'superior_partial_hv' => 120, 'deluxe_suite_partial_hv' => 630,
        'executive_cv' => 300, 'executive_partial_hv' => 415,
        'grand_premier' => 2820, 'two_bedroom_partial_haram' => 1560,
        'three_bedroom_partial_haram' => 4910, 'royal_suite' => 8610,
    ];

    $start = new DateTime($check_in);
    $end = new DateTime($check_out);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    $total = 0;
    $nights = 0;
    $breakdown = [];

    foreach ($period as $date) {
        $current_date = $date->format('Y-m-d');
        $is_weekend_val = isWeekend($current_date) ? 1 : 0;

        $stmt = $pdo->prepare("
            SELECT * FROM hotel_seasonal_pricing 
            WHERE hotel_id = ? AND room_type = 'double' AND is_weekend = ? 
            AND ? BETWEEN start_date AND end_date
        ");
        $stmt->execute([$hotel_id, $is_weekend_val, $current_date]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rule) {
            echo json_encode(['success' => false, 'error' => "No pricing available for date: $current_date"]);
            exit();
        }

        $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
        $total += $night_price;
        $nights++;

        $breakdown[] = [
            'date' => $current_date,
            'price' => $night_price,
            'is_weekend' => $is_weekend_val,
            'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))
        ];
    }

    $supplement_total = ($supplement && isset($supplement_prices[$supplement])) ? $supplement_prices[$supplement] : 0;
    $grand_total = $total + $supplement_total;

    echo json_encode([
        'success' => true,
        'room_total' => $total,
        'supplement_total' => $supplement_total,
        'grand_total' => $grand_total,
        'nights' => $nights,
        'breakdown' => $breakdown
    ]);
    exit();
}

// ============================================================
// FAIRMONT CLOCK TOWER HOTEL MAKKAH & SWISSOTEL MAKKAH
// Same structure: room_type_code + is_weekend, base+70 hidden markup,
// no extra bed, no meal addon -- just room selection + total price.
// ============================================================
if (HotelHandlerFactory::isSimpleHiddenMarkupHotel($hotel_id)) {
    // Bed Type (Double/Triple/Quad) ek ALAG required selection hai --
    // iske bina room_type_code+date se 3 rows (har bed type ki) match ho
    // jaati thin aur fetch() unme se koi bhi ek utha leta tha (galat,
    // undefined price). Ab dono (room category + bed type) filter hote hain.
    if ($bed_type === '') {
        echo json_encode(['success' => false, 'error' => 'Please select a bed type (Double/Triple/Quad)']);
        exit();
    }

    $start = new DateTime($check_in);
    $end = new DateTime($check_out);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    $total = 0;
    $nights = 0;
    $breakdown = [];

    foreach ($period as $date) {
        $current_date = $date->format('Y-m-d');
        $is_weekend_val = isWeekend($current_date) ? 1 : 0;

        $stmt = $pdo->prepare("
            SELECT * FROM hotel_seasonal_pricing 
            WHERE hotel_id = ? AND room_type_code = ? AND room_type = ? AND is_weekend = ? 
            AND ? BETWEEN start_date AND end_date
        ");
        $stmt->execute([$hotel_id, $room_type, $bed_type, $is_weekend_val, $current_date]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rule) {
            echo json_encode([
                'success' => false,
                'error' => "No pricing available for date: $current_date (Room: $room_type, Bed: $bed_type, Weekend: $is_weekend_val)"
            ]);
            exit();
        }

        $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
        $total += $night_price;
        $nights++;

        $breakdown[] = [
            'date' => $current_date,
            'price' => $night_price,
            'is_weekend' => $is_weekend_val,
            'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))
        ];
    }

    echo json_encode([
        'success' => true,
        'room_total' => $total,
        'grand_total' => $total,
        'nights' => $nights,
        'breakdown' => $breakdown
    ]);
    exit();
}

// ============================================================
// OTHER HOTELS - Fallback
// ============================================================
$start = new DateTime($check_in);
$end = new DateTime($check_out);
$interval = new DateInterval('P1D');
$period = new DatePeriod($start, $interval, $end);

$total = 0;
$nights = 0;
$breakdown = [];
$db_room_type = strtolower($room_type);

$stmt = $pdo->prepare("SELECT * FROM hotel_seasonal_pricing WHERE hotel_id = ? AND room_type = ? ORDER BY start_date");
$stmt->execute([$hotel_id, $db_room_type]);
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($period as $date) {
    $current_date = $date->format('Y-m-d');
    $found = false;
    
    foreach ($rules as $rule) {
        if ($current_date >= $rule['start_date'] && $current_date <= $rule['end_date']) {
            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            $nights++;
            
            $breakdown[] = [
                'date' => $current_date,
                'price' => $night_price,
                'is_weekend' => 0,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))
            ];
            
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo json_encode([
            'success' => false,
            'error' => "No pricing available for date: $current_date"
        ]);
        exit();
    }
}

echo json_encode([
    'success' => true,
    'room_total' => $total,
    'grand_total' => $total,
    'nights' => $nights,
    'breakdown' => $breakdown,
    'is_full_board' => false
]);
?>