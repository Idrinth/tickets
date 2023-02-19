<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Twig;

class NewTicket
{
    private Twig $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }
    public function run($post)
    {
        return $this->twig->render('new');
    }
}
