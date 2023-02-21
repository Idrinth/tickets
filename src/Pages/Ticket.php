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
        $wasModified=false;
        $isUpvoter = false;
        if (isset($_SESSION['id'])) {
            if ($_SESSION['id'] === 1) {
                $this->database
                    ->prepare('INSERT IGNORE INTO roles (project, `user`, `role`) VALUES (:project,:user,"contributor")')
                    ->execute([':project' => $project['aid'], ':user' => $_SESSION['id']]);
            } else {
                $this->database
                    ->prepare('INSERT IGNORE INTO roles (project, `user`, `role`) VALUES (:project,:user,"member")')
                    ->execute([':project' => $project['aid'], ':user' => $_SESSION['id']]);
            }
            $stmt = $this->database->prepare('SELECT COUNT(*) FROM upvotes WhERE `user`=:user AND ticket=:ticket');
            $stmt->execute([':ticket' => $ticket['aid'], ':user' => $_SESSION['id']]);
            $isUpvoter = $stmt->fetchColumn()==='1';
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
                $wasModified=true;
            } elseif (isset($post['vote'])) {
                if ($isUpvoter) {
                    $this->database
                        ->prepare('DELETE FROM upvotes WHERE `user`=:user AND `ticket`=:ticket')
                        ->execute([':ticket' => $ticket['aid'],':user' => $_SESSION['id']]);
                    $isUpvoter = false;
                } else {
                    $this->database
                        ->prepare('INSERT INTO upvotes (`user`,`ticket`) VALUES (:user,:ticket)')
                        ->execute([':ticket' => $ticket['aid'],':user' => $_SESSION['id']]);
                    $isUpvoter = true;
                }
                $wasModified=true;
            } elseif($isContributor && isset($post['duration']) && isset($post['task'])) {
                $time = (intval(explode(':', $post['duration'])[0], 10) * 60 + intval(explode(':', $post['duration'])[1], 10)) * 60;
                $this->database
                    ->prepare('INSERT INTO times (`user`,`ticket`,`day`,`duration`,`status`) VALUES (:user,:ticket,:day,:duration,:status)')
                    ->execute([':user' => $_SESSION['id'],':ticket' => $ticket['aid'],':day' => date('Y-m-d'),':duration' => $time,':status' => $post['task']]);
                $stmt = $this->database->prepare('SELECT `user` FROM watchers WHERE ticket=:ticket');
                $stmt->execute([':ticket' => $ticket['aid']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $watcher) {
                    $this->database
                        ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                        ->execute([':url' => "/{$project['slug']}/{$ticket['slug']}#c{$comment}", ':user' => $watcher['user'],':ticket' => $ticket['aid'], ':content' => 'Time was tracked.']);
                }
                $wasModified=true;
            } elseif($isContributor && isset($post['status'])) {
                $this->database
                    ->prepare('UPDATE tickets SET `status`=:status WHERE aid=:aid')
                    ->execute([':status' => $post['status'],':aid' => $ticket['aid']]);
                $stmt = $this->database->prepare('SELECT `user` FROM watchers WHERE ticket=:ticket');
                $stmt->execute([':ticket' => $ticket['aid']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $watcher) {
                    $this->database
                        ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                        ->execute([':url' => "/{$project['slug']}/{$ticket['slug']}#c{$comment}", ':user' => $watcher['user'],':ticket' => $ticket['aid'], ':content' => 'Status was changed.']);
                }
                $wasModified=true;
            }
            if ($wasModified) {
                $this->database
                    ->prepare('UPDATE tickets SET modified=NOW() WHERE aid=:aid')
                    ->execute([':aid' => $ticket['aid']]);
                $stmt = $this->database->prepare('SELECT * FROM tickets WHERE aid=:aid');
                $stmt->execute([':aid' => $ticket['aid']]);
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
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
        $stmt = $this->database->prepare('SELECT COUNT(*) FROM upvotes WHERE ticket=:ticket');
        $stmt->execute([':ticket' => $ticket['aid']]);
        return $this->twig->render(
            'ticket',
            [
                'title' => $ticket['title'],
                'stati' => $stati,
                'isContributor' => $isContributor,
                'times' => $times,
                'users' => $users,
                'project' => $project,
                'ticket' => $ticket,
                'comments' => $comments,
                'upvotes' => intval($stmt->fetchColumn(), 10),
                'isUpvoter' => $isUpvoter,
            ]
        );
    }
}
