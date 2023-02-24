<?php

namespace De\Idrinth\Tickets\Commands;

use PDO;
use PhpImap\Exceptions\ConnectionException;
use PhpImap\Mailbox;

class MailToTicket
{
    private PDO $database;
    private HtmlConverter $converter;
    private Mailer $mailer;

    public function __construct(PDO $database, HtmlConverter $converter, \De\Idrinth\Tickets\Mailer $mailer)
    {
        $this->converter = $converter;
        $this->database = $database;
        $this->mailer = $mailer;
    }

    public function run()
    {
        $mailbox = new Mailbox(
            '{' . $_ENV['MAIL_HOST'] . ':' . $_ENV['MAIL_PORT_IMAP'] . '/imap/ssl}INBOX',
            $_ENV['MAIL_USER'],
            $_ENV['MAIL_PASSWORD']
        );
        $mailbox->setConnectionArgs(CL_EXPUNGE | OP_SECURE);

        try {
            $mailIds = $mailbox->searchMailbox('ALL');
            if(!$mailIds) {
                    return;
            }
            foreach ($mailIds as $mailId) {
                $mail = $mailbox->getMail($mailId);
                $fromMail = $mail->fromAddress;
                $fromName = $mail->fromName;
                $subject = $mail->subject ?? '';
                if ($mail->textHTML === null && $mail->textPlain === null) {
                    $body = '';
                } elseif ($mail->textPlain) {
                    $body = "```\n{$mail->textPlain}\n```";
                } else {
                    $body = $this->converter->convert($mail->textHTML);
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
                        if (intval($watcher['user'], 10) !== $_SESSION['id']) {
                            $this->database
                                ->prepare('INSERT INTO notifications (`url`,`user`,`ticket`,`created`,`content`) VALUES (:url,:user,:ticket,NOW(),:content)')
                                ->execute([':url' => '/'.$post['project'].'/'.$slug, ':user' => $watcher['user'],':ticket' => $id, ':content' => 'A new ticket was written.']);
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
                        'Ticket Created',
                        $fromMail,
                        $fromName
                    );
                }
                $mailbox->deleteMail($mailId);
            }
        } catch(ConnectionException $ex) {
            echo "IMAP connection failed: " . implode(",", $ex->getErrors('all'));
            die(1);
        }
    }
}
