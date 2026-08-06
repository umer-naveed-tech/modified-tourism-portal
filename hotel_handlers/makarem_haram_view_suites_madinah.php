<?php
// hotel_handlers/makarem_haram_view_suites_madinah.php
//
// Makarem Haram View Suites -- 4 Star
// 11 room categories (Studio/Junior Suite/2-3-4 Bedroom Suites/Family
// Suites, Non-View/Haram-View/City-View variants). NO weekday/weekend
// split. ROOM ONLY basis -- no breakfast/meal included. Extra Bed:
// flat 150 SR (rate sheet, "Room only extra bed") + 25 SAR hidden
// markup = 175 SR shown, same every period.

require_once __DIR__ . '/base_handler.php';

class MakaremHaramViewSuitesMadinahHandler implements HotelHandlerInterface {

    private $hotel_id = MAKAREMHARAMVIEWSUITES_HOTEL_ID;

    public function getRooms($hotel_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, room_type, display_name, capacity, description FROM hotel_room_types WHERE hotel_id = ? ORDER BY id");
        $stmt->execute([$hotel_id]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rooms as &$room) {
            $room['has_seasonal'] = true;
            $room['price_label'] = 'Seasonal Pricing';
            $room['extra_bed_available'] = true;
            $room['bed_types'] = [$room['room_type']];
        }
        return $rooms;
    }

    public function getRoomDetails($room_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM hotel_room_types WHERE id = ?");
        $stmt->execute([$room_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function calculatePrice($hotel_id, $room_type, $check_in, $check_out, $options = []) {
        global $pdo;
        $extra_bed = $options['extra_bed'] ?? 0;

        $date_in = new DateTime($check_in);
        $date_out = new DateTime($check_out);
        $nights = $date_in->diff($date_out)->days;
        if ($nights < 1) return ['error' => 'Invalid dates'];

        $total = 0;
        $extra_bed_total = 0;
        $breakdown = [];

        for ($i = 0; $i < $nights; $i++) {
            $current_date = date('Y-m-d', strtotime($check_in . ' + ' . $i . ' days'));
            $is_weekend = $this->isWeekend($current_date) ? 1 : 0;
            $stmt = $pdo->prepare("SELECT * FROM hotel_seasonal_pricing WHERE hotel_id = ? AND room_type_code = ? AND room_type = ? AND is_weekend = ? AND ? BETWEEN start_date AND end_date");
            $stmt->execute([$hotel_id, $room_type, $room_type, $is_weekend, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rule) return ['error' => "No pricing available for date: $current_date"];
            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            if ($extra_bed) $extra_bed_total += ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
            $breakdown[] = ['date' => $current_date, 'price' => $night_price, 'is_weekend' => $is_weekend,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))];
        }

        $grand_total = $total + $extra_bed_total;
        return ['success' => true, 'room_total' => $total, 'extra_bed_total' => $extra_bed_total,
            'grand_total' => $grand_total, 'nights' => $nights, 'breakdown' => $breakdown];
    }

    public function validateBooking($data) {
        $errors = [];
        if (empty($data['check_in'])) $errors[] = 'Check-in date required';
        if (empty($data['check_out'])) $errors[] = 'Check-out date required';
        if (empty($data['room_id'])) $errors[] = 'Room selection required';
        return $errors;
    }

    public function getBookingOptions($hotel_id) {
        return ['extra_bed_available' => true, 'has_weekend_split' => false];
    }

    public function renderRoomSelection($hotel_id, $rooms) {
        ob_start();
        ?>
        <div class="extra-bed-option" style="display:block;">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="mhvs_extra_bed" name="extra_bed" value="1" onchange="calculateTotal()">
                <label class="form-check-label" for="mhvs_extra_bed">
                    Add Extra Bed
                    <span style="color:rgba(255,255,255,0.3); font-size:11px; display:block; margin-top:2px;">(Room only -- additional charge per night)</span>
                </label>
            </div>
        </div>
        <div style="margin-top:16px; background:rgba(255,255,255,0.03); padding:12px 16px; border-radius:8px; border:1px solid rgba(255,255,255,0.05); font-size:13px; color:rgba(255,255,255,0.5);">
            🛏️ Meal Plan: Room Only (no breakfast included)
        </div>
        <?php
        return ob_get_clean();
    }

    private function isWeekend($date) {
        $day = date('N', strtotime($date));
        return ($day == 4 || $day == 5);
    }
}
?>