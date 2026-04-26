<?php
/**
 * View: Defekt-Meldungen-Verwaltung
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;

$canManage = Permissions::check(['admin', 'vehicles.manage']);

// Fahrzeuge laden für Dropdown
$vehicles = $pdo->query("SELECT id, name, identifier, kennzeichen, veh_type FROM intra_fahrzeuge ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Benutzer laden für Zuweisungs-Dropdown
$users = $pdo->query("SELECT u.id, COALESCE(m.fullname, u.username) AS fullname FROM intra_users u LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag WHERE u.is_active = 1 ORDER BY fullname ASC")->fetchAll(PDO::FETCH_ASSOC);

// Kategorien
$categoryLabels = [
    'aufbau_karosserie' => 'Aufbau / Karosserie',
    'ausbau' => 'Ausbau',
    'batterie' => 'Batterie',
    'beleuchtung' => 'Beleuchtung',
    'bremsen' => 'Bremsen',
    'elektrik' => 'Elektrik',
    'fahrwerk' => 'Fahrwerk',
    'getriebe' => 'Getriebe',
    'motor' => 'Motor',
    'reifen' => 'Reifen',
    'service_pruefintervall' => 'Service / Prüfintervall',
    'signalanlage' => 'Signalanlage',
    'sonstiges' => 'Sonstiges',
    'windschutzscheibe' => 'Windschutzscheibe'
];

// Tabelle prüfen & Statistiken laden
$tableExists = true;
$stats = ['total' => 0, 'open_count' => 0, 'in_progress_count' => 0, 'deferred_count' => 0, 'resolved_count' => 0, 'not_operable_open' => 0];
$defects = [];
$filterVehicle = isset($_GET['vehicle']) ? (int)$_GET['vehicle'] : 0;
$filterStatus = $_GET['status'] ?? '';

try {
    $stats = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
        SUM(CASE WHEN status = 'deferred' THEN 1 ELSE 0 END) AS deferred_count,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count,
        SUM(CASE WHEN vehicle_operable = 0 AND status != 'resolved' THEN 1 ELSE 0 END) AS not_operable_open
    FROM intra_fahrzeuge_defects")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tableExists = false;
}

if ($tableExists) {
    $sql = "SELECT d.*, f.name AS vehicle_name, f.identifier AS vehicle_identifier, f.kennzeichen, f.veh_type,
                   COALESCE(m1.fullname, u1.username) AS reporter_name,
                   COALESCE(m2.fullname, u2.username) AS assigned_name,
                   COALESCE(m3.fullname, u3.username) AS resolver_name,
                   last_log.last_status_user, last_log.last_status_details, last_log.last_status_at
            FROM intra_fahrzeuge_defects d
            JOIN intra_fahrzeuge f ON d.vehicle_id = f.id
            LEFT JOIN intra_users u1 ON d.reported_by = u1.id
            LEFT JOIN intra_mitarbeiter m1 ON u1.discord_id = m1.discordtag
            LEFT JOIN intra_users u2 ON d.assigned_to = u2.id
            LEFT JOIN intra_mitarbeiter m2 ON u2.discord_id = m2.discordtag
            LEFT JOIN intra_users u3 ON d.resolved_by = u3.id
            LEFT JOIN intra_mitarbeiter m3 ON u3.discord_id = m3.discordtag
            LEFT JOIN (
                SELECT l.defect_id, COALESCE(m.fullname, u.username) AS last_status_user, l.details AS last_status_details, l.created_at AS last_status_at
                FROM intra_fahrzeuge_defect_log l
                LEFT JOIN intra_users u ON l.user_id = u.id
                LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag
                WHERE l.id = (
                    SELECT l2.id FROM intra_fahrzeuge_defect_log l2
                    WHERE l2.defect_id = l.defect_id AND l2.action IN ('updated', 'resolved')
                    ORDER BY l2.created_at DESC LIMIT 1
                )
            ) last_log ON last_log.defect_id = d.id
            WHERE 1=1";
    $params = [];

    if ($filterVehicle) {
        $sql .= " AND d.vehicle_id = :vid";
        $params['vid'] = $filterVehicle;
    }
    if ($filterStatus && in_array($filterStatus, ['open', 'in_progress', 'deferred', 'resolved'])) {
        $sql .= " AND d.status = :status";
        $params['status'] = $filterStatus;
    }

    $sql .= " ORDER BY FIELD(d.status, 'open', 'in_progress', 'deferred', 'resolved'), CASE WHEN d.status != 'resolved' THEN d.vehicle_operable END ASC, d.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $defects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$statusLabels = [
    'open' => ['Offen', 'danger'],
    'in_progress' => ['In Bearbeitung', 'warning'],
    'deferred' => ['Aufgeschoben', 'primary'],
    'resolved' => ['Gelöst', 'success']
];
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php $SITE_TITLE = 'Fahrzeug-Defekte'; include __DIR__ . '/../../../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="fahrzeuge">
    <?php include __DIR__ . "/../../../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6">
                    <nav class="admin-breadcrumb">
                        <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span>Einstellungen</span>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <a href="<?= BASE_PATH ?>settings/fahrzeuge/fahrzeuge/index.php">Fahrzeuge</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span class="current">Defekt-Meldungen</span>
                    </nav>

                    <div class="page-header mb-4">
                        <h1>Defekt-Meldungen</h1>
                        <div class="header-actions">
                            <button type="button" class="ignis-btn ignis-btn--success" data-bs-toggle="modal" data-bs-target="#createDefectModal">
                                <i class="fa-solid fa-plus"></i> Defekt melden
                            </button>
                        </div>
                    </div>

                    <?php Flash::render(); ?>

                    <?php if (!$tableExists): ?>
                        <div class="ignis-alert ignis-alert--warning">
                            <i class="fa-solid fa-database"></i> Die Tabelle <code>intra_fahrzeuge_defects</code> existiert noch nicht.
                            Lade die Seite neu — die Datenbank wird automatisch migriert. Falls das Problem bestehen bleibt, führe auf der Konsole <code>composer db:migrate</code> aus.
                        </div>
                    <?php endif; ?>

                    <!-- Statistik-Karten -->
                    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5">
                        <div class="intra__tile p-3 text-center">
                            <div class="text-3xl font-bold text-danger"><?= (int)$stats['open_count'] ?></div>
                            <small class="text-gray-400">Offen</small>
                        </div>
                        <div class="intra__tile p-3 text-center">
                            <div class="text-3xl font-bold text-warning"><?= (int)$stats['in_progress_count'] ?></div>
                            <small class="text-gray-400">In Bearbeitung</small>
                        </div>
                        <div class="intra__tile p-3 text-center">
                            <div class="text-3xl font-bold text-primary"><?= (int)$stats['deferred_count'] ?></div>
                            <small class="text-gray-400">Aufgeschoben</small>
                        </div>
                        <div class="intra__tile p-3 text-center">
                            <div class="text-3xl font-bold text-success"><?= (int)$stats['resolved_count'] ?></div>
                            <small class="text-gray-400">Gelöst</small>
                        </div>
                        <div class="intra__tile p-3 text-center">
                            <div class="text-3xl font-bold" style="color:#ff4444;"><?= (int)$stats['not_operable_open'] ?></div>
                            <small class="text-gray-400">Nicht einsatzfähig</small>
                        </div>
                    </div>

                    <!-- Filter -->
                    <div class="intra__tile mb-4 p-3">
                        <form method="GET" class="flex flex-wrap items-end gap-2">
                            <div>
                                <label class="form-label mb-1">Fahrzeug</label>
                                <select name="vehicle" class="form-select form-select-sm" data-custom-dropdown="true">
                                    <option value="">Alle</option>
                                    <?php foreach ($vehicles as $v): ?>
                                        <option value="<?= $v['id'] ?>" <?= $filterVehicle == $v['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['veh_type']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label mb-1">Status</label>
                                <select name="status" class="form-select form-select-sm" data-custom-dropdown="true">
                                    <option value="">Alle</option>
                                    <option value="open" <?= $filterStatus === 'open' ? 'selected' : '' ?>>Offen</option>
                                    <option value="in_progress" <?= $filterStatus === 'in_progress' ? 'selected' : '' ?>>In Bearbeitung</option>
                                    <option value="deferred" <?= $filterStatus === 'deferred' ? 'selected' : '' ?>>Aufgeschoben</option>
                                    <option value="resolved" <?= $filterStatus === 'resolved' ? 'selected' : '' ?>>Gelöst</option>
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary"><i class="fa-solid fa-filter"></i> Filtern</button>
                                <a href="?" class="ignis-btn ignis-btn--sm ignis-btn--ghost no-underline hover:no-underline">Zurücksetzen</a>
                            </div>
                        </form>
                    </div>

                    <!-- Defekt-Liste -->
                    <div class="intra__tile">
                        <?php if (!empty($defects)): ?>
                            <div class="p-3" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                                    <input type="text" id="defectLocalSearch" class="ignis-input" placeholder="Defekte durchsuchen (Titel, Fahrzeug, Kategorie, Melder...)">
                                </div>
                            </div>
                        <?php endif; ?>
                        <div id="defectNoResults" class="p-4 text-center text-gray-400" style="display:none;">
                            <i class="fa-solid fa-search fa-2x mb-2" style="opacity:0.4;"></i>
                            <div>Keine Treffer</div>
                        </div>
                        <?php if (empty($defects)): ?>
                            <div class="p-4 text-center text-gray-400">
                                <i class="fa-solid fa-check-circle fa-2x mb-2" style="opacity:0.4;"></i>
                                <div>Keine Defekte gefunden</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($defects as $d):
                                $stat = $statusLabels[$d['status']] ?? ['?', 'secondary'];
                                $isResolved = $d['status'] === 'resolved';
                                $catLabel = $categoryLabels[$d['category']] ?? $d['category'];
                                $operable = (int)$d['vehicle_operable'];
                            ?>
                                <div class="defect-item <?= $isResolved ? 'defect-resolved' : '' ?>" data-id="<?= $d['id'] ?>" data-search="<?= htmlspecialchars(mb_strtolower($d['title'] . ' ' . $d['vehicle_name'] . ' ' . $d['vehicle_identifier'] . ' ' . ($d['kennzeichen'] ?? '') . ' ' . $catLabel . ' ' . ($d['reporter_name'] ?? '') . ' ' . ($d['description'] ?? '') . ' ' . ($d['resolution_note'] ?? ''))) ?>" style="cursor:pointer;">
                                    <div class="defect-operable-bar <?= $operable ? 'operable-yes' : 'operable-no' ?>"></div>
                                    <div class="defect-body">
                                        <div class="defect-header">
                                            <div class="defect-title-row">
                                                <h5 class="defect-title mb-0"><?= htmlspecialchars($d['title']) ?></h5>
                                                <div class="defect-badges">
                                                    <span class="ignis-chip"><?= htmlspecialchars($catLabel) ?></span>
                                                    <span class="badge text-bg-<?= $stat[1] ?>"><?= $stat[0] ?></span>
                                                    <?php if (!$operable): ?>
                                                        <span class="ignis-chip ignis-chip--danger"><i class="fa-solid fa-ban"></i> Nicht einsatzfähig</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="defect-vehicle">
                                                <i class="fa-solid fa-truck"></i>
                                                <?= htmlspecialchars($d['vehicle_name']) ?>
                                                <span class="text-gray-400">(<?= htmlspecialchars($d['vehicle_identifier']) ?>)</span>
                                            </div>
                                        </div>
                                        <?php if ($d['description']): ?>
                                            <p class="defect-desc mb-2"><?= nl2br(htmlspecialchars($d['description'])) ?></p>
                                        <?php endif; ?>
                                        <div class="defect-meta">
                                            <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($d['reporter_name'] ?? 'Unbekannt') ?></span>
                                            <span><i class="fa-solid fa-clock"></i> <?= \App\Helpers\DateTimeHelper::formatShortLocal($d['created_at']) ?></span>
                                            <?php if ($d['assigned_name']): ?>
                                                <span><i class="fa-solid fa-user-check"></i> <?= htmlspecialchars($d['assigned_name']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($d['last_status_user'] && !$d['resolved_at']): ?>
                                                <span><i class="fa-solid fa-pen"></i> <?= htmlspecialchars($d['last_status_details']) ?> — <?= htmlspecialchars($d['last_status_user']) ?>, <?= \App\Helpers\DateTimeHelper::formatShortLocal($d['last_status_at']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($d['resolved_at']): ?>
                                                <span><i class="fa-solid fa-check"></i> Gelöst am <?= \App\Helpers\DateTimeHelper::formatDateLocal($d['resolved_at']) ?> von <?= htmlspecialchars($d['resolver_name'] ?? 'Unbekannt') ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($d['resolution_note']): ?>
                                            <div class="defect-resolution mt-2">
                                                <small><strong>Lösung:</strong> <?= nl2br(htmlspecialchars($d['resolution_note'])) ?></small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($canManage && !$isResolved): ?>
                                            <div class="defect-actions mt-2">
                                                <?php if ($d['status'] === 'open' || $d['status'] === 'deferred'): ?>
                                                    <button class="ignis-btn ignis-btn--sm ignis-btn--soft-warning defect-status-btn"
                                                            data-id="<?= $d['id'] ?>"
                                                            data-title="<?= htmlspecialchars($d['title']) ?>"
                                                            data-status="in_progress"
                                                            data-label="In Bearbeitung"
                                                            data-btn-class="btn-warning"
                                                            title="In Bearbeitung setzen">
                                                        <i class="fa-solid fa-wrench"></i> In Bearbeitung
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($d['status'] === 'open' || $d['status'] === 'in_progress'): ?>
                                                    <button class="ignis-btn ignis-btn--sm ignis-btn--soft-primary defect-status-btn"
                                                            data-id="<?= $d['id'] ?>"
                                                            data-title="<?= htmlspecialchars($d['title']) ?>"
                                                            data-status="deferred"
                                                            data-label="Aufgeschoben"
                                                            data-btn-class="btn-primary"
                                                            title="Aufgeschoben">
                                                        <i class="fa-solid fa-clock"></i> Aufschieben
                                                    </button>
                                                <?php endif; ?>
                                                <button class="ignis-btn ignis-btn--sm ignis-btn--soft-success defect-resolve-btn"
                                                        data-id="<?= $d['id'] ?>"
                                                        data-title="<?= htmlspecialchars($d['title']) ?>"
                                                        title="Als gelöst markieren">
                                                    <i class="fa-solid fa-check"></i> Lösen
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
            </div>
        </div>
    </div>

    <!-- Neuen Defekt erstellen -->
    <div class="modal fade" id="createDefectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="createDefectForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Defekt melden</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Fahrzeug</label>
                            <select name="vehicle_id" class="form-select" required>
                                <option value="">Bitte wählen...</option>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?= $v['id'] ?>" <?= $filterVehicle == $v['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['name']) ?> — <?= htmlspecialchars($v['kennzeichen'] ?: $v['identifier']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Titel</label>
                            <input type="text" name="title" class="ignis-input" placeholder="Kurze Beschreibung des Defekts" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Beschreibung</label>
                            <textarea name="description" class="ignis-input" rows="3" placeholder="Detaillierte Beschreibung..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategorie</label>
                            <select name="category" class="form-select" required>
                                <option value="" disabled selected>Bitte auswählen...</option>
                                <?php foreach ($categoryLabels as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fahrzeug noch einsatzfähig?</label>
                            <div class="flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="vehicle_operable" id="operable-yes" value="1" checked>
                                    <label class="form-check-label" for="operable-yes">Ja</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="vehicle_operable" id="operable-no" value="0">
                                    <label class="form-check-label" for="operable-no">Nein</label>
                                </div>
                            </div>
                            <small class="text-gray-400">Bei "Nein" wird das Fahrzeug automatisch als nicht einsatzfähig markiert.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="ignis-btn ignis-btn--success"><i class="fa-solid fa-paper-plane"></i> Melden</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Defekt lösen Modal -->
    <div class="modal fade" id="resolveDefectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="resolveDefectForm">
                    <input type="hidden" name="id" id="resolve-defect-id">
                    <div class="modal-header">
                        <h5 class="modal-title">Defekt lösen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Defekt <strong id="resolve-defect-title"></strong> als gelöst markieren?</p>
                        <div class="mb-3">
                            <label class="form-label">Lösungsnotiz <small class="text-gray-400">(optional)</small></label>
                            <textarea name="resolution_note" class="ignis-input" rows="3" placeholder="Was wurde gemacht?"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="ignis-btn ignis-btn--success"><i class="fa-solid fa-check"></i> Als gelöst markieren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Status ändern Modal -->
    <div class="modal fade" id="statusChangeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="statusChangeForm">
                    <input type="hidden" name="id" id="status-change-id">
                    <input type="hidden" name="status" id="status-change-status">
                    <div class="modal-header">
                        <h5 class="modal-title" id="status-change-title">Status ändern</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Defekt <strong id="status-change-defect-title"></strong> auf <span id="status-change-label" class="font-bold"></span> setzen?</p>
                        <div class="mb-3">
                            <label class="form-label">Notiz <small class="text-gray-400">(optional)</small></label>
                            <textarea name="status_note" class="ignis-input" rows="3" placeholder="z.B. Ersatzteil bestellt, wird nächste Woche geliefert..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn" id="status-change-submit">Bestätigen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Defekt-Detail Modal (mit Log) -->
    <div class="modal fade" id="defectDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detail-title">Defekt-Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-12">
                        <div class="md:col-span-6">
                            <small class="text-gray-400">Fahrzeug</small>
                            <div id="detail-vehicle" class="font-bold"></div>
                        </div>
                        <div class="md:col-span-3">
                            <small class="text-gray-400">Kategorie</small>
                            <div id="detail-category"></div>
                        </div>
                        <div class="md:col-span-3">
                            <small class="text-gray-400">Status</small>
                            <div id="detail-status"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-gray-400">Beschreibung</small>
                        <div id="detail-description"></div>
                    </div>
                    <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div>
                            <small class="text-gray-400">Gemeldet von</small>
                            <div id="detail-reporter"></div>
                        </div>
                        <div>
                            <small class="text-gray-400">Zugewiesen an</small>
                            <div id="detail-assigned"></div>
                        </div>
                        <div>
                            <small class="text-gray-400">Einsatzfähig?</small>
                            <div id="detail-operable"></div>
                        </div>
                    </div>
                    <div id="detail-resolution-wrap" class="mb-3" style="display:none;">
                        <small class="text-gray-400">Lösung</small>
                        <div class="defect-resolution p-2" id="detail-resolution"></div>
                    </div>

                    <hr>
                    <h6><i class="fa-solid fa-clock-rotate-left"></i> Verlauf</h6>
                    <div id="detail-log" class="defect-log-timeline"></div>
                </div>
                <div class="modal-footer">
                    <?php if ($canManage): ?>
                        <div class="me-auto flex gap-2">
                            <select id="detail-assign-select" class="form-select form-select-sm" data-custom-dropdown="true" style="width:auto;">
                                <option value="">Zuweisen an...</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="ignis-btn ignis-btn--sm ignis-btn--soft-primary" id="detail-assign-btn">Zuweisen</button>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .defect-item {
            display: flex;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            transition: background 0.15s;
        }
        .defect-item:last-child { border-bottom: none; }
        .defect-item:hover { background: rgba(255,255,255,0.02); }
        .defect-resolved { opacity: 0.55; }
        .defect-operable-bar {
            width: 4px;
            flex-shrink: 0;
            border-radius: 4px 0 0 4px;
        }
        .operable-yes { background: var(--bs-success); }
        .operable-no { background: var(--bs-danger); }
        .defect-body {
            flex: 1;
            padding: 0.85rem 1rem;
            min-width: 0;
        }
        .defect-header { margin-bottom: 0.35rem; }
        .defect-title-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .defect-title {
            font-size: 0.95rem;
            font-weight: 500;
        }
        .defect-badges {
            display: flex;
            gap: 0.3rem;
        }
        .defect-badges .badge { font-size: 0.65rem; font-weight: 500; }
        .defect-vehicle {
            font-size: 0.8rem;
            color: var(--text-dimmed);
            margin-top: 0.15rem;
        }
        .defect-vehicle i { margin-right: 0.3rem; font-size: 0.7rem; }
        .defect-desc {
            font-size: 0.85rem;
            color: var(--text-normal);
            line-height: 1.5;
        }
        .defect-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            font-size: 0.75rem;
            color: var(--text-dimmed);
        }
        .defect-meta i { margin-right: 0.25rem; }
        .defect-resolution {
            background: rgba(25, 135, 84, 0.1);
            border-left: 3px solid var(--bs-success);
            padding: 0.4rem 0.6rem;
            border-radius: 0 6px 6px 0;
            font-size: 0.8rem;
        }
        .defect-actions {
            display: flex;
            gap: 0.4rem;
        }

        /* Log Timeline */
        .defect-log-timeline {
            position: relative;
            padding-left: 1.5rem;
        }
        .defect-log-timeline::before {
            content: '';
            position: absolute;
            left: 0.45rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: rgba(255,255,255,0.1);
        }
        .log-entry {
            position: relative;
            padding: 0.4rem 0;
            font-size: 0.8rem;
        }
        .log-entry::before {
            content: '';
            position: absolute;
            left: -1.15rem;
            top: 0.65rem;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--bs-primary);
            border: 2px solid var(--bs-body-bg);
        }
        .log-entry .log-user { font-weight: 500; }
        .log-entry .log-time {
            font-size: 0.7rem;
            color: var(--text-dimmed);
        }
        .log-entry .log-detail { color: var(--text-normal); }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var handlerUrl = '<?= BASE_PATH ?>api/vehicles/defects-handler.php';
        var categoryLabels = <?= json_encode($categoryLabels) ?>;
        var statusLabels = { open: ['Offen', 'danger'], in_progress: ['In Bearbeitung', 'warning'], deferred: ['Aufgeschoben', 'primary'], resolved: ['Gelöst', 'success'] };

        // Defekt erstellen
        var createForm = document.getElementById('createDefectForm');
        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var fd = new FormData(this);
                fd.append('action', 'create');

                fetch(handlerUrl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.error || 'Fehler');
                        }
                    });
            });
        }

        // Status ändern (In Bearbeitung / Aufschieben) — Modal öffnen
        document.querySelectorAll('.defect-status-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                document.getElementById('status-change-id').value = this.dataset.id;
                document.getElementById('status-change-status').value = this.dataset.status;
                document.getElementById('status-change-defect-title').textContent = this.dataset.title;
                document.getElementById('status-change-label').textContent = this.dataset.label;
                var submitBtn = document.getElementById('status-change-submit');
                submitBtn.className = 'btn ' + this.dataset.btnClass;
                submitBtn.textContent = this.dataset.label;
                document.querySelector('#statusChangeForm textarea[name="status_note"]').value = '';
                new bootstrap.Modal(document.getElementById('statusChangeModal')).show();
            });
        });

        // Status ändern — Absenden
        var statusForm = document.getElementById('statusChangeForm');
        if (statusForm) {
            statusForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var fd = new FormData(this);
                fd.append('action', 'update');

                fetch(handlerUrl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.error || 'Fehler');
                        }
                    });
            });
        }

        // Lösen-Dialog öffnen
        document.querySelectorAll('.defect-resolve-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                document.getElementById('resolve-defect-id').value = this.dataset.id;
                document.getElementById('resolve-defect-title').textContent = this.dataset.title;
                new bootstrap.Modal(document.getElementById('resolveDefectModal')).show();
            });
        });

        // Defekt lösen
        var resolveForm = document.getElementById('resolveDefectForm');
        if (resolveForm) {
            resolveForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var fd = new FormData(this);
                fd.append('action', 'resolve');

                fetch(handlerUrl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.error || 'Fehler');
                        }
                    });
            });
        }

        // Defekt-Detail öffnen bei Klick auf Item
        document.querySelectorAll('.defect-item').forEach(function(item) {
            item.addEventListener('click', function() {
                var defectId = this.dataset.id;
                fetch(handlerUrl + '?action=get&id=' + defectId)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.success) return;
                        var d = data.defect;
                        var stat = statusLabels[d.status] || ['?', 'secondary'];

                        document.getElementById('detail-title').textContent = d.title;
                        document.getElementById('detail-vehicle').textContent = (d.vehicle_name || '') + ' (' + (d.vehicle_identifier || '') + ')';
                        document.getElementById('detail-category').innerHTML = '<span class="ignis-chip">' + (categoryLabels[d.category] || d.category) + '</span>';
                        document.getElementById('detail-status').innerHTML = '<span class="badge text-bg-' + stat[1] + '">' + stat[0] + '</span>';
                        document.getElementById('detail-description').textContent = d.description || '—';
                        document.getElementById('detail-reporter').textContent = (d.reporter_name || 'Unbekannt') + ' am ' + formatDate(d.created_at);
                        document.getElementById('detail-assigned').textContent = d.assigned_name || '—';
                        document.getElementById('detail-operable').innerHTML = d.vehicle_operable == 1
                            ? '<span class="text-success"><i class="fa-solid fa-check"></i> Ja</span>'
                            : '<span class="text-danger"><i class="fa-solid fa-ban"></i> Nein</span>';

                        var resWrap = document.getElementById('detail-resolution-wrap');
                        if (d.resolution_note) {
                            resWrap.style.display = '';
                            document.getElementById('detail-resolution').textContent = d.resolution_note;
                        } else {
                            resWrap.style.display = 'none';
                        }

                        // Log anzeigen
                        var logHtml = '';
                        if (d.log && d.log.length) {
                            d.log.forEach(function(entry) {
                                logHtml += '<div class="log-entry">';
                                logHtml += '<span class="log-user">' + escHtml(entry.user_name || 'System') + '</span> ';
                                logHtml += '<span class="log-detail">' + escHtml(entry.details || entry.action) + '</span>';
                                logHtml += '<div class="log-time">' + formatDate(entry.created_at) + '</div>';
                                logHtml += '</div>';
                            });
                        } else {
                            logHtml = '<div class="text-gray-400">Kein Verlauf vorhanden</div>';
                        }
                        document.getElementById('detail-log').innerHTML = logHtml;

                        // Assign Dropdown vorbereiten
                        var assignSelect = document.getElementById('detail-assign-select');
                        if (assignSelect) {
                            assignSelect.value = d.assigned_to || '';
                            var assignBtn = document.getElementById('detail-assign-btn');
                            assignBtn.onclick = function() {
                                var fd = new FormData();
                                fd.append('action', 'update');
                                fd.append('id', d.id);
                                fd.append('assigned_to', assignSelect.value);
                                fetch(handlerUrl, { method: 'POST', body: fd })
                                    .then(function(r) { return r.json(); })
                                    .then(function(res) {
                                        if (res.success) location.reload();
                                    });
                            };
                        }

                        new bootstrap.Modal(document.getElementById('defectDetailModal')).show();
                    });
            });
        });

        function formatDate(str) {
            if (!str) return '';
            var d = new Date(str);
            return d.toLocaleDateString('de-DE') + ' ' + d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        }

        function escHtml(s) {
            var div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        // Lokale Suche
        var searchInput = document.getElementById('defectLocalSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                var items = document.querySelectorAll('.defect-item');
                var visibleCount = 0;
                items.forEach(function(item) {
                    var searchData = item.dataset.search || '';
                    var match = !q || searchData.indexOf(q) !== -1;
                    item.style.display = match ? '' : 'none';
                    if (match) visibleCount++;
                });
                var noResults = document.getElementById('defectNoResults');
                if (noResults) noResults.style.display = visibleCount === 0 ? '' : 'none';
            });
        }
    });
    </script>

    <?php include __DIR__ . "/../../../../assets/components/footer.php"; ?>
</body>
</html>
