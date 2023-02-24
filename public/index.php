<?php

use De\Idrinth\Tickets\API\Attachment;
use De\Idrinth\Tickets\API\Notification;
use De\Idrinth\Tickets\Application;
use De\Idrinth\Tickets\Pages\DiscordLogin;
use De\Idrinth\Tickets\Pages\EmailBlacklist;
use De\Idrinth\Tickets\Pages\Home;
use De\Idrinth\Tickets\Pages\Imprint;
use De\Idrinth\Tickets\Pages\Login;
use De\Idrinth\Tickets\Pages\MailLogin;
use De\Idrinth\Tickets\Pages\NewTicket;
use De\Idrinth\Tickets\Pages\Profile;
use De\Idrinth\Tickets\Pages\Project;
use De\Idrinth\Tickets\Pages\Ticket;
use De\Idrinth\Tickets\Pages\Times;
use Twig\Loader\FilesystemLoader;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Application())
    ->register(new PDO('mysql:host=' . $_ENV['DATABASE_HOST'] . ';dbname=' . $_ENV['DATABASE_DATABASE'], $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']))
    ->register(new FilesystemLoader(dirname(__DIR__) . '/templates'))
    ->get('/', Home::class)
    ->get('/api/notifications', Notification::class)
    ->get('/api/attachments/{ticket:[a-z0-9]+}/{id:[0-9]+}', Attachment::class)
    ->get('/imprint', Imprint::class)
    ->get('/discord-login', DiscordLogin::class)
    ->get('/email-login/{key:[a-z0-9A-Z]+}', MailLogin::class)
    ->get('/email-blacklist/{key:[a-z0-9]+}', EmailBlacklist::class)
    ->get('/login', Login::class)
    ->post('/login', Login::class)
    ->get('/post', Login::class)
    ->get('/times', Times::class)
    ->get('/new', NewTicket::class)
    ->post('/new', NewTicket::class)
    ->get('/profile', Profile::class)
    ->post('/profile', Profile::class)
    ->get('/{project:[a-z-]+}', Project::class)
    ->get('/{project:[a-z-]+}/{ticket:[a-z0-9]+}', Ticket::class)
    ->post('/{project:[a-z-]+}/{ticket:[a-z0-9]+}', Ticket::class)
    ->run();