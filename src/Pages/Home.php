<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Twig;
use PDO;

class Home
{
    private Twig $twig;
    private PDO $database;

    public function __construct(Twig $twig, PDO $database)
    {
        $this->twig = $twig;
        $this->database = $database;
    }
    public function run()
    {
        $tickets = $this->database->query('SELECT * from tickets')->fetchAll(PDO::FETCH_ASSOC);
        $newTickets = [];
        $wipTickets = [];
        $doneTickets = [];
        foreach ($tickets as &$ticket) {
            if ($ticket['project']['limited_access'] === '1' && !isset($_SESSION['id'])) {
                continue;
            } elseif ($ticket['project']['limited_access'] === '1' && $ticket['creator'] != $_SESSION['id']) {
                $stmt = $this->database->prepare('SELECT 1 FROM roles WHERE role="contributor" AND project=:project AND `user`=:user');
                $stmt->execute([':user' => $_SESSION['id'], ':project' => $ticket['project']]);
                if ($stmt->fetchColumn() !== '1') {
                    continue;
                }
            }
            $stmt = $this->database->prepare('SELECT * FROM stati WHERE aid=:aid');
            $stmt->execute([':aid' => $ticket['status']]);
            $ticket['status'] = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $this->database->prepare('SELECT * FROM users WHERE aid=:aid');
            $stmt->execute([':aid' => $ticket['creator']]);
            $ticket['creator'] = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $this->database->prepare('SELECT * FROM projects WHERE aid=:aid');
            $stmt->execute([':aid' => $ticket['project']]);
            $ticket['project'] = $stmt->fetch(PDO::FETCH_ASSOC);
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
        return $this->twig->render('home', ['title' => 'Home', 'newTickets' => $newTickets, 'wipTickets' => $wipTickets, 'doneTickets' => $doneTickets]);
    }
}
