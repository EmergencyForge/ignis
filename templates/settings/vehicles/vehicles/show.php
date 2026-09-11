<?php
/**
 * View: Fahrzeug-Detail nach dem Detailmuster (Lex L8): Brotkrumen, Titel
 * mit Status-Chips, Aktionen rechts; Hauptspalte mit den Registern Mängel,
 * Beladung und Taktisches Zeichen; Seitenspalte mit Stammdaten und der
 * Aktivität aus dem Audit-Log (App\Support\Activity).
 *
 * Erwartete Variablen (von Settings\FahrzeugeController::show()):
 *   @var array<string,mixed>                              $vehicle          Zeile aus intra_fahrzeuge
 *   @var list<array<string,mixed>>                        $defects          alle Mängel, offene zuerst
 *   @var int                                              $openDefects
 *   @var list<array{category:array<string,mixed>, tiles:list<array<string,mixed>>}> $loadout  Beladung des Typs
 *   @var list<array{label:string, actor:?string, at:string}> $activityEntries
 *   @var array<int,array{id:string,label:string,partial:string}> $tabs
 */

use App\Auth\Gate;
use App\Helpers\DateTimeHelper;

$basePath  = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$canManage = Gate::allows('vehicle.manage');
$canReport = Gate::allows('vehicle.createDefect');
$vehicleId = (int) $vehicle['id'];
$plate     = trim((string) ($vehicle['kennzeichen'] ?? ''));
$heading   = $plate !== '' ? $plate : (string) $vehicle['name'];

$rdTypes = [
    1 => ['warn', 'RD - Mit NA'],
    2 => ['ok', 'RD - Ohne NA'],
    3 => ['danger', 'Feuerwehr'],
];
[$rdChip, $rdLabel] = $rdTypes[(int) ($vehicle['rd_type'] ?? 0)] ?? ['secondary', 'Andere'];
$isActive = (int) ($vehicle['active'] ?? 0) !== 0;

// Taktisches Zeichen: die Felder, die der Generator kennt (wie _preview.php).
$tz = [];
foreach (['grundzeichen', 'organisation', 'fachaufgabe', 'einheit', 'symbol', 'typ', 'text'] as $tzField) {
    if (!empty($vehicle[$tzField])) {
        $tz[$tzField] = (string) $vehicle[$tzField];
    }
}
if (!empty($vehicle['tz_name'])) {
    $tz['name'] = (string) $vehicle['tz_name'];
}

$defectsUrl = $basePath . 'settings/vehicles/defects/index?vehicle=' . $vehicleId;

