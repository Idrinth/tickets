<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Twig;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;

class Login
{
    private Twig $twig;
    private PDO $database;

    public function __construct(Twig $twig, PDO $database)
    {
        $this->twig = $twig;
        $this->database = $database;
    }
    function makeOneTimePass(): string
    {
        $chars = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
        $out = '';
        while (strlen($out) < 255) {
            $out .= $chars[rand(0, 61)];
        }
        return $out;
    }
    public function run($post)
    {
        if (isset($post['mail']) && isset($post['display'])) {
            $oneTime = $this->makeOneTimePass();
            $stmt = $this->database->prepare('SELECT aid FROM `users` WHERE email=:email');
            $stmt->execute([':email' => $post['mail']]);
            $id = intval($stmt->fetchColumn(), 10);
            if (!$id && isset($_SESSION['id'])) {
                $id = $_SESSION['id'];
            }
            if (!$id) {
                $this->database
                    ->prepare('INSERT INTO `users` (`display`,`email`,`password`,`valid_until`) VALUES (:display,:email,:password,:valid_until)')
                    ->execute([':display' => $post['display'],':email' => $post['mail'],':password' => $oneTime, ':valid_until' => date('Y-m-d H:i:s', time()+3600)]);
            } else {
                $stmt = $this->database->prepare('SELECT valid_until FROM `users` WHERE aid=:id');
                $stmt->execute([':id' => $id]);
                $date = $stmt->fetchColumn();
                if ($date !== null && strtotime($date) > time()) {
                    return $this->twig->render('login-sent-too-early', ['title' => 'Login']);
                }
                $stmt = $this->database->prepare('SELECT email,mail_valid FROM `users` WHERE aid=:id');
                $stmt->execute([':id' => $id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($data['mail_valid'] === '1' && $data['email'] !== $post['mail']) {
                    $this->database
                        ->prepare('INSERT INTO `users` (`display`,`email`,`password`,`valid_until`) VALUES (:display,:email,:password,:valid_until)')
                        ->execute([':display' => $post['display'],':email' => $post['mail'],':password' => $oneTime, ':valid_until' => date('Y-m-d H:i:s', time()+3600)]);
                } elseif ($data['mail_valid'] === '1') {
                    $this->database
                        ->prepare('UPDATE `users` SET `password`=:password,`valid_until`=:valid_until WHERE aid=:aid')
                        ->execute([':aid' => $id,':password' => $oneTime, ':valid_until' => date('Y-m-d H:i:s', time()+3600)]);
                } else {
                    $this->database
                        ->prepare('UPDATE `users` SET `email`=:mail,`password`=:password,`valid_until`=:valid_until WHERE aid=:aid')
                        ->execute([':aid' => $id,':mail' => $post['mail'],':password' => $oneTime, ':valid_until' => date('Y-m-d H:i:s', time()+3600)]);
                }
            }
            $mailer = new PHPMailer();
            $mailer->setFrom('ticket@idrinth.de', 'Idrinth\'s Tickets (idrinth)');
            $mailer->addAddress($post['mail'], $post['display']);
            $mailer->Host = $_ENV['MAIL_HOST'];
            $mailer->Username = $_ENV['MAIL_USER'];
            $mailer->Password = $_ENV['MAIL_PASSWORD'];
            $mailer->Port = intval($_ENV['MAIL_PORT_SMTP'], 10);
            $mailer->CharSet = 'utf-8';
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Timeout = 60;
            $mailer->isHTML(true);
            $mailer->Mailer ='smtp';
            $mailer->Subject = 'Login Request at ticket.idrinth.de';
            $mailer->Body = $this->twig->render(
                'login-mail',
                ['oneTime' => $oneTime, 'name' => $post['display']]
            );
            $mailer->SMTPAuth = true;
            if (!$mailer->smtpConnect()) {
                error_log('Mailer failed smtp connect.');
                return $this->twig->render('login-sent-failed', ['title' => 'Login']);
            }
            if (!$mailer->send()) {
                error_log('Mailer failed sending mail.');
                return $this->twig->render('login-sent-failed', ['title' => 'Login']);
            }
            return $this->twig->render('login-sent', ['title' => 'Login']);
        }
        return $this->twig->render('login', ['title' => 'Login']);
    }
}
