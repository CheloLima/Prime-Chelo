<?php
// Warframe Inventar-Terminal by Chelo Lima EIRL
// Datenbank-Konfigurationsdatei

// Definiere die MySQL-Zugangsdaten als Konstanten
// Diese Daten sind fest im Code hinterlegt gemäß der Anforderung.
// In einer Produktivumgebung sollten diese idealerweise über Umgebungsvariablen geladen werden.

define('DB_HOST', 'localhost');       // Hostname der Datenbank
define('DB_USER', 'chelo_prime');      // Datenbank-Benutzername
define('DB_PASS', '%6Opps^c9BBzfb9y'); // Datenbank-Passwort
define('DB_NAME', 'chelo_prime');      // Datenbankname

// Optional: Definiere den Zeichensatz für die Datenbankverbindung
define('DB_CHARSET', 'utf8mb4');

/**
 * Funktion zum Herstellen einer Datenbankverbindung.
 * Gibt ein PDO-Objekt bei Erfolg zurück oder beendet das Skript bei einem Fehler.
 */
function connect_db() {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Fehler als Exceptions werfen
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Ergebnisse als assoziative Arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Echte Prepared Statements verwenden
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // In einer Produktivumgebung sollte hier ein angemessenes Logging stattfinden
        // und keine detaillierte Fehlermeldung an den Client gesendet werden.
        // Für Entwicklungszwecke kann die Fehlermeldung hilfreich sein.
        error_log("Datenbankverbindungsfehler: " . $e->getMessage());
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'status' => 'error',
            'message' => 'Datenbankverbindungsfehler. Bitte kontaktieren Sie den Administrator.'
            // 'debug_message' => $e->getMessage() // Nur für Entwicklung, nicht in Produktion
        ]);
        exit; // Skriptausführung beenden
    }
}

// Test der Verbindung (optional, kann auskommentiert oder entfernt werden)
/*
if (connect_db()) {
    echo "Datenbankverbindung erfolgreich hergestellt.";
} else {
    echo "Fehler bei der Datenbankverbindung.";
}
*/
?>
