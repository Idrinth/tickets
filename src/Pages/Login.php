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
    public function run($post)
    {
        return $this->twig->render('login');
    }
}
