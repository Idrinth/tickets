/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE TABLE IF NOT EXISTS `assignees` (
  `user` bigint(20) unsigned NOT NULL,
  `ticket` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`user`,`ticket`),
  KEY `assignees_fk1` (`ticket`),
  CONSTRAINT `assignees_fk0` FOREIGN KEY (`user`) REFERENCES `users` (`aid`),
  CONSTRAINT `assignees_fk1` FOREIGN KEY (`ticket`) REFERENCES `tickets` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `comments` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket` bigint(20) unsigned NOT NULL,
  `creator` bigint(20) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `content` text NOT NULL,
  PRIMARY KEY (`aid`),
  KEY `comments_fk0` (`ticket`),
  KEY `comments_fk1` (`creator`),
  CONSTRAINT `comments_fk0` FOREIGN KEY (`ticket`) REFERENCES `tickets` (`aid`),
  CONSTRAINT `comments_fk1` FOREIGN KEY (`creator`) REFERENCES `users` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `email_blacklist` (
  `email` varchar(255) NOT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user` bigint(20) unsigned NOT NULL,
  `ticket` bigint(20) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `content` text NOT NULL,
  `read` datetime DEFAULT NULL,
  `notified` datetime DEFAULT NULL,
  `url` text NOT NULL,
  `mail_content` text DEFAULT NULL,
  PRIMARY KEY (`aid`),
  KEY `notifications_fk1` (`ticket`),
  KEY `user_read` (`user`,`read`),
  CONSTRAINT `notifications_fk0` FOREIGN KEY (`user`) REFERENCES `users` (`aid`),
  CONSTRAINT `notifications_fk1` FOREIGN KEY (`ticket`) REFERENCES `tickets` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `projects` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `github` text DEFAULT NULL,
  `nexusmods` text DEFAULT NULL,
  `url` text DEFAULT NULL,
  `limited_access` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`aid`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `url` (`url`) USING HASH,
  UNIQUE KEY `github_project` (`github`) USING HASH,
  UNIQUE KEY `nexusmod_project` (`nexusmods`) USING HASH
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `roles` (
  `user` bigint(20) unsigned NOT NULL,
  `project` bigint(20) unsigned NOT NULL,
  `role` enum('member','contributor') NOT NULL,
  PRIMARY KEY (`user`,`project`),
  KEY `role_fk1` (`project`),
  CONSTRAINT `role_fk0` FOREIGN KEY (`user`) REFERENCES `users` (`aid`),
  CONSTRAINT `role_fk1` FOREIGN KEY (`project`) REFERENCES `projects` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `stati` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` enum('new','wip','done') NOT NULL,
  PRIMARY KEY (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `status_changes` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created` datetime NOT NULL,
  `ticket` bigint(20) unsigned NOT NULL,
  `creator` bigint(20) unsigned NOT NULL,
  `previous` bigint(20) unsigned NOT NULL,
  `current` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`aid`),
  KEY `status_changes_fk0` (`ticket`),
  KEY `status_changes_fk1` (`creator`),
  KEY `status_changes_fk2` (`previous`),
  KEY `status_changes_fk3` (`current`),
  CONSTRAINT `status_changes_fk0` FOREIGN KEY (`ticket`) REFERENCES `tickets` (`aid`),
  CONSTRAINT `status_changes_fk1` FOREIGN KEY (`creator`) REFERENCES `users` (`aid`),
  CONSTRAINT `status_changes_fk2` FOREIGN KEY (`previous`) REFERENCES `stati` (`aid`),
  CONSTRAINT `status_changes_fk3` FOREIGN KEY (`current`) REFERENCES `stati` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tickets` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `creator` bigint(20) unsigned NOT NULL,
  `type` enum('feature','bug','service') NOT NULL,
  `status` bigint(20) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  `project` bigint(20) unsigned NOT NULL,
  `private` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`aid`),
  UNIQUE KEY `slug` (`slug`),
  KEY `tickets_fk0` (`creator`),
  KEY `tickets_fk1` (`status`),
  KEY `tickets_fk2` (`project`),
  CONSTRAINT `tickets_fk0` FOREIGN KEY (`creator`) REFERENCES `users` (`aid`),
  CONSTRAINT `tickets_fk1` FOREIGN KEY (`status`) REFERENCES `stati` (`aid`),
  CONSTRAINT `tickets_fk2` FOREIGN KEY (`project`) REFERENCES `projects` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `times` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user` bigint(20) unsigned NOT NULL,
  `ticket` bigint(20) unsigned NOT NULL,
  `day` date NOT NULL,
  `duration` int(11) NOT NULL,
  `status` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`aid`),
  UNIQUE KEY `user_ticket_day_status` (`user`,`ticket`,`day`,`status`),
  KEY `times_fk1` (`ticket`),
  KEY `times_fk2` (`status`),
  CONSTRAINT `times_fk0` FOREIGN KEY (`user`) REFERENCES `users` (`aid`),
  CONSTRAINT `times_fk1` FOREIGN KEY (`ticket`) REFERENCES `tickets` (`aid`),
  CONSTRAINT `times_fk2` FOREIGN KEY (`status`) REFERENCES `stati` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `uploads` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket` bigint(20) unsigned NOT NULL,
  `user` bigint(20) unsigned NOT NULL,
  `uploaded` datetime NOT NULL,
  `data` blob NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`aid`),
  KEY `uploads_fk0` (`ticket`),
  KEY `uploads_fk1` (`user`),
  CONSTRAINT `uploads_fk0` FOREIGN KEY (`ticket`) REFERENCES `tickets` (`aid`),
  CONSTRAINT `uploads_fk1` FOREIGN KEY (`user`) REFERENCES `users` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `upvotes` (
  `user` bigint(20) unsigned NOT NULL,
  `ticket` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`user`,`ticket`),
  KEY `upvotes_fk1` (`ticket`),
  CONSTRAINT `upvotes_fk0` FOREIGN KEY (`user`) REFERENCES `users` (`aid`),
  CONSTRAINT `upvotes_fk1` FOREIGN KEY (`ticket`) REFERENCES `tickets` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `display` varchar(255) NOT NULL,
  `discord` bigint(20) unsigned DEFAULT NULL,
  `nexusmods` bigint(20) unsigned DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `enable_discord_update` tinyint(4) unsigned NOT NULL DEFAULT 0,
  `enable_mail_update` tinyint(4) unsigned NOT NULL DEFAULT 0,
  `valid_until` datetime DEFAULT NULL,
  `mail_valid` tinyint(4) unsigned NOT NULL DEFAULT 0,
  `discord_name` varchar(255) DEFAULT NULL,
  `collect_mails` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`aid`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `discord` (`discord`),
  UNIQUE KEY `nexusmods` (`nexusmods`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `watchers` (
  `user` bigint(20) unsigned NOT NULL,
  `ticket` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`user`,`ticket`),
  KEY `watchers_fk1` (`ticket`),
  CONSTRAINT `watchers_fk0` FOREIGN KEY (`user`) REFERENCES `users` (`aid`),
  CONSTRAINT `watchers_fk1` FOREIGN KEY (`ticket`) REFERENCES `tickets` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
