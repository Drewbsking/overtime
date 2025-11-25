<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '/home/your_cpanel_username/public_html/overtime.rcocwiki.org/PHPMailer/src/Exception.php';
require '/home/your_cpanel_username/public_html/overtime.rcocwiki.org/PHPMailer/src/PHPMailer.php';
require '/home/your_cpanel_username/public_html/overtime.rcocwiki.org/PHPMailer/src/SMTP.php';

function sendApprovalEmailToRequestor($email, $requestDetails) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.overtime.rcocwiki.org'; // Replace with your SMTP server details
        $mail->SMTPAuth = true;
        $mail->Username = 'admin@overtime.rcocwiki.org'; // Replace with your subdomain email address
        $mail->Password = 'Facts!Food'; // Replace with your email password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('your_email@subdomain.yourdomain.com', 'Overtime Request App');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Overtime Request Has Been Approved';
        $mail->Body    = "Your overtime request has been approved:<br>
                          Name: {$requestDetails['name']}<br>
                          Hours: {$requestDetails['hours']}<br>
                          Reason: {$requestDetails['reason']}<br>";

        $mail->send();
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

function sendDeclineEmailToRequestor($email, $requestDetails) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.overtime.rcocwiki.org'; // Replace with your SMTP server details
        $mail->SMTPAuth = true;
        $mail->Username = 'admin@overtime.rcocwiki.org'; // Replace with your subdomain email address
        $mail->Password = 'Facts!Food'; // Replace with your email password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('your_email@subdomain.yourdomain.com', 'Overtime Request App');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Overtime Request Has Been Declined';
        $mail->Body    = "Your overtime request has been declined:<br>
                          Name: {$requestDetails['name']}<br>
                          Hours: {$requestDetails['hours']}<br>
                          Reason: {$requestDetails['reason']}<br>";

        $mail->send();
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}
?>
