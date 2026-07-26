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
        
        if($new_status == 'confirmed') {
            $mail->Subject = '✅ Booking Confirmed - Ahmed Travels';
            $status_color = '#28a745';
            $status_text = 'CONFIRMED';
            $status_message = 'Your booking has been confirmed! We look forward to serving you.';
        } else {
            $mail->Subject = '❌ Booking Cancelled - Ahmed Travels';
            $status_color = '#dc3545';
            $status_text = 'CANCELLED';
            $status_message = 'Your booking has been cancelled. Please contact support for more information.';
        }
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; }
                .container { max-width: 550px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
                .header { background: #1a2a3a; padding: 25px; text-align: center; }
                .header h1 { color: #e8a87c; margin: 0; font-size: 26px; }
                .header p { color: white; margin: 5px 0 0; }
                .content { padding: 25px; }
                .status-badge { background: $status_color; color: white; padding: 8px 20px; border-radius: 50px; display: inline-block; font-weight: bold; }
                .booking-box { background: #f8f9fa; border-radius: 12px; padding: 20px; margin: 20px 0; }
                .btn-wa { background: #25D366; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; display: inline-block; }
                .footer { background: #f5f5f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ahmed Travels</h1>
                    <p>Saudi Arabia</p>
                </div>
                <div class='content'>
                    <h2>Dear " . htmlspecialchars($customer_name) . ",</h2>
                    <div style='text-align: center; margin: 20px 0;'>
                        <span class='status-badge'>$status_text</span>
                    </div>
                    <p>$status_message</p>
                    <div class='booking-box'>
                        <h3>📋 Booking Details</h3>
                        <p><strong>Booking ID:</strong> " . htmlspecialchars($booking_no) . "</p>
                        <p><strong>Service:</strong> " . ucfirst(htmlspecialchars($service_type)) . "</p>
                        <p><strong>Travel Date:</strong> " . htmlspecialchars($travel_date) . "</p>
                        <p><strong>Total Amount:</strong> SAR " . number_format($total_amount) . "</p>
                        <p><strong>Status:</strong> <span style='color: $status_color; font-weight: bold;'>$status_text</span></p>
                    </div>
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='https://wa.me/923001234567' class='btn-wa' target='_blank'>📱 Need Help? Chat on WhatsApp</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>Ahmed Travels | 📞 +92 300 1234567 | 📧 info@ahmedtravels.com</p>
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