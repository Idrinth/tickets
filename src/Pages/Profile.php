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
        $stmt = $this->database->prepare('SELECT * FROM users WHERE aid=:aid');
        $stmt->execute([':aid' => $_SESSION['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $this->twig->render('profile', ['title' => $user['display'], 'user' => $user]);
    }
}
