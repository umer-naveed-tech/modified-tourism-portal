<?php
// hotel_handlers/maysan_al_harthia_madinah.php
//
// Maysan Al Harthia Ex Frontel Alharthia Hotel Madinah -- 5 Star
// 6 room categories: DBL/TPL/QUAD (standard rooms) + Junior Suite
// (2 Pax) / Executive Suite (2 Rooms) / Royal Suite (2 Rooms, 4 Pax).
// NO weekday/weekend split. Breakfast always included.
//
// Extra Bed is ONLY available on the 3 suite categories -- per Umer's
// explicit instruction, DBL/TPL/QUAD do NOT get an extra-bed option at
// all (rate sheet itself only lists "Extra Bed Suites", nothing for
// the standard rooms). Rate sheet gives 130 SR + 25 SAR hidden markup
// = 155 SR shown, same every period.
//
// Lunch/Dinner = 135 SR per pax -- info text only.

require_once __DIR__ . '/base_handler.php';

class MaysanAlHarthiaMadinahHandler implements HotelHandlerInterface {

    private $hotel_id = MAYSANHARTHIA_HOTEL_ID;

    private $suite_categories = ['junior_suite_2pax', 'executive_suite_2room', 'royal_suite_2room_4pax'];

    public function getRooms($hotel_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, room_type, display_name, capacity, description FROM hotel_room_types WHERE hotel_id = ? ORDER BY id");
        $stmt->execute([$hotel_id]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rooms as &$room) {
            $room['has_seasonal'] = true;
            $room['price_label'] = 'Seasonal Pricing';
            // Only the 3 suite categories offer an extra bed -- standard
            // DBL/TPL/QUAD rooms never show that option.
            $room['extra_bed_available'] = in_array($room['room_type'], $this->suite_categories);
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
        // Extra bed only ever applies for the suite categories, even if
        // somehow requested for a standard room -- ignored otherwise.
        $extra_bed = (in_array($room_type, $this->suite_categories) && !empty($options['extra_bed'])) ? 1 : 0;

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
        // The checkbox is shown for every room type here (same simple
        // pattern used elsewhere in this codebase), but calculatePrice()
        // above silently ignores extra_bed unless the selected room is
        // one of the 3 suite categories -- so a DBL/TPL/QUAD booking can
        // never actually be charged for an extra bed even if this box
        // were ticked.
        ob_start();
        ?>
        <div class="extra-bed-option" style="display:block;">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="maysan_extra_bed" name="extra_bed" value="1" onchange="calculateTotal()">
                <label class="form-check-label" for="maysan_extra_bed">
                    Add Extra Bed
                    <span style="color:rgba(255,255,255,0.3); font-size:11px; display:block; margin-top:2px;">(Suites only -- additional charge per night)</span>
                </label>
            </div>
        </div>
        <div style="margin-top:16px; background:rgba(16,185,129,0.04); padding:12px 16px; border-radius:8px; border:1px solid rgba(16,185,129,0.06); font-size:13px; color:#34d399;">
            🍳 Meal Plan: Breakfast Included<br>
            🍽️ Lunch or Dinner: SAR 135 per person
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