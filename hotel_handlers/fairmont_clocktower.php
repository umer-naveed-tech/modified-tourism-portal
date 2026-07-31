<?php
// hotel_handlers/fairmont_clocktower.php
//
// Fairmont Clock Tower Hotel Makkah -- 5 Star
// Structure: room_type_code = room category (city/haram/kaaba/gold view),
// room_type = bed config (double/triple/quad), is_weekend split.
// Breakfast is always included in the room rate. No extra bed, no meal
// add-on -- just room selection and total price.
//
// Pricing rule for ALL new hotels going forward: base_price_sar in the DB
// is (hotel rate sheet price - 70), and markup_sar is always 70 -- so the
// customer always sees the rate-sheet price, but it's stored as
// base+markup on the backend (matches Marriot/Movenpick convention).

require_once __DIR__ . '/base_handler.php';

class FairmontClockTowerHandler implements HotelHandlerInterface {

    private $hotel_id = FAIRMONT_HOTEL_ID; // set via define() in handler_factory.php

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

        $total = 0;
        $breakdown = [];

        for ($i = 0; $i < $nights; $i++) {
            $current_date = date('Y-m-d', strtotime($check_in . ' + ' . $i . ' days'));
            $is_weekend = $this->isWeekend($current_date) ? 1 : 0;

            $stmt = $pdo->prepare("
                SELECT * FROM hotel_seasonal_pricing
                WHERE hotel_id = ? AND room_type_code = ? AND is_weekend = ?
                AND ? BETWEEN start_date AND end_date
            ");
            $stmt->execute([$hotel_id, $room_type, $is_weekend, $current_date]);
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

        return [
            'success' => true,
            'room_total' => $total,
            'grand_total' => $total,
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
        ];
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