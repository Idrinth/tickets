<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Twig;
use PDO;
use De\Idrinth\Tickets\Mailer;

class Login
{
    private Twig $twig;
    private PDO $database;
    private Mailer $mailer;

    public function __construct(Twig $twig, PDO $database, Mailer $mailer)
    {
        $this->twig = $twig;
        $this->mailer = $mailer;
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
            #return $this->twig->render('login-sent-failed', ['title' => 'Login']);
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
            if (!$this->mailer->send(
                'login-mail',
                [
                    'oneTime' => $oneTime,
                    'name' => $post['display'],
                    'hostname' => $_ENV['SYSTEM_HOSTNAME'],
                ],
                'Login Request at ticket.idrinth.de',
                $post['mail'],
                $post['display']
            )) {
                error_log('Mailer failed sending mail.');
                return $this->twig->render('login-sent-failed', ['title' => 'Login']);
            }
            return $this->twig->render('login-sent', ['title' => 'Login']);
        }
        return $this->twig->render('login', ['title' => 'Login']);
    }
}