$SITE_TITLE = 'Fahrzeug: ' . $heading;
$layout = 'admin';
$bodyId = 'fahrzeuge';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/vehicles/vehicles/index">Fahrzeuge</a></span> <span class="ignis-breadcrumb__item is-active"><?= htmlspecialchars($heading) ?></span></nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Fuhrpark</p>
                    <h1 class="ignis-detail__title">
                        <?= htmlspecialchars((string) $vehicle['name']) ?>
                        <?php if ($isActive): ?>
                            <span class="ignis-chip ignis-chip--dot ignis-chip--ok">Einsatzbereit</span>
                        <?php else: ?>
                            <span class="ignis-chip ignis-chip--dot ignis-chip--danger">Außer Dienst</span>
                        <?php endif; ?>
                        <span class="ignis-chip ignis-chip--<?= $rdChip ?>"><?= $rdLabel ?></span>
                        <?php if (!empty($vehicle['current_status'])): ?>
                            <span class="ignis-chip ignis-chip--secondary" title="Status aus <?= htmlspecialchars((string) ($vehicle['status_source'] ?? 'EMD'), ENT_QUOTES) ?>">Status <?= htmlspecialchars((string) $vehicle['current_status']) ?></span>
                        <?php endif; ?>
                    </h1>
                    <p class="twplus-page-header__description">
                        <?php if ($plate !== ''): ?><span class="ignis-mono"><?= htmlspecialchars($plate) ?></span> · <?php endif; ?><?= htmlspecialchars((string) $vehicle['veh_type']) ?> · Kennung <span class="ignis-mono"><?= htmlspecialchars((string) $vehicle['identifier']) ?></span>
                    </p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <?php if ($canManage): ?>
                        <a href="<?= htmlspecialchars($basePath . 'settings/vehicles/vehicles/' . $vehicleId . '/edit') ?>" class="ignis-btn ignis-btn--secondary" data-ignis-drawer><i class="fa-solid fa-pen" aria-hidden="true"></i> Bearbeiten</a>
                    <?php endif; ?>
                    <?php if ($canReport): ?>
                        <a href="<?= htmlspecialchars($basePath . 'settings/vehicles/defects/create?vehicle=' . $vehicleId) ?>" class="ignis-btn ignis-btn--primary" data-ignis-drawer><i class="fa-solid fa-wrench" aria-hidden="true"></i> Mangel melden</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ignis-detail">
                <div class="ignis-detail__main">
                    <div class="ignis-tabs" data-ignis-tabs data-default="<?= htmlspecialchars($tabs[0]['id']) ?>">
                        <div class="ignis-tabs__headers" role="tablist">
                            <?php foreach ($tabs as $tab): ?>
                                <button type="button" class="ignis-tabs__header" data-tab="<?= htmlspecialchars($tab['id']) ?>"><?= htmlspecialchars($tab['label']) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="ignis-tabs__panels">
                            <?php foreach ($tabs as $tab): ?>
                                <section class="ignis-tabs__panel" data-panel="<?= htmlspecialchars($tab['id']) ?>">
                                    <?php require __DIR__ . '/' . $tab['partial'] . '.php'; ?>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <aside class="ignis-detail__aside">
                    <div class="ignis-detail__block">
                        <h4>Stammdaten</h4>
                        <dl class="ignis-detail__dl">
                            <dt>Kennzeichen</dt>
                            <dd><?= $plate !== '' ? '<span class="ignis-mono">' . htmlspecialchars($plate) . '</span>' : '—' ?></dd>
                            <dt>Funkrufname</dt>
                            <dd><?= htmlspecialchars((string) $vehicle['name']) ?></dd>
                            <dt>Kennung</dt>
                            <dd><span class="ignis-mono"><?= htmlspecialchars((string) $vehicle['identifier']) ?></span></dd>
                            <dt>Typ</dt>
                            <dd><?= htmlspecialchars((string) $vehicle['veh_type']) ?> · <?= $rdLabel ?></dd>
                            <dt>Priorität</dt>
                            <dd><?= (int) ($vehicle['priority'] ?? 0) ?></dd>
                            <?php if (!empty($vehicle['allowed_jobs'])): ?>
                                <dt>Jobs</dt>
                                <dd><?= htmlspecialchars((string) $vehicle['allowed_jobs']) ?></dd>
                            <?php endif; ?>
                            <dt>EMD-Status</dt>
                            <dd>
                                <?php if (!empty($vehicle['current_status'])): ?>
                                    Status <?= htmlspecialchars((string) $vehicle['current_status']) ?>
                                    <span class="ignis-detail__muted">
                                        <?= !empty($vehicle['status_source']) ? 'aus ' . htmlspecialchars((string) $vehicle['status_source']) : '' ?><?= !empty($vehicle['status_updated_at']) ? ', seit ' . htmlspecialchars(DateTimeHelper::formatShortLocal((string) $vehicle['status_updated_at'])) : '' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="ignis-detail__muted">Keine Meldung</span>
                                <?php endif; ?>
                            </dd>
                            <dt>Angelegt</dt>
                            <dd><?= !empty($vehicle['created_at']) ? htmlspecialchars(DateTimeHelper::formatDateLocal((string) $vehicle['created_at'])) : '—' ?></dd>
                        </dl>
                    </div>

                    <div class="ignis-detail__block">
                        <h4>Aktivität</h4>
                        <?php require dirname(__DIR__, 3) . '/partials/activity.php'; ?>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <script type="module" src="<?= BASE_PATH ?>assets/js/pages/vehicle-preview.js"></script>
