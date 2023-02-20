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
        if ($ticket['project'] !== $project['aid']) {
            $stmt = $this->database->prepare('SELECT slug FROM projects WHERE aid=:aid');
            $stmt->execute([':aid' => $ticket['project']]);
            $project = $stmt->fetchColumn();
            header('Location: /' . $project . '/' . $ticket['slug'], true, 303);
            return;
        }
        $isContributor = false;
        if (isset($_SESSION['id'])) {
            $this->database
                ->prepare('INSERT IGNORE INTO roles (project, `user`, `role`) VALUES (:project,:user,"member")')
                ->execute([':project' => $project['aid'], ':user' => $_SESSION['id']]);
            $stmt = $this->database->prepare('SELECT `role` FROM roles WHERE project=:project AND `user`=:user');
            $stmt->execute([':project' => $project['aid'], ':user' => $_SESSION['id']]);
            $isContributor = $stmt->fetchColumn()==='contributor';
            if (isset($post['content'])) {
                $this->database
                    ->prepare('INSERT INTO comments (`ticket`,`creator`,`created`,`content`) VALUES (:ticket,:user,NOW(),:content)')
                    ->execute([':ticket' => $ticket['aid'],':user' => $_SESSION['id'],':content' => $post['content']]);
                $comment = $this->database->lastInsertId();
                $stmt = $this->database->prepare('SELECT `user` FROM watchers WHERE ticket=:ticket');
                $stmt->execute([':ticket' => $ticket['aid']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $watcher) {
                    $this->database
                        ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                        ->execute([':url' => "/{$project['slug']}/{$ticket['slug']}#c{$comment}", ':user' => $watcher['user'],':ticket' => $ticket['aid'], ':content' => 'A new comment was written.']);
                }
            } elseif($isContributor && isset($post['duration']) && isset($post['task'])) {
                $this->database
                    ->prepare('INSERT INTO times (`user`,`ticket`,`day`,`duration`,`status`) VALUES (:user,:ticket,:day,:duration,:status)')
                    ->execute([':user' => $_SESSION['id'],':ticket' => $ticket['aid'],':day' => date('Y-m-d'),':duration' => $post['duration'],':status' => $post['task']]);
            }
            $this->database
                ->prepare('UPDATE notifications SET `read`=NOW() WHERE `read` IS NULL AND `user`=:user AND ticket=:ticket')
                ->execute([':user' => $_SESSION['id'], ':ticket' => $ticket['aid']]);
        }
        $stmt = $this->database->prepare('SELECT * FROM times INNER JOIN stati ON stati.aid=times.`status` WHERE ticket=:ticket');
        $stmt->execute([':ticket' => $ticket['aid']]);
        $times = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->database->prepare('SELECT * FROM comments WHERE ticket=:id');
        $stmt->execute([':id' => $ticket['aid']]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->database->prepare('SELECT aid,display FROM users');
        $stmt->execute([':id' => $ticket['aid']]);
        $users = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $us) {
            $users[$us['aid']] = $us['display'];
        }
        $stati = [];
        foreach ($this->database->query("SELECT * FROM stati")->fetchAll(PDO::FETCH_ASSOC) as $status) {
            $stati[$status['aid']] = $status;
        }
        return $this->twig->render('ticket', ['stati' => $stati, 'isContributor' => $isContributor, 'times' => $times, 'users' => $users, 'project' => $project, 'ticket' => $ticket, 'comments' => $comments]);
    }
}
