<?php

use De\Idrinth\Tickets\Application;
use De\Idrinth\Tickets\Pages\Home;
use De\Idrinth\Tickets\Pages\Imprint;
use De\Idrinth\Tickets\Pages\Login;
use De\Idrinth\Tickets\Pages\NewTicket;
use De\Idrinth\Tickets\Pages\Project;
use De\Idrinth\Tickets\Pages\Ticket;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Application())
    ->register(new PDO('mysql:host=' . $_ENV['DATABASE_HOST'] . ';dbname=' . $_ENV['DATABASE_DATABASE'], $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']))
    ->register(new Environment(new FilesystemLoader(dirname(__DIR__) . '/templates')))
    ->get('/', Home::class)
    ->get('/imprint', Imprint::class)
    ->get('/login', Login::class)
    ->get('/new', NewTicket::class)
    ->get('/{project:[a-z-]+}', Project::class)
    ->get('/{project:[a-z-]+}/{ticket:[a-zA-Z0-9]+}', Ticket::class)
    ->post('/{project:[a-z-]+}/{ticket:[a-zA-Z0-9]+}', Ticket::class)
    ->run();