<?php
// hotel_handlers/elaf_bakkah_qinwan.php
//
// Elaf Bakkah Hotel Makkah (71) and Elaf Qinwan Hotel Makkah (76).
// Both follow the exact same "simpleHiddenMarkupHotel + requires meal
// type" pattern already used by Emaar Al Khalil (86): room_type =
// room_type_code (Double/Triple/Quad, all same price per period), 70
// SAR hidden markup, no extra bed, and a required meal-plan choice
// (Room Only / Breakfast / Half Board / Full Board) that changes the
// price. No handler-specific UI is needed beyond the generic Bed Type
// + Meal Plan selectors hotel_rooms.php already renders for any hotel
// with these flags -- so renderRoomSelection() returns nothing extra.
//
// Two classes (one per hotel) rather than one shared/parameterised
// class, because HotelHandlerFactory::getHandler() always does
// `new $class()` with no constructor arguments -- matching how every
// other single-hotel handler in this project is written.

class ElafBakkahHandler {

    private $hotelId = 71;

    public function getRooms($hotel_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, room_type, display_name, capacity, description FROM hotel_room_types WHERE hotel_id = ? ORDER BY id");
        $stmt->execute([$this->hotelId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rooms = [];
        foreach ($rows as $r) {
            $rooms[] = [
                'id' => $r['id'],
                'room_type' => $r['room_type'],
                'display_name' => $r['display_name'],
                'capacity' => (int)$r['capacity'],
                'description' => $r['description'],
                'image_url' => null,
                'amenities' => [],
                // room_type_code == room_type in this bucket (confirmed
                // against the seeded pricing data), so the bed-type
                // dropdown auto-selects itself -- the agent/customer
                // never sees a redundant second dropdown for the same
                // choice they already made picking the room card.
                'bed_types' => [$r['room_type']],
                'has_seasonal' => true,
                'min_price' => 0,
            ];
        }
        return $rooms;
    }

    public function getRoomDetails($room_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, room_type, display_name, capacity, description FROM hotel_room_types WHERE id = ? AND hotel_id = ?");
        $stmt->execute([$room_id, $this->hotelId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getBookingOptions($hotel_id) {
        return [
            'extra_bed_available' => false,
            // Seeded pricing has identical weekday/weekend prices (same
            // is_weekend=0/1 duplication used for Emaar Al Khalil), so
            // the agent price-management table collapses them into one
            // editable row instead of two.
            'has_weekend_split' => false,
            'requires_meal_type' => true,
            'meal_labels' => [
                'ro'  => 'Room Only',
                'bkf' => 'Breakfast',
                'hb'  => 'Half Board',
                'fb'  => 'Full Board',
            ],
        ];
    }

    public function renderRoomSelection($hotel_id, $rooms) {
        // No hotel-specific extras -- the generic Bed Type + Meal Plan
        // panels in hotel_rooms.php cover everything this hotel needs.
        return '';
    }
}

class ElafQinwanHandler {

    private $hotelId = 76;

    public function getRooms($hotel_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, room_type, display_name, capacity, description FROM hotel_room_types WHERE hotel_id = ? ORDER BY id");
        $stmt->execute([$this->hotelId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rooms = [];
        foreach ($rows as $r) {
            $rooms[] = [
                'id' => $r['id'],
                'room_type' => $r['room_type'],
                'display_name' => $r['display_name'],
                'capacity' => (int)$r['capacity'],
                'description' => $r['description'],
                'image_url' => null,
                'amenities' => [],
                'bed_types' => [$r['room_type']],
                'has_seasonal' => true,
                'min_price' => 0,
            ];
        }
        return $rooms;
    }

    public function getRoomDetails($room_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, room_type, display_name, capacity, description FROM hotel_room_types WHERE id = ? AND hotel_id = ?");
        $stmt->execute([$room_id, $this->hotelId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getBookingOptions($hotel_id) {
        return [
            'extra_bed_available' => false,
            'has_weekend_split' => false,
            'requires_meal_type' => true,
            'meal_labels' => [
                'ro'  => 'Room Only',
                'bkf' => 'Breakfast',
                'hb'  => 'Half Board',
                'fb'  => 'Full Board',
            ],
        ];
    }

    public function renderRoomSelection($hotel_id, $rooms) {
        return '';
    }
}