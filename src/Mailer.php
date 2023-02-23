<?php

namespace De\Idrinth\Tickets;

use De\Idrinth\Tickets\DTO\Mail;
use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    private Twig $twig;
    
    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    public function send(string $template, array $templateContext, string $subject, string $toMail, string $toName)
    {
        $mailer = new PHPMailer();
        $mailer->setFrom($_ENV['MAIL_FROM_MAIL'], $_ENV['MAIL_FROM_NAME']);
        $mailer->addAddress($toMail, $toName);
        $mailer->Host = $_ENV['MAIL_HOST'];
        $mailer->Username = $_ENV['MAIL_USER'];
        $mailer->Password = $_ENV['MAIL_PASSWORD'];
        $mailer->Port = intval($_ENV['MAIL_PORT_SMTP'], 10);
        $mailer->CharSet = 'utf-8';
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Timeout = 60;
        $mailer->isHTML(true);
        $mailer->Mailer ='smtp';
        $mailer->Subject = $subject;
        $mailer->Body = $this->twig->render(
            "mails/$template-html",
            $templateContext
        );
        $mailer->AltBody = $this->twig->render(
            "mails/$template-text",
            $templateContext
        );
        $mailer->SMTPAuth = true;
        if (!$mailer->smtpConnect()) {
            error_log('Mailer failed smtp connect.');
            return false;
        }
        if (!$mailer->send()) {
            error_log('Mailer failed sending mail.');
            return false;
        }
        return true;
    }
}
