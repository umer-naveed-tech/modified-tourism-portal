<?php
/**
 * hotel_handlers/price_calculator.php
 *
 * SINGLE SOURCE OF TRUTH for hotel-stay price calculation.
 *
 * WHY THIS FILE EXISTS:
 * get_hotel_room_price.php (price preview) and book_hotel_room.php
 * (actual booking) used to each have their OWN copy of this exact same
 * per-hotel pricing logic (~600 lines duplicated twice). That let them
 * drift apart silently -- e.g. the preview endpoint correctly errored
 * out when a date had no pricing row, but the booking endpoint for
 * hotels 43/63/41/44 just silently skipped that night (undercharging
 * the customer) because nobody remembered to copy the fix to both
 * places.
 *
 * From now on, BOTH files call calculateHotelStayPrice() below. Fix a
 * pricing rule once here, and preview + booking can never disagree
 * again.
 *
 * Returns an array:
 *   Success: ['success' => true, 'grand_total' => float, 'room_total' => float,
 *             'nights' => int, 'breakdown' => array, ...plus whichever of
 *             meal_total / extra_bed_total / supplements_total /
 *             supplement_total / is_full_board apply to that hotel]
 *   Failure: ['success' => false, 'error' => string]
 */

require_once __DIR__ . '/handler_factory.php';

if (!function_exists('hotelStayIsWeekend')) {
    function hotelStayIsWeekend($date) {
        $day = date('N', strtotime($date));
        return ($day == 4 || $day == 5);
    }
}

/**
 * @param PDO   $pdo
 * @param array $input Expected keys: hotel_id, room_type (aka room_type_code),
 *              bed_type, meal_type, extra_bed, supplement, supplements,
 *              meals, guests, check_in, check_out
 */
