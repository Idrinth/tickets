<?php

namespace De\Idrinth\Tickets;

use Parsedown;
use PDO;
use Twig\Environment;
use Twig\TwigFilter;

class Twig
{
    private Environment $twig;
    private PDO $database;

    public function __construct(Environment $twig, PDO $database, Parsedown $pd)
    {
        $this->twig = $twig;
        $this->database = $database;
        $pd->setSafeMode(true);
        $this->twig->addFilter(new TwigFilter('markdown', function ($content) use ($pd) {
            return $pd->text($content);
        }));
    }
    public function render(string $template, array $context = []): string
    {
        $context['projects'] = $this->database
            ->query("SELECT * FROM projects WHERE aid > 0")
            ->fetchAll(PDO::FETCH_ASSOC);
        $context['user'] = [];
        if (isset($_SESSION['id'])) {
            $stmt = $this->database
                ->prepare("SELECT * FROM users WHERE aid=:aid");
            $stmt->execute([
                ':aid' => $_SESSION['id'],
            ]);
            $context['user'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return $this->twig->render("$template.twig", $context);
    }
}
