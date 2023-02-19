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
            $stmt = $this->database->prepare('SELECT * FROM stati WHERE aid=:aid');
            $stmt->execute([':aid' => $ticket['status']]);
            $ticket['status'] = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $this->database->prepare('SELECT * FROM projects WHERE aid=:aid');
            $stmt->execute([':aid' => $ticket['project']]);
            $ticket['project'] = $stmt->fetch(PDO::FETCH_ASSOC);
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
        return $this->twig->render('home', ['newTickets' => $newTickets, 'wipTickets' => $wipTickets, 'doneTickets' => $doneTickets]);
    }
}
