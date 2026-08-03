<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$booking_no = $_GET['booking_no'] ?? '';
$hotel_name = $_GET['hotel'] ?? '';
$room_name = $_GET['room'] ?? '';
$check_in = $_GET['check_in'] ?? '';
$check_out = $_GET['check_out'] ?? '';
$total = $_GET['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; overflow-x: hidden; }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }
        html { scrollbar-color: #8a6d1f #0a0f1e; scrollbar-width: thin; }

        .bg-ambient { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; background: #0a0f1e; }
        .bg-ambient::before {
            content: ''; position: absolute; inset: -20%;
            background: radial-gradient(circle at 50% 30%, rgba(16,185,129,0.10), transparent 45%),
                        radial-gradient(circle at 20% 80%, rgba(212,175,55,0.08), transparent 40%);
            animation: driftGlow 22s ease-in-out infinite alternate;
        }
        @keyframes driftGlow { 0% { transform: translate(0,0) scale(1); } 100% { transform: translate(2%,-2%) scale(1.05); } }
        .grain-overlay {
            position: fixed; inset: 0; z-index: 9997; pointer-events: none; opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        .success-card {
            position: relative; z-index: 1;
            background: rgba(255,255,255,0.04); backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.05); border-radius: 24px;
            padding: 48px 40px; max-width: 500px; width: 100%; text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            opacity: 0; transform: translateY(20px); animation: cardIn 0.6s cubic-bezier(.2,.8,.3,1) forwards;
        }
        @keyframes cardIn { to { opacity: 1; transform: translateY(0); } }

        .check-wrap { width: 84px; height: 84px; margin: 0 auto 24px; border-radius: 50%; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); display: flex; align-items: center; justify-content: center; opacity: 0; transform: scale(0.5); animation: popIn 0.5s cubic-bezier(.34,1.56,.64,1) forwards; animation-delay: 0.2s; }
        .check-wrap i { font-size: 36px; color: #34d399; }
        @keyframes popIn { to { opacity: 1; transform: scale(1); } }

        .success-card h2 { font-family: 'Playfair Display', serif; color: white; font-size: 26px; margin-bottom: 8px; }
        .success-card > p { color: rgba(255,255,255,0.5); font-size: 14px; margin-bottom: 26px; }

        .detail-box { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; padding: 18px 20px; text-align: left; margin-bottom: 26px; }
        .detail-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 13.5px; border-bottom: 1px solid rgba(255,255,255,0.04); gap: 12px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-row span:first-child { color: rgba(255,255,255,0.4); flex-shrink: 0; }
        .detail-row span:last-child { color: white; font-weight: 600; text-align: right; }
        .detail-row .amt { color: #d4af37; font-size: 15px; }

        .note { font-size: 12.5px; color: rgba(255,255,255,0.35); margin-bottom: 24px; display: flex; align-items: center; justify-content: center; gap: 6px; }

        .btn-row { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-primary, .btn-secondary { flex: 1; padding: 13px; border-radius: 12px; font-weight: 600; font-size: 14px; text-decoration: none; text-align: center; transition: all 0.3s ease; }
        .btn-primary { background: #d4af37; color: #0a0f1e; }
        .btn-primary:hover { background: #b8922e; transform: translateY(-2px); box-shadow: 0 10px 25px rgba(212,175,55,0.2); }
        .btn-secondary { background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.8); border: 1px solid rgba(255,255,255,0.06); }
        .btn-secondary:hover { background: rgba(255,255,255,0.08); transform: translateY(-2px); }

        /* Download-as-PDF button -- generates a real PDF client-side via
           jsPDF, no browser print dialog involved. */
        .btn-download {
            position: relative; overflow: hidden;
            width: 100%; padding: 13px; border-radius: 12px; font-weight: 600; font-size: 13.5px;
            background: rgba(212,175,55,0.08); color: #d4af37; border: 1px solid rgba(212,175,55,0.15);
            cursor: pointer; transition: all 0.3s ease; margin-bottom: 14px;
            display: flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit;
        }
        .btn-download:hover { background: #d4af37; color: #0a0f1e; transform: translateY(-2px); box-shadow: 0 10px 25px rgba(212,175,55,0.2); }
        .btn-download:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        @media (max-width: 500px) { .btn-row { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="bg-ambient" aria-hidden="true"></div>
    <div class="grain-overlay" aria-hidden="true"></div>

    <div class="success-card">
        <div class="check-wrap"><i class="fas fa-check"></i></div>
        <h2>Booking Confirmed!</h2>
        <p>Your hotel booking has been successfully confirmed.</p>

        <div class="detail-box">
            <div class="detail-row"><span>Booking ID</span><span><?php echo htmlspecialchars($booking_no); ?></span></div>
            <div class="detail-row"><span>Hotel</span><span><?php echo htmlspecialchars($hotel_name); ?></span></div>
            <div class="detail-row"><span>Room</span><span><?php echo htmlspecialchars($room_name); ?></span></div>
            <div class="detail-row"><span>Check-in</span><span><?php echo htmlspecialchars($check_in); ?></span></div>
            <div class="detail-row"><span>Check-out</span><span><?php echo htmlspecialchars($check_out); ?></span></div>
            <div class="detail-row"><span>Total Amount</span><span class="amt">SAR <?php echo number_format($total); ?></span></div>
        </div>

        <p class="note"><i class="fas fa-envelope"></i> A confirmation email has been sent to your registered email address.</p>

        <button type="button" class="btn-download" id="downloadBtn"><i class="fas fa-file-arrow-down"></i> Download Receipt (PDF)</button>

        <div class="btn-row">
            <a href="dashboard.php" class="btn-primary">Go to Dashboard</a>
            <a href="services.php?type=hotels" class="btn-secondary">Book Another Hotel</a>
        </div>
    </div>

    <script>
        document.getElementById('downloadBtn').addEventListener('click', function() {
            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Generating...';

            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF({ unit: 'pt', format: 'a4' });

                const gold = [212, 175, 55];
                const dark = [10, 15, 30];
                const gray = [110, 110, 110];
                const pageWidth = doc.internal.pageSize.getWidth();
                const marginX = 48;

                const bookingNo = <?php echo json_encode($booking_no); ?>;
                const hotelName = <?php echo json_encode($hotel_name); ?>;
                const roomName = <?php echo json_encode($room_name); ?>;
                const checkIn = <?php echo json_encode($check_in); ?>;
                const checkOut = <?php echo json_encode($check_out); ?>;
                const total = <?php echo json_encode(number_format($total)); ?>;
                const issuedOn = new Date().toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

                // Header band
                doc.setFillColor(...dark);
                doc.rect(0, 0, pageWidth, 110, 'F');
                doc.setTextColor(255, 255, 255);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(24);
                doc.text('Ahmed Travels', marginX, 55);
                doc.setTextColor(...gold);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(10);
                doc.text('Your trusted travel partner', marginX, 74);

                // Title
                let y = 155;
                doc.setTextColor(...dark);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(19);
                doc.text('Hotel Booking Receipt', marginX, y);
                doc.setDrawColor(...gold);
                doc.setLineWidth(2);
                doc.line(marginX, y + 8, marginX + 130, y + 8);

                // Status pill
                doc.setFillColor(220, 252, 231);
                doc.roundedRect(pageWidth - marginX - 100, y - 18, 100, 24, 12, 12, 'F');
                doc.setTextColor(5, 150, 105);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(10);
                doc.text('CONFIRMED', pageWidth - marginX - 50, y - 2, { align: 'center' });

                // Details box
                y += 40;
                const boxHeight = 210;
                doc.setDrawColor(228, 228, 228);
                doc.setFillColor(250, 250, 250);
                doc.roundedRect(marginX, y, pageWidth - marginX * 2, boxHeight, 8, 8, 'FD');

                const rows = [
                    ['Booking ID', bookingNo],
                    ['Hotel', hotelName],
                    ['Room', roomName],
                    ['Check-in', checkIn],
                    ['Check-out', checkOut],
                    ['Issued On', issuedOn],
                ];
                let ry = y + 30;
                doc.setFontSize(11);
                rows.forEach(([label, value]) => {
                    doc.setTextColor(...gray);
                    doc.setFont('helvetica', 'normal');
                    doc.text(label, marginX + 20, ry);
                    doc.setTextColor(...dark);
                    doc.setFont('helvetica', 'bold');
                    const maxWidth = pageWidth - marginX * 2 - 140;
                    const lines = doc.splitTextToSize(String(value), maxWidth);
                    doc.text(lines, pageWidth - marginX - 20, ry, { align: 'right' });
                    ry += 26 * lines.length;
                });

                // Divider before total
                doc.setDrawColor(228, 228, 228);
                doc.line(marginX + 20, ry - 6, pageWidth - marginX - 20, ry - 6);
                ry += 14;
                doc.setTextColor(...gray);
                doc.setFontSize(12);
                doc.setFont('helvetica', 'normal');
                doc.text('Total Amount', marginX + 20, ry);
                doc.setTextColor(...gold);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(16);
                doc.text('SAR ' + total, pageWidth - marginX - 20, ry, { align: 'right' });

                // Footer note
                y = y + boxHeight + 50;
                doc.setTextColor(...gray);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(9.5);
                doc.text('A confirmation email has also been sent to your registered email address.', marginX, y);
                doc.text('For support, reach us on WhatsApp or via your account dashboard.', marginX, y + 14);

                doc.setDrawColor(240, 240, 240);
                doc.line(marginX, y + 34, pageWidth - marginX, y + 34);
                doc.setFontSize(9);
                doc.setTextColor(180, 180, 180);
                doc.text('Ahmed Travels', marginX, y + 52);
                doc.text('This is a system-generated receipt.', pageWidth - marginX, y + 52, { align: 'right' });

                doc.save('AhmedTravels-Receipt-' + bookingNo + '.pdf');
            } catch (err) {
                alert('Could not generate the PDF. Please try again.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    </script>
</body>
</html>