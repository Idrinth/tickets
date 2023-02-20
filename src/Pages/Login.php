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
                $this->database
                    ->prepare('UPDATE `users` SET `email`=:mail,`password`=:password,`valid_until`=:valid_until WHERE aid=:aid')
                    ->execute([':aid' => $id,':mail' => $post['mail'],':password' => $oneTime, ':valid_until' => date('Y-m-d H:i:s', time()+3600)]);
            }
            $mailer = new PHPMailer();
            $mailer->setFrom('ticket@idrinth.de', 'Idrinth\'s Tickets (idrinth)');
            $mailer->addAddress($post['mail'], $post['display']);
            $mailer->Host = $_ENV['MAIL_HOST'];
            $mailer->Username = $_ENV['MAIL_USER'];
            $mailer->Password = $_ENV['MAIL_PASSWORD'];
            $mailer->Port = intval($_ENV['MAIL_PORT'], 10);
            $mailer->Body = "If you didn't plan to login, just ignore this mail. Otherwise go to https://tickets.idrinth.de/email-login/$oneTime";
            $mailer->Subject = 'Login-Request tickets.idrinth.de';
            $mailer->SMTPAuth = true;
            if ($mailer->send() === false) {
                return $this->twig->render('login-sent-failed', ['title' => 'Login']);
            }
            return $this->twig->render('login-sent', ['title' => 'Login']);
        }
        return $this->twig->render('login', ['title' => 'Login']);
    }
}
