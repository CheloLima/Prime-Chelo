<?php
// Warframe Inventar-Terminal - Backend API
// Version 1.0

header('Content-Type: application/json');
require_once 'db_config.php';

// Strikte Typisierung und Fehlerbehandlung für robustere Entwicklung
declare(strict_types=1);
// error_reporting(E_ALL); // Für Entwicklung aktivieren, für Produktion ggf. anpassen
// ini_set('display_errors', '1'); // Für Entwicklung aktivieren

// --- Hilfsfunktionen ---

/**
 * Sendet eine JSON-Antwort an den Client und beendet das Skript.
 *
 * @param array $data Die zu sendenden Daten.
 * @param int $statusCode Der HTTP-Statuscode (Standard: 200).
 */
function sendJsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

/**
 * Startet die Session sicher.
 */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Setzt sicherere Session-Cookie-Parameter
        session_set_cookie_params([
            'lifetime' => 0, // Session-Cookie gültig bis Browser geschlossen wird
            'path' => '/',
            'domain' => '', // Aktuellen Domain verwenden
            'secure' => isset($_SERVER['HTTPS']), // Nur über HTTPS senden, wenn verfügbar
            'httponly' => true, // Cookie nicht für JavaScript zugänglich
            'samesite' => 'Lax' // Schutz gegen CSRF
        ]);
        session_start();
    }
}

startSecureSession(); // Session am Anfang jeder Anfrage starten

// --- Haupt-Request-Handler ---
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$requestData = [];

// Prüfen, ob der Content-Type application/json ist
if (isset($_SERVER["CONTENT_TYPE"]) && strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
    $jsonInput = file_get_contents('php://input');
    if ($jsonInput) {
        $decodedJson = json_decode($jsonInput, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $requestData = $decodedJson;
        } else {
            // Optional: Fehler loggen oder eine spezifische Antwort senden
            // error_log("Invalid JSON received: " . json_last_error_msg());
        }
    }
}

// Fallback auf $_POST, wenn $requestData noch leer ist (z.B. für form-data)
if (empty($requestData) && !empty($_POST)) {
    $requestData = $_POST;
}


if (!$action && isset($requestData['action'])) {
    $action = $requestData['action'];
}

$conn = getDbConnection();
if (!$conn) {
    sendJsonResponse(['success' => false, 'message' => 'Datenbankverbindungsfehler.'], 500);
}

// --- Benutzerverwaltungsfunktionen ---

/**
 * Registriert einen neuen Benutzer.
 * Erfordert 'tenno_name', 'ingame_name', 'password', 'access_key'.
 * Access Key muss "CheloPrime69" sein.
 */
function registerUser(mysqli $conn, array $data): void {
    $requiredFields = ['tenno_name', 'ingame_name', 'password', 'access_key'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            sendJsonResponse(['success' => false, 'message' => "Fehlender Parameter: {$field}"], 400);
        }
    }

    if ($data['access_key'] !== 'CheloPrime69') {
        sendJsonResponse(['success' => false, 'message' => 'Ungültiger Zugangsschlüssel.'], 403);
    }

    $tenno_name = trim($data['tenno_name']);
    $ingame_name = trim($data['ingame_name']);
    $password = $data['password'];

    if (empty($tenno_name) || empty($ingame_name) || empty($password)) {
        sendJsonResponse(['success' => false, 'message' => 'Benutzername, In-Game-Name und Passwort dürfen nicht leer sein.'], 400);
    }
    if (strlen($password) < 8) { // Beispiel für eine Passwortlängenprüfung
        sendJsonResponse(['success' => false, 'message' => 'Passwort muss mindestens 8 Zeichen lang sein.'], 400);
    }


    // Passwort hashen
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    if ($password_hash === false) {
        error_log("Password hashing failed for user: " . $tenno_name);
        sendJsonResponse(['success' => false, 'message' => 'Registrierung fehlgeschlagen. Bitte versuchen Sie es später erneut.'], 500);
    }

    $stmt = $conn->prepare("INSERT INTO users (tenno_name, ingame_name, password_hash) VALUES (?, ?, ?)");
    if (!$stmt) {
        error_log("DB Prepare Error (registerUser INSERT): " . $conn->error);
        sendJsonResponse(['success' => false, 'message' => 'Datenbankfehler bei der Vorbereitung.'], 500);
    }
    $stmt->bind_param("sss", $tenno_name, $ingame_name, $password_hash);

    if ($stmt->execute()) {
        sendJsonResponse(['success' => true, 'message' => 'Benutzer erfolgreich registriert.']);
    } else {
        if ($conn->errno === 1062) { // Fehlercode für Duplicate entry
            sendJsonResponse(['success' => false, 'message' => 'Tenno-Name oder In-Game-Name bereits vergeben.'], 409);
        } else {
            error_log("DB Execute Error (registerUser INSERT): " . $stmt->error . " (Errno: " . $conn->errno . ")");
            sendJsonResponse(['success' => false, 'message' => 'Registrierung fehlgeschlagen.'], 500);
        }
    }
    $stmt->close();
}

