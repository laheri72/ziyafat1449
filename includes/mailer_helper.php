<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/**
 * Send a professional HTML email using PHPMailer and SMTP
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body HTML body content
 * @return bool True if sent, False otherwise
 */
function send_email($to, $subject, $body) {
    $config = require __DIR__ . '/../config/mail.php';
    
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port       = $config['port'];
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed to $to. Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Generate a professional HTML email template wrapper
 */
function get_email_template($title, $content, $userName) {
    $config = require __DIR__ . '/../config/mail.php';
    $baseUrl = $config['base_url'];
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
            .header { background: linear-gradient(135deg, #243b53 0%, #102a43 100%); color: white; padding: 30px 20px; text-align: center; }
            .content { padding: 30px 20px; background: #ffffff; }
            .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
            .button { display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; margin-top: 20px; }
            .stat-box { background: #f1f5f9; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #2563eb; }
            .progress-bar { width: 100%; height: 10px; background: #e2e8f0; border-radius: 5px; margin-top: 5px; }
            .progress-fill { height: 100%; border-radius: 5px; }
            .category-insight { background: #fffbeb; border: 1px solid #fef3c7; padding: 15px; border-radius: 6px; margin: 15px 0; color: #92400e; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>$title</h1>
            </div>
            <div class='content'>
                <p>Moula (TUS) na Khidmat ma Khush raho, <strong>$userName</strong>,</p>
                $content
                <center>
                    <a href='$baseUrl/auth/login.php' class='button'>Visit Your Portal</a>
                </center>
            </div>
            <div class='footer'>
                <p>&copy; 1449 H · Ziyafat us Shukr Reminders</p>
                <p>This is an automated reminder from your portal.</p>
            </div>
        </div>
    </body>
    </html>";
}
?>