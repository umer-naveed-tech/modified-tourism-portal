<?php
session_start();
require_once 'config.php';

$type = $_GET['type'] ?? 'hotels';
if($type == 'ziyarat' || $type == 'groups') {
    header('Location: services.php?type=hotels');
    exit();
}
$city = $_GET['city'] ?? 'Mecca';

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
} else {
    $services = [];
}

$stmt = $pdo->query("SELECT DISTINCT from_city FROM car_fares WHERE from_city NOT LIKE '%ZIARAT%' ORDER BY from_city");
$cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
if(empty($cities)) {
    $cities = ['JEDDAH', 'MAKKAH', 'MADINA', 'JEDDAH ARPT', 'MADINA ARPT', 'MADINAH HTL'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services | Ahmed Travels</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #0a0f1e; 
            min-height: 100vh;
        }
        
        /* ===== PAGE FADE-IN ===== */
        .page-content {
            animation: fadeIn 0.5s ease forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ===== BUTTON HOVER ===== */
        .btn, button, .tab-link, .city-tab, .service-card-btn {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn:hover, button:hover, .service-card-btn:hover { transform: translateY(-2px); }
        .btn:active, button:active { transform: scale(0.97); }
        
        /* ===== CARD HOVER LIFT ===== */
        .service-card {
            transition: all 0.3s ease;
        }
        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.3);
        }
        
        /* ===== INPUT FOCUS GLOW ===== */
        input:focus, select:focus {
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.08);
            outline: none;
        }
        
        .navbar { background: rgba(10, 15, 30, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(212, 175, 55, 0.08); padding: 14px 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { color: white; font-size: 22px; font-weight: 800; text-decoration: none; letter-spacing: -0.5px; }
        .logo span { color: #d4af37; }
        .nav-links a { color: rgba(255,255,255,0.7); text-decoration: none; margin-left: 24px; font-size: 14px; transition: all 0.3s ease; }
        .nav-links a:hover { color: #d4af37; }
        .nav-links .btn-logout { background: rgba(239,68,68,0.1); color: #f87171; padding: 8px 20px; border-radius: 8px; margin-left: 24px; transition: all 0.3s ease; }
        .nav-links .btn-logout:hover { background: #dc2626; color: white; transform: translateY(-2px); }
        .nav-links .btn-login { background: rgba(212,175,55,0.1); color: #d4af37; padding: 8px 20px; border-radius: 8px; margin-left: 24px; transition: all 0.3s ease; }
        .nav-links .btn-login:hover { background: #d4af37; color: #0a0f1e; transform: translateY(-2px); }
        
        .tabs { display: flex; gap: 8px; border-bottom: 1px solid rgba(255,255,255,0.04); margin: 32px 0 32px; flex-wrap: wrap; }
        .tab-link { 
            padding: 12px 28px; 
            font-size: 15px; 
            font-weight: 500; 
            color: rgba(255,255,255,0.4); 
            text-decoration: none; 
            border-radius: 8px 8px 0 0;
        }
        .tab-link:hover { color: #d4af37; background: rgba(255,255,255,0.02); }
        .tab-link.active { 
            color: #d4af37; 
            border-bottom: 2px solid #d4af37;
            background: rgba(255,255,255,0.02);
        }
        
        .city-tabs { display: flex; gap: 16px; justify-content: center; margin-bottom: 32px; flex-wrap: wrap; }
        .city-tab { 
            padding: 10px 28px; 
            font-size: 14px; 
            font-weight: 500; 
            color: rgba(255,255,255,0.4); 
            text-decoration: none; 
            border-radius: 30px; 
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            transition: all 0.3s ease; 
        }
        .city-tab:hover { border-color: rgba(212, 175, 55, 0.1); color: #d4af37; transform: translateY(-2px); }
        .city-tab.active { 
            background: #d4af37; 
            color: #0a0f1e; 
            border-color: #d4af37; 
        }
        
        .services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 30px; margin-top: 20px; }
        .service-card { 
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 16px; 
            overflow: hidden; 
            cursor: pointer; 
        }
        .service-card:hover { border-color: rgba(212, 175, 55, 0.1); }
        .service-card-img { width: 100%; height: 200px; object-fit: cover; background: rgba(255,255,255,0.02); }
        .service-card-body { padding: 20px; }
        .service-card-title { font-size: 18px; font-weight: 700; color: white; margin-bottom: 8px; }
        .service-card-location { color: rgba(255,255,255,0.4); font-size: 13px; margin-bottom: 8px; }
        .service-card-stars { color: #d4af37; font-size: 13px; margin-bottom: 12px; }
        .service-card-price { font-size: 20px; font-weight: 700; color: #d4af37; margin: 10px 0; }
        .service-card-duration { 
            display: inline-block; 
            background: rgba(255,255,255,0.02);
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 12px; 
            color: rgba(255,255,255,0.3); 
        }
        
        .hotel-details { 
            background: rgba(255,255,255,0.02);
            padding: 12px; 
            border-radius: 12px; 
            margin: 12px 0; 
        }
        .detail-item { 
            display: flex; 
            align-items: baseline; 
            gap: 8px; 
            font-size: 12px; 
            color: rgba(255,255,255,0.5); 
            margin-bottom: 6px; 
        }
        .detail-label { font-weight: 500; color: rgba(255,255,255,0.7); min-width: 70px; }
        .service-value { color: #34d399; font-weight: 500; }
        
        .service-card-btn { 
            background: rgba(212, 175, 55, 0.1);
            color: #d4af37; 
            border: 1px solid rgba(212, 175, 55, 0.05);
            padding: 10px 20px; 
            border-radius: 10px; 
            font-weight: 500; 
            width: 100%; 
            font-size: 14px; 
            cursor: pointer; 
            transition: all 0.3s ease;
        }
        .service-card-btn:hover { background: #d4af37; color: #0a0f1e; }
        
        .car-dropdown-container { max-width: 500px; margin: 0 auto 40px auto; }
        .car-select { 
            width: 100%; 
            padding: 14px 20px; 
            font-size: 15px; 
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px; 
            background: rgba(255,255,255,0.02);
            color: white;
            cursor: pointer; 
            transition: all 0.3s ease;
        }
        .car-select option { background: #0a0f1e; color: white; }
        .car-select:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.08);
        }
        .car-select:hover { border-color: rgba(212, 175, 55, 0.1); }
        
        .car-details-card { 
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 16px; 
            overflow: hidden; 
            margin-top: 20px; 
        }
        .car-header { 
            background: rgba(255,255,255,0.02);
            color: white; 
            padding: 25px; 
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .car-category { 
            display: inline-block; 
            padding: 4px 15px; 
            border-radius: 20px; 
            font-size: 12px; 
            margin-top: 8px; 
        }
        .car-category.luxury { background: rgba(212, 175, 55, 0.1); color: #d4af37; }
        .car-category.premium { background: rgba(8, 145, 178, 0.1); color: #22d3ee; }
        .car-category.standard { background: rgba(255,255,255,0.03); color: rgba(255,255,255,0.5); }
        .car-category.economy { background: rgba(16,185,129,0.1); color: #34d399; }
        .car-image-wrap { width: 100%; height: 250px; background: rgba(255,255,255,0.01); border-radius: 12px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .car-image { width: 100%; height: 100%; object-fit: contain; }
        .fare-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .fare-table th, .fare-table td { padding: 10px; text-align: center; border: 1px solid rgba(255,255,255,0.04); }
        .fare-table th { background: rgba(255,255,255,0.02); font-weight: 600; color: rgba(255,255,255,0.7); }
        .fare-table td { color: rgba(255,255,255,0.5); }
        .city-select { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px; 
            margin-bottom: 15px;
            background: rgba(255,255,255,0.02);
            color: white;
            transition: all 0.3s ease;
        }
        .city-select:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.08);
        }
        .city-select option { background: #0a0f1e; color: white; }
        .city-select:hover { border-color: rgba(212, 175, 55, 0.1); }
        .fare-display { 
            background: rgba(16,185,129,0.05);
            padding: 12px; 
            border-radius: 12px; 
            text-align: center; 
            font-weight: 500; 
            color: #34d399; 
            margin: 15px 0; 
            border: 1px solid rgba(16,185,129,0.05);
        }
        
        .empty-state { 
            text-align: center; 
            padding: 60px; 
            background: rgba(255,255,255,0.02);
            border-radius: 16px; 
            border: 1px solid rgba(255,255,255,0.02);
        }
        .empty-state h3 { color: white; margin-bottom: 8px; }
        .empty-state p { color: rgba(255,255,255,0.3); }
        
        @media (max-width: 768px) { 
            .services-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="page-content">
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">Ahmed<span>Travels</span></a>
            <div class="nav-links">
                <a href="services.php">Services</a>
                <a href="dashboard.php">Dashboard</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="btn-logout">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn-login">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="tabs">
            <a href="?type=hotels&city=Mecca" class="tab-link <?php echo $type == 'hotels' ? 'active' : ''; ?>">Hotels</a>
            <a href="?type=taxi" class="tab-link <?php echo $type == 'taxi' ? 'active' : ''; ?>">Airport Taxi</a>
            <a href="?type=visa" class="tab-link <?php echo $type == 'visa' ? 'active' : ''; ?>">Visa Services</a>
        </div>
        
        <?php if($type == 'hotels'): ?>
            <div class="city-tabs">
                <a href="?type=hotels&city=Mecca" class="city-tab <?php echo $city == 'Mecca' ? 'active' : ''; ?>">Mecca Hotels</a>
                <a href="?type=hotels&city=Madinah" class="city-tab <?php echo $city == 'Madinah' ? 'active' : ''; ?>">Madinah Hotels</a>
            </div>
            <div class="services-grid">
                <?php if(count($services) > 0): ?>
                    <?php foreach($services as $hotel): ?>
                        <div class="service-card" onclick="location.href='hotel_rooms.php?hotel_id=<?php echo $hotel['id']; ?>'">
                            <img class="service-card-img" src="<?php echo htmlspecialchars(!empty($hotel['image_url']) ? $hotel['image_url'] : 'https://placehold.co/400x250/1a1a2e/333?text=Hotel'); ?>" alt="<?php echo htmlspecialchars($hotel['hotel_name'] ?? 'Hotel'); ?>" onerror="this.onerror=null;this.src='https://placehold.co/400x250/1a1a2e/333?text=Hotel';">
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
        
        <?php elseif($type == 'taxi' && isset($cars)): ?>
            <div class="car-dropdown-container">
                <select id="carSelect" class="car-select">
                    <option value="" style="color:rgba(255,255,255,0.3);">— Select a Car —</option>
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
                    faresHtml += '<tr><td style="padding: 10px;">'+fare.from_city+' → '+fare.to_city+'</td><td style="font-weight: bold; color: #d4af37;">SAR '+fare.price_sar+'</td></tr>';
                });
                faresHtml += '</tbody></table>';
                
                let html = `
                    <div class="car-details-card">
                        <div class="car-header">
                            <h2 style="color:white;">${car.name} ${car.model}</h2>
                            <span class="car-category ${categoryClass}">${categoryName} Class</span>
                        </div>
                        <div class="car-image-wrap">
                            <img class="car-image" src="${car.image_url}" onerror="this.src='https://placehold.co/600x300/1a1a2e/333?text=${car.name}'">
                        </div>
                        <div style="padding: 25px;">
                            <p style="margin-bottom: 15px; color:rgba(255,255,255,0.5);"><strong style="color:rgba(255,255,255,0.7);">Capacity:</strong> ${car.capacity} persons &nbsp;|&nbsp; <strong style="color:rgba(255,255,255,0.7);">Air Conditioning:</strong> Yes</p>
                            ${faresHtml}
                            <div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: 16px; margin-top: 20px; border: 1px solid rgba(255,255,255,0.04);">
                                <select id="fromCity" class="city-select"><option value="" style="color:rgba(255,255,255,0.3);">Select Pickup City</option>${cities.map(c => `<option value="${c}">${c}</option>`).join('')}</select>
                                <select id="toCity" class="city-select"><option value="" style="color:rgba(255,255,255,0.3);">Select Drop City</option>${cities.map(c => `<option value="${c}">${c}</option>`).join('')}</select>
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
                            if(fare) { 
                                fareDisplay.innerHTML = 'Total Fare: SAR '+fare.price_sar; 
                                bookBtn.disabled = false; 
                                bookBtn.setAttribute('data-from', from); 
                                bookBtn.setAttribute('data-to', to); 
                            } else { 
                                fareDisplay.innerHTML = 'No route from '+from+' to '+to; 
                                bookBtn.disabled = true; 
                            }
                        } else if(from === to && from) { 
                            fareDisplay.innerHTML = 'Cities cannot be same'; 
                            bookBtn.disabled = true; 
                        } else { 
                            fareDisplay.innerHTML = 'Select cities to see fare'; 
                            bookBtn.disabled = true; 
                        }
                    }
                    fromCity.addEventListener('change', updateFare);
                    toCity.addEventListener('change', updateFare);
                    bookBtn.addEventListener('click', function() {
                        const from = fromCity.value, to = toCity.value;
                        if(from && to) window.location.href = 'booking_taxi.php?car_id='+car.id+'&car_name='+encodeURIComponent(car.name)+'&from='+from+'&to='+to;
                    });
                }, 100);
            }
            
            document.getElementById('carSelect').addEventListener('change', function() {
                const carId = this.value;
                if(carId) showCarDetails(carId);
                else document.getElementById('carDetailsContainer').innerHTML = '<div class="empty-state"><h3>Select a Car</h3><p>Please choose a car from the dropdown above to view fares and book</p></div>';
            });
            </script>
        
        <?php elseif($type == 'visa' && isset($services)): ?>
            <div class="services-grid">
                <?php foreach($services as $service): ?>
                    <div class="service-card" onclick="location.href='booking.php?type=<?php echo $type; ?>&id=<?php echo $service['id']; ?>'">
                        <img class="service-card-img" src="https://placehold.co/400x200/1a1a2e/333?text=<?php echo urlencode($service['title'] ?? 'Service'); ?>" alt="<?php echo htmlspecialchars($service['title'] ?? 'Service'); ?>">
                        <div class="service-card-body">
                            <h3 class="service-card-title"><?php echo htmlspecialchars($service['title'] ?? 'Service Name'); ?></h3>
                            <div class="service-card-location"><?php echo htmlspecialchars($service['description'] ?? 'No description available'); ?></div>
                            <div class="service-card-price">SAR <?php echo number_format($service['price'] ?? 0); ?></div>
                            <button class="service-card-btn">Apply Now</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        
        <?php else: ?>
            <div class="empty-state"><h3>No Services Available</h3><p>Please check back later.</p></div>
        <?php endif; ?>
    </div>
</div>

<?php include 'chatbot_widget.php'; ?>
</body>
</html>