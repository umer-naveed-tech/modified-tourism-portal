<?php
// hotel_handlers/normal_hotel.php

require_once __DIR__ . '/base_handler.php';

class NormalHotelHandler implements HotelHandlerInterface {
    
    public function getRooms($hotel_id) {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT id, room_type, price_per_night_sar, capacity, description, amenities, image_url
            FROM hotel_rooms 
            WHERE hotel_id = ? 
            ORDER BY FIELD(room_type, 'Separate', 'Double', 'Triple', 'Quad')
        ");
        $stmt->execute([$hotel_id]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add default data
        foreach ($rooms as &$room) {
            $room['display_name'] = $room['room_type'] . ' Room';
            $room['has_seasonal'] = false;
            $room['price_label'] = $room['price_per_night_sar'] > 0 ? 'SAR ' . number_format($room['price_per_night_sar']) : 'Pricing Available';
        }
        
        return $rooms;
    }
    
    public function getRoomDetails($room_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM hotel_rooms WHERE id = ?");
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
        
        // Get room price
        $stmt = $pdo->prepare("SELECT price_per_night_sar FROM hotel_rooms WHERE hotel_id = ? AND room_type = ?");
        $stmt->execute([$hotel_id, $room_type]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            return ['error' => 'Room not found'];
        }
        
        $total = $room['price_per_night_sar'] * $nights;
        
        return [
            'success' => true,
            'room_total' => $total,
            'grand_total' => $total,
            'nights' => $nights,
            'breakdown' => [
                ['label' => 'Room Rate', 'price' => $total, 'nights' => $nights]
            ]
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
        return [];
    }
    
    public function renderRoomSelection($hotel_id, $rooms) {
        return '';
    }
}
?>