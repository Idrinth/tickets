<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Twig;
use PDO;

class Ticket
{
    private Twig $twig;
    private PDO $database;

    public function __construct(Twig $twig, PDO $database)
    {
        $this->twig = $twig;
        $this->database = $database;
    }
    public function run($post, $category, $ticket)
    {
        $stmt = $this->database->prepare('SELECT * FROM projects WHERE slug=:slug');
        $stmt->execute([':slug' => $category]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$project) {
            header('Location: /', true, 303);
            return;
        }
        $stmt = $this->database->prepare('SELECT * FROM tickets WHERE slug=:slug');
        $stmt->execute([':slug' => $ticket]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            header('Location: /' . $category, true, 303);
            return;
        }
        if (isset($_SESSION['id'])) {
            if (isset($post['content'])) {
                $this->database
                    ->prepare('INSERT INTO comments (`ticket`,`user`,`created`,`content`) VALUES (:ticket,:user,NOW(),:content)')
                    ->execute([':ticket' => $ticket['aid'],':user' => $_SESSION['id'],':content' => $post['content']]);
            }
        }
        $stmt = $this->database->prepare('SELECT * FROM comments WHERE ticket=:id');
        $stmt->execute([':id' => $ticket['aid']]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->database->prepare('SELECT aid,display FROM users');
        $stmt->execute([':id' => $ticket['aid']]);
        $u = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $users = [];
        foreach ($u as $us) {
            $users[$us['aid']] = $us['display'];
        }
        return $this->twig->render('ticket', ['users' => $users, 'project' => $project, 'ticket' => $ticket, 'comments' => $comments]);
    }
}
