<?php
// hotel_handlers/conrad_makkah.php
//
// Conrad Hotel Makkah -- 5 Star
// Sirf EK room type hai: "Double Room" (koi bed-type selector nahi).
//
// Features:
//  - Weekday/Weekend seasonal pricing, +70 SAR hidden markup (standard rule)
//  - No extra bed (rate-sheet mein "Extra Person Per Night" tha, lekin
//    Umer ne is feature ka specifically zikar nahi kiya, isliye skip
//    kiya hai -- agar chahiye to bata dena, add kar denge)
//  - Meal Plan: sirf static text ("Breakfast Included")
//  - Room Type Supplement: 8 options, single-select (radio), ek-time
//    flat charge, agar select ho to total mein add hota hai
//  - Lunch (175 SR) / Dinner (190 SR) -- sirf info ke tor par likhe
//    hain, koi selection option nahi (jaisa Umer ne kaha)

require_once __DIR__ . '/base_handler.php';

class ConradMakkahHandler implements HotelHandlerInterface {

    private $hotel_id = CONRAD_HOTEL_ID; // set via define() in handler_factory.php

    // Room Type Supplement -- flat one-time charge, markup nahi lagti
    private $supplement_prices = [
        'superior_partial_hv'  => 120,
        'deluxe_suite_partial_hv' => 630,
        'executive_cv'         => 300,
        'executive_partial_hv' => 415,
        'grand_premier'        => 2820,
        'two_bedroom_partial_haram'   => 1560,
        'three_bedroom_partial_haram' => 4910,
        'royal_suite'          => 8610,
    ];
    private $supplement_labels = [
        'superior_partial_hv'  => 'Superior Partial H.V',
        'deluxe_suite_partial_hv' => 'Deluxe Suite Partial H.V',
        'executive_cv'         => 'Executive C.V',
        'executive_partial_hv' => 'Executive Partial H.V',
        'grand_premier'        => 'Grand Premier',
        'two_bedroom_partial_haram'   => 'Two Bedroom Suite Partial Haram',
        'three_bedroom_partial_haram' => 'Three Bedroom Suite Partial Haram',
        'royal_suite'          => 'Royal Suite',
    ];

    public function getRooms($hotel_id) {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT id, room_type, display_name, capacity, description
            FROM hotel_room_types
            WHERE hotel_id = ?
            ORDER BY id
        ");
        $stmt->execute([$hotel_id]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rooms as &$room) {
            $room['has_seasonal'] = true;
            $room['price_label'] = 'Seasonal Pricing';
            $room['extra_bed_available'] = false;
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

        if ($nights < 1) {
            return ['error' => 'Invalid dates'];
        }

        $supplement = $options['supplement'] ?? null;

        $total = 0;
        $breakdown = [];

        for ($i = 0; $i < $nights; $i++) {
            $current_date = date('Y-m-d', strtotime($check_in . ' + ' . $i . ' days'));
            $is_weekend = $this->isWeekend($current_date) ? 1 : 0;

            $stmt = $pdo->prepare("
                SELECT * FROM hotel_seasonal_pricing
                WHERE hotel_id = ? AND room_type = ? AND is_weekend = ?
                AND ? BETWEEN start_date AND end_date
            ");
            $stmt->execute([$hotel_id, 'double', $is_weekend, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rule) {
                return ['error' => "No pricing available for date: $current_date"];
            }

            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;

            $breakdown[] = [
                'date' => $current_date,
                'price' => $night_price,
                'is_weekend' => $is_weekend,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date'])),
            ];
        }

        $supplement_total = ($supplement && isset($this->supplement_prices[$supplement]))
            ? $this->supplement_prices[$supplement] : 0;

        $grand_total = $total + $supplement_total;

        return [
            'success' => true,
            'room_total' => $total,
            'supplement_total' => $supplement_total,
            'grand_total' => $grand_total,
            'nights' => $nights,
            'breakdown' => $breakdown,
        ];
    }

    public function validateBooking($data) {
        $errors = [];
        if (empty($data['check_in'])) $errors[] = 'Check-in date required';
        if (empty($data['check_out'])) $errors[] = 'Check-out date required';
        if (empty($data['room_id'])) $errors[] = 'Room selection required';
        return $errors;
    }

    public function getBookingOptions($hotel_id) {
        return [
            'extra_bed_available' => false,
            'supplements' => $this->supplement_prices,
            'supplement_labels' => $this->supplement_labels,
        ];
    }

    public function renderRoomSelection($hotel_id, $rooms) {
        ob_start();
        ?>
        <div class="supplement-options" style="display:block;">
            <label class="form-label" style="font-weight:600; text-transform:none; color:rgba(255,255,255,0.7); display:block; margin-bottom:10px;">Room Type Supplement <span style="color:rgba(255,255,255,0.3); font-size:11px; font-weight:400;">(optional upgrade)</span></label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="supplement" id="conrad_supp_none" value="" checked onchange="calculateTotal()">
                <label class="form-check-label" for="conrad_supp_none">None</label>
            </div>
            <?php foreach ($this->supplement_labels as $code => $label): ?>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="supplement" id="conrad_supp_<?php echo $code; ?>" value="<?php echo $code; ?>" onchange="calculateTotal()">
                <label class="form-check-label" for="conrad_supp_<?php echo $code; ?>">
                    <?php echo htmlspecialchars($label); ?> <span class="supplement-price">(+SAR <?php echo number_format($this->supplement_prices[$code]); ?>)</span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top:16px; background:rgba(16,185,129,0.04); padding:12px 16px; border-radius:8px; border:1px solid rgba(16,185,129,0.06); font-size:13px; color:#34d399;">
            🍳 Meal Plan: Breakfast Included<br>
            🍽️ Lunch: SAR 175 &nbsp;|&nbsp; Dinner: SAR 190
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