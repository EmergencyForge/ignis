<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

// Für CitizenFX: Nur Header entfernen, KEINE neuen setzen!
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    // Entferne CSP Header - .htaccess kümmert sich um den Rest
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
    // KEIN neuer CSP wird gesetzt!
}
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../assets/functions/enotf/user_auth_middleware.php';

header('Content-Type: application/json');

// Überprüfen ob ENR übergeben wurde
if (!isset($_POST['enr']) || empty($_POST['enr'])) {
    echo json_encode(['success' => false, 'message' => 'Keine Einsatznummer angegeben']);
    exit();
}

$enr = $_POST['enr'];

try {
    // Prüfen ob Protokoll existiert
    $stmt = $pdo->prepare("SELECT enr FROM intra_edivi WHERE enr = :enr");
    $stmt->execute(['enr' => $enr]);
    $protokoll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$protokoll) {
        echo json_encode(['success' => false, 'message' => 'Protokoll nicht gefunden']);
        exit();
    }

    // Prüfe ob bereits ein gültiger Code existiert (noch nicht abgelaufen)
    $stmt = $pdo->prepare("
        SELECT code, expires_at 
        FROM intra_edivi_klinikcodes 
        WHERE enr = :enr AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute(['enr' => $enr]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Gib existierenden Code zurück
        echo json_encode([
            'success' => true,
            'code' => $existing['code'],
            'expires_at' => $existing['expires_at']
        ]);
        exit();
    }

    // Generiere 6-stelligen alphanumerischen Code (Großbuchstaben und Zahlen)
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $maxAttempts = 100;
    $code = null;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        // Prüfe ob Code bereits existiert
        $stmt = $pdo->prepare("SELECT id FROM intra_edivi_klinikcodes WHERE code = :code");
        $stmt->execute(['code' => $code]);

        if (!$stmt->fetch()) {
            // Code ist einzigartig
            break;
        }
        $code = null;
    }

    if (!$code) {
        echo json_encode(['success' => false, 'message' => 'Code-Generierung fehlgeschlagen']);
        exit();
    }

    // Speichere Code in Datenbank (MySQL berechnet expires_at als NOW() + 1 Stunde)
    $stmt = $pdo->prepare("
        INSERT INTO intra_edivi_klinikcodes (enr, code, expires_at) 
        VALUES (:enr, :code, DATE_ADD(NOW(), INTERVAL 1 HOUR))
    ");

    $stmt->execute([
        'enr' => $enr,
        'code' => $code
    ]);

    // Hole die gespeicherten Daten zurück
    $stmt = $pdo->prepare("SELECT code, DATE_FORMAT(expires_at, '%Y-%m-%d %H:%i:%s') as expires_at FROM intra_edivi_klinikcodes WHERE enr = :enr ORDER BY id DESC LIMIT 1");
    $stmt->execute(['enr' => $enr]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'code' => $row['code'],
        'expires_at' => $row['expires_at']
    ]);
} catch (PDOException $e) {
    error_log("Fehler beim Generieren des Klinikkodes: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
