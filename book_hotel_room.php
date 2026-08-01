<?php
session_start();
require_once 'config.php';
require_once 'hotel_handlers/handler_factory.php';

// 🔴 CSRF VERIFY — PEHLE HI HONA CHAHIYE
csrf_verify();

$room_id = $_POST['room_id'] ?? 0;
$hotel_id = $_POST['hotel_id'] ?? 0;
$hotel_name = $_POST['hotel_name'] ?? '';
$room_type_code = $_POST['room_type_code'] ?? '';
$bed_type = $_POST['bed_type'] ?? '';
$check_in = $_POST['check_in'] ?? '';
$check_out = $_POST['check_out'] ?? '';
$meal_type = $_POST['meal_type'] ?? 'breakfast';
$extra_bed = isset($_POST['extra_bed']) ? 1 : 0;
$guests = $_POST['guests'] ?? 2;
$supplements = $_POST['supplements'] ?? [];
$meals = $_POST['meals'] ?? [];

if (!$room_id || !$hotel_id || !$check_in || !$check_out) {
    header('Location: services.php?type=hotels');
    exit();
}

// Guest booking flow
if (!isset($_SESSION['user_id'])) {
    $_SESSION['pending_hotel_booking'] = [
        'room_id' => $room_id,
        'hotel_id' => $hotel_id,
        'hotel_name' => $hotel_name,
        'room_type_code' => $room_type_code,
        'check_in' => $check_in,
        'check_out' => $check_out,
        'meal_type' => $meal_type,
        'extra_bed' => $extra_bed,
        'guests' => $guests,
        'supplements' => $supplements,
        'meals' => $meals,
    ];
    $_SESSION['redirect_after_login'] = 'hotel_rooms.php?hotel_id=' . urlencode($hotel_id) . '&resume=1';
    header('Location: login.php');
    exit();
}

// Get user email if not in session
if (!isset($_SESSION['user_email'])) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $_SESSION['user_email'] = $user['email'];
}

// ============================================================
// 🔴 HAR HOTEL KA APNA HANDLER ROOM DETAILS DEGA
// ============================================================
$handler = HotelHandlerFactory::getHandler($hotel_id);
$room = $handler->getRoomDetails($room_id);

if (!$room) {
    header('Location: services.php?type=hotels');
    exit();
}

// Server-side nights calculation
$date_in = new DateTime($check_in);
$date_out = new DateTime($check_out);
$nights = $date_in->diff($date_out)->days;

if ($nights < 1) {
    header('Location: hotel_rooms.php?hotel_id=' . $hotel_id . '&error=1');
    exit();
}

function isWeekend($date) {
    $day = date('N', strtotime($date));
    return ($day == 4 || $day == 5);
}

$total = 0;
$extra_bed_total = 0;
$supplements_total = 0;
$meal_total = 0;

