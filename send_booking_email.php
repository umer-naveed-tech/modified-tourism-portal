<?php
require_once __DIR__ . '/secrets.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';
 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
 
function sendBookingEmail($to_email, $customer_name, $booking_no, $service_type, $travel_date, $travel_time, $from_location, $to_location, $guests, $total_amount) {
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
        $mail->Subject = 'Booking Received - Ahmed Travels (Saudi Arabia)';

        // NEW: a real travel_date can come through as the internal
        // "no date set" placeholder (1970-01-01) for services that
        // don't need one -- this keeps that from ever showing up as a
        // confusing date in the email.
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
                .greeting { font-size: 16px; color: #2b2620; margin-bottom: 14px; }
                .booking-box { background: #faf7f1; border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid #ece4d4; }
                .booking-box h3 { margin: 0 0 14px; color: #2b2620; font-size: 14px; font-family: Georgia, serif; }
                .detail-row { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #ece4d4; font-size: 13.5px; }
                .detail-row:last-child { border-bottom: none; }
                .detail-label { color: rgba(43,38,32,0.55); }
                .detail-value { color: #2b2620; font-weight: 600; text-align: right; }
                .status-box { background: #fdf6e3; border: 1px solid rgba(212,175,55,0.3); border-radius: 12px; padding: 18px; text-align: center; margin: 20px 0; }
                .status-box .badge { background: #d4af37; color: #201a0d; padding: 5px 16px; border-radius: 20px; font-weight: 700; font-size: 12.5px; letter-spacing: 0.5px; }
                .status-box p { margin: 12px 0 0; color: rgba(43,38,32,0.7); font-size: 13px; line-height: 1.6; }
                .support-box { background: #faf7f1; border-radius: 10px; padding: 18px; text-align: center; margin: 20px 0; border: 1px solid #ece4d4; }
                .support-box strong { color: #2b2620; font-size: 13.5px; }
                .btn-wa { display: inline-block; background: #25D366; color: white; padding: 11px 26px; text-decoration: none; border-radius: 25px; margin-top: 12px; font-weight: 600; font-size: 13px; }
                .contact-line { margin-top: 12px; font-size: 12px; color: rgba(43,38,32,0.5); }
                .footer { background: #2b2416; padding: 22px; text-align: center; color: rgba(255,255,255,0.5); font-size: 11.5px; line-height: 1.8; }
                .footer a { color: #d4af37; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ahmed Travels</h1>
                    <p>SAUDI ARABIA &bull; YOUR TRUSTED TRAVEL PARTNER</p>
                </div>
                
                <div class='content'>
                    <div class='greeting'>
                        Dear <strong>" . htmlspecialchars($customer_name) . "</strong>,
                    </div>
                    
                    <p style='color: rgba(43,38,32,0.75); font-size: 13.5px; line-height: 1.6;'>Thank you for booking with <strong>Ahmed Travels</strong>. We've received your booking and here are the details:</p>
                    
                    <div class='booking-box'>
                        <h3>Booking Details</h3>
                        <div class='detail-row'>
                            <span class='detail-label'>Booking ID</span>
                            <span class='detail-value'>" . htmlspecialchars($booking_no) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Service Type</span>
                            <span class='detail-value'>" . ucfirst(htmlspecialchars($service_type)) . "</span>
                        </div>
                        " . ($from_location || $to_location ? "
                        <div class='detail-row'>
                            <span class='detail-label'>Route / Location</span>
                            <span class='detail-value'>" . htmlspecialchars($from_location) . ($to_location ? " &rarr; " . htmlspecialchars($to_location) : "") . "</span>
                        </div>" : "") . "
                        <div class='detail-row'>
                            <span class='detail-label'>Travel Date</span>
                            <span class='detail-value'>$travel_date_display</span>
                        </div>
                        " . ($travel_time ? "
                        <div class='detail-row'>
                            <span class='detail-label'>Travel Time</span>
                            <span class='detail-value'>" . htmlspecialchars($travel_time) . "</span>
                        </div>" : "") . "
                        <div class='detail-row'>
                            <span class='detail-label'>Number of Guests</span>
                            <span class='detail-value'>" . htmlspecialchars($guests) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Total Amount</span>
                            <span class='detail-value' style='color: #b8912f; font-size: 16px;'>SAR " . number_format($total_amount) . "</span>
                        </div>
                    </div>
                    
                    <div class='status-box'>
                        <span class='badge'>PENDING</span>
                        <p>Your booking is currently <strong>pending</strong>. Our team reviews and confirms every booking within <strong>24 hours</strong>. You'll receive a confirmation email as soon as it's approved.</p>
                    </div>
                    
                    <div class='support-box'>
                        <strong>Need Immediate Assistance?</strong><br>
                        <span style='font-size: 12.5px; color: rgba(43,38,32,0.6);'>If this is urgent, please reach out to customer support directly.</span><br>
                        <a href='https://wa.me/923134830023' class='btn-wa' target='_blank'>
                            Chat on WhatsApp
                        </a>
                        <div class='contact-line'>+966 51 036 1841 &bull; ahmedtvl606@gmail.com</div>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>Ahmed Travels &bull; Saudi Arabia</p>
                    <p><a href='mailto:ahmedtvl606@gmail.com'>ahmedtvl606@gmail.com</a></p>
                    <p>&copy; 2026 Ahmed Travels. All rights reserved.</p>
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