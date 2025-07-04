-- Warframe Inventar-Terminal by Chelo Lima EIRL
-- Datenbank-Schema

-- Tabelle für Benutzer
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tenno_name` VARCHAR(255) NOT NULL UNIQUE, -- Login-Name
  `ingame_name` VARCHAR(255) NOT NULL UNIQUE, -- Warframe In-Game-Name
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle für das Inventar der Benutzer
CREATE TABLE IF NOT EXISTS `inventory` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `category` VARCHAR(100) DEFAULT 'Uncategorized', -- z.B. Prime-Teile, Relikte, Mods, Ressourcen
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `user_item_unique` (`user_id`, `item_name`) -- Stellt sicher, dass ein Benutzer nicht denselben Gegenstand mehrmals hat
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle für Partnerschaften zwischen Benutzern
CREATE TABLE IF NOT EXISTS `partnerships` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_one_id` INT NOT NULL, -- ID des Benutzers, der die Anfrage initiiert hat oder Teil der Partnerschaft ist
  `user_two_id` INT NOT NULL, -- ID des Benutzers, der die Anfrage erhalten hat oder Teil der Partnerschaft ist
  `status` ENUM('pending', 'accepted') NOT NULL DEFAULT 'pending',
  `requested_by_id` INT NOT NULL, -- ID des Benutzers, der die Anfrage ursprünglich gesendet hat
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_one_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_two_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requested_by_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_partnership` (`user_one_id`, `user_two_id`), -- Verhindert doppelte Partnerschaftseinträge
  CHECK (`user_one_id` < `user_two_id`) -- Stellt sicher, dass user_one_id immer kleiner ist, um Duplikate wie (1,2) und (2,1) zu vermeiden
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indizes für häufige Abfragen
CREATE INDEX idx_inventory_user_id ON inventory(user_id);
CREATE INDEX idx_partnerships_user_one_id ON partnerships(user_one_id);
CREATE INDEX idx_partnerships_user_two_id ON partnerships(user_two_id);
CREATE INDEX idx_partnerships_status ON partnerships(status);

-- Einige Beispieldaten (optional, zum Testen)
-- Benutzer
-- INSERT INTO `users` (`tenno_name`, `ingame_name`, `password_hash`) VALUES
-- ('CheloPrime', 'CheloPrimeIGN', '$2y$10$exampleHash1...'), -- Passwörter müssen natürlich korrekt gehasht werden
-- ('TennoPartner1', 'PartnerOneIGN', '$2y$10$exampleHash2...');

-- Inventar für CheloPrime (user_id wäre 1, wenn es der erste Eintrag ist)
-- INSERT INTO `inventory` (`user_id`, `item_name`, `quantity`, `category`) VALUES
-- (1, 'Forma Blueprint', 10, 'Ressourcen'),
-- (1, 'Orokin Cell', 50, 'Ressourcen'),
-- (1, 'Nekros Prime Neuroptics Blueprint', 1, 'Prime-Teile'),
-- (1, 'Lith C6 Relic', 5, 'Relikte');

-- Inventar für TennoPartner1 (user_id wäre 2)
-- INSERT INTO `inventory` (`user_id`, `item_name`, `quantity`, `category`) VALUES
-- (2, 'Ash Prime Systems Blueprint', 2, 'Prime-Teile'),
-- (2, 'Axi A1 Relic', 10, 'Relikte'),
-- (2, 'Serration', 1, 'Mods');

-- Partnerschaftsanfrage
-- INSERT INTO `partnerships` (`user_one_id`, `user_two_id`, `status`, `requested_by_id`) VALUES
-- (1, 2, 'pending', 1); -- CheloPrime (1) sendet Anfrage an TennoPartner1 (2)
