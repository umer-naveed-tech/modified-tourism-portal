<?php
// hotel_handlers/handler_factory.php

// NAYE HOTELS KE IDs -- inhi constants ko poori codebase mein use karo
// taake ID kahin bhi mismatch na ho.
define('FAIRMONT_HOTEL_ID', 145);
define('SWISSOTEL_HOTEL_ID', 146);
define('SWISSOTEL_ALMAQAM_HOTEL_ID', 147);
define('ALMARWA_HOTEL_ID', 148);
define('ALSAFWAH_HOTEL_ID', 149);
define('CONRAD_HOTEL_ID', 150);

require_once __DIR__ . '/base_handler.php';
require_once __DIR__ . '/normal_hotel.php';
require_once __DIR__ . '/marriot_jabal_omer.php';
require_once __DIR__ . '/movenpick_hajar_tower.php';
require_once __DIR__ . '/makkah_hotel.php';
require_once __DIR__ . '/makkah_towers.php';
require_once __DIR__ . '/fairmont_clocktower.php';
require_once __DIR__ . '/swissotel_makkah.php';
require_once __DIR__ . '/swissotel_almaqam.php';
require_once __DIR__ . '/al_marwa_rayhaan.php';
require_once __DIR__ . '/al_safwah_tower3.php';   // NAYA
require_once __DIR__ . '/conrad_makkah.php';      // NAYA

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
        149 => 'AlSafwahTower3Handler',
        150 => 'ConradMakkahHandler',
    ];

    // "Simple" pattern hotels: room_type_code + is_weekend, 70 SAR hidden
    // markup, koi extra bed nahi, koi meal add-on nahi, koi supplement
    // nahi. Al Safwah/Conrad is list mein NAHI hain kyunki unke apne
    // bespoke blocks hain (extra bed / supplements) -- Makkah Hotel jaisa.
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

    public static function registerHandler($hotel_id, $handler_class) {
        self::$handlers[$hotel_id] = $handler_class;
    }
}
?>