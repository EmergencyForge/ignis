<?php
/**
 * Register „Taktisches Zeichen" der Fahrzeugseite (show.php): das Zeichen
 * als SVG (assets/js/pages/vehicle-preview.js zeichnet es aus
 * data-ignis-tz mit taktische-zeichen-core, ohne Netz bleibt der Text)
 * und die JSON-Definition zum Kopieren, etwa für eine TZ-Vorlage.
 *
 *   @var array<string,string>  $tz        Felder des Zeichens, leer ohne Zeichen
 *   @var array<string,mixed>   $vehicle
 *   @var bool                  $canManage
 *   @var int                   $vehicleId
 *   @var string                $basePath
 */

$tzParts = array_values(array_filter([$tz['grundzeichen'] ?? '', $tz['organisation'] ?? '', $tz['fachaufgabe'] ?? '', $tz['einheit'] ?? '', $tz['symbol'] ?? '', $tz['typ'] ?? '']));
$tzJson  = (string) json_encode($tz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
?>
<?php if ($tz === []): ?>
    <div class="ignis-table-empty">
        <p class="mb-2">Kein taktisches Zeichen hinterlegt.</p>
        <?php if ($canManage): ?>
            <a href="<?= htmlspecialchars($basePath . 'settings/vehicles/vehicles/' . $vehicleId . '/edit') ?>" class="ignis-btn ignis-btn--sm ignis-btn--secondary" data-ignis-drawer><i class="fa-solid fa-pen" aria-hidden="true"></i> Zeichen anlegen</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="ignis-detail__tz" data-ignis-tz="<?= htmlspecialchars((string) json_encode($tz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>" data-ignis-tz-class="ignis-detail__tz-svg">
        <div>
            <?php if (!empty($tz['name'])): ?><b><?= htmlspecialchars($tz['name']) ?></b><br><?php endif; ?>
            <span class="ignis-detail__muted"><?= htmlspecialchars(implode(' · ', $tzParts)) ?><?= !empty($tz['text']) ? ' · „' . htmlspecialchars($tz['text']) . '"' : '' ?></span>
        </div>
    </div>
    <div class="ignis-detail__json">
        <div class="ignis-detail__json-head">
            <span>JSON-Definition</span>
            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost" data-ignis-copy="#vehicle-tz-json"><i class="fa-solid fa-copy" aria-hidden="true"></i> Kopieren</button>
        </div>
        <pre id="vehicle-tz-json" class="ignis-mono"><?= htmlspecialchars($tzJson) ?></pre>
    </div>
<?php endif; ?>