/**
 * Loggt einen Benutzer ein.
 * Erfordert 'tenno_name' und 'password'.
 */
function loginUser(mysqli $conn, array $data): void {
    if (empty($data['tenno_name']) || empty($data['password'])) {
        sendJsonResponse(['success' => false, 'message' => 'Tenno-Name und Passwort erforderlich.'], 400);
    }

    $tenno_name = $data['tenno_name'];
    $password = $data['password'];

    $stmt = $conn->prepare("SELECT id, tenno_name, ingame_name, password_hash FROM users WHERE tenno_name = ?");
    if (!$stmt) {
        error_log("DB Prepare Error (loginUser SELECT): " . $conn->error);
        sendJsonResponse(['success' => false, 'message' => 'Datenbankfehler bei der Vorbereitung.'], 500);
    }
    $stmt->bind_param("s", $tenno_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['tenno_name'] = $user['tenno_name'];
            $_SESSION['ingame_name'] = $user['ingame_name'];
            // Regeneriere die Session ID nach erfolgreichem Login, um Session Fixation zu verhindern
            session_regenerate_id(true);
            sendJsonResponse([
                'success' => true,
                'message' => 'Login erfolgreich.',
                'user' => [
                    'id' => $user['id'],
                    'tenno_name' => $user['tenno_name'],
                    'ingame_name' => $user['ingame_name']
                ]
            ]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Ungültiger Tenno-Name oder Passwort.'], 401);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Ungültiger Tenno-Name oder Passwort.'], 401);
    }
    $stmt->close();
}

/**
 * Loggt den aktuellen Benutzer aus.
 */
function logoutUser(): void {
    $_SESSION = array(); // Alle Session-Variablen löschen
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    sendJsonResponse(['success' => true, 'message' => 'Logout erfolgreich.']);
}

/**
 * Überprüft die aktuelle Session und gibt Benutzerdaten zurück, falls eingeloggt.
 */
function checkSession(): void {
    if (isset($_SESSION['user_id']) && isset($_SESSION['tenno_name']) && isset($_SESSION['ingame_name'])) {
        sendJsonResponse([
            'success' => true,
            'loggedIn' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'tenno_name' => $_SESSION['tenno_name'],
                'ingame_name' => $_SESSION['ingame_name']
            ]
        ]);
    } else {
        sendJsonResponse(['success' => true, 'loggedIn' => false]);
    }
}

/**
 * Aktualisiert den In-Game-Namen des eingeloggten Benutzers.
 * Erfordert 'new_ingame_name'.
 */
