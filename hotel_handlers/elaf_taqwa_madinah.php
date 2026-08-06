<?php
// hotel_handlers/elaf_taqwa_madinah.php
//
// Elaf Taqwa Hotel Madinah -- 5 Star
// 3 room types: SGL/DBL, TPL, QUAD. NO weekday/weekend split. Optional
// Haram View Supplement: +150 SR flat, one-time (no markup applied to
// this supplement, same convention as Makkah Hotel/Al Safwah Tower 3
// supplements). Lunch/Dinner = 130 SR -- info text only. Breakfast
// always included.
//
// Note: rate sheet showed "-" (unavailable) for the 15-Dec-26 to
// 05-Jan-27 period. Per Umer's explicit instruction, this period was
// filled using the same rates as the 15-Oct-26 to 15-Dec-26 period
// (580->640/680->740/780->840 SR) rather than left unbookable. The
// "Local School Holidays" row from the rate sheet was intentionally
// NOT added, per Umer's instruction.

require_once __DIR__ . '/base_handler.php';

class ElafTaqwaMadinahHandler implements HotelHandlerInterface {

    private $hotel_id = ELAFTAQWA_HOTEL_ID;

    private $supplement_prices = [
        'haram_view' => 150,
    ];
    private $supplement_labels = [
        'haram_view' => 'Haram View Supplement',
    ];

    public function getRooms($hotel_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, room_type, display_name, capacity, description FROM hotel_room_types WHERE hotel_id = ? ORDER BY id");
        $stmt->execute([$hotel_id]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rooms as &$room) {
            $room['has_seasonal'] = true;
            $room['price_label'] = 'Seasonal Pricing';
            $room['extra_bed_available'] = false;
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
        $supplement = $options['supplement'] ?? null;

        $date_in = new DateTime($check_in);
        $date_out = new DateTime($check_out);
        $nights = $date_in->diff($date_out)->days;
        if ($nights < 1) return ['error' => 'Invalid dates'];

        $total = 0;
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
            $breakdown[] = ['date' => $current_date, 'price' => $night_price, 'is_weekend' => $is_weekend,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))];
        }

        $supplement_total = ($supplement && isset($this->supplement_prices[$supplement])) ? $this->supplement_prices[$supplement] : 0;
        $grand_total = $total + $supplement_total;

        return ['success' => true, 'room_total' => $total, 'supplement_total' => $supplement_total,
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
        return ['extra_bed_available' => false, 'has_weekend_split' => false,
            'supplements' => $this->supplement_prices, 'supplement_labels' => $this->supplement_labels];
    }

    public function renderRoomSelection($hotel_id, $rooms) {
        ob_start();
        ?>
        <div class="supplement-options" style="display:block;">
            <label class="form-label" style="font-weight:600; text-transform:none; color:rgba(255,255,255,0.7); display:block; margin-bottom:10px;">Room View <span style="color:rgba(255,255,255,0.3); font-size:11px; font-weight:400;">(optional upgrade)</span></label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="supplement" id="elaftaqwa_supp_none" value="" checked onchange="calculateTotal()">
                <label class="form-check-label" for="elaftaqwa_supp_none">Standard View</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="supplement" id="elaftaqwa_supp_haram_view" value="haram_view" onchange="calculateTotal()">
                <label class="form-check-label" for="elaftaqwa_supp_haram_view">
                    Haram View <span class="supplement-price">(+SAR 150)</span>
                </label>
            </div>
        </div>
        <div style="margin-top:16px; background:rgba(16,185,129,0.04); padding:12px 16px; border-radius:8px; border:1px solid rgba(16,185,129,0.06); font-size:13px; color:#34d399;">
            🍳 Meal Plan: Breakfast Included<br>
            🍽️ Lunch or Dinner: SAR 130
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