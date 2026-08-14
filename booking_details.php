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

// NEW: country -> dial code, used both to show the right prefix in the
// UI and to sanity-check the phone number length server-side. Not
// every possible country is here, but this covers the ones in the
// COUNTRIES picker below.
$dial_codes = [
    "Afghanistan"=>"93","Albania"=>"355","Algeria"=>"213","Andorra"=>"376","Angola"=>"244","Argentina"=>"54","Armenia"=>"374","Australia"=>"61","Austria"=>"43","Azerbaijan"=>"994",
    "Bahamas"=>"1","Bahrain"=>"973","Bangladesh"=>"880","Barbados"=>"1","Belarus"=>"375","Belgium"=>"32","Belize"=>"501","Benin"=>"229","Bhutan"=>"975","Bolivia"=>"591",
    "Bosnia and Herzegovina"=>"387","Botswana"=>"267","Brazil"=>"55","Brunei"=>"673","Bulgaria"=>"359","Burkina Faso"=>"226","Burundi"=>"257","Cambodia"=>"855","Cameroon"=>"237","Canada"=>"1",
    "Chad"=>"235","Chile"=>"56","China"=>"86","Colombia"=>"57","Comoros"=>"269","Congo"=>"242","Costa Rica"=>"506","Croatia"=>"385","Cuba"=>"53","Cyprus"=>"357",
    "Czech Republic"=>"420","Denmark"=>"45","Djibouti"=>"253","Dominican Republic"=>"1","Ecuador"=>"593","Egypt"=>"20","El Salvador"=>"503","Eritrea"=>"291","Estonia"=>"372","Eswatini"=>"268",
    "Ethiopia"=>"251","Fiji"=>"679","Finland"=>"358","France"=>"33","Gabon"=>"241","Gambia"=>"220","Georgia"=>"995","Germany"=>"49","Ghana"=>"233","Greece"=>"30",
    "Guatemala"=>"502","Guinea"=>"224","Guyana"=>"592","Haiti"=>"509","Honduras"=>"504","Hungary"=>"36","Iceland"=>"354","India"=>"91","Indonesia"=>"62","Iran"=>"98",
    "Iraq"=>"964","Ireland"=>"353","Israel"=>"972","Italy"=>"39","Ivory Coast"=>"225","Jamaica"=>"1","Japan"=>"81","Jordan"=>"962","Kazakhstan"=>"7","Kenya"=>"254",
    "Kuwait"=>"965","Kyrgyzstan"=>"996","Laos"=>"856","Latvia"=>"371","Lebanon"=>"961","Lesotho"=>"266","Liberia"=>"231","Libya"=>"218","Liechtenstein"=>"423","Lithuania"=>"370",
    "Luxembourg"=>"352","Madagascar"=>"261","Malawi"=>"265","Malaysia"=>"60","Maldives"=>"960","Mali"=>"223","Malta"=>"356","Mauritania"=>"222","Mauritius"=>"230","Mexico"=>"52",
    "Moldova"=>"373","Monaco"=>"377","Mongolia"=>"976","Montenegro"=>"382","Morocco"=>"212","Mozambique"=>"258","Myanmar"=>"95","Namibia"=>"264","Nepal"=>"977","Netherlands"=>"31",
    "New Zealand"=>"64","Nicaragua"=>"505","Niger"=>"227","Nigeria"=>"234","North Korea"=>"850","North Macedonia"=>"389","Norway"=>"47","Oman"=>"968","Pakistan"=>"92","Palestine"=>"970",
    "Panama"=>"507","Papua New Guinea"=>"675","Paraguay"=>"595","Peru"=>"51","Philippines"=>"63","Poland"=>"48","Portugal"=>"351","Qatar"=>"974","Romania"=>"40","Russia"=>"7",
    "Rwanda"=>"250","Saudi Arabia"=>"966","Senegal"=>"221","Serbia"=>"381","Sierra Leone"=>"232","Singapore"=>"65","Slovakia"=>"421","Slovenia"=>"386","Somalia"=>"252","South Africa"=>"27",
    "South Korea"=>"82","South Sudan"=>"211","Spain"=>"34","Sri Lanka"=>"94","Sudan"=>"249","Suriname"=>"597","Sweden"=>"46","Switzerland"=>"41","Syria"=>"963","Taiwan"=>"886",
    "Tajikistan"=>"992","Tanzania"=>"255","Thailand"=>"66","Togo"=>"228","Tunisia"=>"216","Turkey"=>"90","Turkmenistan"=>"993","Uganda"=>"256","Ukraine"=>"380","United Arab Emirates"=>"971",
    "United Kingdom"=>"44","United States"=>"1","Uruguay"=>"598","Uzbekistan"=>"998","Venezuela"=>"58","Vietnam"=>"84","Yemen"=>"967","Zambia"=>"260","Zimbabwe"=>"263",
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
    if ($old['country'] === '') $errors[] = 'Please select your country.';

    // NEW: phone number is validated against the selected country's
    // dial code + a sane digit-count range -- this is what catches
    // things like a missing country code or a number that's clearly
    // too short/long to be real, instead of accepting anything typed.
    $phone_digits = preg_replace('/\D/', '', $old['phone']);
    $dial = $dial_codes[$old['country']] ?? null;
    if ($old['phone'] === '') {
        $errors[] = 'Phone number is required.';
    } elseif ($dial && strpos($phone_digits, $dial) !== 0) {
        $errors[] = "Please enter a valid number for {$old['country']} (should start with +$dial).";
    } elseif (strlen($phone_digits) < 8 || strlen($phone_digits) > 15) {
        $errors[] = 'Please enter a valid phone number.';
    }

    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

    // NEW: ID/passport format check, aware of both the id type AND the
    // selected country -- a Pakistani National ID Card must look like
    // a CNIC (13 digits); any Passport must look like a passport
    // number; anything else gets a general sanity check so obviously
    // fake input (e.g. "111", "asdf") is rejected without trying to
    // validate every country's own ID format precisely.
    if ($old['id_number'] === '') {
        $errors[] = ($old['id_type'] === 'passport' ? 'Passport number' : 'ID card number') . ' is required.';
    } else {
        $id_clean = strtoupper(trim($old['id_number']));
        if ($old['id_type'] === 'passport') {
            if (!preg_match('/^[A-Z0-9]{6,9}$/', $id_clean)) {
                $errors[] = 'Please enter a valid passport number (6-9 letters/numbers, no spaces or symbols).';
            }
        } elseif ($old['country'] === 'Pakistan') {
            $cnic_digits = preg_replace('/\D/', '', $old['id_number']);
            if (!preg_match('/^\d{13}$/', $cnic_digits)) {
                $errors[] = 'Please enter a valid 13-digit CNIC number (e.g. 12345-1234567-1).';
            } else {
                $old['id_number'] = substr($cnic_digits, 0, 5) . '-' . substr($cnic_digits, 5, 7) . '-' . substr($cnic_digits, 12, 1);
            }
        } else {
            if (!preg_match('/^[A-Z0-9\-]{5,20}$/', $id_clean)) {
                $errors[] = 'Please enter a valid ID card number.';
            }
        }
    }

    // NEW: the same ID number should not already belong to a DIFFERENT
    // customer account -- catches accidental typos of someone else's
    // ID as well as attempts to reuse another person's identity. The
    // same customer reusing their own ID across their own bookings is
    // completely normal and still allowed.
    if (empty($errors) && $old['id_number'] !== '') {
        $stmt = $pdo->prepare("SELECT user_id FROM bookings WHERE id_number = ? AND user_id != ? LIMIT 1");
        $stmt->execute([$old['id_number'], $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $errors[] = 'This ID/passport number is already associated with a different account. Please double-check what you entered.';
        }
    }

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
        body { font-family: 'Inter', sans-serif; background: #faf7f1; min-height: 100vh; color: #2b2620; }
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #faf7f1; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }

        .wrap { max-width: 640px; margin: 0 auto; padding: 40px 20px 80px; }
        .logo { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 800; text-align: center; margin-bottom: 8px; }
        .logo span { color: #d4af37; }

        .steps { display: flex; justify-content: center; gap: 10px; margin: 24px 0 36px; }
        .step { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: rgba(43,38,32,0.5); }
        .step .num { width: 24px; height: 24px; border-radius: 50%; background: rgba(43,38,32,0.05); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; }
        .step.active { color: #d4af37; }
        .step.active .num { background: #d4af37; color: #201a0d; font-weight: 700; }
        .step.done .num { background: rgba(212,175,55,0.2); color: #d4af37; }
        .step-sep { width: 24px; height: 1px; background: rgba(43,38,32,0.08); align-self: center; }

        .card { background: rgba(43,38,32,0.03); border: 1px solid rgba(43,38,32,0.06); border-radius: 18px; padding: 32px; }
        .card h2 { font-family: 'Playfair Display', serif; font-size: 22px; margin-bottom: 6px; }
        .card .sub { color: rgba(43,38,32,0.6); font-size: 13px; margin-bottom: 26px; }

        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 12.5px; color: rgba(43,38,32,0.7); margin-bottom: 7px; font-weight: 500; }
        .field input[type="text"], .field input[type="email"], .field input[type="tel"] {
            width: 100%; padding: 13px 15px; font-size: 14px; border: 1px solid rgba(43,38,32,0.08);
            background: rgba(43,38,32,0.03); border-radius: 10px; color: #2b2620; font-family: inherit;
        }
        .field input:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 3px rgba(212,175,55,0.08); }

        /* NEW: phone dial-code prefix + validation hint */
        .phone-row { display: flex; gap: 8px; }
        .phone-dial { flex-shrink: 0; padding: 13px 12px; font-size: 14px; font-weight: 600; color: rgba(43,38,32,0.5);
            background: rgba(43,38,32,0.05); border: 1px solid rgba(43,38,32,0.08); border-radius: 10px; min-width: 52px; text-align: center; }
        .phone-row input { flex: 1; }
        .field-hint { font-size: 11.5px; margin-top: 6px; min-height: 14px; }
        .field-hint.error { color: #dc2626; }
        .field-hint.ok { color: #16a34a; }

        .country-wrap { position: relative; }
        .country-input { width: 100%; padding: 13px 15px; font-size: 14px; border: 1px solid rgba(43,38,32,0.08);
            background: rgba(43,38,32,0.03); border-radius: 10px; color: #2b2620; font-family: inherit; cursor: pointer; }
        .country-list { position: absolute; top: calc(100% + 6px); left: 0; right: 0; max-height: 240px; overflow-y: auto;
            background: #fffdfa; border: 1px solid rgba(43,38,32,0.1); border-radius: 10px; z-index: 20; display: none;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4); }
        .country-list.open { display: block; }
        .country-search { width: 100%; padding: 11px 14px; border: none; border-bottom: 1px solid rgba(43,38,32,0.08);
            background: transparent; color: #2b2620; font-family: inherit; font-size: 13.5px; }
        .country-search:focus { outline: none; }
        .country-item { padding: 10px 14px; font-size: 13.5px; cursor: pointer; color: rgba(43,38,32,0.9); }
        .country-item:hover, .country-item.hl { background: rgba(212,175,55,0.1); color: #d4af37; }
        .country-empty { padding: 12px 14px; font-size: 13px; color: rgba(43,38,32,0.5); }

        .id-type-row { display: flex; gap: 20px; margin-bottom: 14px; }
        .id-type-row label { display: flex; align-items: center; gap: 7px; font-size: 13.5px; color: rgba(43,38,32,0.9); cursor: pointer; font-weight: 400; }
        .id-type-row input[type="radio"] { accent-color: #d4af37; width: 15px; height: 15px; }

        .error-message { background: rgba(239,68,68,0.07); color: #f87171; padding: 13px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 20px; border: 1px solid rgba(239,68,68,0.1); }
        .error-message ul { margin: 0; padding-left: 18px; }

        .btn-continue { width: 100%; padding: 15px; background: #d4af37; color: #201a0d; font-weight: 700; border: none; border-radius: 12px;
            font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 8px; transition: all 0.25s ease;  box-shadow: 0 10px 28px rgba(212,175,55,0.3);}
        .btn-continue:hover { background: #b8922e; }

        .back-link { display: block; text-align: center; margin-top: 18px; color: rgba(43,38,32,0.55); font-size: 12.5px; text-decoration: none; }
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
                    <div class="phone-row">
                        <div class="phone-dial" id="phoneDial">+--</div>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($old['phone']); ?>" placeholder="Select country first" required>
                    </div>
                    <div class="field-hint" id="phoneHint"></div>
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
                    <div class="field-hint" id="idHint"></div>
                </div>

                <button type="submit" class="btn-continue">Continue to Confirmation</button>
            </form>
            <a href="dashboard.php" class="back-link">Cancel and return to dashboard</a>
        </div>
    </div>

<script>
const COUNTRIES = ["Afghanistan","Albania","Algeria","Andorra","Angola","Argentina","Armenia","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan","Bolivia","Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi","Cambodia","Cameroon","Canada","Chad","Chile","China","Colombia","Comoros","Congo","Costa Rica","Croatia","Cuba","Cyprus","Czech Republic","Denmark","Djibouti","Dominican Republic","Ecuador","Egypt","El Salvador","Eritrea","Estonia","Eswatini","Ethiopia","Fiji","Finland","France","Gabon","Gambia","Georgia","Germany","Ghana","Greece","Guatemala","Guinea","Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland","Israel","Italy","Ivory Coast","Jamaica","Japan","Jordan","Kazakhstan","Kenya","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya","Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Mauritania","Mauritius","Mexico","Moldova","Monaco","Mongolia","Montenegro","Morocco","Mozambique","Myanmar","Namibia","Nepal","Netherlands","New Zealand","Nicaragua","Niger","Nigeria","North Korea","North Macedonia","Norway","Oman","Pakistan","Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saudi Arabia","Senegal","Serbia","Sierra Leone","Singapore","Slovakia","Slovenia","Somalia","South Africa","South Korea","South Sudan","Spain","Sri Lanka","Sudan","Suriname","Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand","Togo","Tunisia","Turkey","Turkmenistan","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States","Uruguay","Uzbekistan","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"];

// NEW: mirrors the $dial_codes PHP array -- used to show the +XX
// prefix and give the customer instant feedback before they submit.
const DIAL_CODES = {"Afghanistan":"93","Albania":"355","Algeria":"213","Andorra":"376","Angola":"244","Argentina":"54","Armenia":"374","Australia":"61","Austria":"43","Azerbaijan":"994","Bahamas":"1","Bahrain":"973","Bangladesh":"880","Barbados":"1","Belarus":"375","Belgium":"32","Belize":"501","Benin":"229","Bhutan":"975","Bolivia":"591","Bosnia and Herzegovina":"387","Botswana":"267","Brazil":"55","Brunei":"673","Bulgaria":"359","Burkina Faso":"226","Burundi":"257","Cambodia":"855","Cameroon":"237","Canada":"1","Chad":"235","Chile":"56","China":"86","Colombia":"57","Comoros":"269","Congo":"242","Costa Rica":"506","Croatia":"385","Cuba":"53","Cyprus":"357","Czech Republic":"420","Denmark":"45","Djibouti":"253","Dominican Republic":"1","Ecuador":"593","Egypt":"20","El Salvador":"503","Eritrea":"291","Estonia":"372","Eswatini":"268","Ethiopia":"251","Fiji":"679","Finland":"358","France":"33","Gabon":"241","Gambia":"220","Georgia":"995","Germany":"49","Ghana":"233","Greece":"30","Guatemala":"502","Guinea":"224","Guyana":"592","Haiti":"509","Honduras":"504","Hungary":"36","Iceland":"354","India":"91","Indonesia":"62","Iran":"98","Iraq":"964","Ireland":"353","Israel":"972","Italy":"39","Ivory Coast":"225","Jamaica":"1","Japan":"81","Jordan":"962","Kazakhstan":"7","Kenya":"254","Kuwait":"965","Kyrgyzstan":"996","Laos":"856","Latvia":"371","Lebanon":"961","Lesotho":"266","Liberia":"231","Libya":"218","Liechtenstein":"423","Lithuania":"370","Luxembourg":"352","Madagascar":"261","Malawi":"265","Malaysia":"60","Maldives":"960","Mali":"223","Malta":"356","Mauritania":"222","Mauritius":"230","Mexico":"52","Moldova":"373","Monaco":"377","Mongolia":"976","Montenegro":"382","Morocco":"212","Mozambique":"258","Myanmar":"95","Namibia":"264","Nepal":"977","Netherlands":"31","New Zealand":"64","Nicaragua":"505","Niger":"227","Nigeria":"234","North Korea":"850","North Macedonia":"389","Norway":"47","Oman":"968","Pakistan":"92","Palestine":"970","Panama":"507","Papua New Guinea":"675","Paraguay":"595","Peru":"51","Philippines":"63","Poland":"48","Portugal":"351","Qatar":"974","Romania":"40","Russia":"7","Rwanda":"250","Saudi Arabia":"966","Senegal":"221","Serbia":"381","Sierra Leone":"232","Singapore":"65","Slovakia":"421","Slovenia":"386","Somalia":"252","South Africa":"27","South Korea":"82","South Sudan":"211","Spain":"34","Sri Lanka":"94","Sudan":"249","Suriname":"597","Sweden":"46","Switzerland":"41","Syria":"963","Taiwan":"886","Tajikistan":"992","Tanzania":"255","Thailand":"66","Togo":"228","Tunisia":"216","Turkey":"90","Turkmenistan":"993","Uganda":"256","Ukraine":"380","United Arab Emirates":"971","United Kingdom":"44","United States":"1","Uruguay":"598","Uzbekistan":"998","Venezuela":"58","Vietnam":"84","Yemen":"967","Zambia":"260","Zimbabwe":"263"};

const phoneInput = document.getElementById('phone');
const phoneDial = document.getElementById('phoneDial');
const phoneHint = document.getElementById('phoneHint');
const idInput = document.getElementById('id_number');
const idHint = document.getElementById('idHint');

function updatePhoneDial() {
    const dial = DIAL_CODES[countryValue.value];
    phoneDial.textContent = dial ? '+' + dial : '+--';
}

function validatePhoneField() {
    const dial = DIAL_CODES[countryValue.value];
    const digits = phoneInput.value.replace(/\D/g, '');
    if (!phoneInput.value) { phoneHint.textContent = ''; phoneHint.className = 'field-hint'; return true; }
    if (dial && !digits.startsWith(dial)) {
        phoneHint.textContent = 'Should start with +' + dial + ' for ' + countryValue.value;
        phoneHint.className = 'field-hint error';
        return false;
    }
    if (digits.length < 8 || digits.length > 15) {
        phoneHint.textContent = 'Please enter a valid phone number';
        phoneHint.className = 'field-hint error';
        return false;
    }
    phoneHint.textContent = 'Looks good';
    phoneHint.className = 'field-hint ok';
    return true;
}

function validateIdField() {
    const idType = document.querySelector('input[name="id_type"]:checked')?.value || 'national_id';
    const val = idInput.value.trim().toUpperCase();
    if (!val) { idHint.textContent = ''; idHint.className = 'field-hint'; return true; }

    if (idType === 'passport') {
        if (!/^[A-Z0-9]{6,9}$/.test(val)) {
            idHint.textContent = 'Passport number should be 6-9 letters/numbers';
            idHint.className = 'field-hint error';
            return false;
        }
    } else if (countryValue.value === 'Pakistan') {
        const digits = val.replace(/\D/g, '');
        if (!/^\d{13}$/.test(digits)) {
            idHint.textContent = 'CNIC should be 13 digits (e.g. 12345-1234567-1)';
            idHint.className = 'field-hint error';
            return false;
        }
    } else {
        if (!/^[A-Z0-9\-]{5,20}$/.test(val)) {
            idHint.textContent = 'Please enter a valid ID number';
            idHint.className = 'field-hint error';
            return false;
        }
    }
    idHint.textContent = 'Looks good';
    idHint.className = 'field-hint ok';
    return true;
}

phoneInput.addEventListener('input', validatePhoneField);
idInput.addEventListener('input', validateIdField);
document.querySelectorAll('input[name="id_type"]').forEach(r => r.addEventListener('change', validateIdField));


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
    updatePhoneDial();
    validatePhoneField();
    validateIdField();
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
        return;
    }
    if (!validatePhoneField() || !validateIdField()) {
        e.preventDefault();
        alert('Please fix the highlighted phone/ID fields before continuing.');
    }
});

updatePhoneDial();
</script>
</body>
</html>