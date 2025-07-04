-- Warframe Inventar-Terminal - Datenbank Schema
-- Version 1.0

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- -----------------------------------------------------
-- Table `users`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenno_name` VARCHAR(255) NOT NULL UNIQUE,
  `ingame_name` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `tenno_name_UNIQUE` (`tenno_name` ASC),
  UNIQUE INDEX `ingame_name_UNIQUE` (`ingame_name` ASC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table `inventory`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  INDEX `fk_inventory_users_idx` (`user_id` ASC),
  UNIQUE INDEX `user_item_UNIQUE` (`user_id` ASC, `item_name` ASC),
  CONSTRAINT `fk_inventory_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table `partnerships`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `partnerships`;
CREATE TABLE `partnerships` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_one_id` INT NOT NULL,
  `user_two_id` INT NOT NULL,
  `status` ENUM('pending', 'accepted') NOT NULL DEFAULT 'pending',
  `requested_by_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_partnerships_users1_idx` (`user_one_id` ASC),
  INDEX `fk_partnerships_users2_idx` (`user_two_id` ASC),
  INDEX `fk_partnerships_requester_idx` (`requested_by_id` ASC),
  UNIQUE INDEX `partnership_UNIQUE` (`user_one_id` ASC, `user_two_id` ASC), -- Stellt sicher, dass eine Partnerschaft zwischen zwei Nutzern einzigartig ist
  CONSTRAINT `fk_partnerships_users1`
    FOREIGN KEY (`user_one_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_partnerships_users2`
    FOREIGN KEY (`user_two_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_partnerships_requester`
    FOREIGN KEY (`requested_by_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `check_users_not_same` CHECK (`user_one_id` <> `user_two_id`) -- Stellt sicher, dass ein Nutzer keine Partnerschaft mit sich selbst eingeht
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
