<?php
// hotel_handlers/al_marwa_rayhaan.php
//
// Al Marwa Rayhaan by Rotana Makkah -- same structure as Fairmont/
// Swissotel: room_type_code = room category, room_type = bed config,
// is_weekend split. Breakfast always included. No extra bed, no meal
// add-on. Many room categories (Family/Two-bedroom/Royal suites) only
// have a Quad price -- Double/Triple simply have no row for those, so
// they won't appear as bookable bed options for those room types.

require_once __DIR__ . '/base_handler.php';

class AlMarwaRayhaanHandler implements HotelHandlerInterface {

    private $hotel_id = ALMARWA_HOTEL_ID; // set via define() in handler_factory.php

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

            // Har room category ke liye asal DB se pata karo kaunse bed
            // types (Double/Triple/Quad) actually available hain -- kuch
            // room categories ke liye Quad N/A hota hai, is liye customer
            // ko sirf wahi options dikhne chahiye jo rate-sheet mein hon.
            $bedStmt = $pdo->prepare("
                SELECT DISTINCT room_type FROM hotel_seasonal_pricing
                WHERE hotel_id = ? AND room_type_code = ?
                ORDER BY FIELD(room_type, 'double', 'triple', 'quad')
            ");
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

        // $room_type = room category (room_type_code column). Bed type
        // (Double/Triple/Quad) is a SEPARATE required selection -- without
        // filtering by it, 3 rows (one per bed type) would match every
        // date and fetch() would silently return an arbitrary one.
        $bed_type = $options['bed_type'] ?? '';
        if ($bed_type === '') {
            return ['error' => 'Bed type (Double/Triple/Quad) is required'];
        }

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
                WHERE hotel_id = ? AND room_type_code = ? AND room_type = ? AND is_weekend = ?
                AND ? BETWEEN start_date AND end_date
            ");
            $stmt->execute([$hotel_id, $room_type, $bed_type, $is_weekend, $current_date]);
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