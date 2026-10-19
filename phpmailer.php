<?php 

// Load PHPMailer classes
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPAuth = true;  // Fixed: property assignment, not method call
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;  // Use TLS instead of SSL for Gmail
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;  // Fixed: integer instead of string, use 587 for TLS
    $mail->isHTML(true);
    $mail->Username = 'danielmantesprado@gmail.com';  // Fixed: typo in email (was 'gmai.com')
    $mail->Password = 'danz09511874805';  // ⚠️ WARNING: Use App Password, not regular password
    $mail->setFrom('danielmantesprado@gmail.com', 'Daet Public Market Office');  // Fixed: use valid from address
    $mail->Subject = 'Hello World';
    $mail->Body = 'A test email!';  // Fixed: missing closing quote
    $mail->addAddress('fannyuser443@gmail.com');  // Fixed: lowercase 'addAddress' (modern method)
    
    // Enable verbose debug output (optional, for testing)
    // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    
    // Send email
    if ($mail->send()) {
        echo "Email sent successfully!";
    } else {
        echo "Error: " . $mail->ErrorInfo;
    }
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: " . (isset($mail) ? $mail->ErrorInfo : $e->getMessage());
}

?>