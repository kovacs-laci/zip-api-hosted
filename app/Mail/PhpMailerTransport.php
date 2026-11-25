<?php

namespace App\Mail;

use InvalidArgumentException;
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

class PhpMailerTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new InvalidArgumentException('The PHPMailer transport requires an email message.');
        }

        $mailer = new PHPMailer(true);
        $this->configureTransport($mailer);
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;

        $sender = $message->getEnvelope()->getSender();
        $mailer->setFrom($sender->getAddress(), $sender->getName());

        foreach ($email->getTo() as $recipient) {
            $mailer->addAddress($recipient->getAddress(), $recipient->getName());
        }

        foreach ($email->getCc() as $recipient) {
            $mailer->addCC($recipient->getAddress(), $recipient->getName());
        }

        foreach ($email->getBcc() as $recipient) {
            $mailer->addBCC($recipient->getAddress(), $recipient->getName());
        }

        foreach ($email->getReplyTo() as $recipient) {
            $mailer->addReplyTo($recipient->getAddress(), $recipient->getName());
        }

        $htmlBody = $email->getHtmlBody();
        $textBody = $email->getTextBody();

        $mailer->isHTML($htmlBody !== null);
        $mailer->Subject = $email->getSubject() ?? '';
        $mailer->Body = $htmlBody ?? $textBody ?? '';
        $mailer->AltBody = $textBody ?? ($htmlBody === null ? '' : strip_tags($htmlBody));

        $mailer->send();
    }

    private function configureTransport(PHPMailer $mailer): void
    {
        $environment = strtolower(trim((string) config('app.env', 'production')));

        if (in_array($environment, ['prod', 'production'], true)) {
            $mailer->isMail();

            return;
        }

        $mailer->isSMTP();
        $mailer->Host = (string) config('mail.mailers.smtp.host', '127.0.0.1');
        $mailer->Port = (int) config('mail.mailers.smtp.port', 1025);

        $username = config('mail.mailers.smtp.username');
        $mailer->SMTPAuth = is_string($username) && $username !== '';

        if ($mailer->SMTPAuth) {
            $mailer->Username = $username;
            $mailer->Password = (string) config('mail.mailers.smtp.password', '');
        }

        $scheme = config('mail.mailers.smtp.scheme');

        if ($scheme === 'smtps') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($scheme === 'smtp' && $mailer->Port === 587) {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
    }

    public function __toString(): string
    {
        return 'phpmailer://mail';
    }
}
