<?php

namespace De\Idrinth\Tickets\API;

use PDO;

class NewTicket
{
    private PDO $database;
    
    public function __construct(PDO $database)
    {
        $this->database = $database;
    }
    public function run ($post)
    {
        if (!isset($post['key']) || $post['key'] !== $_ENV['BOT_API_KEY']) {
            header('Content-Type: application/json', true, 403);
            return '{"success":false}';
        }
        if (!isset($post['user']) || !isset($post['description']) || !isset($post['title']) || !isset($post['private'])) {
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
            ->prepare('INSERT INTO tickets (title,description,creator,`type`,`status`,created,modified,project,private) VALUES (:title,:description,:creator,"service",1,NOW(),NOW(),0,:private)')
            ->execute([':creator' => $id, ':title' => $post['title'], ':description' => $post['description'], ':private' => $post['private']]);
        $ticket = $this->database->lastInsertId();
        $slug = base_convert("$ticket", 10, 36);
        $this->database
            ->prepare('UPDATE tickets SET slug=:slug WHERE aid=:id')
            ->execute([':slug' => $slug, ':id' => $ticket]);
        return '{"success":true,"link":"https://' . $_ENV['SYSTEM_HOSTNAME'] . '/unknown/' . $slug . '"}';
    }
}
