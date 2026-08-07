<?php
// hotel_handlers/generic_hotel_handler.php
//
// THE key piece that makes "agent creates a hotel with no code" work.
//
// Every hotel added by hand so far (Jayden, Novotel, Al Mokhtara,
// etc.) needed its own PHP class file, even though ~90% of them share
// the exact same pricing shape: room_type_code = room_type = a bed
// type, optional weekday/weekend split, optional flat extra bed.
//
// This ONE class covers that entire shape generically, reading
// everything it needs from hotel_room_types / hotel_seasonal_pricing.
// HotelHandlerFactory::getHandler() (see the patch in handler_factory.php)
// now falls back to THIS class for any hotel_id that has seasonal
// pricing data but no hand-written handler registered -- so a hotel
// the agent creates through the new Manage Hotels screen works
// immediately, with zero new files and zero handler_factory.php edits.
//
// Meal-plan text (Room Only / Breakfast Included / etc.) is read from
// hotel_room_types.description, same convention already used by every
// hand-written "simple" handler in this codebase.

require_once __DIR__ . '/base_handler.php';

class GenericHotelHandler implements HotelHandlerInterface {

    public function getRooms($hotel_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, room_type, display_name, capacity, description FROM hotel_room_types WHERE hotel_id = ? ORDER BY id");
        $stmt->execute([$hotel_id]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rooms as &$room) {
            $room['has_seasonal'] = true;
            $room['price_label'] = 'Seasonal Pricing';

            // Bed type is OPTIONAL: most rooms have exactly one variant
            // (their own code, e.g. "double"), which hotel_rooms.php
            // treats as "no real choice needed". A room the agent gave
            // real variants to (e.g. "City View" / "Haram View" under a
            // "Deluxe Room" category) will have more than one distinct
            // row here, and the customer sees a "Choose Bed Type" step,
            // same convention as the hand-written Swissotel/Fairmont-style
            // handlers already in this codebase.
            $stmt2 = $pdo->prepare("SELECT DISTINCT room_type FROM hotel_seasonal_pricing WHERE hotel_id = ? AND room_type_code = ? ORDER BY room_type");
            $stmt2->execute([$hotel_id, $room['room_type']]);
            $bed_types = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            $room['bed_types'] = !empty($bed_types) ? $bed_types : [$room['room_type']];

            // Extra bed is offered for THIS room only if at least one of
            // its pricing rows actually has a non-zero extra_bed_base --
            // agent-entered rows that never set an extra bed amount
            // simply won't show the option, no per-hotel code needed.
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM hotel_seasonal_pricing WHERE hotel_id = ? AND room_type_code = ? AND extra_bed_base > 0");
            $stmt2->execute([$hotel_id, $room['room_type']]);
            $room['extra_bed_available'] = $stmt2->fetchColumn() > 0;
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
        if ($date_out <= $date_in) return ['error' => 'Check-out date must be after check-in date.'];
        $nights = $date_in->diff($date_out)->days;
        if ($nights < 1) return ['error' => 'Invalid dates'];

        $total = 0;
        $extra_bed_total = 0;
        $breakdown = [];

        for ($i = 0; $i < $nights; $i++) {
            $current_date = date('Y-m-d', strtotime($check_in . ' + ' . $i . ' days'));
            $is_weekend = $this->isWeekend($current_date) ? 1 : 0;

            // Try the exact weekday/weekend row first; if the agent only
            // entered one price for this period (no weekend split), fall
            // back to whichever row exists for that date.
            $stmt = $pdo->prepare("SELECT * FROM hotel_seasonal_pricing WHERE hotel_id = ? AND room_type_code = ? AND room_type = ? AND is_weekend = ? AND ? BETWEEN start_date AND end_date");
            $stmt->execute([$hotel_id, $room_type, $room_type, $is_weekend, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rule) {
                $stmt = $pdo->prepare("SELECT * FROM hotel_seasonal_pricing WHERE hotel_id = ? AND room_type_code = ? AND room_type = ? AND ? BETWEEN start_date AND end_date LIMIT 1");
                $stmt->execute([$hotel_id, $room_type, $room_type, $current_date]);
                $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$rule) {
                return ['error' => "No pricing available for date: $current_date"];
            }

            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            if ($extra_bed) {
                $extra_bed_total += ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
            }
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
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM hotel_seasonal_pricing WHERE hotel_id = ? AND extra_bed_base > 0");
        $stmt->execute([$hotel_id]);
        $has_any_extra_bed = $stmt->fetchColumn() > 0;
        return ['extra_bed_available' => $has_any_extra_bed, 'has_weekend_split' => false];
    }

    public function renderRoomSelection($hotel_id, $rooms) {
        $has_extra_bed = false;
        foreach ($rooms as $r) {
            if (!empty($r['extra_bed_available'])) { $has_extra_bed = true; break; }
        }
        $meal_note = $rooms[0]['description'] ?? '';

        ob_start();
        if ($has_extra_bed) { ?>
        <div class="extra-bed-option" style="display:block;">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="generic_extra_bed" name="extra_bed" value="1" onchange="calculateTotal()">
                <label class="form-check-label" for="generic_extra_bed">
                    Add Extra Bed
                    <span style="color:rgba(255,255,255,0.3); font-size:11px; display:block; margin-top:2px;">(Additional charge per night)</span>
                </label>
            </div>
        </div>
        <?php }
        if ($meal_note) { ?>
        <div style="margin-top:16px; background:rgba(16,185,129,0.04); padding:12px 16px; border-radius:8px; border:1px solid rgba(16,185,129,0.06); font-size:13px; color:#34d399;">
            🍽️ <?php echo htmlspecialchars($meal_note); ?>
        </div>
        <?php }
        return ob_get_clean();
    }

    private function isWeekend($date) {
        $day = date('N', strtotime($date));
        return ($day == 4 || $day == 5);
    }
}
?>