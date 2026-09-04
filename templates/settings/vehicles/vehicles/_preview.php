<?php
/**
 * templates/settings/vehicles/vehicles/_preview.php — Vorschau eines
 * Fahrzeugs im Arbeitsbereich der Fahrzeugliste (rechte Spalte, siehe
 * assets/js/ui/workbench.js).
 *
 * Wird von Settings\FahrzeugeController::preview() ohne Layout gerendert
 * und per fetch in `.ignis-preview` gesetzt. Beantwortet die häufigste
 * Frage an der Liste („Ist das Fahrzeug einsatzbereit, was ist offen?")
 * ohne Seitenwechsel. Das taktische Zeichen steht als JSON in
 * data-ignis-tz; assets/js/pages/vehicle-preview.js zeichnet es.
 *
 * Erwartete Variablen:
 *   @var array<string,mixed>       $vehicle      Zeile aus intra_fahrzeuge
 *   @var int                       $openDefects  offene Mängel (nicht resolved)
 *   @var list<array<string,mixed>> $defects      die letzten drei davon
 *   @var array<string,mixed>|null  $loadout      Beladung des Typs: categories, positions, amount, changed_at
 */

use App\Auth\Gate;
use App\Helpers\DateTimeHelper;

$basePath  = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$canManage = Gate::allows('vehicle.manage');
$canReport = Gate::allows('vehicle.createDefect');
$vehicleId = (int) $vehicle['id'];

$rdTypes = [
    1 => ['warn', 'RD - Mit NA'],
    2 => ['ok', 'RD - Ohne NA'],
    3 => ['danger', 'Feuerwehr'],
];
[$rdChip, $rdLabel] = $rdTypes[(int) ($vehicle['rd_type'] ?? 0)] ?? ['secondary', 'Andere'];
$isActive = (int) ($vehicle['active'] ?? 0) !== 0;

$defectStatus = [
    'open'        => ['Offen', 'danger'],
    'in_progress' => ['In Bearbeitung', 'warn'],
    'deferred'    => ['Aufgeschoben', 'info'],
];
$defectCategories = [
    'mechanik' => 'Mechanik', 'elektrik' => 'Elektrik', 'karosserie' => 'Karosserie',
    'ausruestung' => 'Ausrüstung', 'medizintechnik' => 'Medizintechnik', 'sonstiges' => 'Sonstiges',
];
$blocking = array_filter($defects, static fn (array $d): bool => (int) ($d['vehicle_operable'] ?? 1) === 0);

// Taktisches Zeichen: die Felder, die der Generator kennt.
$tz = [];
foreach (['grundzeichen', 'organisation', 'fachaufgabe', 'einheit', 'symbol', 'typ', 'text'] as $tzField) {
    if (!empty($vehicle[$tzField])) {
        $tz[$tzField] = (string) $vehicle[$tzField];
    }
}
if (!empty($vehicle['tz_name'])) {
    $tz['name'] = (string) $vehicle['tz_name'];
}
$tzParts = array_values(array_filter([$vehicle['grundzeichen'] ?? '', $vehicle['organisation'] ?? '', $vehicle['fachaufgabe'] ?? '', $vehicle['einheit'] ?? '']));

$defectsUrl = $basePath . 'settings/vehicles/defects/index?vehicle=' . $vehicleId;
?>
<h3 class="ignis-preview__title">
    <?= htmlspecialchars((string) $vehicle['name']) ?>
    <small><?= htmlspecialchars((string) $vehicle['veh_type']) ?></small>
</h3>

<div class="ignis-preview__chips">
    <?php if ($isActive): ?>
        <span class="ignis-chip ignis-chip--dot ignis-chip--ok">Einsatzbereit</span>
    <?php else: ?>
        <span class="ignis-chip ignis-chip--dot ignis-chip--danger">Außer Dienst</span>
    <?php endif; ?>
    <span class="ignis-chip ignis-chip--<?= $rdChip ?>"><?= $rdLabel ?></span>
    <?php if (!empty($vehicle['current_status'])): ?>
        <span class="ignis-chip ignis-chip--secondary" title="Status aus <?= htmlspecialchars((string) ($vehicle['status_source'] ?? 'EMD'), ENT_QUOTES) ?>">Status <?= htmlspecialchars((string) $vehicle['current_status']) ?></span>
    <?php endif; ?>
</div>

<?php if ($blocking !== []): ?>
    <?php $first = reset($blocking); ?>
    <div class="ignis-preview__alert" role="alert">
        <i class="fa-solid fa-wrench" aria-hidden="true"></i>
        <span>Nicht einsatzfähig: <?= htmlspecialchars((string) $first['title']) ?></span>
    </div>
<?php endif; ?>

