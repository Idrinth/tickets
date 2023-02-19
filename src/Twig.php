<?php

namespace De\Idrinth\Tickets;

use PDO;
use Twig\Environment;

class Twig
{
    private Environment $twig;
    private PDO $database;

    public function __construct(Environment $twig, PDO $database)
    {
        $this->twig = $twig;
        $this->database = $database;
    }
    public function render(string $template, array $context = []): string
    {
        $context['projects'] = $this->database
            ->query("SELECT * FROM projects")
            ->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->database
            ->prepare("SELECT * FROM users WHERE aid=:aid");
        $stmt->execute([
            ':aid' => $_SESSION['id'],
        ]);
        $context['user'] = $stmt->fetch(PDO::FETCH_ASSOC);
        return $this->twig->render("$template.twig", $context);
    }
}
