<?php
// booking_payment.php
//
// STEP 4 (final) of the new booking flow. Shows the agent's bank
// account details and lets the customer upload proof of their manual
// transfer (screenshot + payment reference/transaction ID + payer
// name). This creates a row in the new `payments` table -- it does
// NOT mark the booking as confirmed; an agent still reviews the proof
// and confirms manually, same as every other booking on this site.

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

// ---- Agent bank details shown to every customer on this step ----
define('AGENT_BANK_NAME', 'Muhammad Umer Naveed');
define('AGENT_BANK_PROVIDER', 'Easypaisa');
define('AGENT_BANK_ACCOUNT', '03706260350');

$booking_id = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
if (!$booking_id) {
    header('Location: dashboard.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: dashboard.php');
    exit();
}

if (empty($booking['customer_name'])) {
    header('Location: booking_details.php?booking_id=' . $booking_id);
    exit();
}

// NEW: if an agent rejected the payment, the booking is cancelled
// automatically -- there's nothing to pay for anymore on THIS
// booking, so show a clear "cancelled" state with the reason and a
// way to start a fresh booking, instead of any payment form.
if ($booking['status'] === 'cancelled') {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$booking_id]);
    $rejected_payment = $stmt->fetch(PDO::FETCH_ASSOC);
    $cancel_reason = ($rejected_payment && $rejected_payment['status'] === 'rejected') ? $rejected_payment['rejection_reason'] : null;
    include 'booking_cancelled_view.php';
    exit();
}

