<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

header('Content-Type: application/json');

date_default_timezone_set('Europe/Berlin');

$ziel = $_GET['klinik'] ?? null;

try {
    $pdo->prepare("UPDATE intra_edivi_prereg SET active = 0 WHERE active = 1 AND arrival IS NOT NULL AND arrival < NOW() - INTERVAL 10 MINUTE")->execute();

    if ($ziel) {
        $stmt = $pdo->prepare("SELECT * FROM intra_edivi_prereg WHERE ziel = :ziel AND active = 1 ORDER BY arrival ASC");
        $stmt->bindParam(':ziel', $ziel);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM intra_edivi_prereg WHERE active = 1 ORDER BY arrival ASC");
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
