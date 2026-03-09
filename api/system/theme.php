<?php

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authentifiziert']);
    exit;
}

$userId = (int)$_SESSION['userid'];
$method = $_SERVER['REQUEST_METHOD'];

// Erlaubte Akzentfarben (Preset-Namen)
$allowedPresets = ['red', 'blue', 'green', 'purple', 'orange', 'teal', 'pink', 'amber'];

if ($method === 'GET') {
    // Theme-Konfiguration laden
    try {
        $stmt = $pdo->prepare("SELECT theme_config FROM intra_users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $config = null;
        if ($row && $row['theme_config']) {
            $config = json_decode($row['theme_config'], true);
        }

        echo json_encode(['config' => $config]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Datenbankfehler']);
    }
    exit;
}

if ($method === 'POST') {
    // Theme-Konfiguration speichern
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['accent'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Daten']);
        exit;
    }

    $accent = $input['accent'];

    // Validierung: Entweder ein Preset-Name oder eine gültige Hex-Farbe
    $isPreset = in_array($accent, $allowedPresets, true);
    $isCustomHex = preg_match('/^#[0-9a-fA-F]{6}$/', $accent);

    if (!$isPreset && !$isCustomHex) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Farbe']);
        exit;
    }

    $config = json_encode([
        'accent' => $accent,
        'type' => $isPreset ? 'preset' : 'custom'
    ]);

    try {
        $stmt = $pdo->prepare("UPDATE intra_users SET theme_config = :config WHERE id = :id");
        $stmt->execute(['config' => $config, 'id' => $userId]);

        echo json_encode(['success' => true, 'config' => json_decode($config, true)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Datenbankfehler']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode nicht erlaubt']);
