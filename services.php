<?php
session_start();
require_once 'config.php';

$type = $_GET['type'] ?? 'hotels';
$city = $_GET['city'] ?? 'Mecca';

// Fetch data based on type
if($type == 'hotels') {
    $stmt = $pdo->prepare("SELECT * FROM hotels_saudi WHERE city = ? ORDER BY hotel_name ASC");
    $stmt->execute([$city]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif($type == 'taxi') {
    $stmt = $pdo->prepare("SELECT * FROM cars");
    $stmt->execute();
    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif($type == 'visa') {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE service_type = 'visa'");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif($type == 'groups') {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE service_type = 'groups'");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $services = [];
}

$cities = ['Jeddah', 'Mecca', 'Madinah', 'Taif'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services | Ahmed Travels</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        
        .navbar { background: #0f172a; padding: 16px 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { color: white; font-size: 22px; font-weight: 600; text-decoration: none; letter-spacing: -0.5px; }
        .logo span { color: #d4af37; }
        .nav-links a { color: #cbd5e1; text-decoration: none; margin-left: 24px; font-size: 14px; transition: color 0.3s ease; }
        .nav-links a:hover { color: #d4af37; }
        .nav-links .btn-logout { background: #ef4444; color: white; padding: 8px 20px; border-radius: 8px; margin-left: 24px; }
        
        .tabs { display: flex; gap: 8px; border-bottom: 1px solid #e2e8f0; margin: 32px 0 32px; }
        .tab-link { padding: 12px 28px; font-size: 15px; font-weight: 500; color: #64748b; text-decoration: none; transition: all 0.3s ease; }
        .tab-link:hover { color: #d4af37; }
        .tab-link.active { color: #d4af37; border-bottom: 2px solid #d4af37; }
        
        .city-tabs { display: flex; gap: 16px; justify-content: center; margin-bottom: 32px; }
        .city-tab { padding: 10px 28px; font-size: 15px; font-weight: 500; color: #64748b; text-decoration: none; border-radius: 30px; background: white; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .city-tab:hover { border-color: #d4af37; color: #d4af37; }
        .city-tab.active { background: #0f172a; color: white; border-color: #0f172a; }
        
        .services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 30px; margin-top: 20px; }
        .service-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: all 0.3s ease; cursor: pointer; border: 1px solid #e2e8f0; }
        .service-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -12px rgba(0,0,0,0.1); border-color: #d4af37; }
        .service-card-img { width: 100%; height: 200px; object-fit: cover; background: #f1f5f9; }
        .service-card-body { padding: 20px; }
        .service-card-title { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .service-card-location { color: #64748b; font-size: 13px; margin-bottom: 8px; }
        .service-card-stars { color: #d4af37; font-size: 13px; margin-bottom: 12px; }
        
        .hotel-details { background: #f8fafc; padding: 12px; border-radius: 12px; margin: 12px 0; }
        .detail-item { display: flex; align-items: baseline; gap: 8px; font-size: 12px; color: #475569; margin-bottom: 6px; }
        .detail-label { font-weight: 500; color: #0f172a; min-width: 70px; }
        .service-value { color: #10b981; font-weight: 500; }
        
        .service-card-btn { background: #0f172a; color: white; padding: 10px 20px; border-radius: 10px; border: none; font-weight: 500; width: 100%; transition: all 0.3s ease; font-size: 14px; cursor: pointer; }
        .service-card-btn:hover { background: #d4af37; color: #0f172a; }
        
        /* Taxi Section Styles */
        .car-dropdown-container { max-width: 500px; margin: 0 auto 40px auto; }
        .car-select { width: 100%; padding: 14px 20px; font-size: 15px; border: 1.5px solid #e2e8f0; border-radius: 12px; background: white; cursor: pointer; }
        .car-details-card { background: white; border-radius: 20px; overflow: hidden; border: 1px solid #e2e8f0; margin-top: 20px; }
        .car-header { background: #0f172a; color: white; padding: 25px; text-align: center; }
        .car-category { display: inline-block; padding: 4px 15px; border-radius: 20px; font-size: 12px; margin-top: 8px; }
        .car-category.luxury { background: #d4af37; color: #0f172a; }
        .car-category.premium { background: #0891b2; color: white; }
        .car-category.standard { background: #64748b; color: white; }
        .car-category.economy { background: #10b981; color: white; }
        .car-image { width: 100%; height: 250px; object-fit: cover; }
        .fare-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .fare-table th, .fare-table td { padding: 10px; text-align: center; border: 1px solid #e2e8f0; }
        .fare-table th { background: #f8fafc; font-weight: 600; }
        .city-select { width: 100%; padding: 12px; border: 1.5px solid #e2e8f0; border-radius: 12px; margin-bottom: 15px; }
        .fare-display { background: #d1fae5; padding: 12px; border-radius: 12px; text-align: center; font-weight: 500; color: #059669; margin: 15px 0; }
        
        .empty-state { text-align: center; padding: 60px; background: white; border-radius: 20px; border: 1px solid #e2e8f0; }
        
        @media (max-width: 768px) { .services-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="container">
        <a href="index.php" class="logo">Ahmed<span>Travels</span></a>
        <div class="nav-links">
            <a href="services.php">Services</a>
            <a href="dashboard.php">Dashboard</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="logout.php" class="btn-logout">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn-logout">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container">
    <div class="tabs">
        <a href="?type=hotels&city=Mecca" class="tab-link <?php echo $type == 'hotels' ? 'active' : ''; ?>">Hotels</a>
        <a href="?type=taxi" class="tab-link <?php echo $type == 'taxi' ? 'active' : ''; ?>">Airport Taxi</a>
        <a href="?type=visa" class="tab-link <?php echo $type == 'visa' ? 'active' : ''; ?>">Visa Services</a>
        <a href="?type=groups" class="tab-link <?php echo $type == 'groups' ? 'active' : ''; ?>">Group Tours</a>
    </div>
    
    <!-- ========== HOTELS SECTION (DIRECT PICTURE LINK FOR AKABIR HIJRAH) ========== -->
    <?php if($type == 'hotels'): ?>
        <div class="city-tabs">
            <a href="?type=hotels&city=Mecca" class="city-tab <?php echo $city == 'Mecca' ? 'active' : ''; ?>">Mecca Hotels</a>
            <a href="?type=hotels&city=Madinah" class="city-tab <?php echo $city == 'Madinah' ? 'active' : ''; ?>">Madinah Hotels</a>
        </div>
        
        <div class="services-grid">
            <?php if(count($services) > 0): ?>
                <?php foreach($services as $hotel): ?>
                    <div class="service-card" onclick="location.href='hotel_rooms.php?hotel_id=<?php echo $hotel['id']; ?>'">
                        <!-- DIRECT PICTURE LINK - EXACTLY AS YOU WANTED -->
                        <img class="service-card-img" src="https://files.catbox.moe/nsxi7x.jpg" alt="<?php echo htmlspecialchars($hotel['hotel_name'] ?? 'Hotel'); ?>">
                        
                        <div class="service-card-body">
                            <h3 class="service-card-title"><?php echo htmlspecialchars($hotel['hotel_name'] ?? 'Hotel Name'); ?></h3>
                            <div class="service-card-location"><?php echo htmlspecialchars($hotel['city'] ?? 'Mecca'); ?></div>
                            <div class="service-card-stars"><?php echo str_repeat('★', $hotel['rating'] ?? 4); ?></div>
                            
                            <div class="hotel-details">
                                <?php if(!empty($hotel['location'])): ?>
                                    <div class="detail-item"><span class="detail-label">Location:</span><span><?php echo htmlspecialchars($hotel['location']); ?></span></div>
                                <?php endif; ?>
                                <?php if(!empty($hotel['distance_meters'])): ?>
                                    <div class="detail-item"><span class="detail-label">Distance:</span><span><?php echo $hotel['distance_meters']; ?> meters</span></div>
                                <?php endif; ?>
                                <?php if(!empty($hotel['shuttle_service']) && $hotel['shuttle_service'] == 'Yes'): ?>
                                    <div class="detail-item"><span class="detail-label">Shuttle:</span><span class="service-value">Free Shuttle</span></div>
                                <?php elseif(!empty($hotel['shuttle_service']) && $hotel['shuttle_service'] == 'Star Shuttle Service'): ?>
                                    <div class="detail-item"><span class="detail-label">Service:</span><span class="service-value">Star Shuttle</span></div>
                                <?php elseif(!empty($hotel['shuttle_service']) && $hotel['shuttle_service'] == 'STAR'): ?>
                                    <div class="detail-item"><span class="detail-label">Service:</span><span>STAR</span></div>
                                <?php endif; ?>
                            </div>
                            
                            <button class="service-card-btn">View Rooms</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><h3>No Hotels Found</h3><p>Hotels in <?php echo htmlspecialchars($city); ?> will be added soon.</p></div>
            <?php endif; ?>
        </div>
    
    <!-- ========== TAXI SECTION ========== -->
    <?php elseif($type == 'taxi' && isset($cars)): ?>
        <div class="car-dropdown-container">
            <select id="carSelect" class="car-select">
                <option value="">— Select a Car —</option>
                <?php foreach($cars as $car): ?>
                    <option value="<?php echo $car['id']; ?>">
                        <?php echo htmlspecialchars($car['car_name']); ?> <?php echo htmlspecialchars($car['car_model']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div id="carDetailsContainer">
            <div class="empty-state">
                <h3>Select a Car</h3>
                <p>Please choose a car from the dropdown above to view fares and book</p>
            </div>
        </div>
        
        <script>
        const carsData = <?php 
            $cars_array = [];
            foreach($cars as $car) {
                $stmt = $pdo->prepare("SELECT from_city, to_city, price_sar FROM car_fares WHERE car_id = ? ORDER BY from_city, to_city");
                $stmt->execute([$car['id']]);
                $fares = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $cars_array[$car['id']] = [
                    'id' => $car['id'],
                    'name' => $car['car_name'] ?? '',
                    'model' => $car['car_model'] ?? '',
                    'capacity' => $car['capacity'] ?? 4,
                    'image_url' => $car['image_url'] ?? '',
                    'fares' => $fares
                ];
            }
            echo json_encode($cars_array);
        ?>;
        
        const cities = <?php echo json_encode($cities); ?>;
        
        function showCarDetails(carId) {
            const car = carsData[carId];
            if(!car) return;
            
            let categoryClass = '', categoryName = '';
            if(car.name == 'Hyundai Sonata') { categoryClass = 'luxury'; categoryName = 'Luxury'; }
            else if(car.name == 'Honda Civic') { categoryClass = 'premium'; categoryName = 'Premium'; }
            else if(car.name == 'Toyota Corolla') { categoryClass = 'standard'; categoryName = 'Standard'; }
            else { categoryClass = 'economy'; categoryName = 'Economy'; }
            
            let faresHtml = '<table class="fare-table"><thead><tr><th>Route</th><th>Fare (SAR)</th></tr></thead><tbody>';
            car.fares.forEach(fare => {
                faresHtml += `<tr><td style="padding: 10px;">${fare.from_city} → ${fare.to_city}</td><td style="font-weight: bold; color: #0f172a;">SAR ${fare.price_sar}</td></td>`;
            });
            faresHtml += '</tbody><table>';
            
            let html = `
                <div class="car-details-card">
                    <div class="car-header">
                        <h2>${car.name} ${car.model}</h2>
                        <span class="car-category ${categoryClass}">${categoryName} Class</span>
                    </div>
                    <img class="car-image" src="${car.image_url}" onerror="this.src='https://placehold.co/600x300/0f172a/e2e8f0?text=${car.name}'">
                    <div style="padding: 25px;">
                        <p style="margin-bottom: 15px;"><strong>Capacity:</strong> ${car.capacity} persons &nbsp;|&nbsp; <strong>Air Conditioning:</strong> Yes</p>
                        ${faresHtml}
                        <div style="background: #f8fafc; padding: 20px; border-radius: 16px; margin-top: 20px;">
                            <select id="fromCity" class="city-select"><option value="">Select Pickup City</option>${cities.map(c => `<option value="${c}">${c}</option>`).join('')}</select>
                            <select id="toCity" class="city-select"><option value="">Select Drop City</option>${cities.map(c => `<option value="${c}">${c}</option>`).join('')}</select>
                            <div id="fareDisplay" class="fare-display">Select cities to see fare</div>
                            <button id="bookNowBtn" class="service-card-btn" disabled>Book Now</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('carDetailsContainer').innerHTML = html;
            
            setTimeout(() => {
                const fromCity = document.getElementById('fromCity');
                const toCity = document.getElementById('toCity');
                const fareDisplay = document.getElementById('fareDisplay');
                const bookBtn = document.getElementById('bookNowBtn');
                
                function updateFare() {
                    const from = fromCity.value, to = toCity.value;
                    if(from && to && from !== to && car.fares) {
                        const fare = car.fares.find(f => f.from_city === from && f.to_city === to);
                        if(fare) { fareDisplay.innerHTML = `Total Fare: SAR ${fare.price_sar}`; bookBtn.disabled = false; bookBtn.setAttribute('data-from', from); bookBtn.setAttribute('data-to', to); }
                        else { fareDisplay.innerHTML = `No route from ${from} to ${to}`; bookBtn.disabled = true; }
                    } else if(from === to && from) { fareDisplay.innerHTML = `Cities cannot be same`; bookBtn.disabled = true; }
                    else { fareDisplay.innerHTML = `Select cities to see fare`; bookBtn.disabled = true; }
                }
                fromCity.addEventListener('change', updateFare);
                toCity.addEventListener('change', updateFare);
                bookBtn.addEventListener('click', function() {
                    const from = fromCity.value, to = toCity.value;
                    if(from && to) window.location.href = `booking_taxi.php?car_id=${car.id}&car_name=${encodeURIComponent(car.name)}&from=${from}&to=${to}`;
                });
            }, 100);
        }
        
        document.getElementById('carSelect').addEventListener('change', function() {
            const carId = this.value;
            if(carId) showCarDetails(carId);
            else document.getElementById('carDetailsContainer').innerHTML = `<div class="empty-state"><h3>Select a Car</h3><p>Please choose a car from the dropdown above to view fares and book</p></div>`;
        });
        </script>
    
    <!-- ========== VISA & TOURS SECTION ========== -->
    <?php elseif(($type == 'visa' || $type == 'groups') && isset($services)): ?>
        <div class="services-grid">
            <?php foreach($services as $service): ?>
                <div class="service-card" onclick="location.href='booking.php?type=<?php echo $type; ?>&id=<?php echo $service['id']; ?>'">
                    <img class="service-card-img" src="https://placehold.co/400x200/0f172a/e2e8f0?text=<?php echo urlencode($service['title'] ?? 'Service'); ?>" alt="<?php echo htmlspecialchars($service['title'] ?? 'Service'); ?>">
                    <div class="service-card-body">
                        <h3 class="service-card-title"><?php echo htmlspecialchars($service['title'] ?? 'Service Name'); ?></h3>
                        <div class="service-card-location"><?php echo htmlspecialchars($service['description'] ?? 'No description available'); ?></div>
                        <div class="service-card-price">SAR <?php echo number_format($service['price'] ?? 0); ?></div>
                        <button class="service-card-btn"><?php echo $type == 'visa' ? 'Apply Now' : 'Book Now'; ?></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    
    <?php else: ?>
        <div class="empty-state"><h3>No Services Available</h3><p>Please check back later.</p></div>
    <?php endif; ?>
</div>

</body>
</html>