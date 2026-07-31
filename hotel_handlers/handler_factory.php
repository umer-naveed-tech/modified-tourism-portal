<?php
// hotel_handlers/handler_factory.php

// 🔴 NAYE HOTELS KE IDs -- inhi constants ko poori codebase mein use karo
// (agent_price_management.php, get_hotel_room_price.php, book_hotel_room.php,
// hotel_rooms.php) taake ID kahin bhi mismatch na ho.
define('FAIRMONT_HOTEL_ID', 145);
define('SWISSOTEL_HOTEL_ID', 146);

require_once __DIR__ . '/base_handler.php';
require_once __DIR__ . '/normal_hotel.php';
require_once __DIR__ . '/marriot_jabal_omer.php';
require_once __DIR__ . '/movenpick_hajar_tower.php';
require_once __DIR__ . '/makkah_hotel.php';
require_once __DIR__ . '/makkah_towers.php';
require_once __DIR__ . '/fairmont_clocktower.php';   // 🔴 NAYA
require_once __DIR__ . '/swissotel_makkah.php';       // 🔴 NAYA

class HotelHandlerFactory {

    private static $handlers = [
        41 => 'MarriotJabalOmerHandler',
        43 => 'MakkahHotelHandler',
        44 => 'MakkahTowersHandler',
        63 => 'MovenpickHajarTowerHandler',
    ];

    public static function getHandler($hotel_id) {
        // Naye hotels constants se register hote hain (self::$handlers array
        // ke bajaye) taake ID ek hi jagah (upar wale define() mein) set ho.
        if ($hotel_id == FAIRMONT_HOTEL_ID) {
            return new FairmontClockTowerHandler();
        }
        if ($hotel_id == SWISSOTEL_HOTEL_ID) {
            return new SwissotelMakkahHandler();
        }
        if (isset(self::$handlers[$hotel_id])) {
            $class = self::$handlers[$hotel_id];
            return new $class();
        }
        return new NormalHotelHandler();
    }

    public static function getHandlerClass($hotel_id) {
        if ($hotel_id == FAIRMONT_HOTEL_ID) return 'FairmontClockTowerHandler';
        if ($hotel_id == SWISSOTEL_HOTEL_ID) return 'SwissotelMakkahHandler';
        return isset(self::$handlers[$hotel_id]) ? self::$handlers[$hotel_id] : 'NormalHotelHandler';
    }

    public static function hasCustomHandler($hotel_id) {
        if ($hotel_id == FAIRMONT_HOTEL_ID || $hotel_id == SWISSOTEL_HOTEL_ID) return true;
        return isset(self::$handlers[$hotel_id]);
    }

    // 🔴 NAYA HOTEL ADD KARNE KE LIYE YE FUNCTION USE KAREN
    public static function registerHandler($hotel_id, $handler_class) {
        self::$handlers[$hotel_id] = $handler_class;
    }
}
?>