function updateIngameName(mysqli $conn, array $data): void {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Nicht eingeloggt.'], 401);
    }
    if (empty($data['new_ingame_name'])) {
        sendJsonResponse(['success' => false, 'message' => 'Neuer In-Game-Name erforderlich.'], 400);
    }

    $new_ingame_name = trim($data['new_ingame_name']);
    if (empty($new_ingame_name)) {
        sendJsonResponse(['success' => false, 'message' => 'In-Game-Name darf nicht leer sein.'], 400);
    }
    $user_id = $_SESSION['user_id'];

    // Überprüfen, ob der neue In-Game-Name bereits von einem ANDEREN Benutzer verwendet wird
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE ingame_name = ? AND id != ?");
    if (!$checkStmt) {
        error_log("DB Prepare Error (updateIngameName SELECT): " . $conn->error);
        sendJsonResponse(['success' => false, 'message' => 'Datenbankfehler bei der Überprüfung.'], 500);
    }
    $checkStmt->bind_param("si", $new_ingame_name, $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows > 0) {
        sendJsonResponse(['success' => false, 'message' => 'Dieser In-Game-Name wird bereits von einem anderen Benutzer verwendet.'], 409);
    }
    $checkStmt->close();

    $stmt = $conn->prepare("UPDATE users SET ingame_name = ? WHERE id = ?");
    if (!$stmt) {
        error_log("DB Prepare Error (updateIngameName UPDATE): " . $conn->error);
        sendJsonResponse(['success' => false, 'message' => 'Datenbankfehler bei der Vorbereitung.'], 500);
    }
    $stmt->bind_param("si", $new_ingame_name, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['ingame_name'] = $new_ingame_name; // Session aktualisieren
            sendJsonResponse(['success' => true, 'message' => 'In-Game-Name erfolgreich aktualisiert.', 'new_ingame_name' => $new_ingame_name]);
        } else {
            // Möglicherweise war der neue Name derselbe wie der alte
            sendJsonResponse(['success' => true, 'message' => 'In-Game-Name war bereits identisch oder konnte nicht geändert werden.', 'new_ingame_name' => $new_ingame_name]);
        }
    } else {
        if ($conn->errno === 1062) { // Sollte durch die vorherige Prüfung abgefangen werden, aber als Fallback
            sendJsonResponse(['success' => false, 'message' => 'Dieser In-Game-Name ist bereits vergeben.'], 409);
        } else {
            error_log("DB Execute Error (updateIngameName UPDATE): " . $stmt->error);
            sendJsonResponse(['success' => false, 'message' => 'Fehler beim Aktualisieren des In-Game-Namens.'], 500);
        }
    }
    $stmt->close();
}

// --- Inventar- und Datenfunktionen ---

/**
 * Aktualisiert das Inventar eines Benutzers.
 * Akzeptiert ein Array von Items, jedes mit 'name' und 'quantity'.
 * Wenn quantity 0 ist, wird das Item gelöscht.
 * Wenn ein Item bereits existiert, wird die Menge aktualisiert.
 * Wenn es nicht existiert, wird es hinzugefügt.
 */
