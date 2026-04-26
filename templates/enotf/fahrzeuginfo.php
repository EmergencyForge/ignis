<?php
/**
 * View: eNOTF Fahrzeuginfo
 *
 * @var array<string,mixed>|null       $vehicle
 * @var array<int,array<string,mixed>> $vehicles
 * @var array<int,array<string,mixed>> $categories
 * @var string                         $pinEnabled
 * @var \PDO                           $pdo
 */

use App\Helpers\EnotfUrl;

$prot_url = "https://" . SYSTEM_URL . "/enotf/index.php";

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "Fahrzeuginfo &rsaquo; eNOTF";
    include __DIR__ . '/../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" id="edivi__login" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    $topbar_left_html = '
        <a href="' . EnotfUrl::page('overview') . '" class="edivi__iconlink">
            <i class="fa-solid fa-chevron-left"></i><br>
            <small>Zurück</small>
        </a>';
    $topbar_sync = ['leitstelle', 'session'];
    $topbar_show_notices = false;
    include __DIR__ . '/../../assets/components/enotf/topbar.php';
    ?>
    <div class="w-full" id="edivi__container">
        <div class="flex flex-wrap -mx-3 h-full">
            <div class="flex-1 px-3" id="edivi__content">

                <div class="hr my-2" style="color:transparent"></div>

                <div>
                        <h4 class="text-white mb-4">

                        </h4>

                        <?php if ($vehicle): ?>
                            <!-- Fahrzeugdaten -->
                            <div class="vehicle-info-card p-4 mb-4">
                                <h5 class="text-white mb-3">
                                    Fahrzeugdaten
                                </h5>
                                <div class="table-responsive">
                                    <table class="table table-dark-custom">
                                        <tbody>
                                            <tr>
                                                <th scope="row" style="width: 200px;" class="text-white">Fahrzeugname:</th>
                                                <td><?= htmlspecialchars($vehicle['name']) ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="text-white">Fahrzeugtyp:</th>
                                                <td>
                                                    <?= htmlspecialchars($vehicle['veh_type']) ?>
                                                    <?php
                                                    switch ($vehicle['rd_type']) {
                                                        case 1:
                                                            echo '<span class="ignis-chip ignis-chip--danger ml-2 badge-vehicle-type">Notarztbesetzt</span>';
                                                            break;
                                                        case 2:
                                                            echo '<span class="ignis-chip ignis-chip--warning ml-2 badge-vehicle-type">Transportmittel</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="ignis-chip ignis-chip--primary ml-2 badge-vehicle-type">Standard</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="text-white">Kennzeichen:</th>
                                                <td><?= $vehicle['kennzeichen'] ? htmlspecialchars($vehicle['kennzeichen']) : '—' ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="text-white">Bezeichnung:</th>
                                                <td><?= htmlspecialchars($vehicle['identifier']) ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="text-white">Erstellt am:</th>
                                                <td><?= (new DateTime($vehicle['created_at']))->format('d.m.Y H:i') ?> Uhr</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Defekt melden -->
                            <div class="vehicle-info-card p-4 mb-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <h5 class="text-white mb-0">Defekt-Meldungen</h5>
                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--outline-warning" id="toggleDefectForm">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Defekt melden
                                    </button>
                                </div>

                                <!-- Meldeformular (ausgeblendet) -->
                                <div id="defectFormWrap" style="display:none;" class="mb-3">
                                    <form id="defectForm" class="p-3" style="background:rgba(255,255,255,0.05);border-radius:8px;">
                                        <input type="hidden" name="vehicle_id" value="<?= $vehicle['id'] ?>">
                                        <input type="hidden" name="action" value="create">
                                        <div class="mb-2">
                                            <label class="ignis-field__label text-white">Meldung durch</label>
                                            <select name="reported_by_name" class="form-select form-select-sm" data-custom-dropdown="true">
                                                <?php if (!empty($_SESSION['fahrername'])): ?>
                                                    <option value="<?= htmlspecialchars($_SESSION['fahrername']) ?>">Fahrer — <?= htmlspecialchars($_SESSION['fahrername']) ?></option>
                                                <?php endif; ?>
                                                <?php if (!empty($_SESSION['beifahrername'])): ?>
                                                    <option value="<?= htmlspecialchars($_SESSION['beifahrername']) ?>">Beifahrer — <?= htmlspecialchars($_SESSION['beifahrername']) ?></option>
                                                <?php endif; ?>
                                                <?php if (!empty($_SESSION['praktikantname'])): ?>
                                                    <option value="<?= htmlspecialchars($_SESSION['praktikantname']) ?>">Praktikant — <?= htmlspecialchars($_SESSION['praktikantname']) ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label class="ignis-field__label text-white">Titel</label>
                                            <input type="text" name="title" class="ignis-input ignis-input--sm" placeholder="Kurze Beschreibung des Defekts" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="ignis-field__label text-white">Beschreibung</label>
                                            <textarea name="description" class="ignis-input ignis-input--sm" rows="2" placeholder="Details..."></textarea>
                                        </div>
                                        <div class="mb-2">
                                            <label class="ignis-field__label text-white">Kategorie</label>
                                            <select name="category" class="form-select form-select-sm" data-custom-dropdown="true" required>
                                                <option value="" disabled selected>Bitte auswählen...</option>
                                                <option value="aufbau_karosserie">Aufbau / Karosserie</option>
                                                <option value="ausbau">Ausbau</option>
                                                <option value="batterie">Batterie</option>
                                                <option value="beleuchtung">Beleuchtung</option>
                                                <option value="bremsen">Bremsen</option>
                                                <option value="elektrik">Elektrik</option>
                                                <option value="fahrwerk">Fahrwerk</option>
                                                <option value="getriebe">Getriebe</option>
                                                <option value="motor">Motor</option>
                                                <option value="reifen">Reifen</option>
                                                <option value="service_pruefintervall">Service / Prüfintervall</option>
                                                <option value="signalanlage">Signalanlage</option>
                                                <option value="sonstiges">Sonstiges</option>
                                                <option value="windschutzscheibe">Windschutzscheibe</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="ignis-field__label text-white">Fahrzeug noch einsatzfähig?</label>
                                            <div class="flex gap-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="vehicle_operable" id="enotf-operable-yes" value="1" checked>
                                                    <label class="form-check-label text-white" for="enotf-operable-yes">Ja</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="vehicle_operable" id="enotf-operable-no" value="0">
                                                    <label class="form-check-label text-white" for="enotf-operable-no">Nein</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--warning"><i class="fa-solid fa-paper-plane"></i> Absenden</button>
                                            <button type="button" class="ignis-btn ignis-btn--sm btn-outline-light" id="cancelDefectForm">Abbrechen</button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Offene Defekte -->
                                <div id="defectList">
                                    <?php
                                    $openDefects = [];
                                    $enotfCategoryLabels = [
                                        'aufbau_karosserie' => 'Aufbau / Karosserie', 'ausbau' => 'Ausbau',
                                        'batterie' => 'Batterie', 'beleuchtung' => 'Beleuchtung', 'bremsen' => 'Bremsen',
                                        'elektrik' => 'Elektrik', 'fahrwerk' => 'Fahrwerk', 'getriebe' => 'Getriebe',
                                        'motor' => 'Motor', 'reifen' => 'Reifen', 'service_pruefintervall' => 'Service / Prüfintervall',
                                        'signalanlage' => 'Signalanlage', 'sonstiges' => 'Sonstiges', 'windschutzscheibe' => 'Windschutzscheibe'
                                    ];
                                    try {
                                        $defStmt = $pdo->prepare("SELECT d.*,
                                            COALESCE(m.fullname, u.username) AS reporter_name,
                                            last_log.last_status_user, last_log.last_status_details, last_log.last_status_at
                                            FROM intra_fahrzeuge_defects d
                                            LEFT JOIN intra_users u ON d.reported_by = u.id
                                            LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag
                                            LEFT JOIN (
                                                SELECT l.defect_id, COALESCE(mp.fullname, up.username) AS last_status_user, l.details AS last_status_details, l.created_at AS last_status_at
                                                FROM intra_fahrzeuge_defect_log l
                                                LEFT JOIN intra_users up ON l.user_id = up.id
                                                LEFT JOIN intra_mitarbeiter mp ON up.discord_id = mp.discordtag
                                                WHERE l.id = (
                                                    SELECT l2.id FROM intra_fahrzeuge_defect_log l2
                                                    WHERE l2.defect_id = l.defect_id AND l2.action IN ('updated', 'resolved')
                                                    ORDER BY l2.created_at DESC LIMIT 1
                                                )
                                            ) last_log ON last_log.defect_id = d.id
                                            WHERE d.vehicle_id = :vid AND d.status != 'resolved'
                                            ORDER BY d.created_at DESC");
                                        $defStmt->execute(['vid' => $vehicle['id']]);
                                        $openDefects = $defStmt->fetchAll(PDO::FETCH_ASSOC);
                                    } catch (PDOException $e) {
                                        // Tabelle existiert noch nicht
                                    }

                                    $statStyles = [
                                        'open' => ['Offen', 'danger'],
                                        'in_progress' => ['In Bearbeitung', 'warning'],
                                        'deferred' => ['Aufgeschoben', 'primary']
                                    ];
                                    ?>

                                    <?php if (empty($openDefects)): ?>
                                        <div class="text-gray-400 text-center py-3">
                                            <i class="fa-solid fa-check-circle" style="font-size:1.5rem;opacity:0.4;"></i>
                                            <div class="mt-1">Keine offenen Defekte</div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($openDefects as $def):
                                            $stat = $statStyles[$def['status']] ?? ['?', 'secondary'];
                                            $operable = (int)$def['vehicle_operable'];
                                            $catLabel = $enotfCategoryLabels[$def['category']] ?? $def['category'];
                                        ?>
                                            <div class="mb-2 flex items-start gap-2 p-2" style="background:rgba(255,255,255,0.03);border-radius:6px;border-left:3px solid var(--bs-<?= $operable ? 'warning' : 'danger' ?>);">
                                                <div class="flex-1">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <strong class="text-white" style="font-size:0.9rem;"><?= htmlspecialchars($def['title']) ?></strong>
                                                        <span class="ignis-chip" style="font-size:0.6rem;"><?= htmlspecialchars($catLabel) ?></span>
                                                        <span class="badge text-bg-<?= $stat[1] ?>" style="font-size:0.6rem;"><?= $stat[0] ?></span>
                                                        <?php if (!$operable): ?>
                                                            <span class="ignis-chip ignis-chip--danger" style="font-size:0.6rem;"><i class="fa-solid fa-ban"></i> Nicht einsatzfähig</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($def['description']): ?>
                                                        <div class="text-gray-400" style="font-size:0.8rem;"><?= htmlspecialchars($def['description']) ?></div>
                                                    <?php endif; ?>
                                                    <div class="text-gray-400" style="font-size:0.7rem;">
                                                        <?= htmlspecialchars($def['reporter_name'] ?? 'Unbekannt') ?> — <?= \App\Helpers\DateTimeHelper::formatShortLocal($def['created_at']) ?>
                                                    </div>
                                                    <?php if (!empty($def['last_status_user'])): ?>
                                                        <div class="mt-1 p-2" style="font-size:0.75rem;background:rgba(255,255,255,0.03);border-radius:4px;border-left:2px solid var(--bs-<?= $statStyles[$def['status']][1] ?? 'secondary' ?>);">
                                                            <i class="fa-solid fa-pen" style="font-size:0.65rem;"></i>
                                                            <?= htmlspecialchars($def['last_status_details']) ?>
                                                            <span class="text-gray-400">— <?= htmlspecialchars($def['last_status_user']) ?>, <?= \App\Helpers\DateTimeHelper::formatShortLocal($def['last_status_at']) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Beladelisten -->
                            <div class="vehicle-info-card p-4">
                                <h5 class="text-white mb-3">Beladeliste</h5>

                                <?php if (count($categories) > 0): ?>
                                    <?php
                                    // Tiles in einem Query laden und nach Kategorie gruppieren (statt N+1).
                                    $catIds = array_column($categories, 'id');
                                    $placeholders = implode(',', array_fill(0, count($catIds), '?'));
                                    $tilesByCategory = [];
                                    if ($catIds) {
                                        $tilesStmt = $pdo->prepare(
                                            "SELECT * FROM intra_fahrzeuge_beladung_tiles
                                             WHERE category IN ($placeholders)
                                             ORDER BY sort_order ASC, title ASC"
                                        );
                                        $tilesStmt->execute($catIds);
                                        foreach ($tilesStmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
                                            $tilesByCategory[(int) $t['category']][] = $t;
                                        }
                                    }
                                    ?>

                                    <div class="beladung-search">
                                        <input type="search" class="ignis-input" data-beladung-search
                                               placeholder="Wo liegt … ? (z. B. Intubationsbesteck)"
                                               autocomplete="off">
                                    </div>

                                    <div data-beladung-results class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                                        <?php foreach ($categories as $category): ?>
                                            <?php
                                            $tiles = $tilesByCategory[(int) $category['id']] ?? [];
                                            $mode  = 'user';
                                            include __DIR__ . '/../../assets/components/beladung/_category-card.php';
                                            ?>
                                        <?php endforeach; ?>
                                    </div>
                                    <div data-beladung-empty class="beladung-no-results" style="display:none;">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                        <p>Kein Treffer für deine Suche.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="text-gray-400 text-center py-4">
                                        <i class="fa-solid fa-exclamation-triangle" style="font-size: 2rem;"></i>
                                        <p class="mt-2">Keine Beladelisten für diesen Fahrzeugtyp vorhanden.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php else: ?>
                            <!-- Fallback: Kein Fahrzeug gefunden -->
                            <div class="vehicle-info-card p-4">
                                <div class="text-center py-4">
                                    <i class="fa-solid fa-exclamation-triangle text-[#ddb84a]" style="font-size: 3rem;"></i>
                                    <h5 class="text-white mt-3">Fahrzeug nicht gefunden</h5>
                                    <p class="text-gray-400">
                                        Das Fahrzeug mit der ID "<?= htmlspecialchars($currentVehicleId) ?>" konnte nicht gefunden werden.
                                    </p>

                                    <?php if (isset($vehicles) && count($vehicles) > 0): ?>
                                        <h6 class="text-white mt-4">Verfügbare Fahrzeuge:</h6>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-dark-custom">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Typ</th>
                                                        <th>Identifier</th>
                                                        <th>Kennzeichen</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($vehicles as $veh): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($veh['name']) ?></td>
                                                            <td><?= htmlspecialchars($veh['veh_type']) ?></td>
                                                            <td><?= htmlspecialchars($veh['identifier']) ?></td>
                                                            <td><?= $veh['kennzeichen'] ? htmlspecialchars($veh['kennzeichen']) : '—' ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var formWrap = document.getElementById('defectFormWrap');
        var toggleBtn = document.getElementById('toggleDefectForm');
        var cancelBtn = document.getElementById('cancelDefectForm');
        var form = document.getElementById('defectForm');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                formWrap.style.display = formWrap.style.display === 'none' ? 'block' : 'none';
            });
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                formWrap.style.display = 'none';
                form.reset();
            });
        }
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var fd = new FormData(form);

                fetch('<?= BASE_PATH ?>api/vehicles/defects-handler', {
                    method: 'POST',
                    body: fd
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || 'Fehler beim Melden');
                    }
                })
                .catch(function() {
                    alert('Verbindungsfehler');
                });
            });
        }
    });
    </script>
    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalLabel">Abmelden</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    Wie möchten Sie sich abmelden?
                </div>
                <div class="modal-footer">
                    <a href="loggedout?mode=self" class="ignis-btn">Mich abmelden</a>
                    <a href="loggedout?mode=all" class="ignis-btn ignis-btn--danger">Alle abmelden</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>