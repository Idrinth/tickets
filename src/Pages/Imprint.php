<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Twig;

class Imprint
{
    private Twig $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }
    public function run()
    {
        return $this->twig->render('imprint.twig');
    }
}
