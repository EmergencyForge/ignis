<?php
/**
 * View: Defekt-Meldungen-Verwaltung
 *
 * Seitenkopf wie die übrigen Listen (Brotkrumen, Titel, Aktionen rechts),
 * Filter als Werkzeugleiste (Fahrzeug als Auswahl, Status als Links), die
 * Meldungen als Stapel mit Anker je Mangel (#defect-ID, Ziel der Links aus
 * Vorschau und Fahrzeugseite). Die Abfragen liegen weiter hier im Template.
 */

use App\Auth\Permissions;
use Illuminate\Database\Capsule\Manager as Capsule;

$canManage = Permissions::check(['admin', 'vehicles.manage']);

// Fahrzeuge laden für Dropdown
$vehicles = Capsule::table('intra_fahrzeuge')
    ->orderBy('name')
    ->get(['id', 'name', 'identifier', 'kennzeichen', 'veh_type'])
    ->map(fn ($row) => (array) $row)
    ->all();

// Benutzer laden für Zuweisungs-Dropdown
$users = Capsule::table('intra_users as u')
    ->leftJoin('intra_mitarbeiter as m', 'u.discord_id', '=', 'm.discordtag')
    ->where('u.is_active', 1)
    ->orderBy('fullname')
    ->get(['u.id', Capsule::raw('COALESCE(m.fullname, u.username) AS fullname')])
    ->map(fn ($row) => (array) $row)
    ->all();

$categoryLabels = \App\Models\VehicleDefect::CATEGORY_LABELS;

// Tabelle prüfen & Statistiken laden
$tableExists = true;
$stats = ['total' => 0, 'open_count' => 0, 'in_progress_count' => 0, 'deferred_count' => 0, 'resolved_count' => 0, 'not_operable_open' => 0];
$defects = [];
$filterVehicle = isset($_GET['vehicle']) ? (int)$_GET['vehicle'] : 0;
$filterStatus = $_GET['status'] ?? '';
if (!in_array($filterStatus, ['open', 'in_progress', 'deferred', 'resolved'], true)) {
    $filterStatus = '';
}

