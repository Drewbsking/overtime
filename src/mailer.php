<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    public static function send(array $recipients, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $hasMailer = class_exists(PHPMailer::class);
        $shouldLog = filter_var(env('MAIL_LOG_FAILURES', true), FILTER_VALIDATE_BOOL);

        if (!$hasMailer) {
            if ($shouldLog) {
                self::log('PHPMailer not installed. Skipping email: ' . $subject);
            }
            return false;
        }

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        try {
            $mail->isSMTP();
            $mail->Host = env('SMTP_HOST');
            $port = (int)env('SMTP_PORT', 587);
            $mail->Port = $port;
            $mail->SMTPAuth = true;
            $mail->Username = env('SMTP_USER');
            $mail->Password = env('SMTP_PASS');
            $encryption = strtolower((string)env('SMTP_ENCRYPTION', ''));
            if (in_array($encryption, ['ssl', 'smtps'], true)) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif (in_array($encryption, ['tls', 'starttls'], true)) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                // Default based on port: 465 -> implicit SSL, otherwise STARTTLS
                $mail->SMTPSecure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            }

            $fromEmail = env('SMTP_FROM_EMAIL', env('SMTP_USER'));
            $fromName = env('SMTP_FROM_NAME', 'Overtime Portal');
            $mail->setFrom($fromEmail, $fromName);

            foreach ($recipients as $email => $name) {
                if (is_int($email)) {
                    $mail->addAddress($name);
                } else {
                    $mail->addAddress($email, $name);
                }
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (Exception $e) {
            if ($shouldLog) {
                self::log('Mailer error: ' . $e->getMessage());
            }
            return false;
        }
    }

    private static function log(string $message): void
    {
        $line = sprintf("[%s] %s\n", now(), $message);
        $logFile = __DIR__ . '/../storage/logs/mail.log';
        file_put_contents($logFile, $line, FILE_APPEND);
    }
}
