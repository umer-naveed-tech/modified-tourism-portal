<?php
// hotel_handlers/movenpick_hajar_tower.php

require_once __DIR__ . '/base_handler.php';

class MovenpickHajarTowerHandler implements HotelHandlerInterface {
    
    private $hotel_id = 63;
    private $meal_types = ['breakfast', 'halfboard', 'fullboard'];
    private $meal_labels = [
        'breakfast' => 'International Breakfast',
        'halfboard' => 'International Half Board',
        'fullboard' => 'International Full Board'
    ];
    
    public function getRooms($hotel_id) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT id, room_type, capacity, description, amenities 
            FROM hotel_rooms 
            WHERE hotel_id = ? 
            ORDER BY room_type
        ");
        $stmt->execute([$hotel_id]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rooms as &$room) {
            $room['display_name'] = $room['room_type'] . ' Room';
            $room['has_seasonal'] = true;
            $room['meal_options'] = $this->meal_types;
            $room['meal_labels'] = $this->meal_labels;
            $room['extra_bed_available'] = true;
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
        
        $meal_type = $options['meal_type'] ?? 'breakfast';
        $extra_bed = $options['extra_bed'] ?? 0;
        $room_type = strtolower($room_type);
        
        if ($meal_type === 'fullboard') {
            $extra_bed = 0;
        }
        
        $total = 0;
        $extra_bed_total = 0;
        $breakdown = [];
        
        for ($i = 0; $i < $nights; $i++) {
            $current_date = date('Y-m-d', strtotime($check_in . ' + ' . $i . ' days'));
            $is_weekend = $this->isWeekend($current_date) ? 1 : 0;
            
            $stmt = $pdo->prepare("
                SELECT * FROM hotel_seasonal_pricing 
                WHERE hotel_id = ? AND room_type = ? AND is_weekend = ? 
                AND ? BETWEEN start_date AND end_date
                AND (meal_type = ? OR is_full_board = 1)
            ");
            $stmt->execute([$hotel_id, $room_type, $is_weekend, $current_date, $meal_type]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$rule) {
                return ['error' => "No pricing available for date: $current_date"];
            }
            
            $night_price = $rule['base_price_sar'] + $rule['markup_sar'];
            $total += $night_price;
            
            if ($extra_bed && !$rule['is_full_board']) {
                $extra_bed_price = ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
                $extra_bed_total += $extra_bed_price;
            }
            
            $breakdown[] = [
                'date' => $current_date,
                'price' => $night_price,
                'is_weekend' => $is_weekend,
                'rule_name' => date('d M Y', strtotime($rule['start_date'])) . ' - ' . date('d M Y', strtotime($rule['end_date']))
            ];
        }
        
        $grand_total = $total + $extra_bed_total;
        
        return [
            'success' => true,
            'room_total' => $total,
            'extra_bed_total' => $extra_bed_total,
            'grand_total' => $grand_total,
            'nights' => $nights,
            'breakdown' => $breakdown,
            'meal_type' => $meal_type,
            'extra_bed' => $extra_bed
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
            'meal_types' => $this->meal_types,
            'meal_labels' => $this->meal_labels,
            'extra_bed_available' => true
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