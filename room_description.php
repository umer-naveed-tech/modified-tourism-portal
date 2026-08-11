<?php
// room_description.php
//
// Shows one room's full description (what's actually in the room --
// bed, view, amenities etc.) on its own elegant page, in whichever
// font the agent picked for it in agent_hotel_form.php. Linked from
// the "What's in this room?" link on hotel_rooms.php.

session_start();
require_once 'config.php';
require_once 'gallery_fonts.php';

$room_id = (int)($_GET['room_id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT rt.display_name, rt.capacity, rt.room_details, rt.room_details_font,
           h.hotel_name, h.city, h.id AS hotel_id
    FROM hotel_room_types rt
    JOIN hotels_saudi h ON rt.hotel_id = h.id
    WHERE rt.id = ?
");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room || empty($room['room_details'])) {
    header('Location: services.php?type=hotels');
    exit();
}

$font_choices = galleryFontChoices();
$font_url = $font_choices[$room['room_details_font']] ?? $font_choices['Inter'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($room['display_name']); ?> — <?php echo htmlspecialchars($room['hotel_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <?php if ($font_url): ?>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($font_url); ?>&display=swap" rel="stylesheet">
    <?php endif; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #faf7f1;
            color: #2b2620;
            min-height: 100vh;
            position: relative;
        }
        .bg-ambient { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        .bg-ambient::before {
            content: ''; position: absolute; inset: -20%;
            background: radial-gradient(circle at 15% 10%, rgba(212,175,55,0.08), transparent 40%),
                        radial-gradient(circle at 90% 15%, rgba(212,175,55,0.05), transparent 35%);
        }
        .wrap { position: relative; z-index: 1; max-width: 720px; margin: 0 auto; padding: 50px 24px 80px; }
        .back-link { color: rgba(43,38,32,0.55); text-decoration: none; font-size: 13px; display: inline-block; margin-bottom: 28px; }
        .back-link:hover { color: #b8912f; }

        .header { text-align: center; margin-bottom: 36px; }
        .gold-line { width: 50px; height: 3px; background: #d4af37; margin: 0 auto 16px; border-radius: 2px; }
        .header .hotel-name { font-size: 12.5px; text-transform: uppercase; letter-spacing: 1px; color: rgba(43,38,32,0.5); margin-bottom: 8px; }
        .header h1 { font-family: 'Playfair Display', serif; font-size: 30px; font-weight: 800; }
        .header .meta { font-size: 13px; color: rgba(43,38,32,0.5); margin-top: 8px; }

        .details-card {
            background: #fffdfa; border: 1px solid #ece4d4; border-radius: 20px; padding: 40px;
            box-shadow: 0 20px 50px rgba(120,95,40,0.08);
            font-family: '<?php echo htmlspecialchars($room['room_details_font']); ?>', Georgia, sans-serif;
            font-size: 16px; line-height: 1.9; color: #3a3428;
            white-space: pre-wrap;
        }

        @media (max-width: 600px) {
            .header h1 { font-size: 24px; }
            .details-card { padding: 26px; font-size: 15px; }
        }
    </style>
</head>
<body>
    <div class="bg-ambient" aria-hidden="true"></div>
    <div class="wrap">
        <a href="hotel_rooms.php?hotel_id=<?php echo (int)$room['hotel_id']; ?>" class="back-link">← Back to <?php echo htmlspecialchars($room['hotel_name']); ?></a>

        <div class="header">
            <div class="gold-line"></div>
            <div class="hotel-name"><?php echo htmlspecialchars($room['hotel_name']); ?> — <?php echo htmlspecialchars($room['city']); ?></div>
            <h1><?php echo htmlspecialchars($room['display_name']); ?></h1>
            <div class="meta">Sleeps up to <?php echo (int)$room['capacity']; ?> guests</div>
        </div>

        <div class="details-card"><?php echo nl2br(htmlspecialchars($room['room_details'])); ?></div>
    </div>
</body>
</html>