function calculateHotelStayPrice(PDO $pdo, array $input) {
    $hotel_id    = (int)($input['hotel_id'] ?? 0);
    $room_type   = $input['room_type'] ?? ($input['room_type_code'] ?? '');
    $bed_type    = $input['bed_type'] ?? '';
    $meal_type   = $input['meal_type'] ?? '';
    $extra_bed   = !empty($input['extra_bed']) ? 1 : 0;
    $supplement  = $input['supplement'] ?? null;
    $supplements = $input['supplements'] ?? [];
    $meals       = $input['meals'] ?? [];
    $guests      = $input['guests'] ?? 2;
    $check_in    = $input['check_in'] ?? '';
    $check_out   = $input['check_out'] ?? '';

    if (!$hotel_id || !$room_type || !$check_in || !$check_out) {
        return ['success' => false, 'error' => 'Missing required fields'];
    }

    $start = new DateTime($check_in);
    $end = new DateTime($check_out);
    $nights_diff = $start->diff($end)->days;
    if ($nights_diff < 1) {
        return ['success' => false, 'error' => 'Invalid dates'];
    }
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    // ============================================================
    // MAKKAH HOTEL (hotel_id = 43)
    // ============================================================
    if ($hotel_id === 43) {
        $meal_type = $meal_type ?: 'breakfast';
        $supplement_prices = [
            'renovated' => 125,
            'junior_suite' => 250,
            'kaaba_view' => 600,
            'suite' => 2450,
        ];
        $meal_prices = [
            'breakfast' => 80,
            'halfboard' => 250,
            'fullboard' => 420,
        ];
        $meal_price_per_night = $meal_prices[$meal_type] ?? 80;

        $supplements_total = 0;
        foreach ($supplements as $supp) {
            if (isset($supplement_prices[$supp])) {
                $supplements_total += $supplement_prices[$supp];
            }
        }

        $total = 0;
        $extra_bed_total = 0;
        $nights = 0;
        $breakdown = [];

        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');
            $is_weekend_val = hotelStayIsWeekend($current_date) ? 1 : 0;

            $stmt = $pdo->prepare("
                SELECT * FROM hotel_seasonal_pricing
                WHERE hotel_id = ? AND room_type_code = ? AND is_weekend = ?
                AND ? BETWEEN start_date AND end_date
            ");
            $stmt->execute([$hotel_id, $room_type, $is_weekend_val, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rule) {
                return ['success' => false, 'error' => "No pricing available for date: $current_date (Room: $room_type, Weekend: $is_weekend_val)"];
            }

            $night_price = $rule['base_price_sar'];
            $total += $night_price;
            $nights++;
            if ($extra_bed) {
                $extra_bed_total += $rule['extra_bed_base'] ?? 0;
            }
            $breakdown[] = [
                'date' => $current_date, 'price' => $night_price, 'is_weekend' => $is_weekend_val,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date'])),
            ];
        }

        $meal_total = $meal_price_per_night * $guests * $nights;
        $grand_total = $total + $meal_total + $extra_bed_total + $supplements_total;

        return [
            'success' => true, 'room_total' => $total, 'meal_total' => $meal_total,
            'extra_bed_total' => $extra_bed_total, 'supplements_total' => $supplements_total,
            'grand_total' => $grand_total, 'nights' => $nights, 'breakdown' => $breakdown,
            'meal_type' => $meal_type, 'guest_count' => $guests,
        ];
    }

    // ============================================================
    // MOVENPICK HAJAR TOWER (hotel_id = 63)
    // ============================================================
    if ($hotel_id === 63) {
        $meal_type = $meal_type ?: 'breakfast';
        if ($meal_type === 'fullboard') {
            $extra_bed = 0;
        }

        $total = 0;
        $extra_bed_total = 0;
        $nights = 0;
        $breakdown = [];
        $is_full_board = false;

        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');
            $is_weekend_val = hotelStayIsWeekend($current_date) ? 1 : 0;

            $stmt = $pdo->prepare("
                SELECT * FROM hotel_seasonal_pricing
                WHERE hotel_id = ? AND room_type = ? AND is_weekend = ?
                AND ? BETWEEN start_date AND end_date
                AND (meal_type = ? OR is_full_board = 1)
            ");
            $stmt->execute([$hotel_id, strtolower($room_type), $is_weekend_val, $current_date, $meal_type]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rule) {
                return ['success' => false, 'error' => "No pricing available for date: $current_date"];
            }

            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            $nights++;
            if ($extra_bed && !$rule['is_full_board']) {
                $extra_bed_total += ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
            }
            if ($rule['is_full_board']) {
                $is_full_board = true;
            }
            $breakdown[] = [
                'date' => $current_date, 'price' => $night_price, 'is_weekend' => $is_weekend_val,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date'])),
            ];
        }

        $grand_total = $total + $extra_bed_total;

        return [
            'success' => true, 'room_total' => $total, 'extra_bed_total' => $extra_bed_total,
            'grand_total' => $grand_total, 'nights' => $nights, 'breakdown' => $breakdown,
            'is_full_board' => $is_full_board, 'meal_type' => $meal_type,
        ];
    }

    // ============================================================
    // MARRIOT JABAL OMER (hotel_id = 41)
    // ============================================================
    if ($hotel_id === 41) {
        $db_room_type = strtolower($room_type);
        $total = 0;
        $meal_total = 0;
        $nights = 0;
        $breakdown = [];
        $is_full_board = false;

        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');
            $is_weekend_val = hotelStayIsWeekend($current_date) ? 1 : 0;

            $stmt = $pdo->prepare("
                SELECT * FROM hotel_seasonal_pricing
                WHERE hotel_id = ? AND room_type = ?
                AND ? BETWEEN start_date AND end_date
            ");
            $stmt->execute([$hotel_id, $db_room_type, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rule) {
                return ['success' => false, 'error' => "No pricing available for date: $current_date"];
            }

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
                'date' => $current_date, 'price' => $night_price, 'is_weekend' => $is_weekend_val,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date'])),
            ];
        }

        $grand_total = $total + $meal_total;

        return [
            'success' => true, 'room_total' => $total, 'meal_total' => $meal_total,
            'grand_total' => $grand_total, 'nights' => $nights, 'breakdown' => $breakdown,
            'is_full_board' => $is_full_board,
        ];
    }

    // ============================================================
    // MAKKAH TOWERS (hotel_id = 44) -- rooms + extra bed only
    // ============================================================
    if ($hotel_id === 44) {
        $total = 0;
        $extra_bed_total = 0;
        $nights = 0;
        $breakdown = [];

        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');
            $is_weekend_val = hotelStayIsWeekend($current_date) ? 1 : 0;

            // Try room_type first, then fall back to room_type_code --
            // same two-step lookup both callers already relied on.
            $stmt = $pdo->prepare("
                SELECT * FROM hotel_seasonal_pricing
                WHERE hotel_id = ? AND room_type = ? AND is_weekend = ?
                AND ? BETWEEN start_date AND end_date
            ");
            $stmt->execute([$hotel_id, $room_type, $is_weekend_val, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rule) {
                $stmt = $pdo->prepare("
                    SELECT * FROM hotel_seasonal_pricing
                    WHERE hotel_id = ? AND room_type_code = ? AND is_weekend = ?
                    AND ? BETWEEN start_date AND end_date
                ");
                $stmt->execute([$hotel_id, $room_type, $is_weekend_val, $current_date]);
                $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // FIX: this used to be a silent skip (no error, no addition
            // to $total) in the booking flow -- a missing pricing row
            // meant that night was charged as SAR 0 instead of failing
            // the booking. Now it fails loudly, matching the preview
            // endpoint's behavior, so preview and booking can never
            // disagree on whether a date is bookable.
            if (!$rule) {
                return ['success' => false, 'error' => "No pricing available for date: $current_date (Room: $room_type, Weekend: $is_weekend_val)"];
            }

            $night_price = $rule['base_price_sar'];
            $total += $night_price;
            $nights++;
            if ($extra_bed) {
                $extra_bed_total += $rule['extra_bed_base'] ?? 0;
            }
            $breakdown[] = [
                'date' => $current_date, 'price' => $night_price, 'is_weekend' => $is_weekend_val,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date'])),
            ];
        }

        $grand_total = $total + $extra_bed_total;

        return [
            'success' => true, 'room_total' => $total, 'extra_bed_total' => $extra_bed_total,
            'grand_total' => $grand_total, 'nights' => $nights, 'breakdown' => $breakdown,
        ];
    }

    // ============================================================
    // LE MERIDIEN TOWER HOTEL MAKKAH -- bespoke
    // ============================================================
    if (defined('LEMERIDIEN_HOTEL_ID') && $hotel_id === LEMERIDIEN_HOTEL_ID) {
        if ($bed_type === '') {
            return ['success' => false, 'error' => 'Please select a room subtype'];
        }
        $lm_meal_valid = ['ro' => 1, 'bb_intl' => 1, 'hb_pk' => 1, 'fb_pk' => 1];
        if ($meal_type === '' || !isset($lm_meal_valid[$meal_type])) {
            return ['success' => false, 'error' => 'Please select a meal plan'];
        }
        $lm_extra_bed = ($room_type === 'rs') ? $extra_bed : 0;

        $total = 0;
        $extra_bed_total = 0;
        $nights = 0;
        $breakdown = [];

        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');
            $is_weekend_val = hotelStayIsWeekend($current_date) ? 1 : 0;

            $stmt = $pdo->prepare("
                SELECT * FROM hotel_seasonal_pricing
                WHERE hotel_id = ? AND room_type_code = ? AND room_type = ? AND meal_type = ? AND is_weekend = ?
                AND ? BETWEEN start_date AND end_date
            ");
            $stmt->execute([$hotel_id, $room_type, $bed_type, $meal_type, $is_weekend_val, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rule) {
                return ['success' => false, 'error' => "No pricing available for date: $current_date"];
            }

            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            $nights++;
            if ($lm_extra_bed) {
                $extra_bed_total += ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
            }
            $breakdown[] = [
                'date' => $current_date, 'price' => $night_price, 'is_weekend' => $is_weekend_val,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date'])),
            ];
        }

        $grand_total = $total + $extra_bed_total;

        return [
            'success' => true, 'room_total' => $total, 'extra_bed_total' => $extra_bed_total,
            'grand_total' => $grand_total, 'nights' => $nights, 'breakdown' => $breakdown,
        ];
    }

    // ============================================================
    // SINGLE-ROOM SUPPLEMENT HOTELS (Al Safwah, Conrad, Hilton Suites,
    // Hilton Convention, future ones registered the same way)
    // ============================================================
    if (HotelHandlerFactory::isSingleRoomSupplementHotel($hotel_id)) {
        $handler = HotelHandlerFactory::getHandler($hotel_id);
        $opts = $handler->getBookingOptions($hotel_id);
        $supplement_prices_map = $opts['supplements'] ?? [];
        $has_extra_bed = $opts['extra_bed_available'] ?? false;

        $total = 0;
        $extra_bed_total = 0;
        $nights = 0;
        $breakdown = [];

        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');
            $is_weekend_val = hotelStayIsWeekend($current_date) ? 1 : 0;

            $stmt = $pdo->prepare("
                SELECT * FROM hotel_seasonal_pricing
                WHERE hotel_id = ? AND room_type = 'double' AND is_weekend = ?
                AND ? BETWEEN start_date AND end_date
            ");
            $stmt->execute([$hotel_id, $is_weekend_val, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rule) {
                return ['success' => false, 'error' => "No pricing available for date: $current_date"];
            }

            $total += $rule['base_price_sar'] + $rule['markup_sar'];
            $nights++;
            if ($has_extra_bed && $extra_bed) {
                $extra_bed_total += ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
            }
            $breakdown[] = [
                'date' => $current_date, 'price' => $rule['base_price_sar'] + $rule['markup_sar'], 'is_weekend' => $is_weekend_val,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date'])),
            ];
        }

        $supplement_total = ($supplement && isset($supplement_prices_map[$supplement])) ? $supplement_prices_map[$supplement] : 0;
        $grand_total = $total + $extra_bed_total + $supplement_total;

        return [
            'success' => true, 'room_total' => $total, 'extra_bed_total' => $extra_bed_total,
            'supplement_total' => $supplement_total, 'grand_total' => $grand_total,
            'nights' => $nights, 'breakdown' => $breakdown,
        ];
    }

    // ============================================================
    // SIMPLE HIDDEN-MARKUP HOTELS (Fairmont, Swissotel, Elaf Kinda,
    // Elaf Bakkah/Qinwan, Sheraton, M Hotel, Emaar variants, etc.)
    // ============================================================
    if (HotelHandlerFactory::isSimpleHiddenMarkupHotel($hotel_id)) {
        if ($bed_type === '') {
            return ['success' => false, 'error' => 'Please select a bed type (Double/Triple/Quad)'];
        }

        $handler = HotelHandlerFactory::getHandler($hotel_id);
        $opts = $handler->getBookingOptions($hotel_id);
        $has_extra_bed = $opts['extra_bed_available'] ?? false;
        $requires_meal_type = $opts['requires_meal_type'] ?? false;
        $meal_labels_valid = $opts['meal_labels'] ?? [];

        if ($requires_meal_type && !isset($meal_labels_valid[$meal_type])) {
            return ['success' => false, 'error' => 'Please select a meal plan'];
        }

        $total = 0;
        $extra_bed_total = 0;
        $nights = 0;
        $breakdown = [];

        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');
            $is_weekend_val = hotelStayIsWeekend($current_date) ? 1 : 0;

            if ($requires_meal_type) {
                $stmt = $pdo->prepare("
                    SELECT * FROM hotel_seasonal_pricing
                    WHERE hotel_id = ? AND room_type_code = ? AND room_type = ? AND meal_type = ? AND is_weekend = ?
                    AND ? BETWEEN start_date AND end_date
                ");
                $stmt->execute([$hotel_id, $room_type, $bed_type, $meal_type, $is_weekend_val, $current_date]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT * FROM hotel_seasonal_pricing
                    WHERE hotel_id = ? AND room_type_code = ? AND room_type = ? AND is_weekend = ?
                    AND ? BETWEEN start_date AND end_date
                ");
                $stmt->execute([$hotel_id, $room_type, $bed_type, $is_weekend_val, $current_date]);
            }
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rule) {
                return ['success' => false, 'error' => "No pricing available for date: $current_date (Room: $room_type, Bed: $bed_type, Weekend: $is_weekend_val)"];
            }

            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            $nights++;
            if ($has_extra_bed && $extra_bed) {
                $extra_bed_total += ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
            }
            $breakdown[] = [
                'date' => $current_date, 'price' => $night_price, 'is_weekend' => $is_weekend_val,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date'])),
            ];
        }

        $grand_total = $total + $extra_bed_total;

        return [
            'success' => true, 'room_total' => $total, 'extra_bed_total' => $extra_bed_total,
            'grand_total' => $grand_total, 'nights' => $nights, 'breakdown' => $breakdown,
        ];
    }

    // ============================================================
    // FALLBACK -- any other/unregistered hotel
    // ============================================================
    $db_room_type = strtolower($room_type);
    $stmt = $pdo->prepare("SELECT * FROM hotel_seasonal_pricing WHERE hotel_id = ? AND room_type = ? ORDER BY start_date");
    $stmt->execute([$hotel_id, $db_room_type]);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    $nights = 0;
    $breakdown = [];

    foreach ($period as $date) {
        $current_date = $date->format('Y-m-d');
        $found = false;

        foreach ($rules as $rule) {
            if ($current_date >= $rule['start_date'] && $current_date <= $rule['end_date']) {
                $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
                $total += $night_price;
                $nights++;
                $breakdown[] = [
                    'date' => $current_date, 'price' => $night_price, 'is_weekend' => 0,
                    'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date'])),
                ];
                $found = true;
                break;
            }
        }

        if (!$found) {
            return ['success' => false, 'error' => "No pricing available for date: $current_date"];
        }
    }

    return [
        'success' => true, 'room_total' => $total, 'grand_total' => $total,
        'nights' => $nights, 'breakdown' => $breakdown, 'is_full_board' => false,
    ];
}