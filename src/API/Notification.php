<?php

namespace De\Idrinth\Tickets\API;

use PDO;

class Notification
{
    private PDO $database;
    
    public function __construct(PDO $database)
    {
        $this->database = $database;
    }
    public function run()
    {
        header('Content-Type: application/json', true);
        if (!isset($_SESSION['id'])) {
            return '[]';
        }
        $stmt = $this->database->prepare('SELECT * FROM notifications WHERE `user`=:user AND `read` IS NULL');
        $stmt->execute([':user' => $_SESSION['id']]);
        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