try {
    $stats = (array) Capsule::table('intra_fahrzeuge_defects')
        ->selectRaw("
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
        SUM(CASE WHEN status = 'deferred' THEN 1 ELSE 0 END) AS deferred_count,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count,
        SUM(CASE WHEN vehicle_operable = 0 AND status != 'resolved' THEN 1 ELSE 0 END) AS not_operable_open")
        ->first();
} catch (PDOException $e) {
    $tableExists = false;
}

if ($tableExists) {
    $lastLogSub = Capsule::table('intra_fahrzeuge_defect_log as l')
        ->leftJoin('intra_users as u', 'l.user_id', '=', 'u.id')
        ->leftJoin('intra_mitarbeiter as m', 'u.discord_id', '=', 'm.discordtag')
        ->whereRaw("l.id = (
                    SELECT l2.id FROM intra_fahrzeuge_defect_log l2
                    WHERE l2.defect_id = l.defect_id AND l2.action IN ('updated', 'resolved')
                    ORDER BY l2.created_at DESC LIMIT 1
                )")
        ->select([
            'l.defect_id',
            Capsule::raw('COALESCE(m.fullname, u.username) AS last_status_user'),
            'l.details as last_status_details',
            'l.created_at as last_status_at',
        ]);

    $query = Capsule::table('intra_fahrzeuge_defects as d')
        ->join('intra_fahrzeuge as f', 'd.vehicle_id', '=', 'f.id')
        ->leftJoin('intra_users as u1', 'd.reported_by', '=', 'u1.id')
        ->leftJoin('intra_mitarbeiter as m1', 'u1.discord_id', '=', 'm1.discordtag')
        ->leftJoin('intra_users as u2', 'd.assigned_to', '=', 'u2.id')
        ->leftJoin('intra_mitarbeiter as m2', 'u2.discord_id', '=', 'm2.discordtag')
        ->leftJoin('intra_users as u3', 'd.resolved_by', '=', 'u3.id')
        ->leftJoin('intra_mitarbeiter as m3', 'u3.discord_id', '=', 'm3.discordtag')
        ->leftJoinSub($lastLogSub, 'last_log', 'last_log.defect_id', '=', 'd.id')
        ->select([
            'd.*',
            'f.name as vehicle_name',
            'f.identifier as vehicle_identifier',
            'f.kennzeichen',
            'f.veh_type',
            Capsule::raw('COALESCE(m1.fullname, u1.username) AS reporter_name'),
            Capsule::raw('COALESCE(m2.fullname, u2.username) AS assigned_name'),
            Capsule::raw('COALESCE(m3.fullname, u3.username) AS resolver_name'),
            'last_log.last_status_user',
            'last_log.last_status_details',
            'last_log.last_status_at',
        ]);

    if ($filterVehicle) {
        $query->where('d.vehicle_id', $filterVehicle);
    }
    if ($filterStatus !== '') {
        $query->where('d.status', $filterStatus);
    }

    $defects = $query
        ->orderByRaw("FIELD(d.status, 'open', 'in_progress', 'deferred', 'resolved')")
        ->orderByRaw("CASE WHEN d.status != 'resolved' THEN d.vehicle_operable END ASC")
        ->orderByDesc('d.created_at')
        ->get()
        ->map(fn ($row) => (array) $row)
        ->all();
}

$statusLabels = \App\Models\VehicleDefect::STATUS_LABELS;

// URL der Liste mit geänderten Filtern (null entfernt den Parameter).
$listUrl = static function (array $overrides = []) use ($filterVehicle, $filterStatus): string {
    $params = array_filter(['vehicle' => $filterVehicle > 0 ? $filterVehicle : null, 'status' => $filterStatus !== '' ? $filterStatus : null] + [], static fn ($v) => $v !== null);
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    return BASE_PATH . 'settings/vehicles/defects/index' . ($params === [] ? '' : '?' . http_build_query($params));
};

$layout = 'admin';
$bodyId = 'fahrzeuge';
$SITE_TITLE = 'Fahrzeug-Defekte';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="mb-6">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/vehicles/vehicles/index">Fahrzeuge</a></span> <span class="ignis-breadcrumb__item is-active">Defekt-Meldungen</span></nav>

                    <div class="page-header twplus-page-header mb-4">
                        <div class="twplus-page-header__copy">
                            <p class="twplus-page-header__eyebrow">Fuhrpark</p>
                            <h1>Defekt-Meldungen</h1>
                            <p class="twplus-page-header__description">Einsatzfähigkeit, Bearbeitungsstand und Lösungen aller Fahrzeugmängel.</p>
                        </div>
                        <div class="header-actions twplus-page-header__actions">
                            <a href="<?= BASE_PATH ?>settings/vehicles/vehicles/index" class="ignis-btn ignis-btn--secondary">
                                <i class="fa-solid fa-truck"></i> Fahrzeuge
                            </a>
                            <a href="<?= BASE_PATH ?>settings/vehicles/defects/create<?= $filterVehicle > 0 ? '?vehicle=' . $filterVehicle : '' ?>" class="ignis-btn ignis-btn--primary" data-ignis-drawer>
                                <i class="fa-solid fa-plus"></i> Mangel melden
                            </a>
                        </div>
                    </div>


                    <?php if (!$tableExists): ?>
                        <div class="ignis-alert ignis-alert--warn">
                            <i class="fa-solid fa-database"></i> Die Tabelle <code>intra_fahrzeuge_defects</code> existiert noch nicht.
                            Lade die Seite neu — die Datenbank wird automatisch migriert. Falls das Problem bestehen bleibt, führe auf der Konsole <code>composer db:migrate</code> aus.
                        </div>
                    <?php endif; ?>

                    <!-- Statistik-Karten -->
                    <dl class="twplus-stats twplus-stats--five" aria-label="Defektstatistik">
                        <div class="twplus-stats__item">
                            <dt class="twplus-stats__label">Offen</dt>
                            <dd class="twplus-stats__value text-[var(--danger)]"><?= (int)$stats['open_count'] ?></dd>
                        </div>
                        <div class="twplus-stats__item">
                            <dt class="twplus-stats__label">In Bearbeitung</dt>
                            <dd class="twplus-stats__value text-[var(--warn)]"><?= (int)$stats['in_progress_count'] ?></dd>
                        </div>
                        <div class="twplus-stats__item">
                            <dt class="twplus-stats__label">Aufgeschoben</dt>
                            <dd class="twplus-stats__value text-[var(--info)]"><?= (int)$stats['deferred_count'] ?></dd>
                        </div>
                        <div class="twplus-stats__item">
                            <dt class="twplus-stats__label">Gelöst</dt>
                            <dd class="twplus-stats__value text-[var(--ok)]"><?= (int)$stats['resolved_count'] ?></dd>
                        </div>
                        <div class="twplus-stats__item">
                            <dt class="twplus-stats__label">Nicht einsatzfähig</dt>
                            <dd class="twplus-stats__value text-[var(--danger)]"><?= (int)$stats['not_operable_open'] ?></dd>
                        </div>
                    </dl>

                    <!-- Filter: Fahrzeug als Auswahl (GET), Status als Links, Suche lokal im Browser -->
                    <form method="GET" action="<?= BASE_PATH ?>settings/vehicles/defects/index" class="ignis-list-toolbar" role="search">
                        <?php if ($filterStatus !== ''): ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                        <?php endif; ?>
                        <label class="ignis-list-toolbar__search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" id="defectLocalSearch" class="ignis-input" placeholder="Titel, Fahrzeug, Kategorie, Melder" aria-label="Defekte durchsuchen"<?= empty($defects) ? ' disabled' : '' ?>>
                        </label>
                        <label for="defectVehicleFilter" class="sr-only">Fahrzeug</label>
                        <select name="vehicle" id="defectVehicleFilter" class="ignis-input ignis-input--sm" data-custom-dropdown="true" style="width:auto;max-width:18rem;">
                            <option value="">Alle Fahrzeuge</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?= $v['id'] ?>" <?= $filterVehicle == $v['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['veh_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--sm">Filtern</button>
                        <?php if ($filterVehicle > 0): ?>
                            <a href="<?= htmlspecialchars($listUrl(['vehicle' => null])) ?>" class="ignis-btn ignis-btn--ghost ignis-btn--sm">Zurücksetzen</a>
                        <?php endif; ?>
                        <span class="ignis-list-toolbar__spacer"></span>
                        <nav class="ignis-filter-links" aria-label="Status">
                            <?php foreach (['' => 'Alle'] + array_map(static fn (array $s): string => $s[0], $statusLabels) as $statusKey => $statusLabel): ?>
                                <a href="<?= htmlspecialchars($listUrl(['status' => $statusKey === '' ? null : $statusKey])) ?>"<?= $filterStatus === $statusKey ? ' class="is-active" aria-current="true"' : '' ?>><?= htmlspecialchars($statusLabel) ?></a>
                            <?php endforeach; ?>
                        </nav>
                    </form>

                    <!-- Defekt-Liste -->
                    <div class="twplus-stacked-list">
                        <div id="defectNoResults" class="ignis-table-empty" hidden>
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Keine Treffer
                        </div>
                        <?php if (empty($defects)): ?>
                            <div class="twplus-empty">
                                <i class="fa-solid fa-circle-check twplus-empty__icon" aria-hidden="true"></i>
                                <h2 class="twplus-empty__title">Keine Defekte gefunden</h2>
                                <p class="twplus-empty__description">Für die gewählten Filter liegen keine offenen oder archivierten Meldungen vor.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($defects as $d):
                                [$statLabel, $statChip] = $statusLabels[$d['status']] ?? ['?', 'secondary'];
                                $isResolved = $d['status'] === 'resolved';
                                $catLabel = $categoryLabels[$d['category']] ?? $d['category'];
                                $operable = (int)$d['vehicle_operable'];
                            ?>
                                <div class="defect-item <?= $isResolved ? 'defect-resolved' : '' ?>" id="defect-<?= (int) $d['id'] ?>" data-id="<?= $d['id'] ?>" data-search="<?= htmlspecialchars(mb_strtolower($d['title'] . ' ' . $d['vehicle_name'] . ' ' . $d['vehicle_identifier'] . ' ' . ($d['kennzeichen'] ?? '') . ' ' . $catLabel . ' ' . ($d['reporter_name'] ?? '') . ' ' . ($d['description'] ?? '') . ' ' . ($d['resolution_note'] ?? ''))) ?>">
                                    <div class="defect-operable-bar <?= $operable ? 'operable-yes' : 'operable-no' ?>"></div>
                                    <div class="defect-body">
                                        <div class="defect-header">
                                            <div class="defect-title-row">
                                                <h3 class="defect-title mb-0"><?= htmlspecialchars($d['title']) ?></h3>
                                                <div class="defect-badges">
                                                    <span class="ignis-chip ignis-chip--secondary"><?= htmlspecialchars($catLabel) ?></span>
                                                    <span class="ignis-chip ignis-chip--dot ignis-chip--<?= $statChip ?>"><?= htmlspecialchars($statLabel) ?></span>
                                                    <?php if (!$operable): ?>
                                                        <span class="ignis-chip ignis-chip--danger"><i class="fa-solid fa-ban"></i> Nicht einsatzfähig</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="defect-vehicle">
                                                <i class="fa-solid fa-truck"></i>
                                                <a href="<?= BASE_PATH ?>settings/vehicles/vehicles/<?= (int) $d['vehicle_id'] ?>" data-vehicle-card="<?= (int) $d['vehicle_id'] ?>">
                                                    <?= htmlspecialchars($d['vehicle_name']) ?>
                                                    <span class="ignis-preview__muted">(<?= htmlspecialchars($d['vehicle_identifier']) ?>)</span>
                                                </a>
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
                                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost defect-status-btn"
                                                            data-id="<?= $d['id'] ?>"
                                                            data-title="<?= htmlspecialchars($d['title']) ?>"
                                                            data-status="in_progress"
                                                            data-label="In Bearbeitung"
                                                            data-variant="warning"
                                                            title="In Bearbeitung setzen">
                                                        <i class="fa-solid fa-wrench"></i> In Bearbeitung
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($d['status'] === 'open' || $d['status'] === 'in_progress'): ?>
                                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost defect-status-btn"
                                                            data-id="<?= $d['id'] ?>"
                                                            data-title="<?= htmlspecialchars($d['title']) ?>"
                                                            data-status="deferred"
                                                            data-label="Aufgeschoben"
                                                            data-variant="primary"
                                                            title="Aufgeschoben">
                                                        <i class="fa-solid fa-clock"></i> Aufschieben
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary defect-resolve-btn"
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

    <!-- Form-Bodies als <template>; Dialoge werden in JS programmatisch erstellt. Melden laeuft ueber /settings/vehicles/defects/create im Drawer. -->
    <template id="resolveDefectFormTemplate">
        <p class="mb-3">Defekt <strong class="resolve-defect-title-display"></strong> als gelöst markieren?</p>
        <div class="mb-3">
            <label class="ignis-field__label">Lösungsnotiz <small class="form-hint">(optional)</small></label>
            <textarea name="resolution_note" class="ignis-input" rows="3" placeholder="Was wurde gemacht?"></textarea>
        </div>
    </template>

    <template id="statusChangeFormTemplate">
        <p class="mb-3">Defekt <strong class="status-change-defect-title-display"></strong> auf <span class="status-change-label-display font-bold"></span> setzen?</p>
        <div class="mb-3">
            <label class="ignis-field__label">Notiz <small class="form-hint">(optional)</small></label>
            <textarea name="status_note" class="ignis-input" rows="3" placeholder="z.B. Ersatzteil bestellt, wird nächste Woche geliefert..."></textarea>
        </div>
    </template>

    <!-- Detail-Body als <template>; Dialog wird in JS programmatisch instanziiert.
         Detail-Werte werden via .detail-*-Selektoren statt globaler IDs angesprochen,
         damit der geklonte Inhalt eindeutig auffindbar bleibt. -->
    <template id="defectDetailTemplate">
        <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-12">
            <div class="md:col-span-6">
                <small class="form-hint">Fahrzeug</small>
                <div class="detail-vehicle font-bold"></div>
            </div>
            <div class="md:col-span-3">
                <small class="form-hint">Kategorie</small>
                <div class="detail-category"></div>
            </div>
            <div class="md:col-span-3">
                <small class="form-hint">Status</small>
                <div class="detail-status"></div>
            </div>
        </div>
        <div class="mb-3">
            <small class="form-hint">Beschreibung</small>
            <div class="detail-description"></div>
        </div>
        <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-3">
            <div>
                <small class="form-hint">Gemeldet von</small>
                <div class="detail-reporter"></div>
            </div>
            <div>
                <small class="form-hint">Zugewiesen an</small>
                <div class="detail-assigned"></div>
            </div>
            <div>
                <small class="form-hint">Einsatzfähig?</small>
                <div class="detail-operable"></div>
            </div>
        </div>
        <div class="detail-resolution-wrap mb-3" hidden>
            <small class="form-hint">Lösung</small>
            <div class="defect-resolution p-2 detail-resolution"></div>
        </div>

        <hr>
        <h6><i class="fa-solid fa-clock-rotate-left"></i> Verlauf</h6>
        <div class="detail-log defect-log-timeline"></div>

        <?php if ($canManage): ?>
            <hr>
            <div class="flex gap-2">
                <select class="detail-assign-select ignis-input ignis-input--sm" data-custom-dropdown="true" style="width:auto;">
                    <option value="">Zuweisen an...</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary detail-assign-btn">Zuweisen</button>
            </div>
        <?php endif; ?>
    </template>

    <style>
        /* Stapel der Meldungen: nur Tokens, damit der helle Satz mitkommt. */
        .defect-item {
            display: flex;
            border-bottom: 1px solid var(--fill-2);
            transition: background 0.15s;
            cursor: pointer;
        }
        .defect-item[hidden], #defectNoResults[hidden] { display: none; }
        .defect-item:last-child { border-bottom: none; }
        .defect-item:hover { background: var(--fill-1); }
        .defect-item:target { background: var(--accent-soft); }
        .defect-resolved { opacity: 0.55; }
        .defect-operable-bar {
            width: 4px;
            flex-shrink: 0;
            border-radius: 4px 0 0 4px;
        }
        .operable-yes { background: var(--ok); }
        .operable-no { background: var(--danger); }
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
        .defect-vehicle {
            font-size: 0.8rem;
            color: var(--text-3);
            margin-top: 0.15rem;
        }
        .defect-vehicle i { margin-right: 0.3rem; font-size: 0.7rem; }
        .defect-vehicle a { color: var(--text-2); }
        .defect-desc {
            font-size: 0.85rem;
            color: var(--text-2);
            line-height: 1.5;
        }
        .defect-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            font-size: 0.75rem;
            color: var(--text-3);
        }
        .defect-meta i { margin-right: 0.25rem; }
        .defect-resolution {
            background: var(--ok-soft);
            border-left: 3px solid var(--ok);
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
            background: var(--fill-3);
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
            background: var(--accent);
            border: 2px solid var(--surface);
        }
        .log-entry .log-user { font-weight: 500; }
        .log-entry .log-time {
            font-size: 0.7rem;
            color: var(--text-3);
        }
        .log-entry .log-detail { color: var(--text-2); }
    </style>

    <script>
    var handlerUrl = '<?= BASE_PATH ?>api/vehicles/defects-handler';
    var categoryLabels = <?= json_encode($categoryLabels) ?>;
    var statusLabels = <?= json_encode($statusLabels) ?>;

    // Generischer AJAX-POST gegen den defects-handler. data ist ein {key: val}-
    // Objekt; action wird automatisch ergaenzt.
    function postDefectAction(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
        return fetch(handlerUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); });
    }
    function reloadOnSuccess(data) {
        if (data.success) location.reload();
        else alert(data.error || 'Fehler');
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Status-Aenderungs-Modal: Title + Submit-Button-Style ist pro Aktion
        // unterschiedlich (z.B. "In Bearbeitung" warning vs. "Aufschieben" primary).
        document.querySelectorAll('.defect-status-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var data = this.dataset;
                Dialog.form({
                    title:         'Status ändern',
                    template:      'statusChangeFormTemplate',
                    submitLabel:   data.label,
                    submitVariant: data.variant || 'primary',
                    onOpen: function (dlg) {
                        dlg.element.querySelector('.status-change-defect-title-display').textContent = data.title;
                        dlg.element.querySelector('.status-change-label-display').textContent = data.label;
                    },
                    onSubmit: function (body, dlg) {
                        postDefectAction('update', {
                            id:          data.id,
                            status:      data.status,
                            status_note: body.querySelector('textarea[name="status_note"]').value,
                        }).then(reloadOnSuccess);
                    },
                });
            });
        });

        document.querySelectorAll('.defect-resolve-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var data = this.dataset;
                Dialog.form({
                    title:        'Defekt lösen',
                    template:     'resolveDefectFormTemplate',
                    submitLabel:  'Als gelöst markieren',
                    submitIcon:   'fa-solid fa-check',
                    submitVariant:'success',
                    onOpen: function (dlg) {
                        dlg.element.querySelector('.resolve-defect-title-display').textContent = data.title;
                    },
                    onSubmit: function (body, dlg) {
                        postDefectAction('resolve', {
                            id:              data.id,
                            resolution_note: body.querySelector('textarea[name="resolution_note"]').value,
                        }).then(reloadOnSuccess);
                    },
                });
            });
        });

        document.querySelectorAll('.defect-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                if (e.target.closest('a, button')) return;
                var defectId = this.dataset.id;
                fetch(handlerUrl + '?action=get&id=' + defectId)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.success) return;
                        var d = data.defect;
                        var stat = statusLabels[d.status] || ['?', 'secondary'];

                        // Body-Element aus dem Template klonen
                        var tpl = document.getElementById('defectDetailTemplate');
                        var wrapper = document.createElement('div');
                        wrapper.appendChild(tpl.content.cloneNode(true));

                        // Felder befuellen via Klassen-Selektoren
                        wrapper.querySelector('.detail-vehicle').textContent = (d.vehicle_name || '') + ' (' + (d.vehicle_identifier || '') + ')';
                        wrapper.querySelector('.detail-category').innerHTML = '<span class="ignis-chip ignis-chip--secondary">' + escHtml(categoryLabels[d.category] || d.category) + '</span>';
                        wrapper.querySelector('.detail-status').innerHTML = '<span class="ignis-chip ignis-chip--dot ignis-chip--' + stat[1] + '">' + escHtml(stat[0]) + '</span>';
                        wrapper.querySelector('.detail-description').textContent = d.description || '—';
                        wrapper.querySelector('.detail-reporter').textContent = (d.reporter_name || 'Unbekannt') + ' am ' + formatDate(d.created_at);
                        wrapper.querySelector('.detail-assigned').textContent = d.assigned_name || '—';
                        wrapper.querySelector('.detail-operable').innerHTML = d.vehicle_operable == 1
                            ? '<span class="text-[var(--ok)]"><i class="fa-solid fa-check"></i> Ja</span>'
                            : '<span class="text-[var(--danger)]"><i class="fa-solid fa-ban"></i> Nein</span>';

                        var resWrap = wrapper.querySelector('.detail-resolution-wrap');
                        if (d.resolution_note) {
                            resWrap.hidden = false;
                            wrapper.querySelector('.detail-resolution').textContent = d.resolution_note;
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
                            logHtml = '<div class="form-hint">Kein Verlauf vorhanden</div>';
                        }
                        wrapper.querySelector('.detail-log').innerHTML = logHtml;

                        // Assign-Select vorbereiten (nur wenn canManage → Element vorhanden)
                        var assignSelect = wrapper.querySelector('.detail-assign-select');
                        if (assignSelect) {
                            assignSelect.value = d.assigned_to || '';
                            wrapper.querySelector('.detail-assign-btn').onclick = function() {
                                postDefectAction('update', {
                                    id:          d.id,
                                    assigned_to: assignSelect.value,
                                }).then(function(res) { if (res.success) location.reload(); });
                            };
                        }

                        new Dialog({
                            title:   d.title || 'Defekt-Details',
                            size:    'lg',
                            body:    wrapper,
                            actions: [{ label: 'Schließen', variant: 'ghost', close: true }],
                        }).open();
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

        // Lokale Suche (Enter darf das Filter-Formular nicht abschicken)
        var searchInput = document.getElementById('defectLocalSearch');
        if (searchInput) {
            searchInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') e.preventDefault(); });
            searchInput.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                var items = document.querySelectorAll('.defect-item');
                var visibleCount = 0;
                items.forEach(function(item) {
                    var searchData = item.dataset.search || '';
                    var match = !q || searchData.indexOf(q) !== -1;
                    item.hidden = !match;
                    if (match) visibleCount++;
                });
                var noResults = document.getElementById('defectNoResults');
                if (noResults) noResults.hidden = visibleCount !== 0;
            });
        }
    });
    </script>
