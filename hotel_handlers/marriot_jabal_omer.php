<?php
// hotel_handlers/marriot_jabal_omer.php

require_once __DIR__ . '/base_handler.php';

class MarriotJabalOmerHandler implements HotelHandlerInterface {
    
    private $hotel_id = 41;
    
    public function getRooms($hotel_id) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT id, room_type, capacity, description, amenities 
            FROM hotel_rooms 
            WHERE hotel_id = ? 
            ORDER BY FIELD(room_type, 'Separate', 'Double', 'Triple', 'Quad')
        ");
        $stmt->execute([$hotel_id]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rooms as &$room) {
            $room['display_name'] = $room['room_type'] . ' Room';
            $room['has_seasonal'] = true;
            $room['meal_options'] = ['breakfast', 'lunch', 'dinner'];
            $room['is_full_board'] = $this->isFullBoard($room['room_type']);
            $room['price_label'] = 'Seasonal Pricing';
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
        
        $room_type = strtolower($room_type);
        $meals = $options['meals'] ?? [];
        $total = 0;
        $meal_total = 0;
        $breakdown = [];
        $is_full_board = false;
        
        for ($i = 0; $i < $nights; $i++) {
            $current_date = date('Y-m-d', strtotime($check_in . ' + ' . $i . ' days'));
            
            $stmt = $pdo->prepare("
                SELECT * FROM hotel_seasonal_pricing 
                WHERE hotel_id = ? AND room_type = ? 
                AND ? BETWEEN start_date AND end_date
            ");
            $stmt->execute([$hotel_id, $room_type, $current_date]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$rule) {
                return ['error' => "No pricing available for date: $current_date"];
            }
            
            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            
            if (!$rule['is_full_board']) {
                if (in_array('breakfast', $meals) && $rule['breakfast_price_sar']) {
                    $meal_total += $rule['breakfast_price_sar'];
                }
                if (in_array('lunch', $meals) && $rule['lunch_price_sar']) {
                    $meal_total += $rule['lunch_price_sar'];
                }
                if (in_array('dinner', $meals) && $rule['dinner_price_sar']) {
                    $meal_total += $rule['dinner_price_sar'];
                }
            } else {
                $is_full_board = true;
            }
            
            $breakdown[] = [
                'date' => $current_date,
                'price' => $night_price,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))
            ];
        }
        
        $grand_total = $total + $meal_total;
        
        return [
            'success' => true,
            'room_total' => $total,
            'meal_total' => $meal_total,
            'grand_total' => $grand_total,
            'nights' => $nights,
            'breakdown' => $breakdown,
            'is_full_board' => $is_full_board,
            'meal_type' => $meals
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
            'meal_options' => ['breakfast', 'lunch', 'dinner'],
            'meal_labels' => [
                'breakfast' => 'Breakfast',
                'lunch' => 'Lunch',
                'dinner' => 'Dinner'
            ]
        ];
    }
    
    public function renderRoomSelection($hotel_id, $rooms) {
        return '';
    }
    
    private function isFullBoard($room_type) {
        $full_board_types = ['Double', 'Triple', 'Quad'];
        return in_array($room_type, $full_board_types);
    }
}
?>