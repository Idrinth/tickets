<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Twig;
use PDO;

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
                    ->execute([':aid' => $id,':email' => $post['mail'],':password' => $oneTime, ':valid_until' => date('Y-m-d H:i:s', time()+3600)]);
            }
            $mbox = imap_open("{{$_ENV['MAIL_HOST']}:{$_ENV['MAIL_PORT']}}INBOX", $_ENV['MAIL_USER'], $_ENV['MAIL_PASSWORD'],  OP_SECURE);
            imap_mail(
                $post['email'],
                'Login-Request tickets.idrinth.de',
                imap_mail_compose(
                    [
                        'from' => 'Idrinth\'s Tickets (idrinth) <ticket@idrinth.de>',
                        'to' => "{$post['display']} <{$post['mail']}>",
                    ],
                    [
                        'type' => TYPETEXT,
                        'subtype' => 'plain',
                        'charset' => 'utf-8',
                        'contents.data' => "If you didn't plan to login, just ignore this mail. Otherwise go to https://tickets.idrinth.de/email-login/$oneTime",
                    ]
                )
            );
            imap_close($mbox);
            return $this->twig->render('login-sent', ['title' => 'Login']);
        }
        return $this->twig->render('login', ['title' => 'Login']);
    }
}
