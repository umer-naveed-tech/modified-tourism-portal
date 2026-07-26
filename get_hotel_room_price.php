<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$raw = json_decode(file_get_contents('php://input'), true);
$hotel_id = $raw['hotel_id'] ?? 0;
$room_type = $raw['room_type'] ?? '';
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
            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            $nights++;
            
            if ($extra_bed) {
                $extra_bed_price = ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
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
            $stmt = $pdo->prepare("
                SELECT * FROM hotel_seasonal_pricing 
                WHERE hotel_id = ? AND room_type = ? AND is_weekend = ? 
                AND ? BETWEEN start_date AND end_date
            ");
            $stmt->execute([$hotel_id, $room_type, $is_weekend_val, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($rule) {
                $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
                $total += $night_price;
                $nights++;
                
                if ($extra_bed) {
                    $extra_bed_price = ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
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