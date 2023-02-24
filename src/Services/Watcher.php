<?php

namespace De\Idrinth\Tickets\Services;

use Generator;
use PDO;

class Watcher
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function ticket(int $ticket, int $user): Generator
    {
        $stmt = $this->database->prepare('SELECT `users`.*
FROM watchers
INNER JOIN `users` ON watchers.`user`=`users`.aid
WHERE ticket=:ticket AND `users.aid`<>:user');
        $stmt->execute([':ticket' => $ticket, ':user' => $user]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $watcher) {
            yield $watcher;
        }
    }
    public function project(int $project, int $user): Generator
    {
        $stmt = $this->database->prepare("SELECT `users`.*
FROM roles
INNER JOIN `users` ON roles.`user`=`users`.aid
WHERE role='contributor' AND project=:project AND `users`.aid<>:user");
        $stmt->execute([':project' => $project, ':user' => $user]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $watcher) {
            yield $watcher;
        }
    }
    public function mailable(array $watcher) : bool
    {
        return $watcher['email'] && $watcher['mail_valid'] === '1' && $watcher['enable_mail_update'] === '1';
    }
}
