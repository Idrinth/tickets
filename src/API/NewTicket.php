<?php

namespace De\Idrinth\Tickets\API;

use De\Idrinth\Tickets\Services\Mailer;
use De\Idrinth\Tickets\Services\Watcher;
use PDO;

class NewTicket
{
    private PDO $database;
    private Watcher $watcher;
    private Mailer $mailer;

    public function __construct(PDO $database, Watcher $watcher, Mailer $mailer)
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
        if (!isset($post['user']) || !isset($post['description']) || !isset($post['title']) || !isset($post['private']) || !isset($post['type'])) {
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
        $this->database
            ->prepare('INSERT INTO tickets (title,description,creator,`type`,`status`,created,modified,project,private) VALUES (:title,:description,:creator,:type,1,NOW(),NOW(),0,:private)')
            ->execute([':creator' => $id, ':title' => $post['title'], ':description' => $post['description'], ':private' => $post['private'], ':type' => $post['type']]);
        $ticket = $this->database->lastInsertId();
        $slug = base_convert("$ticket", 10, 36);
        $this->database
            ->prepare('UPDATE tickets SET slug=:slug WHERE aid=:id')
            ->execute([':slug' => $slug, ':id' => $ticket]);
        foreach ($this->watcher->project(0, $id) as $watcher) {
            if ($this->watcher->mailable($watcher)) {
                $this->mailer->send(
                    $watcher['aid'],
                    'new-ticket',
                    [
                        'hostname' => $_ENV['SYSTEM_HOSTNAME'],
                        'ticket' => $slug,
                        'project' => 'unknown',
                        'author' => $post['user'],
                        'title' => $post['title'],
                    ],
                    "Ticket $slug Created",
                    $watcher['email'],
                    $watcher['display']
                );
            }
            $this->database
                ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                ->execute([':url' => '/'.$post['project'].'/'.$slug, ':user' => $watcher['user'],':ticket' => $id, ':content' => 'A new ticket was written.']);
        }
        $this->database
            ->prepare('INSERT IGNORE INTO watchers (ticket, `user`) VALUES (:id, :user)')
            ->execute([':id' => $ticket, ':user' => $id]);
        return '{"success":true,"link":"https://' . $_ENV['SYSTEM_HOSTNAME'] . '/unknown/' . $slug . '"}';
    }
}
