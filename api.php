<?php
// Warframe Inventar-Terminal by Chelo Lima EIRL
// Haupt-API-Datei

header('Content-Type: application/json');
require_once 'db_config.php';

// Session starten für Login-Status
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Globale Konstanten und Konfigurationen
define('ACCESS_KEY_REGISTER', 'CheloPrime69');

// Datenbankverbindung herstellen
$pdo = connect_db();

// Eingabedaten vom Frontend (JSON)
$input = json_decode(file_get_contents('php://input'), true);

// Routing basierend auf 'action' Parameter
$action = $input['action'] ?? $_GET['action'] ?? null; // Erlaube action auch als GET für einfache Tests (z.B. check_session)

// Hilfsfunktion zum Senden von JSON-Antworten
function send_json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// --- BENUTZERVERWALTUNG ---

if ($action === 'register') {
    $tenno_name = $input['tenno_name'] ?? null;
    $ingame_name = $input['ingame_name'] ?? null;
    $password = $input['password'] ?? null;
    $access_key = $input['access_key'] ?? null;

    if (!$tenno_name || !$ingame_name || !$password || !$access_key) {
        send_json_response(['status' => 'error', 'message' => 'Alle Felder sind erforderlich für die Registrierung.'], 400);
    }

    if ($access_key !== ACCESS_KEY_REGISTER) {
        send_json_response(['status' => 'error', 'message' => 'Ungültiger Zugangsschlüssel.'], 403);
    }

    // Überprüfen, ob tenno_name oder ingame_name bereits existiert
    $stmt = $pdo->prepare("SELECT id FROM users WHERE tenno_name = :tenno_name OR ingame_name = :ingame_name");
    $stmt->execute(['tenno_name' => $tenno_name, 'ingame_name' => $ingame_name]);
    if ($stmt->fetch()) {
        send_json_response(['status' => 'error', 'message' => 'Tenno-Name oder In-Game-Name bereits vergeben.'], 409);
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (tenno_name, ingame_name, password_hash) VALUES (:tenno_name, :ingame_name, :password_hash)");
        $stmt->execute([
            'tenno_name' => $tenno_name,
            'ingame_name' => $ingame_name,
            'password_hash' => $password_hash
        ]);
        send_json_response(['status' => 'success', 'message' => 'Benutzer erfolgreich registriert.']);
    } catch (PDOException $e) {
        // Log error $e->getMessage()
        send_json_response(['status' => 'error', 'message' => 'Fehler bei der Registrierung.'], 500);
    }
}

elseif ($action === 'login') {
    $tenno_name = $input['tenno_name'] ?? null;
    $password = $input['password'] ?? null;

    if (!$tenno_name || !$password) {
        send_json_response(['status' => 'error', 'message' => 'Tenno-Name und Passwort sind erforderlich.'], 400);
    }

    $stmt = $pdo->prepare("SELECT id, tenno_name, ingame_name, password_hash FROM users WHERE tenno_name = :tenno_name");
    $stmt->execute(['tenno_name' => $tenno_name]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['tenno_name'] = $user['tenno_name'];
        $_SESSION['ingame_name'] = $user['ingame_name'];
        send_json_response([
            'status' => 'success',
            'message' => 'Login erfolgreich.',
            'user' => [
                'id' => $user['id'],
                'tenno_name' => $user['tenno_name'],
                'ingame_name' => $user['ingame_name']
            ]
        ]);
    } else {
        send_json_response(['status' => 'error', 'message' => 'Ungültiger Tenno-Name oder Passwort.'], 401);
    }
}

elseif ($action === 'logout') {
    session_unset();
    session_destroy();
    send_json_response(['status' => 'success', 'message' => 'Logout erfolgreich.']);
}

elseif ($action === 'check_session') {
    if (isset($_SESSION['user_id'])) {
        send_json_response([
            'status' => 'success',
            'loggedIn' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'tenno_name' => $_SESSION['tenno_name'],
                'ingame_name' => $_SESSION['ingame_name']
            ]
        ]);
    } else {
        send_json_response(['status' => 'success', 'loggedIn' => false]);
    }
}

