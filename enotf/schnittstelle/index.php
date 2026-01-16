<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

$prot_url = "https://" . SYSTEM_URL . "/enotf/index.php";

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$ziel = $_GET['klinik'] ?? NULL;
$zielName = '';

// Zielname für die Anzeige ermitteln
if ($ziel) {
    if (strpos($ziel, 'poi_') === 0) {
        // POI-basiertes Krankenhaus
        $poiId = substr($ziel, 4);
        $stmt = $pdo->prepare("SELECT name FROM intra_edivi_pois WHERE id = :id");
        $stmt->execute(['id' => $poiId]);
        $poiData = $stmt->fetch(PDO::FETCH_ASSOC);
        $zielName = $poiData ? $poiData['name'] : 'Unbekanntes Krankenhaus';
    } else {
        // Klassisches Ziel
        $stmt = $pdo->prepare("SELECT name FROM intra_edivi_ziele WHERE identifier = :identifier");
        $stmt->execute(['identifier' => $ziel]);
        $zielData = $stmt->fetch(PDO::FETCH_ASSOC);
        $zielName = $zielData ? $zielData['name'] : 'Unbekanntes Ziel';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $SITE_TITLE = 'Arrivalboard &rsaquo; eNOTF';
    include __DIR__ . '/../../assets/components/enotf/_head.php';
    ?>
    <meta http-equiv="refresh" content="60">
</head>

<body data-bs-theme="dark" style="overflow-x:hidden; display: flex; flex-direction: column; min-height: 100vh;" id="edivi__arrivalboard">
    <div class="container-fluid" style="flex: 1;">
        <div class="row h-100">
            <div class="col" id="edivi__content">
                <table class="w-100">
                    <thead>
                        <tr>
                            <th class="text-center">Ankunft</th>
                            <th colspan="2">Verdachtsdiagnose</th>
                            <th>Anmeldetext</th>
                            <th class="text-center">Kreislauf</th>
                            <th class="text-center">GCS</th>
                            <th class="text-center">Intubiert</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $pdo->prepare("UPDATE intra_edivi_prereg SET active = 0 WHERE active = 1 AND arrival IS NOT NULL AND arrival < NOW() - INTERVAL 10 MINUTE")->execute();
                        if ($ziel) {
                            $stmt = $pdo->prepare("SELECT * FROM intra_edivi_prereg WHERE ziel = :ziel AND active = 1 ORDER BY arrival ASC");
                            $stmt->bindParam(':ziel', $ziel);
                        } else {
                            $stmt = $pdo->prepare("SELECT * FROM intra_edivi_prereg WHERE active = 1 ORDER BY arrival ASC");
                        }
                        $stmt->execute();
                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($result as $row) {
                            if (!empty($row['arrival'])) {
                                $row['arrival'] = (new DateTime($row['arrival']))->format('H:i');
                            } else {
                                $row['arrival'] = '—';
                            }

                            if ($row['priority'] == 1) {
                                $rowClass = 'edivi__arrivalboard-prio-1';
                            } elseif ($row['priority'] == 2) {
                                $rowClass = 'edivi__arrivalboard-prio-2';
                            } else {
                                $rowClass = 'edivi__arrivalboard-prio-0';
                            }

                            if ($row['geschlecht'] == 1) {
                                $row['geschlecht'] = '<i class="fa-solid fa-venus"></i>';
                            } elseif ($row['geschlecht'] == 0) {
                                $row['geschlecht'] = '<i class="fa-solid fa-mars"></i>';
                            } elseif ($row['geschlecht'] == 2) {
                                $row['geschlecht'] = '<i class="fa-solid fa-mars-and-venus"></i>';
                            } else {
                                $row['geschlecht'] = '<i class="fa-solid fa-question" style="opacity:0.5"></i>';
                            }

                            if (empty($row['alter'])) {
                                $row['alter'] = '—';
                            }

                            if ($row['kreislauf'] == 1) {
                                $row['kreislauf'] = 'stabil';
                            } else {
                                $row['kreislauf'] = '<span style="color:red">instabil</span>';
                            }

                            if ($row['intubiert'] == 0) {
                                $row['intubiert'] = 'nein';
                            } else {
                                $row['intubiert'] = '<span style="color:red">ja</span>';
                            }

                        ?>
                            <tr class="<?= $rowClass ?>">
                                <td class="edivi__arrivalboard-time">
                                    <span><?= $row['arrival'] ?></span><br>
                                    <?= $row['fahrzeug'] ?>
                                </td>
                                <td><?= $row['diagnose'] ?></td>
                                <td class="edivi__arrivalboard-gender"><?= $row['geschlecht'] ?><br>
                                    <?= $row['alter'] ?></td>
                                <td class="edivi__arrivalboard-text"><?= $row['text'] ?></td>
                                <td class="text-center"><?= $row['kreislauf'] ?></td>
                                <td class="text-center"><?= $row['gcs'] ?></td>
                                <td class="text-center"><?= $row['intubiert'] ?></td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <footer class="text-center py-2 text-white" style="background-color: #131313;">
        <div class="row">
            <div class="col ps-4 d-flex align-items-center" style="font-size:2rem">
                eNOTFArrivalboard
                <?php if ($ziel && !empty($zielName)): ?>
                    <span style="font-size:0.9rem; opacity:0.6; margin-left:1rem;"><?= htmlspecialchars($zielName) ?></span>
                <?php endif; ?>
            </div>
            <div class="col">
                <img src="https://dev.intrarp.de/assets/img/defaultLogo.webp" alt="intraRP Logo" height="48px" width="auto">
            </div>
            <div class="col text-end d-flex justify-content-end align-items-center">
                <div class="d-flex flex-column align-items-end me-3">
                    <span id="current-time"><?= $currentTime ?></span>
                    <span id="current-date"><?= $currentDate ?></span>
                </div>
            </div>
        </div>
    </footer>
    <?php
    include __DIR__ . '/../../assets/functions/enotf/clock.php';
    ?>
</body>

</html>