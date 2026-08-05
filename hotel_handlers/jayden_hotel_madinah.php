<?php
// hotel_handlers/jayden_hotel_madinah.php
//
// Jayden Hotel Madinah -- 5 Star
// Single room category tier, three bed types: Double/Triple/Quad. TWO
// selectable rate plans -- "Standard Rate" and "Indonesian Rate" --
// each with its own price (reused via the meal_type column, same
// mechanism as Emaar Al Khalil's Room Only/Breakfast selector). NO
// weekday/weekend split (rate sheet gives one price per period). No
// extra bed, no supplement. Breakfast is always included in both
// rate plans.

require_once __DIR__ . '/base_handler.php';

class JaydenHotelMadinahHandler implements HotelHandlerInterface {

    private $hotel_id = JAYDEN_HOTEL_ID;

    private $meal_labels = [
        'standard'   => 'Standard Rate (Breakfast Included)',
        'indonesian' => 'Indonesian Rate (Breakfast Included)',
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
            $room['bed_types'] = [$room['room_type']]; // room_type_code == room_type here
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
        $meal_type = $options['meal_type'] ?? '';
        if ($meal_type === '' || !isset($this->meal_labels[$meal_type])) {
            return ['error' => 'Please select a rate plan (Standard / Indonesian)'];
        }

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
            $stmt->execute([$hotel_id, $room_type, $room_type, $meal_type, $is_weekend, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rule) return ['error' => "No pricing available for date: $current_date"];
            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            $breakdown[] = ['date' => $current_date, 'price' => $night_price, 'is_weekend' => $is_weekend,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))];
        }

        return ['success' => true, 'room_total' => $total, 'grand_total' => $total, 'nights' => $nights, 'breakdown' => $breakdown, 'meal_type' => $meal_type];
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