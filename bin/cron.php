<?php

use Appwrite\ClamAV\Pipe;
use De\Idrinth\Tickets\Command;
use De\Idrinth\Tickets\Commands\MailToTicket;
use League\HTMLToMarkdown\HtmlConverter;
use Twig\Loader\FilesystemLoader;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Command())
    ->register(new PDO('mysql:host=' . $_ENV['DATABASE_HOST'] . ';dbname=' . $_ENV['DATABASE_DATABASE'], $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']))
    ->register(new FilesystemLoader(dirname(__DIR__) . '/templates'))
    ->register(new HtmlConverter(array('strip_tags' => true)))
    ->register(new Pipe($_ENV['CLAM_AV_SOCKET']))
    ->add('mail2ticket', MailToTicket::class)
    ->run(...$argv);