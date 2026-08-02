<?php
// hotel_handlers/lemeridien_tower_makkah.php
//
// Le Meridien Tower Hotel Makkah -- 5 Star
// 3 room categories: Deluxe Suite (DS), Executive Suite (ES), Royal
// Suite (RS). Har category ke apne subtypes:
//   DS: Single/Double/Triple/Quad -- no Quint, no Extra Bed
//   ES: Single/Double/Triple/Quad/Quint -- no Extra Bed
//   RS: Quad/Quint/6 Pax -- Extra Bed available (25 SAR hidden markup)
// Meal Plan (Room Only / BB International / HB Pakistan / FB Pakistan)
// REQUIRED selection hai -- Movenpick jaisa -- kyunki har meal plan ki
// bilkul alag room price hai (sirf info text nahi, asal price
// determine karta hai). Extra Bed ki price bhi meal-plan ke hisaab se
// alag hai (RS ke liye).
// NO weekday/weekend split (rate sheet mein sirf date-range based hai).

require_once __DIR__ . '/base_handler.php';

class LeMeridienTowerMakkahHandler implements HotelHandlerInterface {

    private $hotel_id = LEMERIDIEN_HOTEL_ID;

    private $meal_labels = [
        'ro'      => 'Room Only',
        'bb_intl' => 'BB International (Breakfast)',
        'hb_pk'   => 'HB Pakistan Menu (Half Board)',
        'fb_pk'   => 'FB Pakistan Menu (Full Board)',
    ];

    public function getRooms($hotel_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, room_type, display_name, capacity, description FROM hotel_room_types WHERE hotel_id = ? ORDER BY id");
        $stmt->execute([$hotel_id]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rooms as &$room) {
            $room['has_seasonal'] = true;
            $room['price_label'] = 'Seasonal Pricing';
            // Sirf Royal Suite (rs) mein Extra Bed hai -- DS/ES mein nahi.
            $room['extra_bed_available'] = ($room['room_type'] === 'rs');

            $bedStmt = $pdo->prepare("SELECT DISTINCT room_type FROM hotel_seasonal_pricing WHERE hotel_id = ? AND room_type_code = ? ORDER BY FIELD(room_type,'single','double','triple','quad','quint','sixpax')");
            $bedStmt->execute([$hotel_id, $room['room_type']]);
            $room['bed_types'] = $bedStmt->fetchAll(PDO::FETCH_COLUMN);
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
        $bed_type = $options['bed_type'] ?? '';
        $meal_type = $options['meal_type'] ?? '';
        if ($bed_type === '') return ['error' => 'Room selection incomplete'];
        if ($meal_type === '' || !isset($this->meal_labels[$meal_type])) return ['error' => 'Please select a meal plan'];

        $date_in = new DateTime($check_in);
        $date_out = new DateTime($check_out);
        $nights = $date_in->diff($date_out)->days;
        if ($nights < 1) return ['error' => 'Invalid dates'];

        $extra_bed = ($room_type === 'rs') ? ($options['extra_bed'] ?? 0) : 0;
        $total = 0;
        $extra_bed_total = 0;
        $breakdown = [];

        for ($i = 0; $i < $nights; $i++) {
            $current_date = date('Y-m-d', strtotime($check_in . ' + ' . $i . ' days'));
            $is_weekend = $this->isWeekend($current_date) ? 1 : 0;
            $stmt = $pdo->prepare("SELECT * FROM hotel_seasonal_pricing WHERE hotel_id = ? AND room_type_code = ? AND room_type = ? AND meal_type = ? AND is_weekend = ? AND ? BETWEEN start_date AND end_date");
            $stmt->execute([$hotel_id, $room_type, $bed_type, $meal_type, $is_weekend, $current_date]);
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
        return ['extra_bed_available' => true, 'has_weekend_split' => false, 'meal_labels' => $this->meal_labels];
    }

    public function renderRoomSelection($hotel_id, $rooms) {
        ob_start();
        ?>
        <div class="panel selector-panel" id="lemeridienMealPanel" style="display:none;">
            <label class="selector-label" for="lemeridienMealSelect">Select Meal Plan</label>
            <div class="room-select-wrap">
                <select id="lemeridienMealSelect" name="meal_type" class="room-select" onchange="calculateTotal()">
                    <option value="">— Choose a meal plan —</option>
                    <?php foreach ($this->meal_labels as $code => $label): ?>
                        <option value="<?php echo $code; ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="extra-bed-option" id="lemeridienExtraBedPanel" style="display:none; margin-top:16px;">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="lemeridien_extra_bed" name="extra_bed" value="1" onchange="calculateTotal()">
                <label class="form-check-label" for="lemeridien_extra_bed">
                    Add Extra Bed
                    <span style="color:rgba(255,255,255,0.3); font-size:11px; display:block; margin-top:2px;">(Royal Suite only — price varies by meal plan)</span>
                </label>
            </div>
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