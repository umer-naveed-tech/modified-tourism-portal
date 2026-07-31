<?php
// hotel_handlers/makkah_hotel.php

require_once __DIR__ . '/base_handler.php';

class MakkahHotelHandler implements HotelHandlerInterface {
    
    private $hotel_id = 43;
    private $supplement_prices = [
        'renovated' => 125,
        'junior_suite' => 250,
        'kaaba_view' => 600,
        'suite' => 2450
    ];
    private $meal_prices = [
        'breakfast' => 80,
        'halfboard' => 250,
        'fullboard' => 420
    ];
    
    public function getRooms($hotel_id) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT id, room_type, display_name, capacity, description 
            FROM hotel_room_types 
            WHERE hotel_id = ? AND room_type != 'deluxe_hv'
            ORDER BY room_type
        ");
        $stmt->execute([$hotel_id]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rooms as &$room) {
            $room['has_seasonal'] = true;
            $room['meal_options'] = ['breakfast', 'halfboard', 'fullboard'];
            $room['extra_bed_available'] = true;
            $room['price_label'] = 'Seasonal Pricing';
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
        
        $meal_type = $options['meal_type'] ?? 'breakfast';
        $extra_bed = $options['extra_bed'] ?? 0;
        $supplements = $options['supplements'] ?? [];
        $guests = $options['guests'] ?? 2;
        
        $total = 0;
        $extra_bed_total = 0;
        $supplements_total = 0;
        $meal_total = 0;
        $breakdown = [];
        
        foreach ($supplements as $supp) {
            if (isset($this->supplement_prices[$supp])) {
                $supplements_total += $this->supplement_prices[$supp];
            }
        }
        
        $meal_price_per_night = $this->meal_prices[$meal_type] ?? 80;
        
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
            
            if ($extra_bed) {
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
        
        $meal_total = $meal_price_per_night * $guests * $nights;
        $grand_total = $total + $meal_total + $extra_bed_total + $supplements_total;
        
        return [
            'success' => true,
            'room_total' => $total,
            'meal_total' => $meal_total,
            'extra_bed_total' => $extra_bed_total,
            'supplements_total' => $supplements_total,
            'grand_total' => $grand_total,
            'nights' => $nights,
            'breakdown' => $breakdown,
            'meal_type' => $meal_type
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
            'meal_types' => ['breakfast', 'halfboard', 'fullboard'],
            'meal_prices' => $this->meal_prices,
            'supplements' => $this->supplement_prices,
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