<?php
// hotel_handlers/handler_factory.php

// NAYE HOTELS KE IDs
define('FAIRMONT_HOTEL_ID', 145);
define('SWISSOTEL_HOTEL_ID', 146);
define('SWISSOTEL_ALMAQAM_HOTEL_ID', 147);
define('ALMARWA_HOTEL_ID', 148);
define('ALSAFWAH_HOTEL_ID', 149);
define('CONRAD_HOTEL_ID', 150);
define('HILTONSUITES_HOTEL_ID', 151);
define('HILTONCONVENTION_HOTEL_ID', 152);
define('DOUBLETREE_HOTEL_ID', 153);
define('ELAFKINDA_HOTEL_ID', 154);
define('SHERATON_HOTEL_ID', 157);
define('MHOTEL_HOTEL_ID', 158);
define('SAJA_HOTEL_ID', 159);
define('LEMERIDIEN_HOTEL_ID', 160);
define('GRANDALMASSAH_HOTEL_ID', 161);
define('ROYALMAJESTIC_HOTEL_ID', 162);
define('MAYSANMASHAER_HOTEL_ID', 163);
define('MAYSANMAQAM_HOTEL_ID', 164);
define('BADRALMASSAH_HOTEL_ID', 165);
define('RAMADAALFAYZEN_HOTEL_ID', 166);
define('EMAARGRAND_HOTEL_ID', 167);
define('SNOODAJYAD_HOTEL_ID', 168);
define('MHOTELALDANA_HOTEL_ID', 69);
define('EMAARALMANAR_HOTEL_ID', 83);
define('EMAARALANDALUSIA_HOTEL_ID', 84);
define('EMAARWORTHELITE_HOTEL_ID', 85);
define('EMAARALKHALIL_HOTEL_ID', 86);
define('MAKAREMAJYAD_HOTEL_ID', 67);
define('MANZELAJYAD_HOTEL_ID', 70);
// Four Points by Sheraton (155) aur Voco Hotel Makkah (156) ke liye koi
// handler register nahi karte -- inke paas abhi koi room data nahi hai,
// isliye NormalHotelHandler (default fallback) khud-ba-khud 'Coming Soon'
// page dikha dega.

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
require_once __DIR__ . '/al_safwah_tower3.php';
require_once __DIR__ . '/conrad_makkah.php';
require_once __DIR__ . '/hilton_suites_makkah.php';      // NAYA
require_once __DIR__ . '/hilton_convention_makkah.php';
require_once __DIR__ . '/doubletree_hilton_makkah.php';  // NAYA
require_once __DIR__ . '/elaf_kinda_makkah.php';
require_once __DIR__ . '/sheraton_makkah.php';   // NAYA
require_once __DIR__ . '/m_hotel_makkah.php';
require_once __DIR__ . '/saja_hotel_makkah.php';
require_once __DIR__ . '/lemeridien_tower_makkah.php';  // bespoke
require_once __DIR__ . '/grand_al_massah.php';    // NAYA
require_once __DIR__ . '/royal_majestic.php';     // NAYA
require_once __DIR__ . '/maysan_al_mashaer.php';  // NAYA
require_once __DIR__ . '/maysan_al_maqam.php';    // NAYA
require_once __DIR__ . '/badr_al_massah.php';     // NAYA
require_once __DIR__ . '/ramada_al_fayzen.php';   // NAYA
require_once __DIR__ . '/emaar_grand.php';
require_once __DIR__ . '/snood_ajyad.php';         // NAYA
require_once __DIR__ . '/m_hotel_al_dana.php';     // NAYA (existing hotel id 69)
require_once __DIR__ . '/emaar_al_manar.php';      // NAYA (existing hotel id 83)
require_once __DIR__ . '/emaar_al_andalusia.php';  // NAYA (existing hotel id 84)
require_once __DIR__ . '/emaar_worth_elite.php';   // NAYA (existing hotel id 85)
require_once __DIR__ . '/emaar_al_khalil.php';     // NAYA (existing hotel id 86)
require_once __DIR__ . '/makarem_ajyad.php';       // NAYA (existing hotel id 67)
require_once __DIR__ . '/manzel_ajyad.php';        // NAYA (existing hotel id 70)

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
        151 => 'HiltonSuitesMakkahHandler',
        152 => 'HiltonConventionMakkahHandler',
        153 => 'DoubletreeHiltonMakkahHandler',
        154 => 'ElafKindaMakkahHandler',
        157 => 'SheratonMakkahHandler',
        158 => 'MHotelMakkahHandler',
        159 => 'SajaHotelMakkahHandler',
        160 => 'LeMeridienTowerMakkahHandler', // bespoke -- simpleHiddenMarkupHotels mein NAHI (apna khud ka block hai)
        161 => 'GrandAlMassahHandler',
        162 => 'RoyalMajesticHandler',
        163 => 'MaysanAlMashaerHandler',
        164 => 'MaysanAlMaqamHandler',
        165 => 'BadrAlMassahHandler',
        166 => 'RamadaAlFayzenHandler',
        167 => 'EmaarGrandHandler',
        168 => 'SnoodAjyadHandler',
        69 => 'MHotelAlDanaHandler',
        83 => 'EmaarAlManarHandler',
        84 => 'EmaarAlAndalusiaHandler',
        85 => 'EmaarWorthEliteHandler',
        86 => 'EmaarAlKhalilHandler',
        67 => 'MakaremAjyadHandler',
        70 => 'ManzelAjyadHandler',
        // 155 (Four Points), 156 (Voco) -- deliberately NOT registered,
        // falls back to NormalHotelHandler -> empty rooms -> Coming Soon UI
    ];

    // "Simple" hotels: room_type_code + is_weekend, 70 SAR hidden markup,
    // koi extra bed nahi, koi meal add-on nahi, koi supplement nahi.
    private static $simpleHiddenMarkupHotels = [145, 146, 147, 148, 154, 157, 158, 159, 161, 162, 163, 164, 165, 166, 167, 168, 69, 83, 84, 85, 86, 67, 70];

    // "Single-room + supplement" hotels: EK room type ('double'),
    // weekday/weekend, 70 SAR hidden markup on room, optional extra bed
    // (25 SAR hidden markup), optional flat supplements (no markup).
    // Naya aisa hotel add karne ke liye BAS is array mein ek line add
    // karo -- get_hotel_room_price.php, book_hotel_room.php ek hi
    // generic block se sabko handle karte hain, alag block likhne ki
    // zaroorat nahi.
    private static $singleRoomSupplementHotels = [149, 150, 151, 152, 153];

    public static function isSimpleHiddenMarkupHotel($hotel_id) {
        return in_array($hotel_id, self::$simpleHiddenMarkupHotels);
    }

    public static function isSingleRoomSupplementHotel($hotel_id) {
        return in_array($hotel_id, self::$singleRoomSupplementHotels);
    }

    public static function registerSimpleHiddenMarkupHotel($hotel_id) {
        if (!in_array($hotel_id, self::$simpleHiddenMarkupHotels)) {
            self::$simpleHiddenMarkupHotels[] = $hotel_id;
        }
    }

    public static function registerSingleRoomSupplementHotel($hotel_id) {
        if (!in_array($hotel_id, self::$singleRoomSupplementHotels)) {
            self::$singleRoomSupplementHotels[] = $hotel_id;
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