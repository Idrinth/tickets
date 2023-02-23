<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Twig;
use PDO;

class Profile
{
    private Twig $twig;
    private PDO $database;

    public function __construct(Twig $twig, PDO $database)
    {
        $this->twig = $twig;
        $this->database = $database;
    }
    public function run($post)
    {
        if (!isset($_SESSION['id'])) {
            header('Location: /login', true, 303);
            return;
        }
        if (isset($post['display']) && isset($post['enable_mail_update']) && isset($post['enable_discord_update'])) {
            $this->database
                ->prepare('UPDATE `users` SET `display`=:display,`enable_mail_update`=:enable_mail_update,`enable_discord_update`:enable_discord_update WHERE aid=:id')
                ->execute([':id' => $_SESSION['id'], ':display' => $post['display'], ':enable_mail_update' => $post['enable_mail_update'], ':enable_discord_update' => $post['enable_discord_update']]);
        }
        $stmt = $this->database->prepare('SELECT * FROM users WHERE aid=:aid');
        $stmt->execute([':aid' => $_SESSION['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $this->twig->render('profile', ['title' => $user['display'], 'user' => $user]);
    }
}
