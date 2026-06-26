<?php
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendAdminEmail($type, $booking_no, $customer_name, $customer_email, $service_type, $travel_date, $total_amount, $status = 'pending') {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        // ========== YAHAN APNI DETAILS DALO ==========
        $mail->Username   = 'cabubakar663@gmail.com';      // ← Apni Gmail ID
        $mail->Password   = 'ebds gaci apij ssgv'; // ← App Password
        // =============================================
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('cabubakar663@gmail.com', 'Ahmed Travels');
        $mail->addAddress(ADMIN_EMAIL, 'Admin');
        
        $mail->isHTML(true);
        
        if($type == 'booking') {
            $mail->Subject = '🔔 New Booking Alert - Ahmed Travels';
            $color = '#10b981';
            $action = 'New Booking';
        } else {
            $mail->Subject = '⚠️ Booking Cancelled - Ahmed Travels';
            $color = '#dc2626';
            $action = 'Booking Cancelled';
        }
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; }
                .container { max-width: 550px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
                .header { background: #1a2a3a; padding: 25px; text-align: center; }
                .header h1 { color: #d4af37; margin: 0; font-size: 26px; }
                .header p { color: white; margin: 5px 0 0; }
                .content { padding: 25px; }
                .status-badge { background: $color; color: white; padding: 8px 20px; border-radius: 50px; display: inline-block; font-weight: bold; }
                .booking-box { background: #f8f9fa; border-radius: 12px; padding: 20px; margin: 20px 0; }
                .btn-wa { background: #25D366; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; display: inline-block; }
                .footer { background: #f5f5f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ahmed Travels</h1>
                    <p>Admin Notification</p>
                </div>
                <div class='content'>
                    <div style='text-align: center; margin: 20px 0;'>
                        <span class='status-badge'>$action</span>
                    </div>
                    
                    <div class='booking-box'>
                        <h3>📋 Booking Details</h3>
                        <p><strong>Booking ID:</strong> " . htmlspecialchars($booking_no) . "</p>
                        <p><strong>Customer Name:</strong> " . htmlspecialchars($customer_name) . "</p>
                        <p><strong>Customer Email:</strong> " . htmlspecialchars($customer_email) . "</p>
                        <p><strong>Service:</strong> " . ucfirst(htmlspecialchars($service_type)) . "</p>
                        <p><strong>Travel Date:</strong> " . htmlspecialchars($travel_date) . "</p>
                        <p><strong>Total Amount:</strong> SAR " . number_format($total_amount) . "</p>
                        <p><strong>Status:</strong> <span style='color: $color; font-weight: bold;'>" . strtoupper($status) . "</span></p>
                    </div>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='http://localhost/tourism-portal/agent_dashboard.php' class='btn-wa' target='_blank'>
                            📊 Go to Admin Dashboard
                        </a>
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