<?php

namespace De\Idrinth\Tickets\Pages;

use De\Idrinth\Tickets\Twig;
use PDO;

class Times
{
    private PDO $database;
    private Twig $twig;

    public function __construct(PDO $database, Twig $twig)
    {
        $this->database = $database;
    }
    public function run(array $post)
    {
        if (($_SESSION['id'] ?? 0) === 0) {
            header('Location: /', true, 303);
            return;
        }
        $stmt = $this->database->prepare('SELECT `times`.`day`,SUM(`times`.`duration`) AS `duration`,`stati`.`name` AS `status`,`users`.`display` AS `user`,`projects`.`name` AS `project`
FROM `times`
INNER JOIN `users` ON `users`.`aid`=`times`.`user`
INNER JOIN `stati` ON `stati`.`aid`=`times`.`status`
INNER JOIN `tickets` ON `tickets`.`aid`=`times`.`ticket`
INNER JOIN `projects` ON `projects`.`aid`=`tickets`.`project`
GROUP BY `projects`.`aid`,`users`.`aid`,`stati`.`aid`,`times`.`day`');
        $stmt->execute();
        return $this->twig->render(
            'times',
            [
                'title' => 'Time Tracking',
                'times' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            ]
        );
    }
}