// If a payment proof was already submitted for this booking, show the
// "awaiting confirmation" state instead of a fresh upload form.
$stmt = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$booking_id]);
$existing_payment = $stmt->fetch(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing_payment) {
    csrf_verify();

    $payment_reference = trim($_POST['payment_reference'] ?? '');
    $payer_name = trim($_POST['payer_name'] ?? '');

    if ($payment_reference === '') $errors[] = 'Please enter the Payment ID / transaction reference.';
    if ($payer_name === '') $errors[] = 'Please enter the name the payment was sent from.';

    $screenshot_path = null;
    if (empty($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please upload a screenshot of your payment.';
    } else {
        $file = $_FILES['screenshot'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset($allowed[$mime])) {
            $errors[] = 'Screenshot must be a JPG, PNG, or WEBP image.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Screenshot must be under 5 MB.';
        } else {
            $upload_dir = __DIR__ . '/uploads/payment_proofs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $filename = 'PAY-' . preg_replace('/[^A-Za-z0-9-]/', '', $booking['booking_no']) . '-' . time() . '.' . $allowed[$mime];
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $screenshot_path = 'uploads/payment_proofs/' . $filename;
            } else {
                $errors[] = 'Could not save the uploaded screenshot. Please try again.';
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO payments (booking_id, payment_reference, payer_name, screenshot_path, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$booking_id, $payment_reference, $payer_name, $screenshot_path]);

        $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'submitted' WHERE id = ?");
        $stmt->execute([$booking_id]);

        header('Location: booking_payment.php?booking_id=' . $booking_id);
        exit();
    }
}

// Re-fetch in case a payment was just submitted above.
$stmt = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$booking_id]);
$existing_payment = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment | Ahmed Travels</title>
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

        .bank-box { background: rgba(212,175,55,0.06); border: 1px solid rgba(212,175,55,0.15); border-radius: 14px; padding: 22px 24px; margin-bottom: 26px; }
        .bank-box h3 { font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #d4af37; margin-bottom: 14px; }
        .bank-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; border-bottom: 1px solid rgba(212,175,55,0.08); }
        .bank-row:last-child { border-bottom: none; }
        .bank-row span:first-child { color: rgba(255,255,255,0.5); }
        .bank-row span:last-child { color: white; font-weight: 700; }
        .bank-note { margin-top: 14px; font-size: 12.5px; color: rgba(255,255,255,0.45); line-height: 1.6; }

        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 12.5px; color: rgba(255,255,255,0.5); margin-bottom: 7px; font-weight: 500; }
        .field input[type="text"] {
            width: 100%; padding: 13px 15px; font-size: 14px; border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03); border-radius: 10px; color: white; font-family: inherit;
        }
        .field input:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 3px rgba(212,175,55,0.08); }

        .file-drop { border: 1px dashed rgba(255,255,255,0.15); border-radius: 12px; padding: 26px; text-align: center; cursor: pointer; transition: all 0.25s ease; }
        .file-drop:hover { border-color: rgba(212,175,55,0.4); background: rgba(212,175,55,0.03); }
        .file-drop input { display: none; }
        .file-drop .icon { font-size: 26px; color: #d4af37; margin-bottom: 8px; }
        .file-drop .txt { font-size: 13px; color: rgba(255,255,255,0.5); }
        .file-drop .fname { font-size: 13px; color: #34d399; margin-top: 8px; font-weight: 600; }

        .error-message { background: rgba(239,68,68,0.07); color: #f87171; padding: 13px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 20px; border: 1px solid rgba(239,68,68,0.1); }
        .error-message ul { margin: 0; padding-left: 18px; }

        .btn-submit { width: 100%; padding: 15px; background: #d4af37; color: #0a0f1e; border: none; border-radius: 12px;
            font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 8px; transition: all 0.25s ease; }
        .btn-submit:hover { background: #b8922e; }

        .status-box { text-align: center; padding: 30px 10px; }
        .status-box .check-wrap { width: 70px; height: 70px; margin: 0 auto 18px; border-radius: 50%; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); display: flex; align-items: center; justify-content: center; }
        .status-box .check-wrap i { font-size: 28px; color: #34d399; }
        .status-box h3 { font-family: 'Playfair Display', serif; font-size: 20px; margin-bottom: 10px; }
        .status-box p { color: rgba(255,255,255,0.5); font-size: 13.5px; line-height: 1.7; max-width: 420px; margin: 0 auto 20px; }
        .status-box a { color: #d4af37; text-decoration: none; font-weight: 600; font-size: 13.5px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="logo">Ahmed<span>Travels</span></div>

        <div class="steps">
            <div class="step done"><div class="num"><i class="fas fa-check" style="font-size:10px;"></i></div>Service</div>
            <div class="step-sep"></div>
            <div class="step done"><div class="num"><i class="fas fa-check" style="font-size:10px;"></i></div>Details</div>
            <div class="step-sep"></div>
            <div class="step done"><div class="num"><i class="fas fa-check" style="font-size:10px;"></i></div>Confirm</div>
            <div class="step-sep"></div>
            <div class="step active"><div class="num">4</div>Payment</div>
        </div>

        <div class="card">
            <?php if ($existing_payment): ?>
                <div class="status-box">
                    <div class="check-wrap"><i class="fas fa-clock"></i></div>
                    <h3>Payment Submitted -- Awaiting Confirmation</h3>
                    <p>Thank you. We have received your payment details for booking <strong><?php echo htmlspecialchars($booking['booking_no']); ?></strong>. Our team will verify your payment and confirm your booking shortly.</p>
                    <a href="dashboard.php">Go to My Bookings</a>
                </div>
            <?php else: ?>
                <h2>Complete Your Payment</h2>
                <p class="sub">Please send SAR <?php echo number_format($booking['total_amount']); ?> to the account below, then upload your payment proof.</p>

                <div class="bank-box">
                    <h3>Payment Details</h3>
                    <div class="bank-row"><span>Account Name</span><span><?php echo htmlspecialchars(AGENT_BANK_NAME); ?></span></div>
                    <div class="bank-row"><span>Provider</span><span><?php echo htmlspecialchars(AGENT_BANK_PROVIDER); ?></span></div>
                    <div class="bank-row"><span>Account Number</span><span><?php echo htmlspecialchars(AGENT_BANK_ACCOUNT); ?></span></div>
                    <div class="bank-row"><span>Amount</span><span>SAR <?php echo number_format($booking['total_amount']); ?></span></div>
                    <div class="bank-note">Please make your transfer manually using the details above, then fill in the form below with your payment proof. Your booking will be confirmed by our team once payment is verified.</div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <ul>
                            <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">

                    <div class="field">
                        <label for="payer_name">Payment Sent From (Name)</label>
                        <input type="text" id="payer_name" name="payer_name" placeholder="Name on the sending account" value="<?php echo htmlspecialchars($_POST['payer_name'] ?? ''); ?>" required>
                    </div>

                    <div class="field">
                        <label for="payment_reference">Payment ID / Transaction Reference</label>
                        <input type="text" id="payment_reference" name="payment_reference" placeholder="e.g. transaction/reference number" value="<?php echo htmlspecialchars($_POST['payment_reference'] ?? ''); ?>" required>
                    </div>

                    <div class="field">
                        <label>Payment Screenshot</label>
                        <label class="file-drop" id="fileDrop">
                            <input type="file" name="screenshot" id="screenshotInput" accept="image/jpeg,image/png,image/webp" required>
                            <div class="icon"><i class="fas fa-cloud-arrow-up"></i></div>
                            <div class="txt">Click to upload a screenshot of your payment (JPG, PNG, WEBP -- max 5 MB)</div>
                            <div class="fname" id="fileName"></div>
                        </label>
                    </div>

                    <button type="submit" class="btn-submit">Submit Payment Proof</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

<script>
const screenshotInput = document.getElementById('screenshotInput');
const fileName = document.getElementById('fileName');
if (screenshotInput) {
    screenshotInput.addEventListener('change', function() {
        fileName.textContent = this.files.length ? this.files[0].name : '';
    });
}
</script>
</body>
</html>