function updateInventory(mysqli $conn, array $data): void {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Nicht eingeloggt.'], 401);
    }
    if (!isset($data['items']) || !is_array($data['items'])) {
        sendJsonResponse(['success' => false, 'message' => 'Keine Items zum Aktualisieren angegeben oder ungültiges Format.'], 400);
    }

    $user_id = $_SESSION['user_id'];
    $errors = [];
    $updatedItems = 0;
    $insertedItems = 0;
    $deletedItems = 0;

    foreach ($data['items'] as $item) {
        if (empty($item['name']) || !isset($item['quantity'])) {
            $errors[] = "Ungültiges Item-Format: Name und Menge sind erforderlich.";
            continue;
        }
        $item_name = trim($item['name']);
        $quantity = filter_var($item['quantity'], FILTER_VALIDATE_INT);

        if ($quantity === false || $quantity < 0) {
            $errors[] = "Ungültige Menge für Item '{$item_name}'. Menge muss eine nicht-negative Ganzzahl sein.";
            continue;
        }
        if (empty($item_name)) {
            $errors[] = "Item-Name darf nicht leer sein.";
            continue;
        }


        if ($quantity == 0) { // Item löschen
            $stmt = $conn->prepare("DELETE FROM inventory WHERE user_id = ? AND item_name = ?");
            if (!$stmt) { $errors[] = "DB Prepare Error (DELETE): " . $conn->error; continue; }
            $stmt->bind_param("is", $user_id, $item_name);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) $deletedItems++;
            } else {
                $errors[] = "Fehler beim Löschen von Item '{$item_name}': " . $stmt->error;
            }
            $stmt->close();
        } else { // Item hinzufügen oder aktualisieren
            // ON DUPLICATE KEY UPDATE ist MySQL-spezifisch, aber sehr effizient hier.
            $stmt = $conn->prepare("INSERT INTO inventory (user_id, item_name, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = ?");
            if (!$stmt) { $errors[] = "DB Prepare Error (INSERT/UPDATE): " . $conn->error; continue; }
            $stmt->bind_param("isii", $user_id, $item_name, $quantity, $quantity);
            if ($stmt->execute()) {
                // affected_rows: 1 für INSERT, 2 für UPDATE (wenn sich der Wert ändert), 0 wenn keine Änderung
                if ($stmt->affected_rows == 1) $insertedItems++;
                elseif ($stmt->affected_rows > 1) $updatedItems++;
                // Bei affected_rows == 0 wurde nichts geändert (gleiche Menge)
            } else {
                $errors[] = "Fehler beim Aktualisieren/Einfügen von Item '{$item_name}': " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if (!empty($errors)) {
        sendJsonResponse(['success' => false, 'message' => 'Einige Items konnten nicht verarbeitet werden.', 'errors' => $errors, 'inserted' => $insertedItems, 'updated' => $updatedItems, 'deleted' => $deletedItems], 207); // Multi-Status
    } else {
        sendJsonResponse(['success' => true, 'message' => 'Inventar erfolgreich aktualisiert.', 'inserted' => $insertedItems, 'updated' => $updatedItems, 'deleted' => $deletedItems]);
    }
}

/**
 * Löscht das gesamte Inventar des eingeloggten Benutzers.
 */
function deleteOwnInventory(mysqli $conn): void {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Nicht eingeloggt.'], 401);
    }
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("DELETE FROM inventory WHERE user_id = ?");
    if (!$stmt) {
        error_log("DB Prepare Error (deleteOwnInventory): " . $conn->error);
        sendJsonResponse(['success' => false, 'message' => 'Datenbankfehler bei der Vorbereitung.'], 500);
    }
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        sendJsonResponse(['success' => true, 'message' => 'Inventar erfolgreich gelöscht.', 'deleted_count' => $stmt->affected_rows]);
    } else {
        error_log("DB Execute Error (deleteOwnInventory): " . $stmt->error);
        sendJsonResponse(['success' => false, 'message' => 'Fehler beim Löschen des Inventars.'], 500);
    }
    $stmt->close();
}


/**
 * Sendet eine Partnerschaftsanfrage an einen anderen Benutzer.
 * Erfordert 'partner_ingame_name'.
 */
