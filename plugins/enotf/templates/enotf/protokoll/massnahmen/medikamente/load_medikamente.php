<?php
/**
 * View: enotf/protokoll/massnahmen/medikamente/load_medikamente.php
 *
 * @var \PDO $pdo
 */


header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['enr'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ENR fehlt']);
    exit();
}

$enr = $_POST['enr'];

try {
    $query = "SELECT medis FROM intra_edivi WHERE enr = :enr";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['enr' => $enr]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo json_encode([]);
        exit();
    }

    $medikamente = [];
    if (!empty($result['medis']) && $result['medis'] !== '0') {
        $decoded = json_decode($result['medis'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $medikamente = $decoded;
        }
    }

    // Medikamente nach Zeit sortieren (neueste zuerst)
    usort($medikamente, function ($a, $b) {
        return strcmp($b['zeit'], $a['zeit']);
    });

    echo json_encode($medikamente, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankfehler']);
    error_log("Database error in load_medikamente.php: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unerwarteter Fehler']);
    error_log("Error in load_medikamente.php: " . $e->getMessage());
}
