<?php

namespace De\Idrinth\Tickets\Pages;

use PDO;

class MailLogin
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }
    public function run($post, $key)
    {
        if (($_SESSION['id'] ?? 0) !== 0) {
            header('Location: /', true, 303);
            return;
        }
        $stmt = $this->database->prepare('SELECT aid FROM `users` WHERE password=:password AND valid_until>NOW()');
        $stmt->execute([':password' => $key]);
        $id = intval($stmt->fetchColumn(), 10);
        if (!$id) {
            header('Location: /', true, 303);
            return;
        }
        $_SESSION['id'] = $id;
        header('Location: /', true, 303);
        return;
    }
}
