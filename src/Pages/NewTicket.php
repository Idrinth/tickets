<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Services\Watcher;
use De\Idrinth\Tickets\Twig;
use PDO;

class NewTicket
{
    private Twig $twig;
    private PDO $database;
    private Watcher $watcher;

    public function __construct(Twig $twig, PDO $database, Watcher $watcher)
    {
        $this->twig = $twig;
        $this->database = $database;
        $this->watcher = $watcher;
    }
    public function run($post)
    {
        if (isset($_SESSION['id']) && isset($post['title']) && isset($post['description']) && isset($post['type']) && isset($post['project'])) {
            $stmt = $this->database->prepare("SELECT aid FROM projects WHERE slug=:slug");
            $stmt->execute(['slug' => $post['project']]);
            $project = $stmt->fetchColumn();
            $stmt = $this->database->prepare("INSERT INTO tickets (`title`,`description`,`creator`,`type`,`status`,`created`,`modified`,`project`) VALUES (:title,:description,:creator,:type,1,NOW(),NOW(),:project)");
            $stmt->execute([':title' => $post['title'], ':description' => $post['description'], ':creator' => $_SESSION['id'],':type' => $post['type'],':project' => $project]);
            $id = $this->database->lastInsertId();
            $slug = base_convert("$id", 10, 36);
            $this->database
                ->prepare('UPDATE tickets SET slug=:slug WHERE aid=:id')
                ->execute([':slug' => $slug, ':id' => $id]);
            foreach ($this->watcher->project($project, $_SESSION['id']) as $watcher) {
                if ($this->watcher->mailable($watcher)) {
                    //@todo
                }
                $this->database
                    ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                    ->execute([':url' => '/'.$post['project'].'/'.$slug, ':user' => $watcher['user'],':ticket' => $id, ':content' => 'A new ticket was written.']);
            }
            $this->database
                ->prepare('INSERT IGNORE INTO watchers (ticket, `user`) VALUES (:id, :user)')
                ->execute([':id' => $id, ':user' => $_SESSION['id']]);
            header('Location: /'.$post['project'].'/'.$slug, true, 303);
            return;
        }
        return $this->twig->render('new', ['title' => 'New Ticket', 'targetmail' => $_ENV['MAIL_FROM_MAIL']]);
    }
}
