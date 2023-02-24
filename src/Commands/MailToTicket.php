<?php

namespace De\Idrinth\Tickets\Commands;

use De\Idrinth\Tickets\Mailer;
use League\HTMLToMarkdown\HtmlConverter;
use PDO;
use PhpImap\Exceptions\ConnectionException;
use PhpImap\IncomingMail;
use PhpImap\Mailbox;

class MailToTicket
{
    private PDO $database;
    private HtmlConverter $converter;
    private Mailer $mailer;

    public function __construct(PDO $database, HtmlConverter $converter, Mailer $mailer)
    {
        $this->converter = $converter;
        $this->database = $database;
        $this->mailer = $mailer;
    }

    private function handleMail(IncomingMail $mail): void
    {
        $fromMail = $mail->fromAddress;
        $fromName = $mail->fromName;
        $subject = $mail->subject ?? '';
        if ($mail->textHtml === null && $mail->textPlain === null) {
            $body = '';
        } elseif ($mail->textPlain) {
            $body = "```\n{$mail->textPlain}\n```";
        } else {
            $body = $this->converter->convert($mail->textHtml);
        }
        if ($body && $subject) {
            $stmt = $this->database->prepare('SELECT aid FROM `users` WHERE email=:email');
            $stmt->execute([':email' => $fromMail]);
            $user = intval($stmt->fetchColumn(), 10);
            if ($user === 0) {
                $this->database
                    ->prepare('INSERT INTO `users` (display,email,mail_valid,enable_mail_update) VALUES (:name,:email,1,1)')
                    ->execute([':name' => $fromName, ':email' => $fromMail]);
                $user = intval($this->database->lastInsertId(), 10);
            }
            $matches = [];
            if (preg_match('/(^| |:)Ticket\s+([a-z0-9]+)($| )/', $subject, $matches)) {
                $stmt = $this->database->prepare('SELECT aid,slug FROM tickets WHERE slug=:slug');
                $stmt->execute([':slug' => $matches[2]]);
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($ticket) {
                    $this->database
                        ->prepare('INSERT INTO comments (`ticket`,`creator`,`created`,`content`) VALUES (:ticket,:user,NOW(),:content)')
                        ->execute([':ticket' => $ticket['aid'],':user' => $user,':content' => $body]);
                    $comment = $this->database->lastInsertId();
                    $stmt = $this->database->prepare('SELECT `users`.*
FROM watchers
INNER JOIN `users` ON watchers.`user`=`users`.aid
WHERE ticket=:ticket AND `users.aid`<>:user');
                    $stmt->execute([':ticket' => $ticket['aid'],':user' => $user]);
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $watcher) {
                        if ($watcher['email'] && $watcher['mail_valid'] === '1' && $watcher['enable_mail_update'] === '1') {
                            $this->mailer->send(
                                $watcher['aid'],
                                'new-comment',
                                [
                                    'hostname' => $_ENV['SYSTEM_HOSTNAME'],
                                    'ticket' => $matches[2],
                                    'project' => 'unknown',
                                    'name' => $watcher['display'],
                                    'comment' => [
                                        'content' => $body,
                                        'author' => $fromName
                                    ],
                                ],
                                "New comment on Ticket $matches[2]",
                                $watcher['email'],
                                $watcher['display']
                            );
                        }
                        $this->database
                            ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                            ->execute([':url' => "/unknown/{$ticket['slug']}#c{$comment}", ':user' => $watcher['aid'],':ticket' => $ticket['aid'], ':content' => 'A new comment was written.']);
                    }
                    $this->database
                        ->prepare('INSERT IGNORE INTO watchers (ticket, `user`) VALUES (:id, :user)')
                        ->execute([':id' => $ticket['aid'], ':user' => $_SESSION['id']]);
                    $this->database
                        ->prepare('UPDATE tickets SET modified=NOW() WHERE aid=:aid')
                        ->execute([':aid' => $ticket['aid']]);
                    $this->mailer->send(
                        $user,
                        'comment-created',
                        [
                            'hostname' => $_ENV['SYSTEM_HOSTNAME'],
                            'ticket' => $matches[2],
                            'project' => 'unknown',
                            'name' => $fromName,
                        ],
                        "Commented on Ticket $matches[2]",
                        $fromMail,
                        $fromName
                    );
                    return;
                }
            }
            $project = 0;
            if (stripos($subject, 'blacklist') !== false || stripos($subject, 'OptOut') !== false || stripos($subject, 'opt-out') !== false) {
                $project = 31;
            }
            $stmt = $this->database->prepare("INSERT INTO tickets (`title`,`description`,`creator`,`type`,`status`,`created`,`modified`,`project`) VALUES (:title,:description,:creator,:type,1,NOW(),NOW(),:project)");
            $stmt->execute([':title' => $subject, ':description' => $body, ':creator' => $user,':type' => 'service',':project' => $project]);
            $id = $this->database->lastInsertId();
            $slug = base_convert("$id", 10, 36);
            $this->database
                ->prepare('UPDATE tickets SET slug=:slug WHERE aid=:id')
                ->execute([':slug' => $slug, ':id' => $id]);
            $stmt = $this->database->prepare("SELECT `user` FROM roles WHERE role='contributor' AND project=:project");
            $stmt->execute([':project' => $project]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $watcher) {
                if (intval($watcher['user'], 10) !== $user) {
                    $this->database
                        ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                        ->execute([':url' => '/unknown/' . $slug, ':user' => $watcher['user'],':ticket' => $id, ':content' => 'A new ticket was written.']);
                }
            }
            $this->database
                ->prepare('INSERT IGNORE INTO watchers (ticket, `user`) VALUES (:id, :user)')
                ->execute([':id' => $id, ':user' => $user]);
            $this->mailer->send(
                $user,
                'ticket-created',
                [
                    'hostname' => $_ENV['SYSTEM_HOSTNAME'],
                    'ticket' => $slug,
                    'project' => 'unknown',
                    'name' => $fromName,
                ],
                "Ticket $slug Created",
                $fromMail,
                $fromName
            );
        }
    }
    public function run()
    {
        $mailbox = new Mailbox(
            '{' . $_ENV['MAIL_HOST'] . ':' . $_ENV['MAIL_PORT_IMAP'] . '/imap/ssl}INBOX',
            $_ENV['MAIL_USER'],
            $_ENV['MAIL_PASSWORD']
        );
        $mailbox->setConnectionArgs(CL_EXPUNGE);

        try {
            $mailIds = $mailbox->searchMailbox('ALL');
            if(!$mailIds) {
                    return;
            }
            foreach ($mailIds as $mailId) {
                $this->handleMail($mailbox->getMail($mailId));
                $mailbox->deleteMail($mailId);
            }
        } catch(ConnectionException $ex) {
            echo "IMAP connection failed: " . implode(",", $ex->getErrors('all'));
            die(1);
        }
    }
}
