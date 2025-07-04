<?php
// Warframe Inventar-Terminal - Datenbank Konfiguration
// Version 1.0

// Definiere die MySQL Zugangsdaten
// WICHTIG: Ändere diese Werte entsprechend deiner lokalen oder Produktivumgebung,
// aber für dieses Projekt sind sie fest vorgegeben.

define('DB_HOST', 'localhost');
define('DB_USER', 'chelo_prime');
define('DB_PASS', '%6Opps^c9BBzfb9y');
define('DB_NAME', 'chelo_prime');

/**
 * Stellt eine Verbindung zur MySQL-Datenbank her.
 *
 * @return mysqli|false Das mysqli-Verbindungsobjekt bei Erfolg, false bei einem Fehler.
 */
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Überprüfe die Verbindung
    if ($conn->connect_error) {
        // Im Fehlerfall nicht die genauen Fehlerdetails ausgeben,
        // um Informationslecks zu vermeiden. Logge sie serverseitig.
        error_log("Datenbankverbindungsfehler: " . $conn->connect_error);
        return false;
    }

    // Setze den Zeichensatz auf utf8mb4 für volle Unicode-Unterstützung
    if (!$conn->set_charset("utf8mb4")) {
        error_log("Fehler beim Setzen des Zeichensatzes utf8mb4: " . $conn->error);
        // Hier könnte man entscheiden, die Verbindung trotzdem zurückzugeben oder auch false
    }

    return $conn;
}

?>
