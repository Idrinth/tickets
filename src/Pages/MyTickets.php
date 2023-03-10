<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Twig;
use PDO;

class MyTickets {

    private Twig $twig;
    private PDO $database;

    public function __construct(Twig $twig, PDO $database)
    {
        $this->twig = $twig;
        $this->database = $database;
    }
    public function run()
    {
        if (!isset($_SESSION['id'])) {
            header('Location: /login', true, 303);
            return;
        }
        $tickets = $this->database
            ->query("SELECT * from tickets WHERE creator={$_SESSION['id']} OR aid IN (SELECT ticket FROM assignees WHERE `user`={$_SESSION['id']})")
            ->fetchAll(PDO::FETCH_ASSOC);
        $newTickets = [];
        $wipTickets = [];
        $doneTickets = [];
        foreach ($tickets as &$ticket) {
            $stmt = $this->database->prepare('SELECT * FROM projects WHERE aid=:aid');
            $stmt->execute([':aid' => $ticket['project']]);
            $ticket['project'] = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $this->database->prepare('SELECT * FROM stati WHERE aid=:aid');
            $stmt->execute([':aid' => $ticket['status']]);
            $ticket['status'] = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $this->database->prepare('SELECT * FROM users WHERE aid=:aid');
            $stmt->execute([':aid' => $ticket['creator']]);
            $ticket['creator'] = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $this->database->prepare('SELECT COUNT(*) FROM upvotes WHERE ticket=:aid');
            $stmt->execute([':aid' => $ticket['aid']]);
            $ticket['upvotes'] = $stmt->fetchColumn();
            switch ($ticket['status']['type']) {
                case 'new':
                    $newTickets[] = $ticket;
                    break;
                case 'wip':
                    $wipTickets[] = $ticket;
                    break;
                case 'done':
                    $doneTickets[] = $ticket;
                    break;
            }
        }
        usort($newTickets, function(array $t1, array $t2) {
            return $t2['upvotes'] - $t1['upvotes'];
        });
        usort($wipTickets, function(array $t1, array $t2) {
            return $t2['upvotes'] - $t1['upvotes'];
        });
        usort($doneTickets, function(array $t1, array $t2) {
            return $t2['upvotes'] - $t1['upvotes'];
        });
        return $this->twig->render(
            'my-tickets',
            [
                'title' => 'My Tickets',
                'newTickets' => $newTickets,
                'wipTickets' => $wipTickets,
                'doneTickets' => $doneTickets,
            ]
        );
    }
}