elseif ($action === 'update_ign') {
    if (!isset($_SESSION['user_id'])) {
        send_json_response(['status' => 'error', 'message' => 'Nicht eingeloggt.'], 401);
    }

    $new_ingame_name = $input['new_ingame_name'] ?? null;
    if (!$new_ingame_name) {
        send_json_response(['status' => 'error', 'message' => 'Neuer In-Game-Name ist erforderlich.'], 400);
    }

    // Prüfen, ob der neue IGN bereits von einem ANDEREN Benutzer verwendet wird
    $stmt = $pdo->prepare("SELECT id FROM users WHERE ingame_name = :ingame_name AND id != :user_id");
    $stmt->execute(['ingame_name' => $new_ingame_name, 'user_id' => $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        send_json_response(['status' => 'error', 'message' => 'Dieser In-Game-Name wird bereits von einem anderen Benutzer verwendet.'], 409);
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET ingame_name = :ingame_name WHERE id = :user_id");
        $stmt->execute(['ingame_name' => $new_ingame_name, 'user_id' => $_SESSION['user_id']]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['ingame_name'] = $new_ingame_name; // Session aktualisieren
            send_json_response(['status' => 'success', 'message' => 'In-Game-Name erfolgreich aktualisiert.', 'new_ingame_name' => $new_ingame_name]);
        } else {
            // Kann passieren, wenn der neue Name derselbe wie der alte ist oder der Benutzer nicht existiert (letzteres wird durch Session-Check abgefangen)
            send_json_response(['status' => 'info', 'message' => 'In-Game-Name wurde nicht geändert (möglicherweise derselbe Name).']);
        }
    } catch (PDOException $e) {
        // Log error $e->getMessage()
        send_json_response(['status' => 'error', 'message' => 'Fehler beim Aktualisieren des In-Game-Namens.'], 500);
    }
}


// --- INVENTARVERWALTUNG ---

elseif ($action === 'get_user_data') {
    if (!isset($_SESSION['user_id'])) {
        send_json_response(['status' => 'error', 'message' => 'Nicht eingeloggt.'], 401);
    }
    $user_id = $_SESSION['user_id'];

    $userData = [
        'inventory' => [],
        'partners' => [
            'active' => [],
            'pending_sent' => [],
            'pending_received' => [],
        ]
    ];

    // 1. Eigenes Inventar abrufen
    $stmt = $pdo->prepare("SELECT item_name, quantity, category FROM inventory WHERE user_id = :user_id ORDER BY category, item_name");
    $stmt->execute(['user_id' => $user_id]);
    $userData['inventory'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Partnerdaten abrufen
    // Aktive Partner und deren Inventare
    $stmt = $pdo->prepare("
        SELECT
            p.id AS partnership_id,
            u.id AS partner_user_id,
            u.tenno_name AS partner_tenno_name,
            u.ingame_name AS partner_ingame_name
        FROM partnerships p
        JOIN users u ON (u.id = p.user_one_id OR u.id = p.user_two_id)
        WHERE (p.user_one_id = :user_id OR p.user_two_id = :user_id)
          AND p.status = 'accepted'
          AND u.id != :user_id
    ");
    $stmt->execute(['user_id' => $user_id]);
    $activePartners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($activePartners as $partner) {
        $partner_inventory_stmt = $pdo->prepare("SELECT item_name, quantity, category FROM inventory WHERE user_id = :partner_user_id ORDER BY category, item_name");
        $partner_inventory_stmt->execute(['partner_user_id' => $partner['partner_user_id']]);
        $inventory = $partner_inventory_stmt->fetchAll(PDO::FETCH_ASSOC);

        $userData['partners']['active'][] = [
            'user_id' => $partner['partner_user_id'],
            'tenno_name' => $partner['partner_tenno_name'],
            'ingame_name' => $partner['partner_ingame_name'],
            'inventory' => $inventory
        ];
    }

    // Ausstehende gesendete Anfragen (ich habe gesendet, Warte auf Antwort)
    $stmt = $pdo->prepare("
        SELECT u.id AS partner_user_id, u.tenno_name, u.ingame_name
        FROM partnerships p
        JOIN users u ON u.id = p.user_two_id -- Der andere Nutzer ist user_two_id wenn ich (user_one_id) gesendet habe
        WHERE p.user_one_id = :user_id AND p.status = 'pending' AND p.requested_by_id = :user_id
    ");
    $stmt->execute(['user_id' => $user_id]);
    $userData['partners']['pending_sent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ausstehende empfangene Anfragen (jemand hat mir gesendet, ich muss antworten)
    // Hier müssen wir die Logik der `CHECK (user_one_id < user_two_id)` beachten
    $stmt = $pdo->prepare("
        SELECT u.id AS partner_user_id, u.tenno_name, u.ingame_name, p.id as partnership_id
        FROM partnerships p
        JOIN users u ON u.id = p.requested_by_id -- Der Anfragende
        WHERE (p.user_one_id = :user_id OR p.user_two_id = :user_id) -- Ich bin einer der beiden
          AND p.status = 'pending'
          AND p.requested_by_id != :user_id -- Aber ich habe die Anfrage nicht gestellt
    ");
    $stmt->execute(['user_id' => $user_id]);
    $userData['partners']['pending_received'] = $stmt->fetchAll(PDO::FETCH_ASSOC);


    send_json_response(['status' => 'success', 'data' => $userData]);
}

elseif ($action === 'update_inventory') {
    if (!isset($_SESSION['user_id'])) {
        send_json_response(['status' => 'error', 'message' => 'Nicht eingeloggt.'], 401);
    }
    $user_id = $_SESSION['user_id'];
    $inventory_items = $input['inventory'] ?? null; // Erwartet ein Array von Items

    if (!is_array($inventory_items)) {
        send_json_response(['status' => 'error', 'message' => 'Ungültiges Inventarformat.'], 400);
    }

    $pdo->beginTransaction();
    try {
        // Altes Inventar löschen
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);

        // Neues Inventar einfügen
        $stmt_insert = $pdo->prepare("INSERT INTO inventory (user_id, item_name, quantity, category) VALUES (:user_id, :item_name, :quantity, :category)");
        foreach ($inventory_items as $item) {
            if (empty($item['item_name']) || !isset($item['quantity'])) {
                // Optional: Fehler werfen oder fehlerhafte Items überspringen
                continue;
            }
            $stmt_insert->execute([
                'user_id' => $user_id,
                'item_name' => $item['item_name'],
                'quantity' => (int)$item['quantity'],
                'category' => $item['category'] ?? 'Uncategorized' // Standardkategorie
            ]);
        }
        $pdo->commit();
        send_json_response(['status' => 'success', 'message' => 'Inventar erfolgreich aktualisiert.']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        // Log error $e->getMessage()
        send_json_response(['status' => 'error', 'message' => 'Fehler beim Aktualisieren des Inventars: ' . $e->getMessage()], 500);
    }
}

// --- PARTNERSYSTEM ---

elseif ($action === 'send_partner_request') {
    if (!isset($_SESSION['user_id'])) {
        send_json_response(['status' => 'error', 'message' => 'Nicht eingeloggt.'], 401);
    }
    $user_id = $_SESSION['user_id'];
    $partner_tenno_name = $input['partner_tenno_name'] ?? null;

    if (!$partner_tenno_name) {
        send_json_response(['status' => 'error', 'message' => 'Tenno-Name des Partners ist erforderlich.'], 400);
    }

    // Partner-ID abrufen
    $stmt = $pdo->prepare("SELECT id FROM users WHERE tenno_name = :tenno_name");
    $stmt->execute(['tenno_name' => $partner_tenno_name]);
    $partner = $stmt->fetch();

    if (!$partner) {
        send_json_response(['status' => 'error', 'message' => 'Partner nicht gefunden.'], 404);
    }
    $partner_id = $partner['id'];

    if ($user_id == $partner_id) {
        send_json_response(['status' => 'error', 'message' => 'Du kannst dich nicht selbst als Partner hinzufügen.'], 400);
    }

    // Um die CHECK (user_one_id < user_two_id) Bedingung zu erfüllen:
    $user_one_id = min($user_id, $partner_id);
    $user_two_id = max($user_id, $partner_id);
    $requested_by_id = $user_id; // Wer die Anfrage stellt

    // Überprüfen, ob bereits eine Partnerschaft oder Anfrage existiert
    $stmt = $pdo->prepare("SELECT id, status FROM partnerships WHERE user_one_id = :user_one_id AND user_two_id = :user_two_id");
    $stmt->execute(['user_one_id' => $user_one_id, 'user_two_id' => $user_two_id]);
    $existing_partnership = $stmt->fetch();

    if ($existing_partnership) {
        if ($existing_partnership['status'] === 'accepted') {
            send_json_response(['status' => 'info', 'message' => 'Ihr seid bereits Partner.'], 200);
        } else { // pending
            send_json_response(['status' => 'info', 'message' => 'Eine Partnerschaftsanfrage existiert bereits.'], 200);
        }
        return;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO partnerships (user_one_id, user_two_id, status, requested_by_id) VALUES (:user_one_id, :user_two_id, 'pending', :requested_by_id)");
        $stmt->execute([
            'user_one_id' => $user_one_id,
            'user_two_id' => $user_two_id,
            'requested_by_id' => $requested_by_id
        ]);
        send_json_response(['status' => 'success', 'message' => 'Partnerschaftsanfrage gesendet.']);
    } catch (PDOException $e) {
        // Log error $e->getMessage()
        // Fehlercode 23000, SQLSTATE 23000 ist typisch für UNIQUE constraint violations
        if ($e->getCode() == '23000') {
             send_json_response(['status' => 'info', 'message' => 'Eine Partnerschaftsanfrage existiert bereits oder ihr seid bereits Partner.'], 200);
        } else {
            send_json_response(['status' => 'error', 'message' => 'Fehler beim Senden der Partnerschaftsanfrage: ' . $e->getMessage()], 500);
        }
    }
}

elseif ($action === 'respond_to_request') {
    if (!isset($_SESSION['user_id'])) {
        send_json_response(['status' => 'error', 'message' => 'Nicht eingeloggt.'], 401);
    }
    $user_id = $_SESSION['user_id']; // Derjenige, der antwortet
    $partnership_id = $input['partnership_id'] ?? null; // Die ID der Partnerschaftsanfrage aus der partnerships Tabelle
    $response_status = $input['response_status'] ?? null; // 'accepted' oder 'rejected'

    if (!$partnership_id || !in_array($response_status, ['accepted', 'rejected'])) {
        send_json_response(['status' => 'error', 'message' => 'Partnerschafts-ID und gültige Antwort (accepted/rejected) sind erforderlich.'], 400);
    }

    // Überprüfen, ob der aktuelle Benutzer der Empfänger der Anfrage ist und die Anfrage 'pending' ist.
    // Der Empfänger ist derjenige, der NICHT `requested_by_id` ist.
    $stmt = $pdo->prepare("SELECT id, user_one_id, user_two_id, requested_by_id FROM partnerships WHERE id = :partnership_id AND status = 'pending'");
    $stmt->execute(['partnership_id' => $partnership_id]);
    $request = $stmt->fetch();

    if (!$request) {
        send_json_response(['status' => 'error', 'message' => 'Anfrage nicht gefunden oder bereits beantwortet.'], 404);
    }

    // Sicherstellen, dass der aktuelle Benutzer derjenige ist, der die Anfrage beantworten darf
    // (also nicht der, der sie ursprünglich gesendet hat)
    if ($request['requested_by_id'] == $user_id) {
        send_json_response(['status' => 'error', 'message' => 'Du kannst nicht auf deine eigene Anfrage antworten.'], 403);
    }
    // Und sicherstellen, dass der user_id einer der beiden Partner ist
    if ($request['user_one_id'] != $user_id && $request['user_two_id'] != $user_id) {
        send_json_response(['status' => 'error', 'message' => 'Du bist nicht Teil dieser Partnerschaftsanfrage.'], 403);
    }


    if ($response_status === 'accepted') {
        $stmt = $pdo->prepare("UPDATE partnerships SET status = 'accepted' WHERE id = :partnership_id");
        $stmt->execute(['partnership_id' => $partnership_id]);
        send_json_response(['status' => 'success', 'message' => 'Partnerschaftsanfrage akzeptiert.']);
    } elseif ($response_status === 'rejected') {
        $stmt = $pdo->prepare("DELETE FROM partnerships WHERE id = :partnership_id");
        $stmt->execute(['partnership_id' => $partnership_id]);
        send_json_response(['status' => 'success', 'message' => 'Partnerschaftsanfrage abgelehnt.']);
    }
}

elseif ($action === 'disconnect_partner') {
    if (!isset($_SESSION['user_id'])) {
        send_json_response(['status' => 'error', 'message' => 'Nicht eingeloggt.'], 401);
    }
    $user_id = $_SESSION['user_id'];
    $partner_user_id_to_disconnect = $input['partner_user_id'] ?? null;

    if (!$partner_user_id_to_disconnect) {
        send_json_response(['status' => 'error', 'message' => 'Partner-Benutzer-ID ist erforderlich.'], 400);
    }

    $partner_user_id_to_disconnect = (int)$partner_user_id_to_disconnect;

    // Bestimme user_one_id und user_two_id für die Abfrage
    $user_one_id = min($user_id, $partner_user_id_to_disconnect);
    $user_two_id = max($user_id, $partner_user_id_to_disconnect);

    try {
        $stmt = $pdo->prepare("DELETE FROM partnerships WHERE user_one_id = :user_one_id AND user_two_id = :user_two_id AND status = 'accepted'");
        $stmt->execute([
            'user_one_id' => $user_one_id,
            'user_two_id' => $user_two_id
        ]);

        if ($stmt->rowCount() > 0) {
            send_json_response(['status' => 'success', 'message' => 'Partnerschaft erfolgreich beendet.']);
        } else {
            send_json_response(['status' => 'error', 'message' => 'Keine aktive Partnerschaft mit diesem Benutzer gefunden oder Fehler.'], 404);
        }
    } catch (PDOException $e) {
        // Log error $e->getMessage()
        send_json_response(['status' => 'error', 'message' => 'Fehler beim Beenden der Partnerschaft.'], 500);
    }
}

// --- GEHEIME FUNKTION: INVENTAR LÖSCHEN ---
elseif ($action === 'delete_my_inventory') {
    if (!isset($_SESSION['user_id'])) {
        send_json_response(['status' => 'error', 'message' => 'Nicht eingeloggt.'], 401);
    }
    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        send_json_response(['status' => 'success', 'message' => 'Dein gesamtes Inventar wurde erfolgreich gelöscht.']);
    } catch (PDOException $e) {
        // Log error $e->getMessage()
        send_json_response(['status' => 'error', 'message' => 'Fehler beim Löschen des Inventars.'], 500);
    }
}


// Fallback für ungültige Aktionen
else {
    if ($action) {
        send_json_response(['status' => 'error', 'message' => "Unbekannte Aktion: $action"], 400);
    } else {
        send_json_response(['status' => 'error', 'message' => 'Keine Aktion angegeben.'], 400);
    }
}

?>
