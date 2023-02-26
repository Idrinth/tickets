<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Services\Antivirus;
use De\Idrinth\Tickets\Services\Mailer;
use De\Idrinth\Tickets\Services\MimeTypeDetector;
use De\Idrinth\Tickets\Services\Watcher;
use De\Idrinth\Tickets\Twig;
use PDO;

class Ticket
{
    private Twig $twig;
    private PDO $database;
    private Watcher $watcher;
    private Mailer $mailer;
    private Antivirus $av;

    public function __construct(Twig $twig, PDO $database, Watcher $watcher, Mailer $mailer, Antivirus $av)
    {
        $this->twig = $twig;
        $this->database = $database;
        $this->mailer = $mailer;
        $this->watcher = $watcher;
        $this->av = $av;
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
        $stati = [];
        foreach ($this->database->query("SELECT * FROM stati")->fetchAll(PDO::FETCH_ASSOC) as $status) {
            $stati[$status['aid']] = $status;
        }
        $isDone = $stati[$ticket['status']]['type'] === 'done';
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
            if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name']) && $_FILES['file']['tmp_name'] && !$isDone) {
                if ($this->av->fclean($_FILES['file']['tmp_name'])) {
                    $data = file_get_contents($_FILES['file']['tmp_name']);
                    $this->database
                        ->prepare('INSERT INTO uploads (`ticket`,`user`,`uploaded`,`data`,`name`,`hash`,`mime`) VALUES (:ticket,:user,NOW(),:data,:name,:hash,:mime)')
                        ->execute([
                            ':ticket' => $ticket['aid'],
                            ':user' => $_SESSION['id'],
                            ':data' => $data,
                            ':name' => basename($_FILES['file']['name']),
                            ':hash' => md5($data),
                            ':mime' => MimeTypeDetector::detect($data),
                        ]);
                    var_dump($this->database->errorInfo());
                    die();
                }
                $wasModified = true;
            } elseif (isset($post['content'])) {
                if ($isDone) {
                    $this->database
                        ->prepare('UPDATE `tickets` SET `status`=:status WHERE aid=:aid')
                        ->execute([':aid' => $ticket['aid'], ':status' => $_ENV['STATUS_ID_REOPENED']]);
                }
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
            } elseif (isset($post['vote']) && !$isDone) {
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
            } elseif (isset($post['watch']) && !$isDone) {
                $this->database
                    ->prepare('INSERT IGNORE INTO watchers (ticket, `user`) VALUES (:id, :user)')
                    ->execute([':id' => $ticket['aid'], ':user' => $_SESSION['id']]);
            } elseif (isset($post['type']) && $isContributor && !$isDone) {
                $this->database
                    ->prepare('UPDATE tickets set `type`=:type WHERE aid=:aid')
                    ->execute([':aid' => $ticket['aid'], ':type' => $post['type']]);
                $wasModified=true;
            } elseif($isContributor && isset($post['duration']) && isset($post['task'])) {
                $time = (intval(explode(':', $post['duration'])[0], 10) * 60 + intval(explode(':', $post['duration'])[1], 10)) * 60 + intval(explode(':', $post['duration'])[2], 10);
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
            } elseif($isContributor && isset($post['project']) && !$isDone) {
                $stmt = $this->database->prepare('SELECT * FROM projects WHERE slug=:slug');
                $stmt->execute([':slug' => $post['project']]);
                $project = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->database
                    ->prepare('UPDATE tickets SET `project`=:project WHERE aid=:aid')
                    ->execute([':project' => $project['aid'],':aid' => $ticket['aid']]);
                foreach ($this->watcher->ticket($ticket['aid'], $_SESSION['id']) as $watcher) {
                    if ($this->watcher->mailable($watcher)) {
                        $this->mailer->send(
                            $watcher['aid'],
                            'project-changed',
                            [
                                'hostname' => $_ENV['SYSTEM_HOSTNAME'],
                                'ticket' => $ticket['slug'],
                                'name' => $watcher['display'],
                                'project' => $project,
                            ],
                            "Project change for Ticket {$ticket['slug']}",
                            $watcher['email'],
                            $watcher['display']
                        );
                    }
                    $this->database
                        ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                        ->execute([':url' => "/{$project['slug']}/{$ticket['slug']}", ':user' => $watcher['user'],':ticket' => $ticket['aid'], ':content' => 'Project was changed.']);
                }
                $this->database
                    ->prepare('INSERT IGNORE INTO watchers (ticket, `user`) VALUES (:id, :user)')
                    ->execute([':id' => $ticket['aid'], ':user' => $_SESSION['id']]);
                $wasModified=true;
            } elseif($isContributor && isset($post['assignees']) && !$isDone) {
                $this->database
                    ->prepare('DELETE FROM assignees WHERE ticket=:ticket')
                    ->execute([':ticket' => $ticket['aid']]);
                foreach ($post['assignees'] as $assignee) {
                    $this->database
                        ->prepare('INSERT INTO assignees (`ticket`,`user`) VALUES (:ticket,:user)')
                        ->execute([':ticket' => $ticket['aid'], ':user' => $assignee]);
                    $this->database
                        ->prepare('INSERT IGNORE INTO watchers (ticket, `user`) VALUES (:id, :user)')
                        ->execute([':id' => $ticket['aid'], ':user' => $assignee]);
                }
                $stmt = $this->database->prepare('SELECT `users`.`display`
FROM `users`
INNER JOIN assignees ON assignees.`user`=`users`.aid AND assignees.ticket=:ticket');
                $stmt->execute([':ticket' => $ticket['aid']]);
                $assignees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($this->watcher->ticket($ticket['aid'], $_SESSION['id']) as $watcher) {
                    if ($this->watcher->mailable($watcher)) {
                        $this->mailer->send(
                            $watcher['aid'],
                            'assignees-changed',
                            [
                                'hostname' => $_ENV['SYSTEM_HOSTNAME'],
                                'ticket' => $ticket['slug'],
                                'name' => $watcher['display'],
                                'project' => $project['slug'],
                                'assignees' => $assignees,
                            ],
                            "Assignees changed for Ticket {$ticket['slug']}",
                            $watcher['email'],
                            $watcher['display']
                        );
                    }
                    $this->database
                        ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                        ->execute([':url' => "/{$project['slug']}/{$ticket['slug']}", ':user' => $watcher['aid'],':ticket' => $ticket['aid'], ':content' => 'Assignees were changed.']);
                }
                $wasModified = true;
            } elseif($isContributor && isset($post['unlisted']) && !$isDone) {
                $this->database
                    ->prepare('UPDATE tickets SET `private`=:private WHERE aid=:aid')
                    ->execute([':private' => $post['unlisted'],':aid' => $ticket['aid']]);
                foreach ($this->watcher->ticket($ticket['aid'], $_SESSION['id']) as $watcher) {
                    if ($this->watcher->mailable($watcher)) {
                        $this->mailer->send(
                            $watcher['aid'],
                            'visibility-changed',
                            [
                                'hostname' => $_ENV['SYSTEM_HOSTNAME'],
                                'ticket' => $ticket['slug'],
                                'name' => $watcher['display'],
                                'project' => $project['slug'],
                                'unlisted' => $post['unlisted'],
                            ],
                            "Visibility change for Ticket {$ticket['slug']}",
                            $watcher['email'],
                            $watcher['display']
                        );
                    }
                    $this->database
                        ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                        ->execute([':url' => "/{$project['slug']}/{$ticket['slug']}", ':user' => $watcher['aid'],':ticket' => $ticket['aid'], ':content' => 'Visibility was changed.']);
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
        $stmt = $this->database->prepare('SELECT COUNT(*) FROM upvotes WHERE ticket=:ticket');
        $stmt->execute([':ticket' => $ticket['aid']]);
        $stmt2 = $this->database->prepare('SELECT `user` FROM watchers WHERE ticket=:ticket');
        $stmt2->execute([':ticket' => $ticket['aid']]);
        $stmt3 = $this->database->prepare('SELECT * FROM uploads WHERE ticket=:ticket');
        $stmt3->execute([':ticket' => $ticket['aid']]);
        $stmt4 = $this->database->prepare('SELECT `users`.`aid`,`users`.`display`,IF(assignees.ticket, 1, 0) AS assigned
FROM roles
INNER JOIN `users` ON `users`.aid=roles.`user`
LEFT JOIN assignees ON assignees.`user`=`users`.aid AND assignees.ticket=:ticket
WHERE roles.project=:project AND roles.role="contributor"');
        $stmt4->execute([':project' => $ticket['project'], ':ticket' => $ticket['aid']]);
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
                'attachments' => $stmt3->fetchAll(PDO::FETCH_ASSOC),
                'assignees' => $stmt4->fetchAll(PDO::FETCH_ASSOC),
                'isDone' => $isDone
            ]
        );
    }
}
