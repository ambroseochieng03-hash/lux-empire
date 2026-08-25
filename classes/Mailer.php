<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/mail.php';
    }

    public function send(string $toEmail, string $toName, string $subject, string $bodyHtml): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->SMTPSecure = $this->config['encryption'] === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $this->config['port'];

            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log('LUX EMPIRE Mailer error: ' . $mail->ErrorInfo);
            return false;
        }
    }
}
