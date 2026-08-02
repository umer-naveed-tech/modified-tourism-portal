<?php
// hotel_handlers/doubletree_hilton_makkah.php
//
// DoubleTree by Hilton Makkah -- 5 Star
// Sirf EK room type: "Double Room". Extra Bed hai (rate-sheet 100 SR +
// 25 hidden markup = 125 SR customer ko dikhta hai). 6 supplement
// options. Meal/Lunch/Dinner sirf static text.
// "Weekend stay minimum 3 nights" -- add nahi kiya.

require_once __DIR__ . '/base_handler.php';

class DoubletreeHiltonMakkahHandler implements HotelHandlerInterface {

    private $hotel_id = DOUBLETREE_HOTEL_ID;

    private $supplement_prices = [
        'standard_hv'          => 110,
        'junior_suite'         => 200,
        'delux_partial_hv'     => 190,
        'onebed_junior_suite'  => 275,
        'onebed_junior_suite_phv' => 375,
        'presidential_hv'      => 4400,
    ];
    private $supplement_labels = [
        'standard_hv'          => 'Standard H.V',
        'junior_suite'         => 'Juniour Suite',
        'delux_partial_hv'     => 'Delux Room Partial H.V',
        'onebed_junior_suite'  => 'One Bedroom Junier Suite',
        'onebed_junior_suite_phv' => 'One Bedroom Junier Suite P.H.V',
        'presidential_hv'      => 'Predidential Suite H.V',
    ];

    public function getRooms($hotel_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, room_type, display_name, capacity, description FROM hotel_room_types WHERE hotel_id = ? ORDER BY id");
        $stmt->execute([$hotel_id]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rooms as &$room) {
            $room['has_seasonal'] = true;
            $room['price_label'] = 'Seasonal Pricing';
            $room['extra_bed_available'] = true;
            $room['bed_types'] = ['double'];
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
        $date_in = new DateTime($check_in);
        $date_out = new DateTime($check_out);
        $nights = $date_in->diff($date_out)->days;
        if ($nights < 1) return ['error' => 'Invalid dates'];

        $extra_bed = $options['extra_bed'] ?? 0;
        $supplement = $options['supplement'] ?? null;
        $total = 0;
        $extra_bed_total = 0;
        $breakdown = [];

        for ($i = 0; $i < $nights; $i++) {
            $current_date = date('Y-m-d', strtotime($check_in . ' + ' . $i . ' days'));
            $is_weekend = $this->isWeekend($current_date) ? 1 : 0;
            $stmt = $pdo->prepare("SELECT * FROM hotel_seasonal_pricing WHERE hotel_id = ? AND room_type = 'double' AND is_weekend = ? AND ? BETWEEN start_date AND end_date");
            $stmt->execute([$hotel_id, $is_weekend, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rule) return ['error' => "No pricing available for date: $current_date"];
            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            if ($extra_bed) $extra_bed_total += ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
            $breakdown[] = ['date' => $current_date, 'price' => $night_price, 'is_weekend' => $is_weekend,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))];
        }

        $supplement_total = ($supplement && isset($this->supplement_prices[$supplement])) ? $this->supplement_prices[$supplement] : 0;
        $grand_total = $total + $extra_bed_total + $supplement_total;

        return ['success' => true, 'room_total' => $total, 'extra_bed_total' => $extra_bed_total,
            'supplement_total' => $supplement_total, 'grand_total' => $grand_total, 'nights' => $nights, 'breakdown' => $breakdown];
    }

    public function validateBooking($data) {
        $errors = [];
        if (empty($data['check_in'])) $errors[] = 'Check-in date required';
        if (empty($data['check_out'])) $errors[] = 'Check-out date required';
        if (empty($data['room_id'])) $errors[] = 'Room selection required';
        return $errors;
    }

    public function getBookingOptions($hotel_id) {
        return ['extra_bed_available' => true, 'supplements' => $this->supplement_prices, 'supplement_labels' => $this->supplement_labels];
    }

    public function renderRoomSelection($hotel_id, $rooms) {
        ob_start();
        ?>
        <div class="extra-bed-option" style="display:block;">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="dt_extra_bed" name="extra_bed" value="1" onchange="calculateTotal()">
                <label class="form-check-label" for="dt_extra_bed">
                    Add Extra Bed
                    <span style="color:rgba(255,255,255,0.3); font-size:11px; display:block; margin-top:2px;">(Additional charge per night)</span>
                </label>
            </div>
        </div>
        <div class="supplement-options" style="display:block; margin-top:16px;">
            <label class="form-label" style="font-weight:600; text-transform:none; color:rgba(255,255,255,0.7); display:block; margin-bottom:10px;">Room Type Supplement <span style="color:rgba(255,255,255,0.3); font-size:11px; font-weight:400;">(optional upgrade)</span></label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="supplement" id="dt_supp_none" value="" checked onchange="calculateTotal()">
                <label class="form-check-label" for="dt_supp_none">None</label>
            </div>
            <?php foreach ($this->supplement_labels as $code => $label): ?>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="supplement" id="dt_supp_<?php echo $code; ?>" value="<?php echo $code; ?>" onchange="calculateTotal()">
                <label class="form-check-label" for="dt_supp_<?php echo $code; ?>">
                    <?php echo htmlspecialchars($label); ?> <span class="supplement-price">(+SAR <?php echo number_format($this->supplement_prices[$code]); ?>)</span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:16px; background:rgba(16,185,129,0.04); padding:12px 16px; border-radius:8px; border:1px solid rgba(16,185,129,0.06); font-size:13px; color:#34d399;">
            🍳 Meal Plan: Breakfast Included<br>
            🍽️ Lunch or Dinner: SAR 140 per pax
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