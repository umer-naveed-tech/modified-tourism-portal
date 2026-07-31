<?php
// hotel_handlers/base_handler.php

interface HotelHandlerInterface {
    
    // Get rooms for this hotel
    public function getRooms($hotel_id);
    
    // Calculate price for this hotel
    public function calculatePrice($hotel_id, $room_type, $check_in, $check_out, $options = []);
    
    // Get room details by ID
    public function getRoomDetails($room_id);
    
    // Validate booking data
    public function validateBooking($data);
    
    // Get booking options (meal plans, supplements, etc.)
    public function getBookingOptions($hotel_id);
    
    // Render room selection UI (if custom)
    public function renderRoomSelection($hotel_id, $rooms);
}
?>