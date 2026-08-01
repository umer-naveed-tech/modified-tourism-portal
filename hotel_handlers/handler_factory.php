<?php
// hotel_handlers/handler_factory.php

// NAYE HOTELS KE IDs -- inhi constants ko poori codebase mein use karo
// (agent_price_management.php, get_hotel_room_price.php, book_hotel_room.php,
// hotel_rooms.php) taake ID kahin bhi mismatch na ho.
define('FAIRMONT_HOTEL_ID', 145);
define('SWISSOTEL_HOTEL_ID', 146);
define('SWISSOTEL_ALMAQAM_HOTEL_ID', 147);
define('ALMARWA_HOTEL_ID', 148);

require_once __DIR__ . '/base_handler.php';
require_once __DIR__ . '/normal_hotel.php';
require_once __DIR__ . '/marriot_jabal_omer.php';
require_once __DIR__ . '/movenpick_hajar_tower.php';
require_once __DIR__ . '/makkah_hotel.php';
require_once __DIR__ . '/makkah_towers.php';
require_once __DIR__ . '/fairmont_clocktower.php';
require_once __DIR__ . '/swissotel_makkah.php';
require_once __DIR__ . '/swissotel_almaqam.php';   // NAYA
require_once __DIR__ . '/al_marwa_rayhaan.php';    // NAYA

class HotelHandlerFactory {

    private static $handlers = [
        41 => 'MarriotJabalOmerHandler',
        43 => 'MakkahHotelHandler',
        44 => 'MakkahTowersHandler',
        63 => 'MovenpickHajarTowerHandler',
        145 => 'FairmontClockTowerHandler',
        146 => 'SwissotelMakkahHandler',
        147 => 'SwissotelAlMaqamHandler',
        148 => 'AlMarwaRayhaanHandler',
    ];

    // "Simple" pattern hotels: room_type_code + is_weekend, 70 SAR hidden
    // markup, koi extra bed nahi, koi meal add-on nahi. Naya aisa hotel
    // add karte waqt bas iske array mein ID daal do -- get_hotel_room_price.php,
    // book_hotel_room.php, hotel_rooms.php, agent_price_management.php
    // sab is ek list se check karte hain, kahin bhi lambi || chain nahi
    // badhani padti.
    private static $simpleHiddenMarkupHotels = [145, 146, 147, 148];

    public static function isSimpleHiddenMarkupHotel($hotel_id) {
        return in_array($hotel_id, self::$simpleHiddenMarkupHotels);
    }

    public static function registerSimpleHiddenMarkupHotel($hotel_id) {
        if (!in_array($hotel_id, self::$simpleHiddenMarkupHotels)) {
            self::$simpleHiddenMarkupHotels[] = $hotel_id;
        }
    }

    public static function getHandler($hotel_id) {
        if (isset(self::$handlers[$hotel_id])) {
            $class = self::$handlers[$hotel_id];
            return new $class();
        }
        return new NormalHotelHandler();
    }

    public static function getHandlerClass($hotel_id) {
        return isset(self::$handlers[$hotel_id]) ? self::$handlers[$hotel_id] : 'NormalHotelHandler';
    }

    public static function hasCustomHandler($hotel_id) {
        return isset(self::$handlers[$hotel_id]);
    }

    // NAYA HOTEL ADD KARNE KE LIYE YE FUNCTION USE KAREN
    public static function registerHandler($hotel_id, $handler_class) {
        self::$handlers[$hotel_id] = $handler_class;
    }
}
?>