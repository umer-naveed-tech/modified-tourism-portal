<?php
/**
 * seed_images.php — ONE-TIME script.
 *
 * Kya karta hai:
 *  - hotels_saudi table ke image_url column mai, HAR HOTEL ko uske city
 *    (Mecca / Madinah) ke hisab se ek real, alag photo assign karta hai.
 *    Same city ke hotels ko bari-bari (round-robin) alag photo milti hai.
 *  - cars table ke image_url column mai, har car ko uske naam (Corolla /
 *    Civic / Sonata / baaki) ke hisab se sahi car ki photo assign karta hai.
 *
 * Koi table/column NAYA nahi banaya — dono tables mai image_url column
 * pehle se maujood hai, ye script sirf us column ki VALUE set karta hai.
 *
 * Kaise chalayen:
 *  1. Is file ko project ke root folder mai daal do (config.php ke sath).
 *  2. Browser mai kholo: http://localhost/<project-folder>/seed_images.php
 *  3. Result page dikhayega ke kis hotel/car ko kaunsi image mili.
 *  4. Kaam ho jane ke baad ye file DELETE kar dena (dobara chalane ki zaroorat nahi).
 */

require_once 'config.php';

// ===================== IMAGE POOLS =====================

// Mecca hotels — real Mecca-area photos, cycled per row
$mecca_images = [
    'https://images.unsplash.com/photo-1713157468774-573a362a3916?w=800&h=500&fit=crop',
    'https://images.unsplash.com/photo-1550525772-26795fa158d7?w=800&h=500&fit=crop',
    'https://images.unsplash.com/photo-1580589368625-7f7f1f96e6b3?w=800&h=500&fit=crop',
];

// Madinah hotels — real Madinah-area photos, cycled per row
$madinah_images = [
    'https://images.unsplash.com/photo-1724191078796-8a997b989f43?w=800&h=500&fit=crop',
    'https://images.unsplash.com/photo-1692566123227-0f68f1b9dac6?w=800&h=500&fit=crop',
    'https://images.unsplash.com/photo-1692977579997-948328cdb7d2?w=800&h=500&fit=crop',
];

// Car images — matched by keyword found in car_name/car_model (case-insensitive)
$car_image_rules = [
    'corolla' => 'https://commons.wikimedia.org/wiki/Special:FilePath/2020_White_Toyota_Corolla_SE.png',
    'civic'   => 'https://commons.wikimedia.org/wiki/Special:FilePath/2012_Honda_Civic_LX_sedan.jpg',
    'sonata'  => 'https://commons.wikimedia.org/wiki/Special:FilePath/Hyundai_Sonata_2017_Front.jpg',
];
// Fallback for any car that doesn't match a keyword above (e.g. the plain "CAR")
$car_default_image = 'https://commons.wikimedia.org/wiki/Special:FilePath/Kia_Cerato_LD_sedan_01_China_2012-04-28.jpg';

// ===================== UPDATE HOTELS =====================

$report = [];

foreach(['Mecca' => $mecca_images, 'Madinah' => $madinah_images] as $city => $pool) {
    $stmt = $pdo->prepare("SELECT id, hotel_name FROM hotels_saudi WHERE city = ? ORDER BY id ASC");
    $stmt->execute([$city]);
    $hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $i = 0;
    foreach($hotels as $hotel) {
        $image = $pool[$i % count($pool)];
        $upd = $pdo->prepare("UPDATE hotels_saudi SET image_url = ? WHERE id = ?");
        $upd->execute([$image, $hotel['id']]);
        $report[] = ['type' => 'Hotel', 'name' => $hotel['hotel_name'] . " ($city)", 'image' => $image];
        $i++;
    }
}

// Also try common alternate spellings in case the city column uses them
foreach(['Madina' => $madinah_images, 'Makkah' => $mecca_images, 'Madinah Munawwarah' => $madinah_images] as $city => $pool) {
    $stmt = $pdo->prepare("SELECT id, hotel_name FROM hotels_saudi WHERE city = ? ORDER BY id ASC");
    $stmt->execute([$city]);
    $hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $i = 0;
    foreach($hotels as $hotel) {
        $image = $pool[$i % count($pool)];
        $upd = $pdo->prepare("UPDATE hotels_saudi SET image_url = ? WHERE id = ?");
        $upd->execute([$image, $hotel['id']]);
        $report[] = ['type' => 'Hotel', 'name' => $hotel['hotel_name'] . " ($city)", 'image' => $image];
        $i++;
    }
}

// ===================== UPDATE CARS =====================

$stmt = $pdo->query("SELECT id, car_name, car_model FROM cars");
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($cars as $car) {
    $haystack = strtolower($car['car_name'] . ' ' . $car['car_model']);
    $image = $car_default_image;
    foreach($car_image_rules as $keyword => $url) {
        if(strpos($haystack, $keyword) !== false) {
            $image = $url;
            break;
        }
    }
    $upd = $pdo->prepare("UPDATE cars SET image_url = ? WHERE id = ?");
    $upd->execute([$image, $car['id']]);
    $report[] = ['type' => 'Car', 'name' => $car['car_name'] . ' ' . $car['car_model'], 'image' => $image];
}

// ===================== REPORT =====================
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Image Seeding Report</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; padding: 30px; }
        h1 { color: #0f172a; }
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
        th, td { padding: 10px 14px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 14px; }
        th { background: #0f172a; color: white; }
        img { width: 90px; height: 55px; object-fit: cover; border-radius: 6px; }
        .empty { color: #ef4444; font-weight: bold; padding: 20px; background: white; border-radius: 8px; }
        .warn { background: #fff7ed; border: 1px solid #fdba74; padding: 14px; border-radius: 8px; margin-bottom: 20px; color: #9a3412; }
    </style>
</head>
<body>
    <h1>✅ Image Seeding Report</h1>
    <?php if(empty($report)): ?>
        <div class="empty">
            Koi hotel ya car update nahi hui — matlab hotels_saudi/cars table mai koi row nahi mili,
            ya city column ki value 'Mecca'/'Madinah' se match nahi ho rahi. Apni "city" column ki
            exact values check karo (phpMyAdmin mai hotels_saudi table dekho) aur mujhe batao.
        </div>
    <?php else: ?>
        <div class="warn">
            <?php echo count($report); ?> rows update ho gayi hain. Neeche check karo har image sahi dikh rahi hai —
            agar koi image na dikhe to wo row noted kar lena, main uski jagah dusri image de dunga.
        </div>
        <table>
            <tr><th>Type</th><th>Name</th><th>Preview</th></tr>
            <?php foreach($report as $r): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['type']); ?></td>
                <td><?php echo htmlspecialchars($r['name']); ?></td>
                <td><img src="<?php echo htmlspecialchars($r['image']); ?>" onerror="this.style.border='2px solid red'; this.alt='FAILED TO LOAD';"></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
    <p style="margin-top:20px; color:#64748b;">Kaam mukammal hone ke baad ye file (<code>seed_images.php</code>) delete kar dena.</p>
</body>
</html>