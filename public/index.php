<?php

use De\Idrinth\Tickets\Application;
use De\Idrinth\Tickets\Pages\Home;
use De\Idrinth\Tickets\Pages\Imprint;
use De\Idrinth\Tickets\Pages\Login;
use De\Idrinth\Tickets\Pages\Project;
use De\Idrinth\Tickets\Pages\Ticket;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Application())
    ->register(new PDO($dsn, $username, $passwd))
    ->register(new Environment(new FilesystemLoader(dirname(__DIR__) . '/templates')))
    ->get('/', Home::class)
    ->post('/', Home::class)
    ->get('/login', Login::class)
    ->get('/{project:[a-z-]+}', Project::class)
    ->post('/{project:[a-z-]+}', Project::class)
    ->get('/{project:[a-z-]+}/{ticket:[a-zA-Z0-9]+}', Ticket::class)
    ->post('/{project:[a-z-]+}/{ticket:[a-zA-Z0-9]+}', Ticket::class)
    ->get('/imprint', Imprint::class)
    ->run();