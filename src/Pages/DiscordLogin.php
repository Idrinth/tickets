<?php

namespace De\Idrinth\Tickets\Pages;

use PDO;
use Wohali\OAuth2\Client\Provider\Discord;

class DiscordLogin
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
        $provider = new Discord([
            'clientId' => $_ENV['DISCORD_CLIENT_ID'],
            'clientSecret' => $_ENV['DISCORD_CLIENT_SECRET'],
            'redirectUri' => "https://{$_ENV['SYSTEM_HOSTNAME']}/discord-login"
        ]);
        if (!isset($_GET['code'])) {
            $authUrl = $provider->getAuthorizationUrl(['scope' => 'identify']);
            $_SESSION['oauth2state'] = $provider->getState();
            header('Location: ' . $authUrl, true, 303);
            return;
        }
        if (empty($_GET['state']) || !isset($_SESSION['oauth2state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
            header('Location: /login', true, 303);
            return;
        }
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);
        $user = $provider->getResourceOwner($token);
        $stmt = $this->database->prepare('SELECT aid FROM users WHERE discord=:discordId');
        $stmt->execute([
            ':discordId' => $user->getId(),
        ]);
        $_SESSION['id'] = intval($stmt->fetchColumn(), 10);
        if ($_SESSION['id'] === 0) {
            $stmt = $this->database->prepare('SELECT aid FROM users WHERE discord_name=:discord AND NOT discord');
            $stmt->execute([
                ':discord' => $user->getUsername() . '#' . $user->getDiscriminator(),
            ]);
            $_SESSION['id'] = intval($stmt->fetchColumn(), 10);
        }
        if ($_SESSION['id'] === 0) {
            $this->database
                ->prepare("INSERT INTO users (discord, display,discord_name) VALUES (:discordId, :display,:display)")
                ->execute([
                    ':discordId' => $user->getId(),
                    ':display' => $user->getUsername() . '#' . $user->getDiscriminator(),
                ]);
            $stmt = $this->database
                ->prepare("SELECT aid FROM users WHERE discord=:discordId");
            $stmt->execute([
                ':discordId' => $user->getId(),
            ]);
            $_SESSION['id'] = intval($stmt->fetchColumn(), 10);
        }
        if (isset($_SESSION['redirect'])) {
            header('Location: ' . $_SESSION['redirect'], true, 303);
            return;
        }
        header('Location: /', true, 303);
    }
}
