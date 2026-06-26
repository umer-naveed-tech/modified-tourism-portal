<?php
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendCancellationEmail($to_email, $customer_name, $booking_no, $service_type, $travel_date, $total_amount, $reason) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        // ========== YAHAN APNI DETAILS DALO ==========
        $mail->Username   = 'YOUR_EMAIL@gmail.com';
        $mail->Password   = 'YOUR_16_DIGIT_APP_PASSWORD';
        // =============================================
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('YOUR_EMAIL@gmail.com', 'Ahmed Travels');
        $mail->addAddress($to_email, $customer_name);
        
        $mail->isHTML(true);
        $mail->Subject = '❌ Booking Cancelled - Ahmed Travels';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; }
                .container { max-width: 550px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
                .header { background: #1a2a3a; padding: 25px; text-align: center; }
                .header h1 { color: #e8a87c; margin: 0; font-size: 26px; }
                .content { padding: 25px; }
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
                    <p>Your booking has been <strong style='color: #dc3545;'>CANCELLED</strong> as requested.</p>
                    
                    <div class='booking-box'>
                        <h3>📋 Cancelled Booking Details</h3>
                        <p><strong>Booking ID:</strong> " . htmlspecialchars($booking_no) . "</p>
                        <p><strong>Service:</strong> " . ucfirst(htmlspecialchars($service_type)) . "</p>
                        <p><strong>Travel Date:</strong> " . htmlspecialchars($travel_date) . "</p>
                        <p><strong>Total Amount:</strong> SAR " . number_format($total_amount) . "</p>
                        <p><strong>Cancellation Reason:</strong> " . htmlspecialchars($reason) . "</p>
                    </div>
                    
                    <p>If you did not request this cancellation, please contact us immediately.</p>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='https://wa.me/923001234567' class='btn-wa' target='_blank'>📱 Need Help? Chat on WhatsApp</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>Ahmed Travels | 📞 +92 300 1234567</p>
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