function sendPartnerRequest(mysqli $conn, array $data): void {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Nicht eingeloggt.'], 401);
    }
    if (empty($data['partner_ingame_name'])) {
        sendJsonResponse(['success' => false, 'message' => 'In-Game-Name des Partners erforderlich.'], 400);
    }

    $requester_id = $_SESSION['user_id'];
    $partner_ingame_name = trim($data['partner_ingame_name']);

    // Partner-ID abrufen
    $stmt = $conn->prepare("SELECT id FROM users WHERE ingame_name = ?");
    if (!$stmt) { sendJsonResponse(['success' => false, 'message' => 'DB Fehler 1'], 500); }
    $stmt->bind_param("s", $partner_ingame_name);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!($partner = $result->fetch_assoc())) {
        sendJsonResponse(['success' => false, 'message' => 'Partner nicht gefunden.'], 404);
    }
    $partner_id = $partner['id'];
    $stmt->close();

    if ($requester_id == $partner_id) {
        sendJsonResponse(['success' => false, 'message' => 'Du kannst keine Partnerschaft mit dir selbst eingehen.'], 400);
    }

    // Stelle sicher, dass die Benutzer-IDs in einer konsistenten Reihenfolge gespeichert werden, um Duplikate zu vermeiden (user_one_id < user_two_id)
    $user_one_id = min($requester_id, $partner_id);
    $user_two_id = max($requester_id, $partner_id);

    // Überprüfen, ob bereits eine Partnerschaft oder Anfrage existiert
    $stmt = $conn->prepare("SELECT id, status FROM partnerships WHERE user_one_id = ? AND user_two_id = ?");
    if (!$stmt) { sendJsonResponse(['success' => false, 'message' => 'DB Fehler 2'], 500); }
    $stmt->bind_param("ii", $user_one_id, $user_two_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($existingPartnership = $result->fetch_assoc()) {
        if ($existingPartnership['status'] === 'accepted') {
            sendJsonResponse(['success' => false, 'message' => 'Bereits Partner.'], 409);
        } else { // pending
            sendJsonResponse(['success' => false, 'message' => 'Partnerschaftsanfrage existiert bereits.'], 409);
        }
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO partnerships (user_one_id, user_two_id, status, requested_by_id) VALUES (?, ?, 'pending', ?)");
    if (!$stmt) { sendJsonResponse(['success' => false, 'message' => 'DB Fehler 3'], 500); }
    $stmt->bind_param("iii", $user_one_id, $user_two_id, $requester_id);

    if ($stmt->execute()) {
        sendJsonResponse(['success' => true, 'message' => 'Partnerschaftsanfrage gesendet.']);
    } else {
        error_log("DB Execute Error (sendPartnerRequest): " . $stmt->error);
        sendJsonResponse(['success' => false, 'message' => 'Fehler beim Senden der Anfrage.'], 500);
    }
    $stmt->close();
}

/**
 * Antwortet auf eine Partnerschaftsanfrage.
 * Erfordert 'request_id' und 'response_status' ('accepted' oder 'declined').
 */
function respondToPartnerRequest(mysqli $conn, array $data): void {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Nicht eingeloggt.'], 401);
    }
    if (empty($data['request_id']) || empty($data['response_status'])) {
        sendJsonResponse(['success' => false, 'message' => 'Request-ID und Antwortstatus erforderlich.'], 400);
    }

    $user_id = $_SESSION['user_id'];
    $request_id = filter_var($data['request_id'], FILTER_VALIDATE_INT);
    $response_status = $data['response_status']; // 'accepted' oder 'declined'

    if ($request_id === false) {
         sendJsonResponse(['success' => false, 'message' => 'Ungültige Request-ID.'], 400);
    }

    // Überprüfen, ob der aktuelle Benutzer der Empfänger der Anfrage ist (nicht der Anforderer)
    $stmt = $conn->prepare("SELECT id, requested_by_id, user_one_id, user_two_id FROM partnerships WHERE id = ? AND status = 'pending'");
    if (!$stmt) { sendJsonResponse(['success' => false, 'message' => 'DB Fehler 1'], 500); }
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!($request = $result->fetch_assoc())) {
        sendJsonResponse(['success' => false, 'message' => 'Anfrage nicht gefunden oder bereits beantwortet.'], 404);
    }
    $stmt->close();

    // Sicherstellen, dass der aktuelle Benutzer nicht derjenige ist, der die Anfrage gestellt hat
    // und dass der aktuelle Benutzer entweder user_one_id oder user_two_id ist.
    if ($request['requested_by_id'] == $user_id) {
        sendJsonResponse(['success' => false, 'message' => 'Du kannst nicht auf deine eigene Anfrage antworten.'], 403);
    }
    if ($request['user_one_id'] != $user_id && $request['user_two_id'] != $user_id) {
        sendJsonResponse(['success' => false, 'message' => 'Diese Anfrage ist nicht an dich gerichtet.'], 403);
    }


    if ($response_status === 'accepted') {
        $stmt = $conn->prepare("UPDATE partnerships SET status = 'accepted' WHERE id = ?");
        if (!$stmt) { sendJsonResponse(['success' => false, 'message' => 'DB Fehler 2'], 500); }
        $stmt->bind_param("i", $request_id);
        if ($stmt->execute()) {
            sendJsonResponse(['success' => true, 'message' => 'Partnerschaftsanfrage akzeptiert.']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Fehler beim Akzeptieren der Anfrage.'], 500);
        }
        $stmt->close();
    } elseif ($response_status === 'declined') {
        $stmt = $conn->prepare("DELETE FROM partnerships WHERE id = ?");
        if (!$stmt) { sendJsonResponse(['success' => false, 'message' => 'DB Fehler 3'], 500); }
        $stmt->bind_param("i", $request_id);
        if ($stmt->execute()) {
            sendJsonResponse(['success' => true, 'message' => 'Partnerschaftsanfrage abgelehnt.']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Fehler beim Ablehnen der Anfrage.'], 500);
        }
        $stmt->close();
    } else {
        sendJsonResponse(['success' => false, 'message' => "Ungültiger Antwortstatus: {$response_status}."], 400);
    }
}

/**
 * Beendet eine bestehende Partnerschaft.
 * Erfordert 'partner_id' (die ID des Partners, nicht die Partnership-ID).
 */
function disconnectPartner(mysqli $conn, array $data): void {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Nicht eingeloggt.'], 401);
    }
    if (empty($data['partner_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Partner-ID erforderlich.'], 400);
    }

    $user_id = $_SESSION['user_id'];
    $partner_id_to_disconnect = filter_var($data['partner_id'], FILTER_VALIDATE_INT);

    if($partner_id_to_disconnect === false){
        sendJsonResponse(['success' => false, 'message' => 'Ungültige Partner-ID.'], 400);
    }

    $user_one_id = min($user_id, $partner_id_to_disconnect);
    $user_two_id = max($user_id, $partner_id_to_disconnect);

    $stmt = $conn->prepare("DELETE FROM partnerships WHERE user_one_id = ? AND user_two_id = ? AND status = 'accepted'");
    if (!$stmt) { sendJsonResponse(['success' => false, 'message' => 'DB Fehler'], 500); }
    $stmt->bind_param("ii", $user_one_id, $user_two_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(['success' => true, 'message' => 'Partnerschaft erfolgreich beendet.']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Keine aktive Partnerschaft mit diesem Benutzer gefunden.'], 404);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Fehler beim Beenden der Partnerschaft.'], 500);
    }
    $stmt->close();
}

