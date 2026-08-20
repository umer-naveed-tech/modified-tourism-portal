<?php
require_once __DIR__ . '/secrets.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';
 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
 
function sendAdminEmail($type, $booking_no, $customer_name, $customer_email, $service_type, $travel_date, $total_amount, $status = 'pending') {
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
        $mail->addAddress(ADMIN_EMAIL, 'Admin');
        
        $mail->isHTML(true);
        
        if($type == 'booking') {
            $mail->Subject = 'New Booking Confirmed - Ahmed Travels';
            $color = '#2e9e6a';
            $action = 'NEW CONFIRMED BOOKING';
        } else {
            $mail->Subject = 'Booking Cancelled - Ahmed Travels';
            $color = '#dc2626';
            $action = 'BOOKING CANCELLED';
        }

        $travel_date_display = ($travel_date && strtotime($travel_date) > strtotime('1970-01-02'))
            ? htmlspecialchars($travel_date) : 'To be confirmed';

        // NEW: relative link instead of a hardcoded localhost URL --
        // works correctly no matter which domain this ends up live on.
        $dashboard_url = (isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : '') . '/agent_dashboard.php';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f1ea; margin: 0; padding: 0; }
                .container { max-width: 560px; margin: 20px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(60,45,20,0.1); }
                .header { background: linear-gradient(135deg, #2b2416, #4a3d22); padding: 26px; text-align: center; }
                .header h1 { color: #d4af37; margin: 0; font-size: 22px; font-family: Georgia, serif; }
                .header p { color: rgba(255,255,255,0.65); margin: 5px 0 0; font-size: 12.5px; letter-spacing: 0.5px; }
                .content { padding: 26px; }
                .status-badge { background: $color; color: white; padding: 7px 20px; border-radius: 50px; display: inline-block; font-weight: 700; font-size: 12.5px; letter-spacing: 0.5px; }
                .booking-box { background: #faf7f1; border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid #ece4d4; }
                .booking-box h3 { margin: 0 0 14px; color: #2b2620; font-size: 14px; font-family: Georgia, serif; }
                .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #ece4d4; font-size: 13.5px; }
                .detail-row:last-child { border-bottom: none; }
                .detail-label { color: rgba(43,38,32,0.55); }
                .detail-value { color: #2b2620; font-weight: 600; text-align: right; }
                .btn-dash { background: #d4af37; color: #201a0d; padding: 11px 26px; text-decoration: none; border-radius: 25px; display: inline-block; font-weight: 700; font-size: 13px; }
                .footer { background: #2b2416; padding: 20px; text-align: center; color: rgba(255,255,255,0.5); font-size: 11.5px; line-height: 1.8; }
                .footer a { color: #d4af37; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ahmed Travels</h1>
                    <p>ADMIN NOTIFICATION</p>
                </div>
                <div class='content'>
                    <div style='text-align: center; margin: 4px 0 20px;'>
                        <span class='status-badge'>$action</span>
                    </div>
                    
                    <div class='booking-box'>
                        <h3>Booking Details</h3>
                        <div class='detail-row'><span class='detail-label'>Booking ID</span><span class='detail-value'>" . htmlspecialchars($booking_no) . "</span></div>
                        <div class='detail-row'><span class='detail-label'>Customer</span><span class='detail-value'>" . htmlspecialchars($customer_name) . "</span></div>
                        <div class='detail-row'><span class='detail-label'>Email</span><span class='detail-value'>" . htmlspecialchars($customer_email) . "</span></div>
                        <div class='detail-row'><span class='detail-label'>Service</span><span class='detail-value'>" . ucfirst(htmlspecialchars($service_type)) . "</span></div>
                        <div class='detail-row'><span class='detail-label'>Travel Date</span><span class='detail-value'>$travel_date_display</span></div>
                        <div class='detail-row'><span class='detail-label'>Total Amount</span><span class='detail-value' style='color:#b8912f;'>SAR " . number_format($total_amount) . "</span></div>
                        <div class='detail-row'><span class='detail-label'>Status</span><span class='detail-value' style='color: $color;'>" . strtoupper($status) . "</span></div>
                    </div>
                    
                    <div style='text-align: center; margin: 22px 0 4px;'>
                        <a href='" . htmlspecialchars($dashboard_url) . "' class='btn-dash' target='_blank'>
                            Go to Admin Dashboard
                        </a>
                    </div>
                </div>
                <div class='footer'>
                    <p>Ahmed Travels &bull; Saudi Arabia</p>
                    <p>+966 51 036 1841 &bull; <a href='mailto:ahmedtvl606@gmail.com'>ahmedtvl606@gmail.com</a></p>
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