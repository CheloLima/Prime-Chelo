# Warframe Inventar-Terminal by Chelo Lima EIRL

## Inhaltsverzeichnis
1.  [Über das Projekt](#über-das-projekt)
2.  [Features](#features)
3.  [Visuelles Design & Ästhetik](#visuelles-design--ästhetik)
4.  [Technische Struktur](#technische-struktur)
    *   [Datenbank (`database.sql`)](#datenbank-databasesql)
    *   [PHP-Konfiguration (`db_config.php`)](#php-konfiguration-db_configphp)
    *   [PHP-Backend (`api.php`)](#php-backend-apiphp)
    *   [HTML-Frontend (`index.html`)](#html-frontend-indexhtml)
5.  [Setup-Anweisungen](#setup-anweisungen)
    *   [Voraussetzungen](#voraussetzungen)
    *   [Datenbank einrichten](#datenbank-einrichten)
    *   [Webserver konfigurieren](#webserver-konfigurieren)
    *   [Anwendung starten](#anwendung-starten)
6.  [Wichtige Hinweise](#wichtige-hinweise)
    *   [Access Key](#access-key)
    *   [Gemini API Key](#gemini-api-key)
    *   [Geheime Funktion](#geheime-funktion)
7.  [Entwickler](#entwickler)

## Über das Projekt

Das **Warframe Inventar-Terminal** ist eine Full-Stack-Webanwendung, die es Warframe-Spielern ermöglichen soll, ihr In-Game-Inventar effizient zu verwalten. Spieler können ihr Inventar manuell eintragen, es potenziell über Bild-/Videoerkennung importieren (UI-Platzhalter vorhanden) und es mit Handelspartnern teilen und deren Inventare durchsuchen.

Das Projekt legt großen Wert auf eine ansprechende, moderne Sci-Fi-Ästhetik, die stark von der Benutzeroberfläche des Spiels Warframe inspiriert ist.

## Features

*   **Benutzerauthentifizierung**: Sichere Registrierung und Login für Spieler.
*   **Inventarverwaltung**:
    *   Hinzufügen, Bearbeiten (Menge) und Löschen von Items im eigenen Inventar.
    *   Kategorisierung von Items (z.B. Prime-Teile, Relikte, Mods, Ressourcen).
    *   Live-Suche im eigenen Inventar.
    *   Pagination für übersichtliche Darstellung großer Inventare.
*   **Partnersystem**:
    *   Senden und Empfangen von Partnerschaftsanfragen.
    *   Akzeptieren oder Ablehnen von Anfragen.
    *   Anzeige aktiver Partner.
    *   Trennen von bestehenden Partnerschaften.
*   **Power-Suche**:
    *   Gleichzeitige Live-Suche im eigenen Inventar und in den Inventaren ausgewählter aktiver Partner.
    *   Anzeige der Ergebnisse mit Item-Name, Besitzer (In-Game-Name), individueller Menge und Gesamtmenge aller Fundorte.
*   **Handelsanfrage-Helfer**:
    *   Button neben Suchergebnissen von Partnern, um eine vorformulierte Handelsanfrage in die Zwischenablage zu kopieren (z.B. `/w [Partner-IGN] Hallo! Ich würde gerne [Menge]x "[Item-Name]" von dir im Inventar-Terminal anfragen.`).
*   **Datenexport und -kopie**:
    *   Export des eigenen Inventars als `.CSV`-Datei.
    *   Kopieren des eigenen Inventars als einfachen Text-String (z.B. für KI-Prompts).
*   **Gemini Bilderkennung (UI Prototyp)**:
    *   Datei-Upload-Feld für Bilder/Videos zum potenziellen Inventar-Import.
    *   Verwendung eines Standard-API-Schlüssels.
    *   Möglichkeit für Benutzer, einen eigenen Gemini API-Schlüssel einzugeben und lokal zu speichern.
    *   *Hinweis: Die eigentliche Bilderkennungslogik und API-Anbindung ist nicht Teil dieser Implementierung.*
*   **Einstellungen**:
    *   Änderung des eigenen In-Game-Namens (IGN).
*   **Geheime Funktion**:
    *   Hotkey (Strg + Alt + C) zum Löschen des gesamten eigenen Inventars (mit Bestätigungsdialog).
*   **Responsive Design**: Grundlegend für verschiedene Bildschirmgrößen angepasst.
*   **Dynamischer Footer**: Zeigt das aktuelle Jahr und die Versionsnummer an.

## Visuelles Design & Ästhetik

*   **Thema**: Modernes, futuristisches Sci-Fi-Interface, inspiriert von Warframe.
*   **Farbschema**: Dunkler Hintergrund (`#1a1a1a`), hellere Container (`#2a2a2a`), leuchtend-grüne Akzentfarbe (`#90ee90`).
*   **Typografie**:
    *   Überschriften: Google Font "Orbitron" mit leuchtendem Textschatten.
    *   Textkörper: Google Font "Rajdhani".
*   **Animationen & Effekte**:
    *   Sanftes Einblenden von Elementen.
    *   Interaktive Hover-Effekte für Buttons.
    *   Modale Fenster mit abgedunkeltem, verschwommenem Hintergrund (`backdrop-filter: blur(5px)`).
    *   Durchgehend sanfte Übergänge.

## Technische Struktur

Die Anwendung besteht aus vier Hauptkomponenten:

### Datenbank (`database.sql`)
Das SQL-Schema definiert die notwendigen Tabellen:
*   `users`: Speichert Benutzerinformationen (ID, Tenno-Name, In-Game-Name, Passwort-Hash, Erstellungsdatum).
    *   `tenno_name` und `ingame_name` sind UNIQUE.
*   `inventory`: Speichert die Inventargegenstände der Benutzer (ID, Benutzer-ID, Item-Name, Menge, Kategorie).
    *   UNIQUE-Constraint auf (`user_id`, `item_name`).
*   `partnerships`: Verwaltet die Beziehungen zwischen Benutzern (ID, user_one_id, user_two_id, Status (`pending`, `accepted`), requested_by_id).
    *   UNIQUE-Constraint auf (`user_one_id`, `user_two_id`).
    *   `CHECK` Constraint (`user_one_id < user_two_id`) zur Vermeidung von Duplikaten.

### PHP-Konfiguration (`db_config.php`)
Diese Datei enthält die Zugangsdaten für die MySQL-Datenbank als PHP-Konstanten:
*   `DB_HOST`: 'localhost'
*   `DB_USER`: 'chelo_prime'
*   `DB_PASS`: '%6Opps^c9BBzfb9y' (Passwort sollte in einer Produktivumgebung sicherer verwaltet werden, z.B. über Umgebungsvariablen)
*   `DB_NAME`: 'chelo_prime'
*   `DB_CHARSET`: 'utf8mb4'
Die Datei beinhaltet auch eine Funktion `connect_db()`, die eine PDO-Datenbankverbindung herstellt.

### PHP-Backend (`api.php`)
Das Herzstück der serverseitigen Logik. `api.php` empfängt Anfragen vom Frontend als JSON und gibt JSON-Antworten zurück. Es nutzt PHP-Sessions zur Verwaltung des Login-Status.
**Hauptaktionen:**
*   **Benutzerverwaltung**: `register`, `login`, `logout`, `check_session`, `update_ign`.
    *   Registrierung erfordert `tenno_name`, `ingame_name`, `password` und den `access_key` "CheloPrime69".
*   **Inventarverwaltung**: `get_user_data`, `update_inventory`.
    *   `get_user_data` liefert das Inventar des eingeloggten Benutzers sowie alle relevanten Partnerdaten (Anfragen, aktive Partner und deren vollständige Inventare).
    *   `update_inventory` empfängt ein komplettes Inventar-Array und überschreibt das alte in der Datenbank.
*   **Partnersystem**: `send_partner_request`, `respond_to_request`, `disconnect_partner`.
*   **Geheime Funktion**: `delete_my_inventory`.

### HTML-Frontend (`index.html`)
Eine Single-Page-Application (SPA), die die gesamte Benutzeroberfläche und Client-seitige Logik enthält.
*   **HTML**: Struktur der Seite.
*   **CSS**: (innerhalb von `<style>`-Tags) Verantwortlich für das gesamte visuelle Design, Animationen und Layout, gemäß den Sci-Fi-Anforderungen.
*   **JavaScript**: (innerhalb von `<script>`-Tags) Steuert die gesamte dynamische Funktionalität:
    *   Kommunikation mit `api.php` (AJAX/Fetch).
    *   Dynamisches Rendern von Inhalten (Inventarlisten, Partnerinformationen, Suchergebnisse).
    *   Verarbeitung von Benutzereingaben.
    *   Implementierung der UI-Features wie Modals, Suche, Pagination, etc.

## Setup-Anweisungen

### Voraussetzungen
*   Ein Webserver mit PHP-Unterstützung (z.B. Apache, Nginx).
*   PHP Version 7.4 oder höher (mit PDO MySQL-Erweiterung).
*   Ein MySQL- oder MariaDB-Datenbankserver.

### Datenbank einrichten
1.  Erstellen Sie eine neue Datenbank namens `chelo_prime` (oder passen Sie `DB_NAME` in `db_config.php` an).
    ```sql
    CREATE DATABASE chelo_prime CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ```
2.  Erstellen Sie einen Datenbankbenutzer `chelo_prime` mit dem Passwort `%6Opps^c9BBzfb9y` und gewähren Sie ihm alle Rechte für die Datenbank `chelo_prime` (oder passen Sie `DB_USER` und `DB_PASS` in `db_config.php` an).
    ```sql
    CREATE USER 'chelo_prime'@'localhost' IDENTIFIED BY '%6Opps^c9BBzfb9y';
    GRANT ALL PRIVILEGES ON chelo_prime.* TO 'chelo_prime'@'localhost';
    FLUSH PRIVILEGES;
    ```
    *Hinweis: Ersetzen Sie `'localhost'`, falls sich Ihre Anwendung und Datenbank auf unterschiedlichen Hosts befinden.*
3.  Importieren Sie das Datenbankschema aus der Datei `database.sql` in Ihre `chelo_prime`-Datenbank.
    ```bash
    mysql -u chelo_prime -p chelo_prime < database.sql
    ```
    (Sie werden zur Eingabe des Passworts aufgefordert.)

### Webserver konfigurieren
1.  Laden Sie alle Projektdateien (`index.html`, `api.php`, `db_config.php`, `database.sql` - obwohl letztere nur für das Setup ist) in ein Verzeichnis auf Ihrem Webserver (z.B. `htdocs/warframe-inventory` oder `www/warframe-inventory`).
2.  Stellen Sie sicher, dass der Webserver PHP-Dateien korrekt ausführt.
3.  Der Zugriff auf die Anwendung erfolgt dann über die `index.html`-Datei in Ihrem Browser (z.B. `http://localhost/warframe-inventory/` oder `http://yourdomain.com/warframe-inventory/`).

### Anwendung starten
Öffnen Sie die `index.html` in Ihrem Webbrowser. Sie sollten zunächst das Login-/Registrierungsformular sehen.

## Wichtige Hinweise

### Access Key
Für die Registrierung neuer Benutzer wird der Access Key `CheloPrime69` benötigt. Dieser ist fest im Backend (`api.php`) hinterlegt.

### Gemini API Key
*   Ein Standard-API-Schlüssel (`AIzaSyAFzoq1uNS0B_N4YrZgwNt6uxnd-Xd8q6E`) ist im Frontend-Code hinterlegt.
*   Benutzer können über den "Custom Gemini Key"-Button einen eigenen Schlüssel eingeben, der im `localStorage` des Browsers gespeichert und vorrangig verwendet wird.
*   **Die eigentliche Bild- und Videoerkennungsfunktionalität mit Gemini ist in dieser Version nicht implementiert.** Die UI-Elemente sind Platzhalter.

### Geheime Funktion
Durch Drücken von **Strg + Alt + C** im eingeloggten Zustand wird ein Bestätigungsdialog angezeigt. Bei Bestätigung wird das gesamte Inventar des aktuellen Benutzers unwiderruflich gelöscht. Es gibt keinen sichtbaren Button für diese Funktion.

## Entwickler
Chelo Lima EIRL

---
Dieses `README.md` sollte einen guten Überblick über das Projekt geben und Benutzern helfen, es einzurichten und zu verstehen.
