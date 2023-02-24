<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Services\Mailer;
use De\Idrinth\Tickets\Services\Watcher;
use De\Idrinth\Tickets\Twig;
use PDO;

class Ticket
{
    private Twig $twig;
    private PDO $database;
    private Watcher $watcher;
    private Mailer $mailer;

    public function __construct(Twig $twig, PDO $database, Watcher $watcher, Mailer $mailer)
    {
        $this->twig = $twig;
        $this->database = $database;
        $this->mailer = $mailer;
        $this->watcher = $watcher;
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
        if ($project['limited_access'] === '1' && !isset($_SESSION['id'])) {
            header('Location: /' . $category, true, 303);
            return;
        } elseif ($project['limited_access'] === '1' && $ticket['creator'] != $_SESSION['id']) {
            $stmt = $this->database->prepare('SELECT 1 FROM roles WHERE role="contributor" AND project=:project AND `user`=:user');
            $stmt->execute([':user' => $_SESSION['id'], ':project' => $ticket['project']]);
            if ($stmt->fetchColumn() !== '1') {
                header('Location: /' . $category, true, 303);
                return;
            }
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
            $stmt = $this->database->prepare('SELECT COUNT(*) FROM upvotes WHERE `user`=:user AND ticket=:ticket');
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
                foreach ($this->watcher->ticket($ticket['aid'], $_SESSION['id']) as $watcher) {
                    if ($this->watcher->mailable($watcher)) {
                        $this->mailer->send(
                            $watcher['aid'],
                            'new-comment',
                            [
                                'hostname' => $_ENV['SYSTEM_HOSTNAME'],
                                'ticket' => $ticket['slug'],
                                'project' => 'unknown',
                                'name' => $watcher['display'],
                                'comment' => [
                                    'content' => $post['content'],
                                    'author' => 'someone'
                                ],
                            ],
                            "New comment on Ticket {$ticket['slug']}",
                            $watcher['email'],
                            $watcher['display']
                        );
                    }
                    $this->database
                        ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                        ->execute([':url' => "/{$project['slug']}/{$ticket['slug']}#c{$comment}", ':user' => $watcher['user'],':ticket' => $ticket['aid'], ':content' => 'A new comment was written.']);
                }
                $this->database
                    ->prepare('INSERT IGNORE INTO watchers (ticket, `user`) VALUES (:id, :user)')
                    ->execute([':id' => $ticket['aid'], ':user' => $_SESSION['id']]);
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
            }elseif (isset($post['watch'])) {
                $this->database
                    ->prepare('INSERT IGNORE INTO watchers (ticket, `user`) VALUES (:id, :user)')
                    ->execute([':id' => $ticket['aid'], ':user' => $_SESSION['id']]);
            } elseif($isContributor && isset($post['duration']) && isset($post['task'])) {
                $time = (intval(explode(':', $post['duration'])[0], 10) * 60 + intval(explode(':', $post['duration'])[1], 10)) * 60;
                $this->database
                    ->prepare('INSERT INTO times (`user`,`ticket`,`day`,`duration`,`status`) VALUES (:user,:ticket,:day,:duration,:status) ON DUPLICATE KEY UPDATE `duration`=`duration`+:duration')
                    ->execute([':user' => $_SESSION['id'],':ticket' => $ticket['aid'],':day' => date('Y-m-d'),':duration' => $time,':status' => $post['task']]);
                $stmt = $this->database->prepare('SELECT `user` FROM watchers WHERE ticket=:ticket');
                $stmt->execute([':ticket' => $ticket['aid']]);
                foreach ($this->watcher->ticket($ticket['aid'], $_SESSION['id']) as $watcher) {
                    if ($this->watcher->mailable($watcher)) {
                        $this->mailer->send(
                            $watcher['aid'],
                            'time-logged',
                            [
                                'hostname' => $_ENV['SYSTEM_HOSTNAME'],
                                'ticket' => $ticket['slug'],
                                'project' => 'unknown',
                                'name' => $watcher['display'],
                                'duration' => $post['duration'],
                            ],
                            "Status change for Ticket {$ticket['slug']}",
                            $watcher['email'],
                            $watcher['display']
                        );
                    }
                    $this->database
                        ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                        ->execute([':url' => "/{$project['slug']}/{$ticket['slug']}", ':user' => $watcher['aid'],':ticket' => $ticket['aid'], ':content' => 'Time was tracked.']);
                }
                $this->database
                    ->prepare('INSERT IGNORE INTO watchers (ticket, `user`) VALUES (:id, :user)')
                    ->execute([':id' => $ticket['aid'], ':user' => $_SESSION['id']]);
                $wasModified=true;
            } elseif($isContributor && isset($post['status'])) {
                $this->database
                    ->prepare('UPDATE tickets SET `status`=:status WHERE aid=:aid')
                    ->execute([':status' => $post['status'],':aid' => $ticket['aid']]);
                $stmt = $this->database->prepare('SELECT name FROM stati WHERE aid=:aid');
                $stmt->execute([':aid' => $post['status']]);
                $status = $stmt->fetchColumn();
                foreach ($this->watcher->ticket($ticket['aid'], $_SESSION['id']) as $watcher) {
                    if ($this->watcher->mailable($watcher)) {
                        $this->mailer->send(
                            $watcher['aid'],
                            'status-changed',
                            [
                                'hostname' => $_ENV['SYSTEM_HOSTNAME'],
                                'ticket' => $ticket['slug'],
                                'project' => 'unknown',
                                'name' => $watcher['display'],
                                'status' => $status,
                            ],
                            "Status change for Ticket {$ticket['slug']}",
                            $watcher['email'],
                            $watcher['display']
                        );
                    }
                    $this->database
                        ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                        ->execute([':url' => "/{$project['slug']}/{$ticket['slug']}", ':user' => $watcher['aid'],':ticket' => $ticket['aid'], ':content' => 'Status was changed.']);
                }
                
                $this->database
                    ->prepare('INSERT IGNORE INTO watchers (ticket, `user`) VALUES (:id, :user)')
                    ->execute([':id' => $ticket['aid'], ':user' => $_SESSION['id']]);
                $wasModified=true;
            } elseif($isContributor && isset($post['project'])) {
                $stmt = $this->database->prepare('SELECT * FROM projects WHERE slug=:slug');
                $stmt->execute([':slug' => $post['project']]);
                $project = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->database
                    ->prepare('UPDATE tickets SET `project`=:project WHERE aid=:aid')
                    ->execute([':project' => $project['aid'],':aid' => $ticket['aid']]);
                $stmt = $this->database->prepare('SELECT `user` FROM watchers WHERE ticket=:ticket');
                $stmt->execute([':ticket' => $ticket['aid']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $watcher) {
                    if (intval($watcher['user']) !== $_SESSION['id']) {
                        $this->database
                            ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                            ->execute([':url' => "/{$project['slug']}/{$ticket['slug']}", ':user' => $watcher['user'],':ticket' => $ticket['aid'], ':content' => 'Project was changed.']);
                    }
                }
                $this->database
                    ->prepare('INSERT IGNORE INTO watchers (ticket, `user`) VALUES (:id, :user)')
                    ->execute([':id' => $ticket['aid'], ':user' => $_SESSION['id']]);
                $wasModified=true;
            }
            if ($wasModified) {
                $this->database
                    ->prepare('UPDATE tickets SET modified=NOW() WHERE aid=:aid')
                    ->execute([':aid' => $ticket['aid']]);
                header('Location: /' . $project['slug'] . '/' . $ticket['slug'], true, 303);
                return;
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
        $stmt2 = $this->database->prepare('SELECT `user` FROM watchers WHERE ticket=:ticket');
        $stmt2->execute([':ticket' => $ticket['aid']]);
        return $this->twig->render(
            'ticket',
            [
                'title' => $ticket['title'],
                'stati' => $stati,
                'isContributor' => $isContributor,
                'times' => $times,
                'users' => $users,
                'ticket_project' => $project,
                'ticket' => $ticket,
                'comments' => $comments,
                'upvotes' => intval($stmt->fetchColumn(), 10),
                'isUpvoter' => $isUpvoter,
                'watchers' => $stmt2->fetchAll(PDO::FETCH_ASSOC),
            ]
        );
    }
}
