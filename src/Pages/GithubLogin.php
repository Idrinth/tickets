<?php

namespace De\Idrinth\Tickets\Pages;

use PDO;
use SocialConnect\OAuth2\Provider\GitHub;

class GithubLogin
{
    private $database;
    public function __construct(PDO $database)
    {
        $this->database = $database;
    }
    public function run(array $post): void
    {
        if (($_SESSION['id'] ?? 0) !== 0) {
            header('Location: /', true, 303);
            return;
        }
        $provider = new GitHub([
            'clientId' => $_ENV['GITHUB_CLIENT_ID'],
            'clientSecret' => $_ENV['DGITHUB_CLIENT_SECRET'],
            'redirectUri' => 'https://tickets.idrinth.de/github-login'
        ]);
        if (!isset($_GET['code'])) {
            $authUrl = $provider->getAuthorizeUri();
            header('Location: ' . $authUrl, true, 303);
            return;
        }
        if (empty($_GET['state'])) {
            header('Location: /login', true, 303);
            return;
        }
        $token = $provider->getAccessToken($_GET['code']);
        $user = $provider->getIdentity($token);
        $stmt = $this->database->prepare('SELECT aid FROM users WHERE github=:github');
        $stmt->execute([
            ':github' => $user->id,
        ]);
        $_SESSION['id'] = intval($stmt->fetchColumn(), 10);
        if ($_SESSION['id'] === 0) {
            $this->database
                ->prepare("INSERT INTO users (github, display) VALUES (:github, :display)")
                ->execute([
                    ':github' => $user->getId(),
                    ':display' => $user->fullname,
                ]);
            $stmt = $this->database
                ->prepare("SELECT aid FROM users WHERE github=:github");
            $stmt->execute([
                ':github' => $user->getId(),
            ]);
            $_SESSION['id'] = intval($stmt->fetchColumn(), 10);
        }
        $this->database
            ->prepare("UPDATE users SET display=:display WHERE github=:github")
            ->execute([
                ':github' => $user->getId(),
                ':display' => $user->fullname,
            ]);
        if (isset($_SESSION['redirect'])) {
            header('Location: ' . $_SESSION['redirect'], true, 303);
            return;
        }
        header('Location: /', true, 303);
    }
}
