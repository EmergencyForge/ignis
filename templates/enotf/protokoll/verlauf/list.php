<?php
/**
 * View: enotf/protokoll/verlauf/list.php
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;

$message = '';
$messageType = '';

// Parameter prüfen
if (!isset($_GET['enr'])) {
    header("Location: " . BASE_PATH . "enotf/");
    exit();
}

$enr = $_GET['enr'];
$action = $_GET['action'] ?? 'manage';

// ENR-Berechtigung prüfen
$queryget = "SELECT * FROM intra_edivi WHERE enr = :enr";
$stmt = $pdo->prepare($queryget);
$stmt->execute(['enr' => $enr]);
$daten = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$daten) {
    header("Location: " . BASE_PATH . "enotf/");
    exit();
}

// Prüfung ob freigegeben
if ($daten['freigegeben'] == 1) {
    $ist_freigegeben = true;
} else {
    $ist_freigegeben = false;
}

// Actions verarbeiten (nur wenn nicht freigegeben)
if (!$ist_freigegeben) {
    switch ($action) {
        case 'delete':
            if (isset($_GET['id'])) {
                $id = $_GET['id'];

                // Soft Delete
                $query = "UPDATE intra_edivi_vitalparameter_einzelwerte 
                          SET geloescht = 1, geloescht_am = NOW(), geloescht_von = :username 
                          WHERE id = :id AND enr = :enr";
                $stmt = $pdo->prepare($query);
                $result = $stmt->execute([
                    'id' => $id,
                    'enr' => $enr,
                    'username' => $_SESSION['username'] ?? 'Unbekannt'
                ]);

                if ($result) {
                    $message = 'Vitalparameter erfolgreich gelöscht.';
                    $messageType = 'success';
                } else {
                    $message = 'Fehler beim Löschen des Vitalparameters.';
                    $messageType = 'danger';
                }
            }
            break;
    }
}

// Alle Vitalparameter für diese ENR laden (nur aktive)
$query = "SELECT * FROM intra_edivi_vitalparameter_einzelwerte 
          WHERE enr = :enr AND geloescht = 0
          ORDER BY zeitpunkt DESC, parameter_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute(['enr' => $enr]);
$vitalparameter = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nach Zeitpunkt gruppieren
$grouped_vitals = [];
foreach ($vitalparameter as $vital) {
    $zeitpunkt = $vital['zeitpunkt'];
    if (!isset($grouped_vitals[$zeitpunkt])) {
        $grouped_vitals[$zeitpunkt] = [];
    }
    $grouped_vitals[$zeitpunkt][] = $vital;
}

date_default_timezone_set('Europe/Berlin');
$prot_url = "https://" . SYSTEM_URL . "/enotf/prot/index.php?enr=" . $enr;
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../../assets/components/enotf/_head.php';
    ?>

    <style>
        .vitals-container {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .vitals-table {
            width: 100%;
            margin: 0;
            background: transparent;
        }

        .vitals-table thead th {
            background: rgba(255, 255, 255, 0.08);
            border-bottom: 2px solid rgba(255, 255, 255, 0.15);
            color: white;
            font-weight: 600;
            padding: 12px 8px;
            font-size: 13px;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .vitals-table tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: background-color 0.2s ease;
        }

        .vitals-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .vitals-table tbody td {
            padding: 8px;
            color: white;
            font-size: 13px;
            border: none;
            vertical-align: middle;
        }

        .time-group {
            background: rgba(255, 255, 255, 0.08);
            border-top: 2px solid rgba(255, 255, 255, 0.2);
        }

        .time-group td {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            padding: 10px 8px !important;
            font-size: 14px;
        }

        .parameter-name {
            font-weight: 500;
            color: white;
        }

        .parameter-value {
            font-weight: 600;
            color: #4CAF50;
            font-size: 14px;
        }

        .parameter-unit {
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            margin-left: 2px;
        }

        .parameter-meta {
            color: rgba(255, 255, 255, 0.5);
            font-size: 11px;
        }

        .btn-delete-compact {
            background: rgba(220, 53, 69, 0.8);
            border: none;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .btn-delete-compact:hover {
            background: rgba(220, 53, 69, 1);
            color: white;
            text-decoration: none;
            transform: scale(1.05);
        }

        .btn-delete-compact i {
            font-size: 12px;
        }

        .no-data-compact {
            background: rgba(108, 117, 125, 0.1);
            border: 1px solid rgba(108, 117, 125, 0.3);
            color: white;
            padding: 40px 20px;
            border-radius: 8px;
            text-align: center;
        }

        .header-actions {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-primary-compact {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s ease;
            margin-right: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary-compact:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
            color: white;
            text-decoration: none;
            background: linear-gradient(135deg, #5a6268, #495057);
        }

        .btn-secondary-compact {
            background: rgba(108, 117, 125, 0.6);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-secondary-compact:hover {
            background: rgba(108, 117, 125, 0.8);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
        }

        .stats-row {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            padding: 10px 15px;
            margin-bottom: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-item {
            display: inline-block;
            background: rgba(108, 117, 125, 0.3);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            margin-right: 10px;
            font-size: 12px;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .vitals-table {
                font-size: 11px;
            }

            .vitals-table th,
            .vitals-table td {
                padding: 6px 4px;
            }

            .btn-delete-compact {
                padding: 3px 6px;
                font-size: 10px;
            }
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="verlauf" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php include __DIR__ . '/../../../../assets/components/enotf/topbar.php'; ?>

    <div class="container-fluid" id="edivi__container">
        <div class="row h-100">
            <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
            <div class="col" id="edivi__content">
                <div class="my-3"></div>
                <!-- Erfolg/Fehler-Meldung -->
                <?php if (!empty($message)): ?>
                    <div class="row mb-3">
                        <div class="col">
                            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Info bei freigegebener Dokumentation -->
                <?php if ($ist_freigegeben): ?>
                    <div class="row mb-3">
                        <div class="col">
                            <div class="alert alert-warning">
                                <i class="fa-solid fa-lock"></i> <strong>Hinweis:</strong> Diese Dokumentation ist freigegeben und kann nicht mehr bearbeitet werden.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Statistiken -->
                <?php if (!empty($grouped_vitals)): ?>
                    <div class="stats-row">
                        <span class="stat-item">
                            <i class="fa-regular fa-clock"></i> <?= count($grouped_vitals) ?> Zeitpunkte
                        </span>
                        <span class="stat-item">
                            <i class="fa-solid fa-heart-pulse"></i> <?= count($vitalparameter) ?> Einzelwerte
                        </span>
                        <span class="stat-item">
                            <i class="fa-regular fa-calendar"></i> <?= !empty($vitalparameter) ? date('d.m.Y H:i', strtotime($vitalparameter[0]['zeitpunkt'])) . ' - ' . date('d.m.Y H:i', strtotime(end($vitalparameter)['zeitpunkt'])) : 'Keine Daten' ?>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Vitalparameter-Tabelle -->
                <?php if (empty($grouped_vitals)): ?>
                    <div class="row">
                        <div class="col">
                            <div class="no-data-compact">
                                <i class="fa-solid fa-circle-info" style="font-size: 32px; margin-bottom: 10px;"></i>
                                <h6>Noch keine Vitalparameter erfasst</h6>
                                <p class="mb-0" style="font-size: 13px;">
                                    <?php if (!$ist_freigegeben): ?>
                                        Klicken Sie auf "Neue Werte hinzufügen", um die ersten Vitalparameter zu dokumentieren.
                                    <?php else: ?>
                                        Für diese Dokumentation wurden keine Vitalparameter erfasst.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <div class="col">
                            <div class="vitals-container">
                                <table class="vitals-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 30%;">Parameter</th>
                                            <th style="width: 15%;">Wert</th>
                                            <th style="width: 10%;">Einheit</th>
                                            <th style="width: 25%;"></th>
                                            <?php if (!$ist_freigegeben): ?>
                                                <th style="width: 20%;">Aktion</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $lastZeitpunkt = '';
                                        foreach ($grouped_vitals as $zeitpunkt => $vitals):
                                            $zeitpunktFormatted = date('d.m.Y H:i', strtotime($zeitpunkt));

                                            // Zeitpunkt-Gruppierungszeile
                                            if ($zeitpunkt !== $lastZeitpunkt): ?>
                                                <tr class="time-group">
                                                    <td colspan="<?= !$ist_freigegeben ? '5' : '4' ?>">
                                                        <i class="fa-regular fa-clock"></i> <?= $zeitpunktFormatted ?> Uhr
                                                        <small style="margin-left: 15px; color: rgba(255,255,255,0.6);">
                                                            (<?= count($vitals) ?> Parameter)
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php
                                                $lastZeitpunkt = $zeitpunkt;
                                            endif;

                                            // Parameter-Zeilen (ohne redundanten Zeitpunkt)
                                            foreach ($vitals as $vital): ?>
                                                <tr>
                                                    <td class="parameter-name">
                                                        <?= htmlspecialchars($vital['parameter_name']) ?>
                                                    </td>
                                                    <td class="parameter-value">
                                                        <?= htmlspecialchars($vital['parameter_wert']) ?>
                                                    </td>
                                                    <td class="parameter-unit">
                                                        <?= htmlspecialchars($vital['parameter_einheit']) ?>
                                                    </td>
                                                    <td class="parameter-meta">
                                                    </td>
                                                    <?php if (!$ist_freigegeben): ?>
                                                        <td>
                                                            <a href="?enr=<?= $enr ?>&action=delete&id=<?= $vital['id'] ?>"
                                                                class="btn-delete-compact"
                                                                onclick="event.preventDefault(); showConfirm('Parameter \'<?= htmlspecialchars($vital['parameter_name']) ?>\' (<?= htmlspecialchars($vital['parameter_wert']) ?> <?= htmlspecialchars($vital['parameter_einheit']) ?>) löschen?', {danger: true, confirmText: 'Löschen', title: 'Parameter löschen'}).then(result => { if(result) window.location.href = this.href; });">
                                                                <i class="fa-solid fa-trash"></i>
                                                                Löschen
                                                            </a>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    include __DIR__ . '/../../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../../assets/functions/enotf/field_checks.php';
    include __DIR__ . '/../../../../assets/functions/enotf/clock.php';
    ?>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>