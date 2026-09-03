<?php
/**
 * View: Fahrzeugverwaltung
 *
 * Sortierung, Suche und Seiten laufen über den Server (App\Support\ListQuery,
 * Settings\FahrzeugeController::index).
 *
 * @var \Illuminate\Support\Collection<int, array<string,mixed>> $vehicles  Zeilen der aktuellen Seite
 * @var \App\Support\ListQuery                                    $list
 */

use App\Auth\Permissions;

$layout = 'admin';
$bodyId = 'fahrzeuge';
$SITE_TITLE = 'Fahrzeuge';
?>
    <div class="container-full relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item is-active">Fahrzeuge</span></nav>
                    <div class="page-header twplus-page-header mb-4">
                        <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Fuhrpark</p><h1>Fahrzeugverwaltung</h1><p class="twplus-page-header__description">Fahrzeuge, Kennungen und Stammdaten verwalten.</p></div>
                        <div class="header-actions twplus-page-header__actions">
                            <a href="<?= BASE_PATH ?>settings/vehicles/defects/index" class="ignis-btn ignis-btn--secondary">
                                <i class="fa-solid fa-triangle-exclamation"></i> Defekt-Meldungen
                            </a>
                            <?php if (Permissions::check(['admin', 'vehicles.manage'])) : ?>
                                <button type="button" class="ignis-btn ignis-btn--ghost" onclick="openTzTemplateManager()">
                                    <i class="fa-solid fa-shapes"></i> TZ-Vorlagen
                                </button>
                                <button type="button" class="ignis-btn ignis-btn--secondary" onclick="openVehicleImport()">
                                    <i class="fa-solid fa-satellite-dish"></i> EMD-Import
                                    <span class="ignis-chip ignis-chip--danger ml-1 hidden" id="importBadge">0</span>
                                </button>
                                <button type="button" class="ignis-btn ignis-btn--primary" onclick="openCreateFahrzeugModal()">
                                    <i class="fa-solid fa-plus"></i> Fahrzeug erstellen
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    $pgPath  = 'settings/vehicles/vehicles/index';
                    $pgLabel = 'Fahrzeuge';
                    $canManage = Permissions::check(['admin', 'vehicles.manage']);
                    $rdTypes = [
                        1 => ['warn', 'RD - Mit NA'],
                        2 => ['ok', 'RD - Ohne NA'],
                        3 => ['danger', 'Feuerwehr'],
                    ];
                    ?>
                    <form class="ignis-list-toolbar" method="get" action="<?= BASE_PATH . $pgPath ?>" role="search">
                        <?php if ($list->sort !== 'priority' || $list->dir !== 'asc'): ?>
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($list->sort) ?>">
                            <input type="hidden" name="dir" value="<?= htmlspecialchars($list->dir) ?>">
                        <?php endif; ?>
                        <?php if ($list->filter('active') !== ''): ?>
                            <input type="hidden" name="active" value="<?= htmlspecialchars($list->filter('active')) ?>">
                        <?php endif; ?>
                        <label class="ignis-list-toolbar__search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Bezeichnung, Kennzeichen oder Typ" aria-label="Fahrzeuge suchen">
                        </label>
                        <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--sm">Suchen</button>
                        <?php if ($list->q !== ''): ?>
                            <a class="ignis-btn ignis-btn--ghost ignis-btn--sm" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'page' => null])) ?>">Zurücksetzen</a>
                        <?php endif; ?>
                        <span class="ignis-list-toolbar__spacer"></span>
                        <nav class="ignis-filter-links" aria-label="Aktiv">
                            <?php foreach (['' => 'Alle', '1' => 'Aktiv', '0' => 'Inaktiv'] as $activeKey => $activeLabel): ?>
                                <a href="<?= htmlspecialchars($list->url($pgPath, ['active' => $activeKey === '' ? null : $activeKey, 'page' => null])) ?>"<?= $list->filter('active') === $activeKey ? ' class="is-active" aria-current="true"' : '' ?>><?= $activeLabel ?></a>
                            <?php endforeach; ?>
                        </nav>
                    </form>
                    <div class="twplus-table-card">
                        <div class="twplus-table-card__scroll">
                        <table class="ignis-table" id="table-fahrzeuge">
                            <thead>
                                <tr>
                                    <?= $list->th('priority', 'Priorität', $pgPath, 'ignis-table__num') ?>
                                    <?= $list->th('name', 'Bezeichnung (Typ)', $pgPath) ?>
                                    <?= $list->th('kennzeichen', 'Kennzeichen', $pgPath) ?>
                                    <?= $list->th('rd', 'Fahrzeugtyp', $pgPath) ?>
                                    <?= $list->th('defects', 'Defekte', $pgPath, 'ignis-table__num') ?>
                                    <?= $list->th('active', 'Aktiv?', $pgPath) ?>
                                    <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($vehicles->isEmpty()): ?>
                                    <tr><td colspan="7" class="ignis-table-empty">Keine Fahrzeuge gefunden.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($vehicles as $row):
                                    [$rdChip, $rdLabel] = $rdTypes[(int) $row['rd_type']] ?? ['secondary', 'Andere'];
                                    $isActive    = (int) $row['active'] !== 0;
                                    $openDefects = (int) ($row['open_defects'] ?? 0);
                                    $minOperable = $row['min_operable'];
                                    $defectChip  = ($minOperable !== null && (int) $minOperable === 0) ? 'danger' : 'warn';

                                    $dataStr = '';
                                    if ($canManage) {
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
                                        foreach ($dataAttrs as $key => $val) {
                                            $dataStr .= ' data-' . $key . '="' . htmlspecialchars((string) $val, ENT_QUOTES) . '"';
                                        }
                                    }
                                ?>
                                    <tr<?= $isActive ? '' : ' class="is-muted"' ?>>
                                        <td class="ignis-table__num"><?= (int) $row['priority'] ?></td>
                                        <td><span data-vehicle-card="<?= (int) $row['id'] ?>" style="cursor:help;"><?= htmlspecialchars($row['name']) ?> (<?= htmlspecialchars($row['veh_type']) ?>)</span></td>
                                        <td><?= ($row['kennzeichen'] ?? '') !== '' ? '<span class="ignis-mono">' . htmlspecialchars($row['kennzeichen']) . '</span>' : '-' ?></td>
                                        <td><span class="ignis-chip ignis-chip--<?= $rdChip ?>"><?= $rdLabel ?></span></td>
                                        <td class="ignis-table__num">
                                            <?php if ($openDefects > 0): ?>
                                                <a href="<?= BASE_PATH ?>settings/vehicles/defects/index?vehicle=<?= (int) $row['id'] ?>" class="ignis-chip ignis-chip--<?= $defectChip ?>" title="Offene Defekte anzeigen"><?= $openDefects ?></a>
                                            <?php else: ?>
                                                <span class="text-[var(--text-3)]">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isActive): ?>
                                                <span class="ignis-chip ignis-chip--dot ignis-chip--ok">Ja</span>
                                            <?php else: ?>
                                                <span class="ignis-chip ignis-chip--dot ignis-chip--danger">Nein</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="ignis-table__actions">
                                            <?php if ($canManage): ?>
                                                <div class="ignis-row-actions">
                                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon edit-btn" data-ignis-tooltip="Fahrzeug bearbeiten" aria-label="Fahrzeug bearbeiten" onclick="openEditFahrzeugModal(this)"<?= $dataStr ?>><i class="fa-solid fa-pen"></i></button>
                                                    <a href="#" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon copy-btn" data-ignis-tooltip="Fahrzeug kopieren" aria-label="Fahrzeug kopieren"<?= $dataStr ?>><i class="fa-solid fa-copy"></i></a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <?php require dirname(__DIR__, 3) . '/partials/pagination.php'; ?>
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
    <div data-dialog-source class="modal" id="tzTemplateModal" tabindex="-1" aria-labelledby="tzTemplateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tzTemplateModalLabel">
                        <i class="fa-solid fa-shapes mr-2"></i>TZ-Vorlagen verwalten
                    </h5>
                    <button type="button" class="btn-close" data-dialog-dismiss aria-label="Schließen"></button>
                </div>
                <div class="modal-body" id="tzTemplateModalBody">
                    <div class="twplus-skeleton" aria-label="Vorlagen werden geladen">
                        <div class="twplus-skeleton__line twplus-skeleton__line--short"></div>
                        <div class="twplus-skeleton__line"></div>
                        <div class="twplus-skeleton__line"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- EMD Vehicle Import Modal -->
    <?php if (Permissions::check(['admin', 'vehicles.manage'])) : ?>
    <div data-dialog-source class="modal" id="vehicleImportModal" tabindex="-1" aria-labelledby="vehicleImportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vehicleImportModalLabel">
                        <i class="fa-solid fa-satellite-dish mr-2"></i>Fahrzeuge aus EMD importieren
                    </h5>
                    <button type="button" class="btn-close" data-dialog-dismiss aria-label="Schließen"></button>
                </div>
                <div class="modal-body" id="importModalBody">
                    <div class="twplus-skeleton" aria-label="Importstatus wird geladen">
                        <div class="twplus-skeleton__line twplus-skeleton__line--short"></div>
                        <div class="twplus-skeleton__line"></div>
                        <div class="twplus-skeleton__line"></div>
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
