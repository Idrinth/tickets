<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Twig;
use PDO;

class NewTicket
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
        if (isset($post['title']) && isset($post['description']) && isset($post['type']) && isset($post['project'])) {
            $stmt = $this->database->prepare("SELECT aid FROM projects WHERE slug=:slug");
            $stmt->execute(['title' => $post['project']]);
            $project = $stmt->fetchColumn();
            $stmt = $this->database->prepare("INSERT INTO tickets (`title`,`description`,`creator`,`type`,`status`,`created`,`modified`,`project`) VALUES (:title,:description,:creator,:type,1,NOW(),NOW(),:project)");
            $stmt->execute([':title' => $post['title'], ':description' => $post['description'], ':creator' => $_SESSION['id'],':type' => $post['type'],':project' => $project]);
            $id = $this->database->lastInsertId();
            $slug = base_convert("$id", 10, 36);
            $this->database
                ->prepare('UPDATE tickets SET slug=:slug WHERE aid=:id')
                ->execute([':slug' => $slug, ':id' => $id]);
            header('Location: /'.$post['project'].'/'.$slug, true, 303);
            return;
        }
        return $this->twig->render('new');
    }
}
