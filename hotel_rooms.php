<?php
session_start();
require_once 'config.php';

$hotel_id = $_GET['hotel_id'] ?? 0;

// Get hotel details
$stmt = $pdo->prepare("SELECT * FROM hotels_saudi WHERE id = ?");
$stmt->execute([$hotel_id]);
$hotel = $stmt->fetch();

if(!$hotel) {
    header('Location: services.php?type=hotels');
    exit();
}

// Get all rooms for this hotel
$stmt = $pdo->prepare("SELECT * FROM hotel_rooms WHERE hotel_id = ? ORDER BY FIELD(room_type, 'Separate', 'Double', 'Triple', 'Quad')");
$stmt->execute([$hotel_id]);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build a clean JS-ready array (also used to pre-fill the dropdown)
$capacityMap = ['Separate' => 1, 'Double' => 2, 'Triple' => 3, 'Quad' => 4];
$roomsForJs = [];
foreach($rooms as $room) {
    $amenities = !empty($room['amenities'])
        ? array_map('trim', explode(',', $room['amenities']))
        : ['Attached Washroom', 'Air Conditioning', 'WiFi'];

    $roomsForJs[] = [
        'id'          => (int)$room['id'],
        'type'        => $room['room_type'],
        'label'       => $room['room_type'] . ' Room',
        'price'       => (float)$room['price_per_night_sar'],
        'capacity'    => $capacityMap[$room['room_type']] ?? 1,
        'image'       => !empty($room['image_url']) ? $room['image_url'] : null,
        'hasImage'    => !empty($room['image_url']),
        'description' => $room['description'] ?? 'Comfortable room with all amenities',
        'amenities'   => $amenities,
    ];
}

