<?php

namespace De\Idrinth\Tickets\Pages;

class Project
{
    private Twig $twig;
    private PDO $database;

    public function __construct(Twig $twig, PDO $database)
    {
        $this->twig = $twig;
        $this->database = $database;
    }
    public function run($post, $slug)
    {
        $stmt = $this->database->prepare('SELECT * FROM projects WHERE slug=:slug');
        $stmt->execute([':slug' => $slug]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$project) {
            header('Location: /', true, 303);
            return;
        }
        $tickets = $this->database->query("SELECT * from tickets WHERE project={$project['aid']}")->fetchAll(PDO::FETCH_ASSOC);
        $newTickets = [];
        $wipTickets = [];
        $doneTickets = [];
        foreach ($tickets as &$ticket) {
            $stmt = $this->database->prepare('SELECT * FROM stati WHERE aid=:aid');
            $stmt->execute([':aid' => $ticket['status']]);
            $ticket['status'] = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $this->database->prepare('SELECT * FROM users WHERE aid=:aid');
            $stmt->execute([':aid' => $ticket['creator']]);
            $ticket['creator'] = $stmt->fetch(PDO::FETCH_ASSOC);
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
        return $this->twig->render('project', ['project' => $project, 'newTickets' => $newTickets, 'wipTickets' => $wipTickets, 'doneTickets' => $doneTickets]);
    }
}