/**
 * Ruft alle relevanten Daten für den eingeloggten Benutzer ab.
 * Eigenes Inventar, Partnerliste, Anfragen, Inventare der Partner.
 * Diese Funktion sollte idealerweise als GET-Request aufgerufen werden.
 */
function getUserData(mysqli $conn): void {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Nicht eingeloggt.'], 401);
    }
    $user_id = $_SESSION['user_id'];
    $userData = [
        'inventory' => [],
        'partners' => [],
        'incoming_requests' => [],
        'outgoing_requests' => [],
        'partner_inventories' => []
    ];

    // 1. Eigenes Inventar abrufen
    $stmt = $conn->prepare("SELECT item_name, quantity FROM inventory WHERE user_id = ? ORDER BY item_name ASC");
    if (!$stmt) { sendJsonResponse(['success' => false, 'message' => 'DB Fehler beim Abrufen des Inventars.'], 500); }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $userData['inventory'][] = $row;
    }
    $stmt->close();

    // 2. Partnerliste (akzeptierte Partnerschaften) und deren Inventare abrufen
    // Diese Abfrage holt Partner, bei denen der aktuelle Benutzer entweder user_one_id oder user_two_id ist
    // und der Status 'accepted' ist.
    $stmt = $conn->prepare("
        SELECT
            p.id as partnership_id,
            CASE
                WHEN p.user_one_id = ? THEN u2.id
                ELSE u1.id
            END as partner_user_id,
            CASE
                WHEN p.user_one_id = ? THEN u2.tenno_name
                ELSE u1.tenno_name
            END as partner_tenno_name,
            CASE
                WHEN p.user_one_id = ? THEN u2.ingame_name
                ELSE u1.ingame_name
            END as partner_ingame_name
        FROM partnerships p
        JOIN users u1 ON p.user_one_id = u1.id
        JOIN users u2 ON p.user_two_id = u2.id
        WHERE (p.user_one_id = ? OR p.user_two_id = ?) AND p.status = 'accepted'
    ");
    if (!$stmt) { sendJsonResponse(['success' => false, 'message' => 'DB Fehler beim Abrufen der Partner.'], 500); }
    $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $partnersResult = $stmt->get_result();

    $partnerIds = [];
    while ($partner = $partnersResult->fetch_assoc()) {
        $userData['partners'][] = $partner;
        $partnerIds[] = (int)$partner['partner_user_id']; // Zum Abrufen der Inventare
    }
    $stmt->close();

    // 3. Inventare der Partner abrufen
    if (!empty($partnerIds)) {
        // Erzeuge Platzhalter für die IN-Klausel: ?,?,?
        $placeholders = implode(',', array_fill(0, count($partnerIds), '?'));
        // Erzeuge Typenstring für bind_param: 'iii...'
        $types = str_repeat('i', count($partnerIds));

        $invStmt = $conn->prepare("
            SELECT inv.user_id, u.ingame_name as owner_ingame_name, inv.item_name, inv.quantity
            FROM inventory inv
            JOIN users u ON inv.user_id = u.id
            WHERE inv.user_id IN ($placeholders) ORDER BY inv.user_id, inv.item_name ASC
        ");
        if (!$invStmt) { sendJsonResponse(['success' => false, 'message' => 'DB Fehler beim Abrufen der Partner-Inventare.'], 500); }

        // Binde die Parameter dynamisch
        $invStmt->bind_param($types, ...$partnerIds);
        $invStmt->execute();
        $partnerInvResult = $invStmt->get_result();
        while ($item = $partnerInvResult->fetch_assoc()) {
            $ownerId = $item['user_id'];
            if (!isset($userData['partner_inventories'][$ownerId])) {
                $userData['partner_inventories'][$ownerId] = [
                    'owner_ingame_name' => $item['owner_ingame_name'],
                    'items' => []
                ];
            }
            $userData['partner_inventories'][$ownerId]['items'][] = ['item_name' => $item['item_name'], 'quantity' => $item['quantity']];
        }
        $invStmt->close();
        // Umwandeln in ein Array, falls das Frontend ein Array von Objekten erwartet statt eines assoziativen Arrays mit User-ID als Key
        $userData['partner_inventories'] = array_values($userData['partner_inventories']);
    }


    // 4. Eingehende Partnerschaftsanfragen abrufen (Status 'pending' und der aktuelle Benutzer ist NICHT der Anforderer)
    // Der aktuelle Benutzer kann user_one_id oder user_two_id sein, aber nicht requested_by_id
    $stmt = $conn->prepare("
        SELECT p.id as request_id, u.id as requester_user_id, u.tenno_name as requester_tenno_name, u.ingame_name as requester_ingame_name
        FROM partnerships p
        JOIN users u ON p.requested_by_id = u.id
        WHERE ((p.user_one_id = ? AND p.requested_by_id != ?) OR (p.user_two_id = ? AND p.requested_by_id != ?))
        AND p.status = 'pending'
    ");
    if (!$stmt) { sendJsonResponse(['success' => false, 'message' => 'DB Fehler beim Abrufen eingehender Anfragen.'], 500); }
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $userData['incoming_requests'][] = $row;
    }
    $stmt->close();

    // 5. Ausgehende Partnerschaftsanfragen abrufen (Status 'pending' und der aktuelle Benutzer IST der Anforderer)
    $stmt = $conn->prepare("
        SELECT
            p.id as request_id,
            CASE
                WHEN p.user_one_id = ? THEN u2.id
                ELSE u1.id
            END as recipient_user_id,
            CASE
                WHEN p.user_one_id = ? THEN u2.tenno_name
                ELSE u1.tenno_name
            END as recipient_tenno_name,
            CASE
                WHEN p.user_one_id = ? THEN u2.ingame_name
                ELSE u1.ingame_name
            END as recipient_ingame_name
        FROM partnerships p
        JOIN users u1 ON p.user_one_id = u1.id
        JOIN users u2 ON p.user_two_id = u2.id
        WHERE p.requested_by_id = ? AND p.status = 'pending'
    ");
    if (!$stmt) { sendJsonResponse(['success' => false, 'message' => 'DB Fehler beim Abrufen ausgehender Anfragen.'], 500); }
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $userData['outgoing_requests'][] = $row;
    }
    $stmt->close();

    sendJsonResponse(['success' => true, 'data' => $userData]);
}


// --- Routing ---
// Die meisten Aktionen erwarten POST-Requests mit JSON-Body oder Form-Daten.
// GET wird primär für 'check_session', 'logout' und 'get_user_data' verwendet.

// Bevorzugt Aktionen aus $requestData (JSON Body oder POST)
if (empty($action) && isset($requestData['action'])) {
    $action = $requestData['action'];
}
// Fallback auf GET-Parameter für $action, falls nicht in $requestData
if (empty($action) && isset($_GET['action'])) {
    $action = $_GET['action'];
}


if ($action) {
    // GET-Requests zuerst behandeln, da sie typischerweise keine Daten im Body haben
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        switch ($action) {
            case 'check_session':
                checkSession();
                break;
            case 'logout':
                logoutUser();
                break;
            case 'get_user_data':
                getUserData($conn);
                break;
            default:
                sendJsonResponse(['success' => false, 'message' => "Unbekannte GET-Aktion: {$action}"], 400);
                break;
        }
    }
    // POST-Requests
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        switch ($action) {
            case 'register':
                registerUser($conn, $requestData);
                break;
            case 'login':
                loginUser($conn, $requestData);
                break;
            case 'logout': // Kann auch als POST aufgerufen werden
                logoutUser();
                break;
            case 'update_ign':
                updateIngameName($conn, $requestData);
                break;
            case 'update_inventory':
                updateInventory($conn, $requestData);
                break;
            case 'delete_own_inventory': // Für die geheime Funktion
                deleteOwnInventory($conn);
                break;
            case 'send_partner_request':
                sendPartnerRequest($conn, $requestData);
                break;
            case 'respond_to_request':
                respondToPartnerRequest($conn, $requestData);
                break;
            case 'disconnect_partner':
                disconnectPartner($conn, $requestData);
                break;
             case 'check_session': // check_session kann auch per POST kommen, wenn z.B. CSRF Token mitgesendet wird
                checkSession();
                break;
            default:
                sendJsonResponse(['success' => false, 'message' => "Unbekannte POST-Aktion: {$action}"], 400);
                break;
        }
    }
    // Andere Methoden (PUT, DELETE etc.)
    else {
        sendJsonResponse(['success' => false, 'message' => 'Ungültige Anfrage-Methode.'], 405);
    }
} else {
    sendJsonResponse(['success' => false, 'message' => 'Keine Aktion angegeben.'], 400);
}

$conn->close();
?>
