<?php

namespace De\Idrinth\Tickets;

class Twig
{
    private Environment $twig;
    private PDO $database;

    public function __construct(Environment $twig, \PDO $database)
    {
        $this->twig = $twig;
        $this->database = $database;
    }
    public function render(string $template, array $context = []): string
    {
        $categories = $this->database->query("SELECT name, slug FROM projects");
        $context['projects'] = $categories;
        return $this->twig->render('imprint.twig', $context);
    }
}
