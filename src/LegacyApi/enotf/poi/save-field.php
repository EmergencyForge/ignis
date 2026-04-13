<?php
require_once __DIR__ . '/../../../../assets/config/config.php';
require_once __DIR__ . '/../../../../assets/config/database.php';

header('Content-Type: application/json');

// Nur für eingeloggte User
// if (!isset($_SESSION['userid'])) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit();
// }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$enr = $_POST['enr'] ?? null;
$transp_poi = $_POST['transp_poi'] ?? null;
$transp_adresse = $_POST['transp_adresse'] ?? null;
$ziel_poi = $_POST['ziel_poi'] ?? null;
$ziel_adresse = $_POST['ziel_adresse'] ?? null;

if (empty($enr)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Bestimme welche Felder aktualisiert werden sollen
$isTransport = (!empty($transp_poi) || !empty($transp_adresse));
$isZiel = (!empty($ziel_poi) || !empty($ziel_adresse));

if (!$isTransport && !$isZiel) {
    echo json_encode(['success' => false, 'message' => 'No fields to update']);
    exit();
}

// Validiere JSON wenn vorhanden
if (!empty($transp_adresse)) {
    $decoded = json_decode($transp_adresse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON format for transp_adresse']);
        exit();
    }
}

if (!empty($ziel_adresse)) {
    $decoded = json_decode($ziel_adresse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON format for ziel_adresse']);
        exit();
    }
}

try {
    // Dynamische Query-Erstellung basierend auf übergebenen Feldern
    if ($isTransport && $isZiel) {
        // Beide Feldsets aktualisieren
        $stmt = $pdo->prepare("UPDATE intra_edivi SET transp_poi = :transp_poi, transp_adresse = :transp_adresse, ziel_poi = :ziel_poi, ziel_adresse = :ziel_adresse, last_edit = NOW() WHERE enr = :enr");
        $stmt->execute([
            'transp_poi' => $transp_poi,
            'transp_adresse' => $transp_adresse,
            'ziel_poi' => $ziel_poi,
            'ziel_adresse' => $ziel_adresse,
            'enr' => $enr
        ]);
    } elseif ($isTransport) {
        // Nur Transport-Felder aktualisieren
        $stmt = $pdo->prepare("UPDATE intra_edivi SET transp_poi = :transp_poi, transp_adresse = :transp_adresse, last_edit = NOW() WHERE enr = :enr");
        $stmt->execute([
            'transp_poi' => $transp_poi,
            'transp_adresse' => $transp_adresse,
            'enr' => $enr
        ]);
    } elseif ($isZiel) {
        // Nur Ziel-Felder aktualisieren
        $stmt = $pdo->prepare("UPDATE intra_edivi SET ziel_poi = :ziel_poi, ziel_adresse = :ziel_adresse, last_edit = NOW() WHERE enr = :enr");
        $stmt->execute([
            'ziel_poi' => $ziel_poi,
            'ziel_adresse' => $ziel_adresse,
            'enr' => $enr
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Fields updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
