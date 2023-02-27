<?php

namespace De\Idrinth\Tickets\API;

use De\Idrinth\Tickets\Services\Mailer;
use De\Idrinth\Tickets\Services\Watcher;
use PDO;

class Comment
{
    private PDO $database;
    private Watcher $watcher;
    private Mailer $mailer;
    
    public function __construct(PDO $database, Mailer $mailer, Watcher $watcher)
    {
        $this->database = $database;
        $this->watcher = $watcher;
        $this->mailer = $mailer;
    }
    public function run ($post)
    {
        if (!isset($post['key']) || $post['key'] !== $_ENV['BOT_API_KEY']) {
            header('Content-Type: application/json', true, 403);
            return '{"success":false}';
        }
        if (!isset($post['user']) || !isset($post['comment']) || !isset($post['ticket'])) {
            header('Content-Type: application/json', true, 400);
            return '{"success":false}';
        }
        header('Content-Type: application/json', true, 200);
        $stmt = $this->database->prepare('SELECT aid FROM `users` WHERE discord_name=:user');
        $stmt->execute([':user' => $post['user']]);
        $id = intval($stmt->fetchColumn(),10);
        if ($id === 0) {
            $this->database
                ->prepare('INSERT INTO `users` (discord_name,display) VALUES (:user,:user)')
                ->execute([':user' => $post['user']]);
            $id = intval($this->database->lastInsertId(), 10);
        }
        $stmt = $this->database->prepare('SELECT aid,project,private,`status` FROM tickets WHERE slug=:slug');
        $stmt->execute([':slug' => $post['ticket']]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            header('Content-Type: application/json', true, 404);
            return '{"success":false}';
        }
        $this->database
            ->prepare('INSERT INTO comments (`ticket`,`creator`,`created`,`content`) VALUES (:ticket,:creator,NOW(),:content)')
            ->execute([':ticket' => $ticket['aid'], ':creator' => $id, ':content' => $post['comment']]);
        $comment = intval($this->database->lastInsertId(), 10);
        $this->database
            ->prepare('INSERT IGNORE INTO watchers (ticket, `user`) VALUES (:id, :user)')
            ->execute([':id' => $ticket['aid'], ':user' => $_SESSION['id']]);
        $stmt = $this->database->prepare('SELECT slug,limited_access FROM projects WHERE aid=:id');
        $stmt->execute([':id' => $ticket['project']]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        $private = $project['limited_access'] === '1' || $ticket['private'] === '1';
        foreach ($this->watcher->ticket($ticket['aid'], $id) as $watcher) {
            if ($this->watcher->mailable($watcher)) {
                $this->mailer->send(
                    $watcher['aid'],
                    'new-comment',
                    [
                        'hostname' => $_ENV['SYSTEM_HOSTNAME'],
                        'ticket' => $ticket['slug'],
                        'project' => $project['slug'],
                        'name' => $watcher['display'],
                        'comment' => [
                            'content' => $post['comment'],
                            'author' => $post['user']
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
        return '{"success":true,"link":"https://' . $_ENV['SYSTEM_HOSTNAME'] . '/' . $project['slug'] . '/' . $post['ticket'] . '#c' . $comment . '","private":'. ($private ? 'true' : 'false') .'}';
    }
}