// ============================================================
// MAKKAH HOTEL (hotel_id = 43)
// ============================================================
if ($hotel_id == 43) {
    $meal_type = $_POST['meal_type'] ?? 'breakfast';
    $extra_bed = isset($_POST['extra_bed']) ? 1 : 0;
    $supplements = $_POST['supplements'] ?? [];
    $guests = $_POST['guests'] ?? 2;
    
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
    
    for ($i = 0; $i < $nights; $i++) {
        $current_date = date('Y-m-d', strtotime($check_in . ' + ' . $i . ' days'));
        $is_weekend_val = isWeekend($current_date) ? 1 : 0;
        
        $stmt = $pdo->prepare("
            SELECT * FROM hotel_seasonal_pricing 
            WHERE hotel_id = ? AND room_type_code = ? AND is_weekend = ? 
            AND ? BETWEEN start_date AND end_date
        ");
        $stmt->execute([$hotel_id, $room_type_code, $is_weekend_val, $current_date]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rule) {
            $night_price = $rule['base_price_sar'];
            $total += $night_price;
            
            if ($extra_bed) {
                $extra_bed_price = $rule['extra_bed_base'] ?? 0;
                $extra_bed_total += $extra_bed_price;
            }
        }
    }
    
    $meal_total = $meal_price_per_night * $guests * $nights;
    $grand_total = $total + $meal_total + $extra_bed_total + $supplements_total;
    
} elseif ($hotel_id == 63) {
    // MOVENPICK
    $meal_type = $_POST['meal_type'] ?? 'breakfast';
    $extra_bed = isset($_POST['extra_bed']) ? 1 : 0;
    if ($meal_type === 'fullboard') {
        $extra_bed = 0;
    }
    
    for ($i = 0; $i < $nights; $i++) {
        $current_date = date('Y-m-d', strtotime($check_in . ' + ' . $i . ' days'));
        $is_weekend_val = isWeekend($current_date) ? 1 : 0;
        
        $stmt = $pdo->prepare("
            SELECT * FROM hotel_seasonal_pricing 
            WHERE hotel_id = ? AND room_type = ? AND is_weekend = ? 
            AND ? BETWEEN start_date AND end_date
            AND (meal_type = ? OR is_full_board = 1)
        ");
        $stmt->execute([$hotel_id, strtolower($room_type_code), $is_weekend_val, $current_date, $meal_type]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rule) {
            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            
            if ($extra_bed && !$rule['is_full_board']) {
                $extra_bed_price = ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
                $extra_bed_total += $extra_bed_price;
            }
        }
    }
    $grand_total = $total + $extra_bed_total;
    
} elseif ($hotel_id == 41) {
    // MARRIOT
    $meals = $_POST['meals'] ?? [];
    
    for ($i = 0; $i < $nights; $i++) {
        $current_date = date('Y-m-d', strtotime($check_in . ' + ' . $i . ' days'));
        
        $stmt = $pdo->prepare("
            SELECT * FROM hotel_seasonal_pricing 
            WHERE hotel_id = ? AND room_type = ? 
            AND ? BETWEEN start_date AND end_date
        ");
        $stmt->execute([$hotel_id, strtolower($room_type_code), $current_date]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rule) {
            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            
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
            }
        }
    }
    $grand_total = $total + $meal_total;
    
} elseif ($hotel_id == 44) {
    // 🔴 MAKKAH TOWERS — SIRF ROOMS + EXTRA BED
    $extra_bed = isset($_POST['extra_bed']) ? 1 : 0;
    
    for ($i = 0; $i < $nights; $i++) {
        $current_date = date('Y-m-d', strtotime($check_in . ' + ' . $i . ' days'));
        $is_weekend_val = isWeekend($current_date) ? 1 : 0;
        
        // 🔴 room_type se try karein
        $stmt = $pdo->prepare("
            SELECT * FROM hotel_seasonal_pricing 
            WHERE hotel_id = ? AND room_type = ? AND is_weekend = ? 
            AND ? BETWEEN start_date AND end_date
        ");
        $stmt->execute([$hotel_id, $room_type_code, $is_weekend_val, $current_date]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 🔴 agar nahi mili toh room_type_code se try karein
        if (!$rule) {
            $stmt = $pdo->prepare("
                SELECT * FROM hotel_seasonal_pricing 
                WHERE hotel_id = ? AND room_type_code = ? AND is_weekend = ? 
                AND ? BETWEEN start_date AND end_date
            ");
            $stmt->execute([$hotel_id, $room_type_code, $is_weekend_val, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($rule) {
            $night_price = $rule['base_price_sar'];
            $total += $night_price;
            
            if ($extra_bed) {
                $extra_bed_price = $rule['extra_bed_base'] ?? 0;
                $extra_bed_total += $extra_bed_price;
            }
        }
    }
    $grand_total = $total + $extra_bed_total;
    
} elseif (HotelHandlerFactory::isSimpleHiddenMarkupHotel($hotel_id)) {
    // FAIRMONT / SWISSOTEL / SWISSOTEL AL MAQAM / AL MARWA RAYHAAN
    // Bed Type (Double/Triple/Quad) zaroori hai -- iske bina room
    // category+date se 3 rows match hoti thin aur galat/random price
    // select ho jaati thi.
    if ($bed_type === '') {
        header('Location: hotel_rooms.php?hotel_id=' . $hotel_id . '&error=1');
        exit();
    }

    for ($i = 0; $i < $nights; $i++) {
        $current_date = date('Y-m-d', strtotime($check_in . ' + ' . $i . ' days'));
        $is_weekend_val = isWeekend($current_date) ? 1 : 0;

        $stmt = $pdo->prepare("
            SELECT * FROM hotel_seasonal_pricing 
            WHERE hotel_id = ? AND room_type_code = ? AND room_type = ? AND is_weekend = ? 
            AND ? BETWEEN start_date AND end_date
        ");
        $stmt->execute([$hotel_id, $room_type_code, $bed_type, $is_weekend_val, $current_date]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rule) {
            // Chup-chaap skip karne ki bajaye booking fail karo -- warna
            // customer se kam nights ka paisa lag jata (ek missing date
            // silently total se bahar reh jaati).
            header('Location: hotel_rooms.php?hotel_id=' . $hotel_id . '&error=1');
            exit();
        }

        $total += $rule['base_price_sar'] + $rule['markup_sar'];
    }

    $grand_total = $total;
    
} else {
    // OTHER HOTELS
    $stmt = $pdo->prepare("SELECT price_per_night_sar FROM hotel_rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $room_price = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT base_price_sar, markup_sar FROM hotel_seasonal_pricing 
        WHERE hotel_id = ? AND room_type = ? 
        AND ? BETWEEN start_date AND end_date
    ");
    $stmt->execute([$hotel_id, strtolower($room_type_code), $check_in]);
    $seasonal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($seasonal) {
        $total = ($seasonal['base_price_sar'] + $seasonal['markup_sar']) * $nights;
    } else {
        $total = ($room_price['price_per_night_sar'] ?? 0) * $nights;
    }
    $grand_total = $total;
}

$booking_no = 'HOTEL-' . date('Ymd') . '-' . rand(1000, 9999);
$travel_date = $check_in;
$room_display = $room['room_type'] ?? $room['display_name'] ?? 'Room';
$capacity = $room['capacity'] ?? 2;
$from_location = $hotel_name . ' - ' . $room_display . ' (Check-in: ' . $check_in . ', Check-out: ' . $check_out . ')';

// 🔴 BOOKING INSERT
$stmt = $pdo->prepare("
    INSERT INTO bookings (
        booking_no, user_id, service_type, service_id, booking_date, 
        travel_date, from_location, guests, extra_bed, extra_bed_price, total_amount, 
        meal_total, status, payment_status, can_cancel_until
    ) VALUES (?, ?, 'hotel', ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', DATE_ADD(NOW(), INTERVAL 1 HOUR))
");

if ($stmt->execute([
    $booking_no, 
    $_SESSION['user_id'], 
    $hotel_id, 
    $travel_date, 
    $from_location, 
    $capacity, 
    $extra_bed,
    $extra_bed_total,
    $grand_total,
    $meal_total
])) {
    if (file_exists('send_booking_email.php')) {
        require_once 'send_booking_email.php';
        sendBookingEmail(
            $_SESSION['user_email'],
            $_SESSION['user_name'],
            $booking_no,
            'Hotel - ' . $hotel_name . ' (' . $room_display . ')',
            $check_in . ' to ' . $check_out,
            $grand_total,
            $hotel_name,
            $room_display,
            $capacity,
            $grand_total
        );
    }
    
    header('Location: booking_success.php?booking_no=' . $booking_no . '&type=hotel&amount=' . $grand_total);
    exit();
} else {
    header('Location: hotel_rooms.php?hotel_id=' . $hotel_id . '&error=1');
    exit();
}
?>