$error = '';
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($hotel['hotel_name']); ?> - Room Selection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        .hotel-header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; padding: 50px 0 28px; }
        .hotel-header h2 { font-weight: 700; margin-bottom: 6px; }
        .hotel-header p { opacity: .85; margin: 0; }
        .container { max-width: 900px; margin: 0 auto; padding: 0 24px; }

        .panel { background: white; border-radius: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #eef1f5; }

        /* --- Room type selector --- */
        .selector-panel { padding: 28px; margin-top: -40px; position: relative; z-index: 2; }
        .selector-label { font-weight: 600; color: #0f172a; margin-bottom: 10px; display: block; font-size: 15px; }
        .room-select-wrap { position: relative; }
        .room-select {
            appearance: none; -webkit-appearance: none;
            width: 100%; padding: 16px 44px 16px 18px; border-radius: 14px;
            border: 1.5px solid #e2e8f0; background: #fff; font-size: 16px; font-weight: 500;
            color: #0f172a; cursor: pointer; transition: border-color .2s ease;
        }
        .room-select:focus { outline: none; border-color: #d4af37; }
        .room-select-wrap::after {
            content: "\25BE"; position: absolute; right: 18px; top: 50%; transform: translateY(-50%);
            color: #64748b; font-size: 18px; pointer-events: none;
        }

        /* --- Selected room detail card --- */
        .room-detail { margin-top: 20px; overflow: hidden; display: none; }
        .room-detail.active { display: block; animation: fadeIn .25s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .room-detail img { width: 100%; height: 280px; object-fit: cover; background: #f1f5f9; display: block; }
        .room-detail-noimg {
            width: 100%; height: 220px; display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #94a3b8;
        }
        .room-detail-noimg svg { width: 46px; height: 46px; opacity: .6; margin-bottom: 10px; }
        .room-detail-noimg span { font-size: 13px; letter-spacing: .3px; }
        .room-detail-body { padding: 26px; }
        .room-type-badge { display: inline-block; padding: 5px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; letter-spacing: .3px; background: #f1f5f9; color: #0f172a; margin-bottom: 12px; }
        .room-detail-body h4 { font-weight: 700; margin-bottom: 10px; }
        .room-desc { color: #64748b; margin-bottom: 16px; }
        .amenities { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
        .amenity { background: #f1f5f9; padding: 6px 14px; border-radius: 20px; font-size: 12.5px; color: #475569; }
        .price-row { display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid #eef1f5; }
        .price { font-size: 26px; font-weight: 700; color: #d4af37; }
        .price small { font-size: 14px; color: #94a3b8; font-weight: 500; }
        .capacity-pill { font-size: 13.5px; color: #475569; background: #f8fafc; border: 1px solid #eef1f5; padding: 6px 14px; border-radius: 20px; }

        /* --- Booking section --- */
        .booking-section { padding: 26px; margin-top: 22px; margin-bottom: 50px; display: none; }
        .booking-section.active { display: block; }
        .booking-section h4 { font-weight: 700; margin-bottom: 18px; }
        .btn-book { background: #d4af37; color: #0f172a; border: none; padding: 14px; border-radius: 12px; width: 100%; font-weight: 600; font-size: 17px; transition: all 0.3s ease; margin-top: 8px; }
        .btn-book:hover { background: #c9a227; color: white; }
        .btn-book:disabled { opacity: .5; cursor: not-allowed; }
        .total-price { font-size: 26px; font-weight: 700; color: #059669; }
        input, select.form-control { border-radius: 10px; padding: 12px; border: 1.5px solid #e2e8f0; width: 100%; font-family: inherit; }
        input:focus { outline: none; border-color: #d4af37; }
        .error-message { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 12px; margin-bottom: 20px; }
        .empty-state { padding: 50px 24px; text-align: center; }
        label.form-label { font-weight: 500; color: #334155; font-size: 14px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">Ahmed Travels - Saudi</a>
        <div>
            <?php if(isset($_SESSION['user_id'])): ?>
                <span class="text-white me-2">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <a href="dashboard.php" class="btn btn-light btn-sm">Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-light btn-sm me-2">Login</a>
                <a href="signup.php" class="btn btn-light btn-sm">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="hotel-header">
    <div class="container">
        <h2><?php echo htmlspecialchars($hotel['hotel_name']); ?></h2>
        <p><?php echo htmlspecialchars($hotel['city']); ?> &nbsp;|&nbsp; <?php echo str_repeat('★', (int)$hotel['rating']); ?></p>
    </div>
</div>

<div class="container mt-4">
    <?php if(isset($_GET['error'])): ?>
        <div class="error-message">Booking failed. Please try again.</div>
    <?php endif; ?>

    <?php if(count($rooms) > 0): ?>
        <div class="panel selector-panel">
            <label class="selector-label" for="roomTypeSelect">Select Room Type</label>
            <div class="room-select-wrap">
                <select id="roomTypeSelect" class="room-select">
                    <option value="">— Choose a room type —</option>
                    <?php foreach($roomsForJs as $i => $r): ?>
                        <option value="<?php echo $i; ?>">
                            <?php echo htmlspecialchars($r['label']); ?> — SAR <?php echo number_format($r['price']); ?>/night (<?php echo $r['capacity']; ?> <?php echo $r['capacity'] > 1 ? 'persons' : 'person'; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Selected room details, filled in by JS -->
        <div class="panel room-detail" id="roomDetail">
            <div id="rd_image_wrap"></div>
            <div class="room-detail-body">
                <span class="room-type-badge" id="rd_badge"></span>
                <h4 id="rd_title"></h4>
                <p class="room-desc" id="rd_desc"></p>
                <div class="amenities" id="rd_amenities"></div>
                <div class="price-row">
                    <div><span class="price" id="rd_price"></span> <small>/night</small></div>
                    <span class="capacity-pill" id="rd_capacity"></span>
                </div>
            </div>
        </div>

        <!-- Booking Section -->
        <div class="panel booking-section" id="bookingSection">
            <h4>Complete Your Booking</h4>
            <?php if(!isset($_SESSION['user_id'])): ?>
                <p style="background:#fff7ed; color:#9a3412; padding:10px 14px; border-radius:10px; font-size:13.5px; margin-bottom:16px;">
                    You'll be asked to log in (or create a free account) when you confirm — your room and dates will be saved.
                </p>
            <?php endif; ?>
            <form method="POST" action="book_hotel_room.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="room_id" id="selected_room_id">
                <input type="hidden" name="hotel_id" value="<?php echo (int)$hotel_id; ?>">
                <input type="hidden" name="hotel_name" value="<?php echo htmlspecialchars($hotel['hotel_name']); ?>">

                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Check-in Date</label>
                        <input type="date" name="check_in" id="check_in" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Check-out Date</label>
                        <input type="date" name="check_out" id="check_out" class="form-control" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Number of Nights</label>
                        <input type="text" id="nights" class="form-control" readonly value="0">
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-12">
                        <label class="form-label">Total Amount</label>
                        <input type="text" id="total_amount" class="form-control total-price" readonly value="SAR 0">
                    </div>
                </div>

                <button type="submit" class="btn-book" id="btnBook" disabled>Confirm Booking</button>
            </form>
        </div>
    <?php else: ?>
        <div class="panel empty-state">
            <h4>Coming Soon</h4>
            <p class="text-muted">Room details for this hotel will be added soon.</p>
            <a href="services.php?type=hotels&city=<?php echo urlencode($hotel['city']); ?>" class="btn btn-secondary">Back to Hotels</a>
        </div>
    <?php endif; ?>
</div>

<script>
// Room data rendered server-side — values already come from the DB, no extra fetch needed
const roomsData = <?php echo json_encode($roomsForJs, JSON_UNESCAPED_SLASHES); ?>;
let selectedRoom = null;

const select = document.getElementById('roomTypeSelect');
if(select) {
    select.addEventListener('change', function() {
        if(this.value === '') {
            document.getElementById('roomDetail').classList.remove('active');
            document.getElementById('bookingSection').classList.remove('active');
            document.getElementById('btnBook').disabled = true;
            selectedRoom = null;
            return;
        }

        selectedRoom = roomsData[parseInt(this.value)];
        renderRoomDetail(selectedRoom);
        document.getElementById('selected_room_id').value = selectedRoom.id;
        document.getElementById('roomDetail').classList.add('active');
        document.getElementById('bookingSection').classList.add('active');
        calculateTotal();
        document.getElementById('roomDetail').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
}

function renderRoomDetail(room) {
    const imgWrap = document.getElementById('rd_image_wrap');
    if(room.hasImage) {
        imgWrap.innerHTML = '<img src="' + room.image + '" alt="' + room.type + ' Room" onerror="this.onerror=null;this.parentElement.innerHTML=noImageMarkup(\'' + room.type + '\');">';
    } else {
        imgWrap.innerHTML = noImageMarkup(room.type);
    }

    document.getElementById('rd_badge').textContent = room.type;
    document.getElementById('rd_title').textContent = room.label;
    document.getElementById('rd_desc').textContent = room.description;
    document.getElementById('rd_price').textContent = 'SAR ' + room.price.toLocaleString();
    document.getElementById('rd_capacity').textContent = 'Capacity: ' + room.capacity + (room.capacity > 1 ? ' persons' : ' person');

    const amenitiesBox = document.getElementById('rd_amenities');
    amenitiesBox.innerHTML = '';
    room.amenities.forEach(a => {
        const span = document.createElement('span');
        span.className = 'amenity';
        span.textContent = a;
        amenitiesBox.appendChild(span);
    });
}

function noImageMarkup(roomType) {
    return '<div class="room-detail-noimg">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 12h18M3 12v6a1 1 0 001 1h16a1 1 0 001-1v-6M3 12l1.5-5A2 2 0 016.4 5.5h11.2a2 2 0 011.9 1.5L21 12M7 9.5h2M7 12.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>' +
        '<span>' + roomType + ' Room — photo coming soon</span>' +
        '</div>';
}

function calculateNights() {
    const checkIn = document.getElementById('check_in').value;
    const checkOut = document.getElementById('check_out').value;
    if(checkIn && checkOut) {
        const nights = Math.ceil((new Date(checkOut) - new Date(checkIn)) / (1000 * 60 * 60 * 24));
        document.getElementById('nights').value = nights > 0 ? nights : 0;
        return nights > 0 ? nights : 0;
    }
    document.getElementById('nights').value = 0;
    return 0;
}

function calculateTotal() {
    const nights = calculateNights();
    const price = selectedRoom ? selectedRoom.price : 0;
    const total = price * nights;
    document.getElementById('total_amount').value = 'SAR ' + total.toLocaleString();
    document.getElementById('btnBook').disabled = !(selectedRoom && total > 0);
}

const checkInEl = document.getElementById('check_in');
const checkOutEl = document.getElementById('check_out');
if(checkInEl && checkOutEl) {
    checkInEl.addEventListener('change', calculateTotal);
    checkOutEl.addEventListener('change', calculateTotal);
}
</script>

</body>
</html>