<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht authentifiziert']);
    exit();
}

use App\MANV\MANVLage;
use App\MANV\MANVPatient;
use App\MANV\MANVLog;

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$lageId = $_GET['lage_id'] ?? $_POST['lage_id'] ?? null;

$manvLage = new MANVLage($pdo);
$manvPatient = new MANVPatient($pdo);
$manvLog = new MANVLog($pdo);

try {
    switch ($action) {
        case 'get_stats':
            if (!$lageId) {
                throw new Exception('Lage-ID fehlt');
            }

            $stats = $manvLage->getStatistics((int)$lageId);
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        case 'get_patients':
            if (!$lageId) {
                throw new Exception('Lage-ID fehlt');
            }

            $kategorie = $_GET['kategorie'] ?? null;
            $patienten = $manvPatient->getByLage((int)$lageId, $kategorie);

            echo json_encode([
                'success' => true,
                'data' => $patienten
            ]);
            break;

        case 'search_patients':
            if (!$lageId) {
                throw new Exception('Lage-ID fehlt');
            }

            $searchTerm = $_GET['search'] ?? '';
            $patienten = $manvPatient->search((int)$lageId, $searchTerm);

            echo json_encode([
                'success' => true,
                'data' => $patienten
            ]);
            break;

        case 'update_sichtung':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Ungültige Anfrage');
            }

            $postData = json_decode(file_get_contents('php://input'), true);
            $patientId = $postData['patient_id'] ?? null;
            $kategorie = $postData['kategorie'] ?? null;

            if (!$patientId || !$kategorie) {
                throw new Exception('Fehlende Parameter');
            }

            $manvPatient->updateSichtung(
                (int)$patientId,
                $kategorie,
                $_SESSION['user_id'] ?? null
            );

            echo json_encode([
                'success' => true,
                'message' => 'Sichtungskategorie aktualisiert'
            ]);
            break;

        case 'get_lage':
            if (!$lageId) {
                throw new Exception('Lage-ID fehlt');
            }

            $lage = $manvLage->getById((int)$lageId);
            if (!$lage) {
                throw new Exception('Lage nicht gefunden');
            }

            echo json_encode([
                'success' => true,
                'data' => $lage
            ]);
            break;

        case 'transport_abfahrt':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Ungültige Anfrage');
            }

            $patientId = $_POST['patient_id'] ?? null;
            if (!$patientId) {
                throw new Exception('Patient-ID fehlt');
            }

            // Patient-Daten abrufen
            $stmt = $pdo->prepare("SELECT manv_lage_id, patienten_nummer, transportmittel_rufname FROM intra_manv_patienten WHERE id = ?");
            $stmt->execute([(int)$patientId]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$patient) {
                throw new Exception('Patient nicht gefunden');
            }

            // Transport-Abfahrt markieren
            $updateStmt = $pdo->prepare("
                UPDATE intra_manv_patienten 
                SET transport_abfahrt = NOW(),
                    geaendert_von = ?,
                    geaendert_am = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([
                $_SESSION['user_id'] ?? null,
                (int)$patientId
            ]);

            // Fahrzeug aus Ressourcen entfernen (wenn vorhanden)
            if ($patient['transportmittel_rufname']) {
                $deleteRessourceStmt = $pdo->prepare("
                    DELETE FROM intra_manv_ressourcen 
                    WHERE manv_lage_id = ? 
                    AND bezeichnung = ?
                    LIMIT 1
                ");
                $deleteRessourceStmt->execute([
                    (int)$patient['manv_lage_id'],
                    $patient['transportmittel_rufname']
                ]);

                // Log für Ressourcen-Entfernung
                if ($deleteRessourceStmt->rowCount() > 0) {
                    $manvLog->log(
                        (int)$patient['manv_lage_id'],
                        'ressource_abgefahren',
                        'Fahrzeug ' . $patient['transportmittel_rufname'] . ' mit Patient abgefahren',
                        $_SESSION['user_id'] ?? null,
                        $_SESSION['username'] ?? null,
                        'ressource',
                        null
                    );
                }
            }

            // Log-Eintrag erstellen
            $manvLog->log(
                (int)$patient['manv_lage_id'],
                'patient_abfahrt',
                'Patient ' . $patient['patienten_nummer'] . ' ist abgefahren',
                $_SESSION['user_id'] ?? null,
                $_SESSION['username'] ?? null,
                'patient',
                (int)$patientId
            );

            echo json_encode([
                'success' => true,
                'message' => 'Patient als abgefahren markiert'
            ]);
            break;

        default:
            throw new Exception('Ungültige Aktion');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
