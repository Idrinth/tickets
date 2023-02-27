<?php

namespace De\Idrinth\Tickets\API;

class Comment
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
        if (!isset($post['user']) || !isset($post['content']) || !isset($post['ticket'])) {
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
            ->execute([':ticket' => $ticket['aid'], ':creator' => $id, ':content' => $post['content']]);
        $stmt = $this->database->prepare('SELECT slug,limited_access FROM projects WHERE aid=:id');
        $stmt->execute([':id' => $ticket['project']]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        $private = $project['limited_access'] === '1' || $ticket['private'] === '1';
        return '{"success":true,"link":"https://' . $_ENV['SYSTEM_HOSTNAME'] . '/' . $project['slug'] . '/' . $post['ticket'] . '","private":'. ($private ? 'true' : 'false') .'}';
    }
}
