<?php
require_once __DIR__ . '/secrets.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';
 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
 
function sendStatusEmail($to_email, $customer_name, $booking_no, $service_type, $travel_date, $total_amount, $new_status) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $customer_name);
        
        $mail->isHTML(true);

        // 🔴 FIX: this was checking for 'confirmed', a status that no
        // longer exists since the site moved to a simplified 3-status
        // model (pending / completed / cancelled) -- this email would
        // never have taken the "confirmed" branch for a real booking.
        // Checking 'completed' now matches what verify_payment.php
        // actually sets.
        if ($new_status == 'completed') {
            $mail->Subject = 'Booking Confirmed - Ahmed Travels';
            $status_color = '#2e9e6a';
            $status_text = 'CONFIRMED';
            $status_message = 'Great news -- your payment has been verified and your booking is confirmed. We look forward to serving you.';
        } else {
            $mail->Subject = 'Booking Cancelled - Ahmed Travels';
            $status_color = '#dc3545';
            $status_text = 'CANCELLED';
            $status_message = 'Your booking has been cancelled. If you believe this is a mistake, please contact customer support.';
        }

        $travel_date_display = ($travel_date && strtotime($travel_date) > strtotime('1970-01-02'))
            ? htmlspecialchars($travel_date) : 'To be confirmed';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f1ea; margin: 0; padding: 0; }
                .container { max-width: 560px; margin: 20px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(60,45,20,0.1); }
                .header { background: linear-gradient(135deg, #2b2416, #4a3d22); padding: 30px; text-align: center; }
                .header h1 { color: #d4af37; margin: 0; font-size: 24px; font-family: Georgia, serif; }
                .header p { color: rgba(255,255,255,0.7); margin: 6px 0 0; font-size: 13px; letter-spacing: 0.5px; }
                .content { padding: 28px; }
                .greeting { font-size: 16px; color: #2b2620; margin-bottom: 6px; }
                .status-badge { background: $status_color; color: white; padding: 8px 22px; border-radius: 50px; display: inline-block; font-weight: 700; font-size: 13px; letter-spacing: 0.5px; }
                .booking-box { background: #faf7f1; border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid #ece4d4; }
                .booking-box h3 { margin: 0 0 14px; color: #2b2620; font-size: 14px; font-family: Georgia, serif; }
                .detail-row { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #ece4d4; font-size: 13.5px; }
                .detail-row:last-child { border-bottom: none; }
                .detail-label { color: rgba(43,38,32,0.55); }
                .detail-value { color: #2b2620; font-weight: 600; text-align: right; }
                .btn-wa { background: #25D366; color: white; padding: 11px 26px; text-decoration: none; border-radius: 25px; display: inline-block; font-weight: 600; font-size: 13px; }
                .contact-line { margin-top: 10px; font-size: 12px; color: rgba(43,38,32,0.5); }
                .footer { background: #2b2416; padding: 22px; text-align: center; color: rgba(255,255,255,0.5); font-size: 11.5px; line-height: 1.8; }
                .footer a { color: #d4af37; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ahmed Travels</h1>
                    <p>SAUDI ARABIA</p>
                </div>
                <div class='content'>
                    <div class='greeting'>Dear " . htmlspecialchars($customer_name) . ",</div>
                    <div style='text-align: center; margin: 18px 0;'>
                        <span class='status-badge'>$status_text</span>
                    </div>
                    <p style='color: rgba(43,38,32,0.75); font-size: 13.5px; line-height: 1.6; text-align: center;'>$status_message</p>
                    <div class='booking-box'>
                        <h3>Booking Details</h3>
                        <div class='detail-row'><span class='detail-label'>Booking ID</span><span class='detail-value'>" . htmlspecialchars($booking_no) . "</span></div>
                        <div class='detail-row'><span class='detail-label'>Service</span><span class='detail-value'>" . ucfirst(htmlspecialchars($service_type)) . "</span></div>
                        <div class='detail-row'><span class='detail-label'>Travel Date</span><span class='detail-value'>$travel_date_display</span></div>
                        <div class='detail-row'><span class='detail-label'>Total Amount</span><span class='detail-value' style='color:#b8912f; font-size:15px;'>SAR " . number_format($total_amount) . "</span></div>
                        <div class='detail-row'><span class='detail-label'>Status</span><span class='detail-value' style='color: $status_color;'>$status_text</span></div>
                    </div>
                    <div style='text-align: center; margin: 22px 0;'>
                        <a href='https://wa.me/923134830023' class='btn-wa' target='_blank'>Need Help? Chat on WhatsApp</a>
                        <div class='contact-line'>+966 51 036 1841 &bull; ahmedtvl606@gmail.com</div>
                    </div>
                </div>
                <div class='footer'>
                    <p>Ahmed Travels &bull; Saudi Arabia</p>
                    <p><a href='mailto:ahmedtvl606@gmail.com'>ahmedtvl606@gmail.com</a></p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}
?>