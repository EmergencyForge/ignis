<?php
/**
 * View: Fahrzeugverwaltung
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . '/../../../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="fahrzeuge">
    <?php include __DIR__ . "/../../../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item is-active">Fahrzeuge</span></nav>
                    <div class="page-header mb-4">
                        <h1>Fahrzeugverwaltung</h1>
                        <div class="header-actions">
                            <a href="<?= BASE_PATH ?>settings/vehicles/defects/index" class="ignis-btn ignis-btn--outline-warning">
                                <i class="fa-solid fa-triangle-exclamation"></i> Defekt-Meldungen
                            </a>
                            <?php if (Permissions::check(['admin', 'vehicles.manage'])) : ?>
                                <button type="button" class="ignis-btn ignis-btn--ghost" onclick="openTzTemplateManager()">
                                    <i class="fa-solid fa-shapes"></i> TZ-Vorlagen
                                </button>
                                <button type="button" class="ignis-btn ignis-btn--soft-primary" onclick="openVehicleImport()">
                                    <i class="fa-solid fa-satellite-dish"></i> EMD-Import
                                    <span class="ignis-chip ignis-chip--danger ml-1 hidden" id="importBadge">0</span>
                                </button>
                                <button type="button" class="ignis-btn ignis-btn--success" onclick="openCreateFahrzeugModal()">
                                    <i class="fa-solid fa-plus"></i> Fahrzeug erstellen
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-fahrzeuge">
                            <thead>
                                <tr>
                                    <th scope="col">Priorität</th>
                                    <th scope="col">Bezeichnung (Typ)</th>
                                    <th scope="col">Kennzeichen</th>
                                    <th scope="col">Fahrzeugtyp</th>
                                    <th scope="col">Defekte</th>
                                    <th scope="col">Aktiv?</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../../../../assets/config/database.php';
                                try {
                                    $stmt = $pdo->prepare("SELECT f.*,
                                        (SELECT COUNT(*) FROM intra_fahrzeuge_defects d WHERE d.vehicle_id = f.id AND d.status != 'resolved') AS open_defects,
                                        (SELECT MIN(d.vehicle_operable) FROM intra_fahrzeuge_defects d WHERE d.vehicle_id = f.id AND d.status != 'resolved') AS min_operable
                                        FROM intra_fahrzeuge f");
                                    $stmt->execute();
                                } catch (PDOException $e) {
                                    // Fallback: Tabelle existiert noch nicht
                                    $stmt = $pdo->prepare("SELECT *, 0 AS open_defects, NULL AS min_operable FROM intra_fahrzeuge");
                                    $stmt->execute();
                                }
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($result as $row) {
                                    switch ($row['rd_type']) {
                                        case 1:
                                            $docYes = "<span class='ignis-chip ignis-chip--warning'>RD - Mit NA</span>";
                                            break;
                                        case 2:
                                            $docYes = "<span class='ignis-chip ignis-chip--success'>RD - Ohne NA</span>";
                                            break;
                                        case 3:
                                            $docYes = "<span class='ignis-chip ignis-chip--danger'>Feuerwehr</span>";
                                            break;
                                        default:
                                            $docYes = "<span class='ignis-chip ignis-chip--dark'>Andere</span>";
                                            break;
                                    }

                                    $dimmed = '';

                                    switch ($row['active']) {
                                        case 0:
                                            $vehActive = "<span class='badge-status status-danger'><span class='status-dot'></span>Nein</span>";
                                            $dimmed = "style='color:var(--tag-color)'";
                                            break;
                                        default:
                                            $vehActive = "<span class='badge-status status-success'><span class='status-dot'></span>Ja</span>";
                                            break;
                                    }

                                    $kennzeichen = $row['kennzeichen'] ?? '';
                                    $kennzeichenDisplay = $kennzeichen ?: '-';

                                    $actions = "";
                                    if (Permissions::check(['admin', 'vehicles.manage'])) {
                                        $dataAttrs = [
                                            'id' => $row['id'],
                                            'name' => $row['name'],
                                            'kennzeichen' => $row['kennzeichen'],
                                            'type' => $row['veh_type'],
                                            'priority' => $row['priority'],
                                            'identifier' => $row['identifier'],
                                            'rd_type' => $row['rd_type'],
                                            'active' => $row['active'],
                                            'allowed_jobs' => $row['allowed_jobs'] ?? '',
                                            'tz-grundzeichen' => $row['grundzeichen'] ?? '',
                                            'tz-organisation' => $row['organisation'] ?? '',
                                            'tz-fachaufgabe' => $row['fachaufgabe'] ?? '',
                                            'tz-einheit' => $row['einheit'] ?? '',
                                            'tz-symbol' => $row['symbol'] ?? '',
                                            'tz-typ' => $row['typ'] ?? '',
                                            'tz-text' => $row['text'] ?? '',
                                            'tz-name' => $row['tz_name'] ?? ''
                                        ];

                                        $dataStr = '';
                                        foreach ($dataAttrs as $key => $val) {
                                            $dataStr .= " data-{$key}='" . htmlspecialchars($val, ENT_QUOTES) . "'";
                                        }

                                        $actions .= "<button type='button' title='Fahrzeug bearbeiten' class='ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon edit-btn' onclick='openEditFahrzeugModal(this)'{$dataStr}><i class='fa-solid fa-pen'></i></button> ";
                                        $actions .= "<a title='Fahrzeug kopieren' href='#' class='ignis-btn ignis-btn--sm ignis-btn--soft-success ignis-btn--icon copy-btn'{$dataStr}><i class='fa-solid fa-copy'></i></a>";
                                    }

                                    $openDefects = (int)($row['open_defects'] ?? 0);
                                    $minOperable = $row['min_operable'];
                                    $defectBadge = '';
                                    if ($openDefects > 0) {
                                        $badgeColor = ($minOperable !== null && (int)$minOperable === 0) ? 'danger' : 'warning';
                                        $defectBadge = "<a href='" . BASE_PATH . "settings/vehicles/defects/index?vehicle=" . $row['id'] . "' class='ignis-chip ignis-chip--{$badgeColor}' title='Offene Defekte anzeigen'>{$openDefects}</a>";
                                    } else {
                                        $defectBadge = "<span class='text-muted'>—</span>";
                                    }

                                    echo "<tr>";
                                    echo "<td " . $dimmed . ">" . $row['priority'] . "</td>";
                                    echo "<td " . $dimmed . "><span data-vehicle-card='" . (int) $row['id'] . "' style='cursor:help;'>" . htmlspecialchars($row['name']) . " (" . htmlspecialchars($row['veh_type']) . ")</span></td>";
                                    echo "<td " . $dimmed . ">" . $kennzeichenDisplay . "</td>";
                                    echo "<td>" . $docYes . "</td>";
                                    echo "<td>" . $defectBadge . "</td>";
                                    echo "<td>" . $vehActive . "</td>";
                                    echo "<td>{$actions}</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (Permissions::check('admin')) : ?>
        <!-- Form-Body als <template>; Edit + Create teilen sich denselben
             Prefix `fahrzeug-`, weil pro Open nur eine Dialog-Instanz im DOM
             ist. Die tactical-symbol-form-Partial wird mit useGlobalBind=true
             eingebunden, damit ihre inline-<script>-Bloecke nicht emittiert
             werden — die Bindings macht bindTacticalSymbolForm im onOpen. -->
        <template id="fahrzeugFormTemplate">
            <div class="mb-3">
                <label for="fahrzeug-name" class="ignis-field__label">Bezeichnung <small class="form-hint">(z.B. Funkrufname)</small></label>
                <input type="text" class="ignis-input" name="name" id="fahrzeug-name" required>
            </div>
            <div class="mb-3">
                <label for="fahrzeug-kennzeichen" class="ignis-field__label">Kennzeichen</label>
                <input type="text" class="ignis-input" name="kennzeichen" id="fahrzeug-kennzeichen" required>
            </div>
            <div class="mb-3">
                <label for="fahrzeug-identifier" class="ignis-field__label">Identifier <small class="form-hint">(eindeutige interne Kennung)</small></label>
                <input type="text" class="ignis-input" name="identifier" id="fahrzeug-identifier" required>
            </div>
            <div class="mb-3">
                <label for="fahrzeug-veh_typ" class="ignis-field__label">Typ <small class="form-hint">(RTW,NEF,RTH etc.)</small></label>
                <input type="text" class="ignis-input" name="veh_type" id="fahrzeug-veh_typ" required>
            </div>
            <div class="mb-3">
                <label for="fahrzeug-priority" class="ignis-field__label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                <input type="number" class="ignis-input" name="priority" id="fahrzeug-priority" value="0" required>
            </div>
            <div class="form-group mb-3">
                <label for="fahrzeug-rd_type">Typ (Rettungsdienstlich)</label>
                <select class="ignis-input" name="rd_type" id="fahrzeug-rd_type">
                    <option value="0">Andere</option>
                    <option value="1">Rettungsdienst mit NA</option>
                    <option value="2">Rettungsdienst ohne NA</option>
                    <option value="3">Feuerwehr</option>
                </select>
            </div>
            <label class="ignis-checkbox" for="fahrzeug-active"><input type="checkbox" name="active" id="fahrzeug-active"><span>Aktiv?</span></label>
            <div class="mb-3">
                <label for="fahrzeug-allowed_jobs" class="ignis-field__label">Erlaubte Jobs <small class="form-hint">(kommagetrennt, leer = alle)</small></label>
                <input type="text" class="ignis-input" name="allowed_jobs" id="fahrzeug-allowed_jobs" placeholder="z.B. BF,FF_Stadt">
            </div>
            <?php
            $prefix         = 'fahrzeug-';
            $showPreview    = true;
            $useGlobalBind  = true;
            include __DIR__ . '/../../../../assets/components/tactical-symbol-form.php';
            ?>
        </template>

        <form id="delete-fahrzeug-form" action="<?= BASE_PATH ?>settings/vehicles/vehicles/delete" method="POST" style="display:none;">
            <input type="hidden" name="id" id="fahrzeug-delete-id">
        </form>
    <?php endif; ?>


    <!-- TZ Template Manager Modal -->
    <?php if (Permissions::check(['admin', 'vehicles.manage'])) : ?>
    <div class="modal fade" id="tzTemplateModal" tabindex="-1" aria-labelledby="tzTemplateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tzTemplateModalLabel">
                        <i class="fa-solid fa-shapes mr-2"></i>TZ-Vorlagen verwalten
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body" id="tzTemplateModalBody">
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- EMD Vehicle Import Modal -->
    <?php if (Permissions::check(['admin', 'vehicles.manage'])) : ?>
    <div class="modal fade" id="vehicleImportModal" tabindex="-1" aria-labelledby="vehicleImportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vehicleImportModalLabel">
                        <i class="fa-solid fa-satellite-dish mr-2"></i>Fahrzeuge aus EMD importieren
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body" id="importModalBody">
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Laden...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <script src="<?= BASE_PATH ?>assets/js/modules/tactical-symbol-form.js"></script>
    <script src="<?= BASE_PATH ?>assets/js/modules/vehicles-admin.js"></script>
    <script>
    initVehiclesAdminPage({
        basePath:  '<?= BASE_PATH ?>',
        tzTplApi:  '<?= BASE_PATH ?>api/vehicles/tz-templates',
        importApi: '<?= BASE_PATH ?>api/vehicles/import-handler',
    });
    </script>
    <?php include __DIR__ . "/../../../../assets/components/footer.php"; ?>
</body>

</html>
