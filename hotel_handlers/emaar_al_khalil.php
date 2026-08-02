<?php
// hotel_handlers/emaar_al_khalil.php
//
// Ek hi room category, Double/Triple/Quad. NO weekday/weekend split.
// Meal Plan (Room Only vs Breakfast) REQUIRED selection hai -- yahan
// dono ki genuinely alag prices hain (info text kaafi nahi tha).

require_once __DIR__ . '/base_handler.php';

class EmaarAlKhalilHandler implements HotelHandlerInterface {

    private $hotel_id = EMAARALKHALIL_HOTEL_ID;

    private $meal_labels = [
        'ro'  => 'Room Only',
        'bkf' => 'Breakfast Included',
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

            $bedStmt = $pdo->prepare("SELECT DISTINCT room_type FROM hotel_seasonal_pricing WHERE hotel_id = ? AND room_type_code = ?");
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

        $total = 0;
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
            $breakdown[] = ['date' => $current_date, 'price' => $night_price, 'is_weekend' => $is_weekend,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))];
        }

        return ['success' => true, 'room_total' => $total, 'grand_total' => $total, 'nights' => $nights, 'breakdown' => $breakdown];
    }

    public function validateBooking($data) {
        $errors = [];
        if (empty($data['check_in'])) $errors[] = 'Check-in date required';
        if (empty($data['check_out'])) $errors[] = 'Check-out date required';
        if (empty($data['room_id'])) $errors[] = 'Room selection required';
        return $errors;
    }

    public function getBookingOptions($hotel_id) {
        return ['extra_bed_available' => false, 'has_weekend_split' => false, 'requires_meal_type' => true, 'meal_labels' => $this->meal_labels];
    }

    public function renderRoomSelection($hotel_id, $rooms) {
        return '';
    }

    private function isWeekend($date) {
        $day = date('N', strtotime($date));
        return ($day == 4 || $day == 5);
    }
}
?>