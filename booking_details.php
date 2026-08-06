<?php
// booking_details.php
//
// STEP 2 of the new booking flow (Select Service -> Personal Details ->
// Confirm -> Payment). The booking row already exists at this point
// (created by book_hotel_room.php / booking_taxi.php / booking.php with
// its normal, unchanged pricing logic) -- this page only collects and
// saves the traveler's personal details onto that same row, then moves
// to the confirmation step.

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$booking_id = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
if (!$booking_id) {
    header('Location: dashboard.php');
    exit();
}

// Ownership check -- a customer can only fill in details for their OWN
// booking, never someone else's, regardless of what id is in the URL.
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: dashboard.php');
    exit();
}

$errors = [];
$old = [
    'name'    => $booking['customer_name'] ?: ($_SESSION['user_name'] ?? ''),
    'phone'   => $booking['customer_phone'] ?: '',
    'email'   => $booking['customer_email'] ?: ($_SESSION['user_email'] ?? ''),
    'country' => $booking['customer_country'] ?: '',
    'id_type' => $booking['id_type'] ?: 'national_id',
    'id_number' => $booking['id_number'] ?: '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['name']      = trim($_POST['name'] ?? '');
    $old['phone']     = trim($_POST['phone'] ?? '');
    $old['email']     = trim($_POST['email'] ?? '');
    $old['country']   = trim($_POST['country'] ?? '');
    $old['id_type']   = ($_POST['id_type'] ?? '') === 'passport' ? 'passport' : 'national_id';
    $old['id_number'] = trim($_POST['id_number'] ?? '');

    if ($old['name'] === '') $errors[] = 'Full name is required.';
    if ($old['phone'] === '') $errors[] = 'Phone number is required.';
    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if ($old['country'] === '') $errors[] = 'Please select your country.';
    if ($old['id_number'] === '') $errors[] = ($old['id_type'] === 'passport' ? 'Passport number' : 'ID card number') . ' is required.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE bookings SET
                customer_name = ?, customer_phone = ?, customer_email = ?,
                customer_country = ?, id_type = ?, id_number = ?,
                payment_status = 'awaiting_confirmation'
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $old['name'], $old['phone'], $old['email'] ?: null,
            $old['country'], $old['id_type'], $old['id_number'],
            $booking_id, $_SESSION['user_id'],
        ]);

        header('Location: booking_review.php?booking_id=' . $booking_id);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Details | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; min-height: 100vh; color: white; }
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }

        .wrap { max-width: 640px; margin: 0 auto; padding: 40px 20px 80px; }
        .logo { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 800; text-align: center; margin-bottom: 8px; }
        .logo span { color: #d4af37; }

        .steps { display: flex; justify-content: center; gap: 10px; margin: 24px 0 36px; }
        .step { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: rgba(255,255,255,0.3); }
        .step .num { width: 24px; height: 24px; border-radius: 50%; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; }
        .step.active { color: #d4af37; }
        .step.active .num { background: #d4af37; color: #0a0f1e; }
        .step.done .num { background: rgba(212,175,55,0.2); color: #d4af37; }
        .step-sep { width: 24px; height: 1px; background: rgba(255,255,255,0.08); align-self: center; }

        .card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 18px; padding: 32px; }
        .card h2 { font-family: 'Playfair Display', serif; font-size: 22px; margin-bottom: 6px; }
        .card .sub { color: rgba(255,255,255,0.4); font-size: 13px; margin-bottom: 26px; }

        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 12.5px; color: rgba(255,255,255,0.5); margin-bottom: 7px; font-weight: 500; }
        .field input[type="text"], .field input[type="email"], .field input[type="tel"] {
            width: 100%; padding: 13px 15px; font-size: 14px; border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03); border-radius: 10px; color: white; font-family: inherit;
        }
        .field input:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 3px rgba(212,175,55,0.08); }

        .country-wrap { position: relative; }
        .country-input { width: 100%; padding: 13px 15px; font-size: 14px; border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03); border-radius: 10px; color: white; font-family: inherit; cursor: pointer; }
        .country-list { position: absolute; top: calc(100% + 6px); left: 0; right: 0; max-height: 240px; overflow-y: auto;
            background: #10182c; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; z-index: 20; display: none;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4); }
        .country-list.open { display: block; }
        .country-search { width: 100%; padding: 11px 14px; border: none; border-bottom: 1px solid rgba(255,255,255,0.08);
            background: transparent; color: white; font-family: inherit; font-size: 13.5px; }
        .country-search:focus { outline: none; }
        .country-item { padding: 10px 14px; font-size: 13.5px; cursor: pointer; color: rgba(255,255,255,0.75); }
        .country-item:hover, .country-item.hl { background: rgba(212,175,55,0.1); color: #d4af37; }
        .country-empty { padding: 12px 14px; font-size: 13px; color: rgba(255,255,255,0.3); }

        .id-type-row { display: flex; gap: 20px; margin-bottom: 14px; }
        .id-type-row label { display: flex; align-items: center; gap: 7px; font-size: 13.5px; color: rgba(255,255,255,0.7); cursor: pointer; font-weight: 400; }
        .id-type-row input[type="radio"] { accent-color: #d4af37; width: 15px; height: 15px; }

        .error-message { background: rgba(239,68,68,0.07); color: #f87171; padding: 13px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 20px; border: 1px solid rgba(239,68,68,0.1); }
        .error-message ul { margin: 0; padding-left: 18px; }

        .btn-continue { width: 100%; padding: 15px; background: #d4af37; color: #0a0f1e; border: none; border-radius: 12px;
            font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 8px; transition: all 0.25s ease; }
        .btn-continue:hover { background: #b8922e; }

        .back-link { display: block; text-align: center; margin-top: 18px; color: rgba(255,255,255,0.35); font-size: 12.5px; text-decoration: none; }
        .back-link:hover { color: #d4af37; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="logo">Ahmed<span>Travels</span></div>

        <div class="steps">
            <div class="step done"><div class="num"><i class="fas fa-check" style="font-size:10px;"></i></div>Service</div>
            <div class="step-sep"></div>
            <div class="step active"><div class="num">2</div>Details</div>
            <div class="step-sep"></div>
            <div class="step"><div class="num">3</div>Confirm</div>
            <div class="step-sep"></div>
            <div class="step"><div class="num">4</div>Payment</div>
        </div>

        <div class="card">
            <h2>Your Details</h2>
            <p class="sub">Please tell us who this booking is for -- Booking No. <?php echo htmlspecialchars($booking['booking_no']); ?></p>

            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <ul>
                        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" id="detailsForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">

                <div class="field">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($old['name']); ?>" required>
                </div>

                <div class="field">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($old['phone']); ?>" required>
                </div>

                <div class="field">
                    <label for="email">Email Address <span style="color:rgba(255,255,255,0.3); font-weight:400;">(optional)</span></label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($old['email']); ?>">
                </div>

                <div class="field">
                    <label for="countryInput">Country</label>
                    <div class="country-wrap">
                        <input type="text" id="countryInput" class="country-input" placeholder="Select your country" readonly value="<?php echo htmlspecialchars($old['country']); ?>">
                        <input type="hidden" name="country" id="countryValue" value="<?php echo htmlspecialchars($old['country']); ?>">
                        <div class="country-list" id="countryList">
                            <input type="text" class="country-search" id="countrySearch" placeholder="Search countries...">
                            <div id="countryItems"></div>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label>Identification</label>
                    <div class="id-type-row">
                        <label><input type="radio" name="id_type" value="national_id" <?php echo $old['id_type'] === 'national_id' ? 'checked' : ''; ?>> National ID Card</label>
                        <label><input type="radio" name="id_type" value="passport" <?php echo $old['id_type'] === 'passport' ? 'checked' : ''; ?>> Passport</label>
                    </div>
                    <input type="text" name="id_number" id="id_number" placeholder="Enter ID card or passport number" value="<?php echo htmlspecialchars($old['id_number']); ?>" required>
                </div>

                <button type="submit" class="btn-continue">Continue to Confirmation</button>
            </form>
            <a href="dashboard.php" class="back-link">Cancel and return to dashboard</a>
        </div>
    </div>

<script>
const COUNTRIES = ["Afghanistan","Albania","Algeria","Andorra","Angola","Argentina","Armenia","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan","Bolivia","Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi","Cambodia","Cameroon","Canada","Chad","Chile","China","Colombia","Comoros","Congo","Costa Rica","Croatia","Cuba","Cyprus","Czech Republic","Denmark","Djibouti","Dominican Republic","Ecuador","Egypt","El Salvador","Eritrea","Estonia","Eswatini","Ethiopia","Fiji","Finland","France","Gabon","Gambia","Georgia","Germany","Ghana","Greece","Guatemala","Guinea","Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland","Israel","Italy","Ivory Coast","Jamaica","Japan","Jordan","Kazakhstan","Kenya","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya","Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Mauritania","Mauritius","Mexico","Moldova","Monaco","Mongolia","Montenegro","Morocco","Mozambique","Myanmar","Namibia","Nepal","Netherlands","New Zealand","Nicaragua","Niger","Nigeria","North Korea","North Macedonia","Norway","Oman","Pakistan","Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saudi Arabia","Senegal","Serbia","Sierra Leone","Singapore","Slovakia","Slovenia","Somalia","South Africa","South Korea","South Sudan","Spain","Sri Lanka","Sudan","Suriname","Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand","Togo","Tunisia","Turkey","Turkmenistan","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States","Uruguay","Uzbekistan","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"];

const countryInput = document.getElementById('countryInput');
const countryValue = document.getElementById('countryValue');
const countryList = document.getElementById('countryList');
const countryItems = document.getElementById('countryItems');
const countrySearch = document.getElementById('countrySearch');

function renderCountries(filter) {
    const f = (filter || '').toLowerCase();
    const matches = COUNTRIES.filter(c => c.toLowerCase().includes(f));
    if (matches.length === 0) {
        countryItems.innerHTML = '<div class="country-empty">No matching country</div>';
        return;
    }
    countryItems.innerHTML = matches.map(c => `<div class="country-item" data-val="${c}">${c}</div>`).join('');
}

countryInput.addEventListener('click', function() {
    countryList.classList.add('open');
    renderCountries('');
    countrySearch.value = '';
    countrySearch.focus();
});

countrySearch.addEventListener('input', function() {
    renderCountries(this.value);
});

countryItems.addEventListener('click', function(e) {
    const item = e.target.closest('.country-item');
    if (!item) return;
    const val = item.dataset.val;
    countryInput.value = val;
    countryValue.value = val;
    countryList.classList.remove('open');
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.country-wrap')) {
        countryList.classList.remove('open');
    }
});

document.getElementById('detailsForm').addEventListener('submit', function(e) {
    if (!countryValue.value) {
        e.preventDefault();
        alert('Please select your country.');
        countryInput.click();
    }
});
</script>
</body>
</html>