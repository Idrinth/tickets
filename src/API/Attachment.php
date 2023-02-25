<?php

namespace De\Idrinth\Tickets\API;

use PDO;

class Attachment
{
    private PDO $database;
    
    public function __construct(PDO $database)
    {
        $this->database = $database;
    }
    public function run($post, $slug, $id)
    {
        $stmt = $this->database->prepare('SELECT `data`,name,mime FROM uploads WHERE aid=:id AND `ticket` IN (SELECT aid FROM tickets WHERE slug=:slug)');
        $stmt->execute([':id' => $id, ':slug' => $slug]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        header('Content-Type: ' . $file['mime'], true);
        if (!in_array($file['mime'], ['image/png', 'image/jpeg', 'image/gif'], true)) {
            header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
        }
        return $file['data'];
    }
}
