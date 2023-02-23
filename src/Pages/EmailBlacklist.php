<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Services\BlacklistHash;
use De\Idrinth\Tickets\Twig;
use PDO;

class EmailBlacklist
{
    private Twig $twig;
    private PDO $database;

    public function __construct(Twig $twig, PDO $database)
    {
        $this->twig = $twig;
        $this->database = $database;
    }
    public function run($post, $key)
    {
        $stmt = $this->database->query('SELECT aid,display,email FROM `users` WHERE NOT ISNULL(email)');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (BlacklistHash::hash($row['email'], intval($row['aid'], 10)) === $key) {
                $this->database
                    ->prepare('INSERT IGNORE INTO email_blacklist (email) VALUES (:email)')
                    ->execute([':email' => $row['email']]);
                return $this->twig->render('blacklist-success', ['title' => 'Blacklisting', 'blacklisted_user' => $row]);
            }
        }
        return $this->twig->render('blacklist-failure', ['title' => 'Blacklisting', 'email' => $_ENV['MAIL_FROM_MAIL']]);
    }
}
