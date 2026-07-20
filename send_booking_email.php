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
        $mail->Subject = '🧾 Booking Confirmation - Ahmed Travels (Saudi)';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 0; }
                .container { max-width: 550px; margin: 20px auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
                .header { background: #0a1a2f; padding: 25px; text-align: center; }
                .header h1 { color: #c9a03d; margin: 0; font-size: 26px; }
                .header p { color: white; margin: 5px 0 0; font-size: 14px; }
                .content { padding: 25px; }
                .greeting { font-size: 18px; color: #0a1a2f; margin-bottom: 15px; }
                .booking-box { background: #f8f9fa; border-radius: 12px; padding: 20px; margin: 20px 0; border-left: 4px solid #c9a03d; }
                .booking-box h3 { margin: 0 0 15px; color: #0a1a2f; }
                .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0; }
                .detail-label { font-weight: 600; color: #555; }
                .detail-value { color: #0a1a2f; font-weight: 500; }
                .status-box { background: #fff3cd; border-radius: 10px; padding: 15px; text-align: center; margin: 20px 0; }
                .status-box span { background: #ffc107; color: #0a1a2f; padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 14px; }
                .status-box p { margin: 10px 0 0; color: #856404; }
                .support-box { background: #e8f5e9; border-radius: 10px; padding: 15px; text-align: center; margin: 20px 0; }
                .btn-wa { display: inline-block; background: #25D366; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; margin-top: 10px; }
                .footer { background: #0a1a2f; padding: 20px; text-align: center; color: #aaa; font-size: 12px; }
                .footer a { color: #c9a03d; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ahmed Travels</h1>
                    <p>Saudi Arabia | Your Trusted Travel Partner</p>
                </div>
                
                <div class='content'>
                    <div class='greeting'>
                        Dear <strong>" . htmlspecialchars($customer_name) . "</strong>,
                    </div>
                    
                    <p>Thank you for choosing <strong>Ahmed Travels</strong> for your travel needs!</p>
                    
                    <div class='booking-box'>
                        <h3>📋 Booking Details</h3>
                        <div class='detail-row'>
                            <span class='detail-label'>Booking ID:</span>
                            <span class='detail-value'><strong>" . htmlspecialchars($booking_no) . "</strong></span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Service Type:</span>
                            <span class='detail-value'>" . ucfirst(htmlspecialchars($service_type)) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Route / Location:</span>
                            <span class='detail-value'>" . htmlspecialchars($from_location) . " → " . htmlspecialchars($to_location) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Travel Date:</span>
                            <span class='detail-value'>" . htmlspecialchars($travel_date) . "</span>
                        </div>
                        " . ($travel_time ? "
                        <div class='detail-row'>
                            <span class='detail-label'>Travel Time:</span>
                            <span class='detail-value'>" . htmlspecialchars($travel_time) . "</span>
                        </div>" : "") . "
                        <div class='detail-row'>
                            <span class='detail-label'>Number of Guests:</span>
                            <span class='detail-value'>" . htmlspecialchars($guests) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Total Amount:</span>
                            <span class='detail-value' style='color: #c9a03d; font-size: 18px;'>SAR " . number_format($total_amount) . "</span>
                        </div>
                    </div>
                    
                    <div class='status-box'>
                        <span>⏳ PENDING</span>
                        <p>Your booking is currently <strong>PENDING</strong>. You will receive final confirmation within <strong>30 minutes</strong>.</p>
                    </div>
                    
                    <div class='support-box'>
                        <strong>📞 Need Immediate Assistance?</strong><br>
                        <a href='https://wa.me/923001234567' class='btn-wa' target='_blank'>
                            📱 Chat on WhatsApp
                        </a>
                        <p style='margin-top: 10px; font-size: 12px;'>+92 300 1234567 | +92 321 7654321</p>
                    </div>
                    
                    <p style='margin-top: 20px; font-size: 13px; color: #666;'>
                        Our team is reviewing your booking. Once approved, you will receive a confirmation email.
                    </p>
                </div>
                
                <div class='footer'>
                    <p>Ahmed Travels | Saudi Arabia</p>
                    <p>📧 info@ahmedtravels.com | 🌐 www.ahmedtravels.com</p>
                    <p>© 2026 Ahmed Travels. All rights reserved.</p>
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