<dl class="ignis-preview__dl">
    <dt>Kennzeichen</dt>
    <dd><?= ($vehicle['kennzeichen'] ?? '') !== '' ? '<span class="ignis-mono">' . htmlspecialchars((string) $vehicle['kennzeichen']) . '</span>' : '—' ?></dd>
    <dt>Kennung</dt>
    <dd><span class="ignis-mono"><?= htmlspecialchars((string) $vehicle['identifier']) ?></span></dd>
    <dt>Priorität</dt>
    <dd><?= (int) ($vehicle['priority'] ?? 0) ?></dd>
    <?php if (!empty($vehicle['allowed_jobs'])): ?>
        <dt>Jobs</dt>
        <dd><?= htmlspecialchars((string) $vehicle['allowed_jobs']) ?></dd>
    <?php endif; ?>
    <dt>Beladung</dt>
    <dd>
        <?php if ($loadout !== null && (int) $loadout['categories'] > 0): ?>
            <a href="<?= htmlspecialchars($basePath . 'settings/vehicles/vehload/index') ?>"><?= (int) $loadout['positions'] ?> Positionen</a>
            <span class="ignis-preview__muted">in <?= (int) $loadout['categories'] ?> Kategorien<?= (int) $loadout['amount'] > 0 ? ', ' . (int) $loadout['amount'] . ' Stück' : '' ?><?= !empty($loadout['changed_at']) ? ', zuletzt ' . htmlspecialchars(DateTimeHelper::formatDateLocal((string) $loadout['changed_at'])) : '' ?></span>
        <?php else: ?>
            <span class="ignis-preview__muted">Keine Beladeliste für <?= htmlspecialchars((string) $vehicle['veh_type']) ?></span>
        <?php endif; ?>
    </dd>
</dl>

<div class="ignis-preview__section">
    <h4>Taktisches Zeichen</h4>
    <?php if ($tz === []): ?>
        <p class="ignis-preview__muted">Kein Zeichen hinterlegt.</p>
    <?php else: ?>
        <div class="ignis-preview__tz" data-ignis-tz="<?= htmlspecialchars((string) json_encode($tz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>">
            <span>
                <?php if (!empty($vehicle['tz_name'])): ?><b><?= htmlspecialchars((string) $vehicle['tz_name']) ?></b><br><?php endif; ?>
                <span class="ignis-preview__muted"><?= htmlspecialchars(implode(' · ', $tzParts)) ?><?= !empty($vehicle['text']) ? ' · „' . htmlspecialchars((string) $vehicle['text']) . '"' : '' ?></span>
            </span>
        </div>
    <?php endif; ?>
</div>

<div class="ignis-preview__section">
    <h4>
        <span><?= $openDefects === 0 ? 'Keine offenen Mängel' : ($openDefects === 1 ? '1 offener Mangel' : $openDefects . ' offene Mängel') ?></span>
        <?php if ($openDefects > count($defects)): ?><a href="<?= htmlspecialchars($defectsUrl) ?>">alle</a><?php endif; ?>
    </h4>
    <?php if ($defects === []): ?>
        <p class="ignis-preview__muted">Nichts gemeldet.</p>
    <?php else: ?>
        <ul class="ignis-preview__list">
            <?php foreach ($defects as $defect): ?>
                <?php [$statusLabel, $statusChip] = $defectStatus[(string) $defect['status']] ?? [(string) $defect['status'], 'secondary']; ?>
                <li>
                    <a href="<?= htmlspecialchars($defectsUrl) ?>#defect-<?= (int) $defect['id'] ?>"><?= htmlspecialchars((string) $defect['title']) ?></a>
                    <span class="ignis-preview__muted"><?= htmlspecialchars($defectCategories[(string) $defect['category']] ?? (string) $defect['category']) ?> · <?= htmlspecialchars(DateTimeHelper::formatDateLocal((string) $defect['created_at'])) ?></span>
                    <span class="ignis-chip ignis-chip--sm ignis-chip--<?= $statusChip ?>"><?= htmlspecialchars($statusLabel) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="ignis-preview__actions">
    <a href="<?= htmlspecialchars($defectsUrl) ?>" class="ignis-btn ignis-btn--sm ignis-btn--primary"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> Öffnen</a>
    <?php if ($canReport): ?>
        <a href="<?= htmlspecialchars($basePath . 'settings/vehicles/defects/create?vehicle=' . $vehicleId) ?>" class="ignis-btn ignis-btn--sm ignis-btn--secondary" data-ignis-drawer><i class="fa-solid fa-wrench" aria-hidden="true"></i> Mangel melden</a>
    <?php endif; ?>
    <?php if ($canManage): ?>
        <a href="<?= htmlspecialchars($basePath . 'settings/vehicles/vehicles/' . $vehicleId . '/edit') ?>" class="ignis-btn ignis-btn--sm ignis-btn--ghost" data-ignis-drawer><i class="fa-solid fa-pen" aria-hidden="true"></i> Bearbeiten</a>
    <?php endif; ?>
</div>
