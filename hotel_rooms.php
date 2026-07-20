<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
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
$rooms = $stmt->fetchAll();

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
        .hotel-header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; padding: 60px 0 30px; }
        .room-card { background: white; border-radius: 20px; overflow: hidden; margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); cursor: pointer; transition: all 0.3s ease; border: 2px solid transparent; }
        .room-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -12px rgba(0,0,0,0.1); }
        .room-card.selected { border-color: #d4af37; }
        .room-card img { width: 100%; height: 200px; object-fit: cover; background: #f1f5f9; }
        .room-card .card-body { padding: 20px; }
        .price { font-size: 24px; font-weight: 700; color: #d4af37; }
        .amenities { display: flex; flex-wrap: wrap; gap: 8px; margin: 15px 0; }
        .amenity { background: #f1f5f9; padding: 5px 12px; border-radius: 20px; font-size: 12px; color: #475569; }
        .booking-section { background: white; border-radius: 20px; padding: 25px; margin-top: 30px; margin-bottom: 50px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .btn-book { background: #d4af37; color: #0f172a; border: none; padding: 14px; border-radius: 12px; width: 100%; font-weight: 600; font-size: 18px; transition: all 0.3s ease; }
        .btn-book:hover { background: #c9a227; color: white; }
        .total-price { font-size: 28px; font-weight: 700; color: #059669; }
        .room-type-badge { display: inline-block; padding: 4px 15px; border-radius: 20px; font-size: 12px; font-weight: 500; background: #e2e8f0; color: #0f172a; margin-bottom: 10px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        input, select { border-radius: 10px; padding: 12px; border: 1.5px solid #e2e8f0; width: 100%; font-family: inherit; }
        input:focus { outline: none; border-color: #d4af37; }
        .error-message { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 12px; margin-bottom: 20px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">Ahmed Travels - Saudi</a>
        <div>
            <span class="text-white me-2">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="dashboard.php" class="btn btn-light btn-sm">Dashboard</a>
        </div>
    </div>
</nav>

<div class="hotel-header">
    <div class="container">
        <h2><?php echo htmlspecialchars($hotel['hotel_name']); ?></h2>
        <p><?php echo htmlspecialchars($hotel['city']); ?> | <?php echo str_repeat('★', $hotel['rating']); ?></p>
    </div>
</div>

<div class="container mt-4">
    <?php if(isset($_GET['error'])): ?>
        <div class="error-message">Booking failed. Please try again.</div>
    <?php endif; ?>
    
    <h3 class="mb-3">Select Room Type</h3>
    <div class="row">
        <?php if(count($rooms) > 0): ?>
            <?php foreach($rooms as $room): 
                $capacityText = '';
                if($room['room_type'] == 'Separate') $capacityText = '1 person';
                else if($room['room_type'] == 'Double') $capacityText = '2 persons';
                else if($room['room_type'] == 'Triple') $capacityText = '3 persons';
                else $capacityText = '4 persons';
            ?>
            <div class="col-md-6">
                <div class="room-card" data-room-id="<?php echo $room['id']; ?>" data-price="<?php echo $room['price_per_night_sar']; ?>" data-room-name="<?php echo $room['room_type']; ?>" onclick="selectRoom(this)">
                    <img src="<?php echo $room['image_url'] ?? 'https://placehold.co/400x200/0f172a/e2e8f0?text=Room'; ?>" alt="<?php echo $room['room_type']; ?> Room">
                    <div class="card-body">
                        <span class="room-type-badge"><?php echo $room['room_type']; ?></span>
                        <h4><?php echo $room['room_type']; ?> Room</h4>
                        <div class="amenities">
                            <?php 
                            if(!empty($room['amenities'])) {
                                $amenities = explode(', ', $room['amenities']);
                                foreach($amenities as $item): ?>
                                    <span class="amenity"><?php echo trim($item); ?></span>
                                <?php endforeach;
                            } else { ?>
                                <span class="amenity">Attached Washroom</span>
                                <span class="amenity">Air Conditioning</span>
                                <span class="amenity">WiFi</span>
                            <?php } ?>
                        </div>
                        <p><small><?php echo htmlspecialchars($room['description'] ?? 'Comfortable room with all amenities'); ?></small></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="price">SAR <?php echo number_format($room['price_per_night_sar']); ?></span>
                                <small>/night</small>
                            </div>
                            <small>Capacity: <?php echo $capacityText; ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center" style="padding: 40px;">
                    <h4>Coming Soon</h4>
                    <p>Room details for this hotel will be added soon.</p>
                    <a href="services.php?type=hotels&city=<?php echo $hotel['city']; ?>" class="btn btn-secondary">Back to Hotels</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Booking Section -->
    <div class="booking-section" id="bookingSection" style="display: none;">
        <h4>Complete Your Booking</h4>
        <form method="POST" action="book_hotel_room.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="room_id" id="selected_room_id">
            <input type="hidden" name="hotel_id" value="<?php echo $hotel_id; ?>">
            <input type="hidden" name="hotel_name" value="<?php echo htmlspecialchars($hotel['hotel_name']); ?>">
            <input type="hidden" name="total_amount" id="total_amount_hidden">
            
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
                    <input type="number" name="nights" id="nights" class="form-control" readonly>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <label class="form-label">Selected Room</label>
                    <input type="text" id="selected_room_name" class="form-control" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Price per Night</label>
                    <input type="text" id="room_price" class="form-control" readonly value="SAR 0 / night">
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-12">
                    <label class="form-label">Total Amount</label>
                    <input type="text" id="total_amount" class="form-control total-price" readonly value="SAR 0">
                </div>
            </div>
            
            <button type="submit" class="btn-book mt-4">Confirm Booking</button>
        </form>
    </div>
</div>

<script>
let selectedRoomId = null;
let selectedPrice = 0;
let selectedRoomName = '';

function selectRoom(element) {
    document.querySelectorAll('.room-card').forEach(card => card.classList.remove('selected'));
    element.classList.add('selected');
    
    selectedRoomId = element.dataset.roomId;
    selectedPrice = parseInt(element.dataset.price);
    selectedRoomName = element.dataset.roomName + ' Room';
    
    document.getElementById('selected_room_id').value = selectedRoomId;
    document.getElementById('selected_room_name').value = selectedRoomName;
    document.getElementById('room_price').value = 'SAR ' + selectedPrice.toLocaleString() + ' / night';
    document.getElementById('bookingSection').style.display = 'block';
    document.getElementById('bookingSection').scrollIntoView({ behavior: 'smooth' });
    calculateTotal();
}

function calculateNights() {
    const checkIn = document.getElementById('check_in').value;
    const checkOut = document.getElementById('check_out').value;
    if(checkIn && checkOut) {
        const nights = Math.ceil((new Date(checkOut) - new Date(checkIn)) / (1000 * 60 * 60 * 24));
        if(nights > 0) { document.getElementById('nights').value = nights; return nights; }
        else { document.getElementById('nights').value = 0; return 0; }
    } return 0;
}

function calculateTotal() {
    const nights = calculateNights();
    const total = selectedPrice * nights;
    if(total > 0 && selectedPrice > 0) {
        document.getElementById('total_amount').value = 'SAR ' + total.toLocaleString();
        document.getElementById('total_amount_hidden').value = total;
    } else {
        document.getElementById('total_amount').value = 'SAR 0';
        document.getElementById('total_amount_hidden').value = 0;
    }
}

document.getElementById('check_in').addEventListener('change', calculateTotal);
document.getElementById('check_out').addEventListener('change', calculateTotal);
</script>

</body>
</html>