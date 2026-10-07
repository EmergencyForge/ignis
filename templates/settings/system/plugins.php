<?php

/**
 * View: Plugin-Verwaltung
 *
 * @var list<array{id: string, manifest: \EmergencyForge\Plugins\PluginManifest, installed: bool, bundled: bool, enabled: bool, active: bool, skipReason: ?string, requiredBy: list<string>}> $rows
 * @var string                              $message
 * @var string                              $messageType
 * @var list<array<string,mixed>>           $catalogRows
 * @var bool                                $catalogStale
 * @var string|null                         $catalogFetchedAt
 * @var string|null                         $catalogError
 * @var int                                 $maxUploadBytes
 */

use App\Security\CsrfProtection;

$csrfToken = CsrfProtection::getToken();

$layout = 'admin';
$bodyId = 'settings';
$SITE_TITLE = 'Plugins';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/system/index">System</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Plugins</span></nav>

            <div class="mb-6">
                <div class="twplus-page-header mb-4">
                    <div class="twplus-page-header__copy">
                        <p class="twplus-page-header__eyebrow">Erweiterungen</p>
                        <h1>Plugins</h1>
                        <p class="twplus-page-header__description">Installierte Module, Abhängigkeiten und Aktivierungsstatus verwalten.</p>
                    </div>
                    <a href="https://hub.emergencyforge.de/plugins" target="_blank" rel="nofollow"
                        class="ignis-btn ignis-btn--secondary ignis-btn--sm">
                        <i class="fa-solid fa-compass mr-1"></i>Plugins erkunden
                        <i class="fa-solid fa-arrow-up-right-from-square ml-1" style="font-size:0.65rem;opacity:0.6;"></i>
                    </a>
                </div>

                <p class="text-tertiary-text mb-4" style="max-width: 720px;">
                    Module, die als Plugin ausgeliefert werden, lassen sich hier einzeln
                    aktivieren oder deaktivieren. Beim Deaktivieren verschwinden Navigation,
                    Routen und Berechtigungen des Moduls, <strong>alle Daten und Tabellen
                    bleiben erhalten</strong> und stehen nach dem Reaktivieren unverändert
                    wieder zur Verfügung.
                </p>
                <div class="ignis-alert ignis-alert--warn mb-4">
                    <i class="fa-solid fa-shield-halved ignis-alert__icon"></i>
                    <div class="ignis-alert__body">
                        <div class="ignis-alert__title">Community-Plugins: Nutzung auf eigenes Risiko</div>
                        Nicht offiziell mitgelieferte Plugins bleiben nach dem Hochladen zunächst
                        vollständig inaktiv: Es wird kein Code ausgeführt und keine Migration
                        angewendet, bis die Installation auf der Bestätigungsseite ausdrücklich
                        gestartet wird.
                        Für Community-Plugins übernimmt EmergencyForge keine Gewähr, weder für
                        Funktion und Sicherheit noch für mögliche Datenverluste. Support leistet
                        der jeweilige Herausgeber.
                    </div>
                </div>

                <?php if ($message !== ''): ?>
                    <div class="ignis-alert ignis-alert--<?= htmlspecialchars($messageType) ?> mb-4" role="alert">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <section class="mb-6" aria-labelledby="plugin-upload-heading">
                    <p class="twplus-page-header__eyebrow">Eigenes Paket</p>
                    <h2 id="plugin-upload-heading" class="mb-2">Plugin hochladen</h2>
                    <form method="post" enctype="multipart/form-data" id="plugin-upload-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="plugin_action" value="upload">
                        <div class="ignis-file ignis-file--dropzone mb-2" id="plugin-upload-dropzone" data-ignis-file data-max-bytes="<?= (int) $maxUploadBytes ?>">
                            <input type="file" id="plugin-upload" name="plugin_zip" accept=".zip,application/zip,application/x-zip-compressed" class="ignis-file__input" required>
                            <label for="plugin-upload" class="ignis-file__zone">
                                <span class="ignis-file__icon" aria-hidden="true"><i class="fa-solid fa-file-zipper"></i></span>
                                <span class="ignis-file__title">Plugin-ZIP hierher ziehen oder <span class="ignis-file__link">auswählen</span></span>
                                <span class="ignis-file__hint">ZIP mit manifest.php, max. <?= htmlspecialchars(number_format($maxUploadBytes / 1048576, 0, ',', '.')) ?> MB</span>
                            </label>
                            <div class="ignis-file__selected" hidden></div>
                            <p class="ignis-file__error" role="alert" hidden></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--secondary" id="plugin-upload-submit">
                                <i class="fa-solid fa-magnifying-glass mr-1"></i>Prüfen
                            </button>
                            <span class="text-tertiary-text" style="font-size: 0.78rem;">
                                Das Archiv wird zuerst nur geprüft. Installiert wird erst nach deiner Bestätigung auf der nächsten Seite.
                            </span>
                        </div>
                    </form>
                </section>

                <?php if ($rows === []): ?>
                    <?php
                    $empty = [
                        'variant' => 'sm',
                        'icon'    => 'fa-puzzle-piece',
                        'heading' => 2,
                        'title'   => 'Noch keine Plugins installiert',
                        'text'    => 'Plugins aus dem Katalog ergänzen ignis um weitere Module.',
                        'actions' => [['label' => 'Katalog öffnen', 'href' => '#plugin-catalog-heading', 'style' => 'secondary']],
                    ];
                    require dirname(__DIR__, 2) . '/partials/empty.php';
                    ?>
                <?php endif; ?>

                <?php if ($rows !== []): ?><div class="twplus-stacked-list"><?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php $m = $row['manifest']; ?>
                    <div class="twplus-stacked-list__item">
                        <span class="twplus-stacked-list__icon"><i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i></span>
                        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
                            <div class="flex-1" style="min-width: min(260px, 100%);">
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong><?= htmlspecialchars($m->name) ?></strong>
                                    <span class="ignis-chip"><?= htmlspecialchars($m->version) ?></span>
                                    <?php if (!$row['installed']): ?>
                                        <span class="ignis-chip ignis-chip--danger">Nicht installiert</span>
                                    <?php elseif ($row['active']): ?>
                                        <span class="ignis-chip ignis-chip--ok">Aktiv</span>
                                    <?php elseif ($row['enabled'] && $row['skipReason'] !== null): ?>
                                        <span class="ignis-chip ignis-chip--warn" data-ignis-tooltip="<?= htmlspecialchars($row['skipReason']) ?>">Übersprungen</span>
                                    <?php else: ?>
                                        <span class="ignis-chip">Inaktiv</span>
                                    <?php endif; ?>
                                    <?php if ($row['bundled']): ?>
                                        <span class="ignis-chip ignis-chip--info" data-ignis-tooltip="Offiziell mit ıgnıs ausgeliefert.">Offiziell</span>
                                    <?php endif; ?>
                                    <?php if (!$m->removable): ?>
                                        <span class="ignis-chip ignis-chip--info" data-ignis-tooltip="Dieses Plugin ist fester Bestandteil und kann nicht deaktiviert werden.">Erforderlich</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-tertiary-text mt-1" style="font-size: 0.82rem;">
                                    von <?= htmlspecialchars($m->vendor) ?>
                                    <?php if ($m->depends !== []): ?>
                                        &middot; benötigt: <?= htmlspecialchars(implode(', ', $m->depends)) ?>
                                    <?php endif; ?>
                                    <?php if ($row['requiredBy'] !== []): ?>
                                        &middot; benötigt von: <?= htmlspecialchars(implode(', ', $row['requiredBy'])) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($row['enabled'] && $row['skipReason'] !== null): ?>
                                    <div class="text-warning mt-1" style="font-size: 0.82rem;">
                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i><?= htmlspecialchars($row['skipReason']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!$row['bundled']): ?>
                                    <div class="text-tertiary-text mt-1" style="font-size: 0.78rem;">
                                        <i class="fa-solid fa-scale-balanced mr-1"></i>Community-Plugin, Nutzung auf eigenes Risiko.
                                        EmergencyForge übernimmt keine Gewähr für Funktion, Sicherheit oder mögliche Datenverluste.
                                        Support leistet ausschließlich der jeweilige Herausgeber.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="shrink-0 flex flex-wrap gap-2">
                                <?php if (!$row['installed']): ?>
                                    <a href="<?= BASE_PATH ?>settings/system/plugins?confirm=install&amp;plugin=<?= rawurlencode($row['id']) ?>"
                                        class="ignis-btn ignis-btn--sm ignis-btn--secondary">
                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i>Installieren
                                    </a>
                                    <form method="post" class="inline"
                                        onsubmit="event.preventDefault(); showConfirm('Nur die Plugin-Dateien werden entfernt. Das Plugin war nie installiert, es gibt also keine Tabellen oder Daten.', {title: 'Plugin-Dateien entfernen', confirmText: 'Dateien entfernen', cancelText: 'Abbrechen', danger: true}).then(result => { if (result) this.submit(); });">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="plugin_action" value="remove">
                                        <input type="hidden" name="plugin_id" value="<?= htmlspecialchars($row['id']) ?>">
                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--secondary">Verwerfen</button>
                                    </form>
                                <?php elseif ($row['enabled']): ?>
                                    <?php $blocked = !$m->removable || $row['requiredBy'] !== []; ?>
                                    <form method="post" class="inline"
                                        <?php if (!$blocked): ?>onsubmit="event.preventDefault(); showConfirm('Plugin <?= htmlspecialchars($m->name, ENT_QUOTES) ?> wirklich deaktivieren? Daten bleiben erhalten.', {title: 'Plugin deaktivieren', confirmText: 'Deaktivieren', cancelText: 'Abbrechen', danger: true}).then(result => { if (result) this.submit(); });"<?php endif; ?>>
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="plugin_id" value="<?= htmlspecialchars($row['id']) ?>">
                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--secondary" <?= $blocked ? 'disabled' : '' ?>
                                            <?php if (!$m->removable): ?>data-ignis-tooltip="Fester Bestandteil, nicht deaktivierbar"<?php elseif ($row['requiredBy'] !== []): ?>data-ignis-tooltip="Wird von anderen aktiven Plugins benötigt"<?php endif; ?>>
                                            Deaktivieren
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="plugin_id" value="<?= htmlspecialchars($row['id']) ?>">
                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--secondary">
                                            Aktivieren
                                        </button>
                                    </form>
                                    <?php if (!$row['bundled']): ?>
                                        <form method="post" class="inline"
                                            onsubmit="event.preventDefault(); showConfirm('Nur die Plugin-Dateien werden entfernt. Tabellen und vorhandene Daten bleiben erhalten.', {title: 'Plugin-Dateien entfernen', confirmText: 'Dateien entfernen', cancelText: 'Abbrechen', danger: true}).then(result => { if (result) this.submit(); });">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="plugin_action" value="remove">
                                            <input type="hidden" name="plugin_id" value="<?= htmlspecialchars($row['id']) ?>">
                                            <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--secondary">Entfernen</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($rows !== []): ?></div><?php endif; ?>

                <section class="mt-8" aria-labelledby="plugin-catalog-heading">
                    <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
                        <div>
                            <p class="twplus-page-header__eyebrow">Hub-Katalog</p>
                            <h2 id="plugin-catalog-heading" class="m-0">Aus dem Katalog</h2>
                        </div>
                        <?php if ($catalogFetchedAt !== null): ?>
                            <span class="text-tertiary-text" style="font-size:0.75rem;">
                                Stand <?= htmlspecialchars((new DateTimeImmutable($catalogFetchedAt))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i')) ?>
                                <?= $catalogStale ? ' · Cache' : '' ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($catalogError !== null && $catalogRows !== []): ?>
                        <div class="ignis-alert ignis-alert--warn mb-4" role="status">
                            <?= htmlspecialchars($catalogError) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($catalogRows === []): ?>
                        <?php
                        $empty = $catalogError !== null
                            ? [
                                'variant' => 'sm',
                                'tone'    => 'danger',
                                'icon'    => 'fa-plug-circle-xmark',
                                'title'   => 'Katalog nicht erreichbar',
                                'text'    => 'Der Plugin-Katalog hat nicht geantwortet. Installierte Plugins laufen weiter.',
                                'actions' => [['label' => 'Erneut versuchen', 'href' => BASE_PATH . 'settings/system/plugins', 'style' => 'secondary', 'icon' => 'fa-rotate-right']],
                                'code'    => $catalogError,
                            ]
                            : [
                                'variant' => 'sm',
                                'icon'    => 'fa-cloud-arrow-down',
                                'title'   => 'Der Katalog ist leer',
                                'text'    => 'Sobald im Hub Plugins veröffentlicht sind, erscheinen sie hier.',
                            ];
                        require dirname(__DIR__, 2) . '/partials/empty.php';
                        ?>
                    <?php else: ?>
                        <div class="twplus-resource-grid">
                            <?php foreach ($catalogRows as $plugin): ?>
                                <?php
                                $trustLabels = ['official' => 'Offiziell', 'verified' => 'Geprüft', 'tested' => 'Geprüft', 'untested' => 'Ungetestet'];
                                $trust = (string) ($plugin['trust'] ?? 'untested');
                                $installedVersion = $plugin['installed_version'] ?? null;
                                ?>
                                <article class="ignis-card ignis-card--bordered twplus-resource-card">
                                    <div class="ignis-card__header">
                                        <div>
                                            <h3 class="ignis-card__title"><?= htmlspecialchars((string) $plugin['name']) ?></h3>
                                            <span class="ignis-card__subtitle">Version <?= htmlspecialchars((string) $plugin['version']) ?></span>
                                        </div>
                                        <span class="ignis-chip <?= $trust === 'untested' ? 'ignis-chip--warn' : 'ignis-chip--info' ?>">
                                            <?= htmlspecialchars($trustLabels[$trust] ?? 'Ungetestet') ?>
                                        </span>
                                    </div>
                                    <div class="ignis-card__body">
                                        <p class="ignis-card__text"><?= htmlspecialchars((string) ($plugin['description'] ?: 'Keine Beschreibung hinterlegt.')) ?></p>
                                        <div class="text-tertiary-text mb-1" style="font-size:0.78rem;">
                                            <?php if (($plugin['publisher'] ?? '') !== ''): ?>von <?= htmlspecialchars((string) $plugin['publisher']) ?> &middot; <?php endif; ?>
                                            <?= $plugin['third_party'] ? 'Drittanbieter' : 'EmergencyForge' ?>
                                        </div>
                                        <?php if ($plugin['bundled']): ?>
                                            <div class="text-tertiary-text" style="font-size:0.72rem;">
                                                Im Lieferumfang von ignis enthalten, Updates kommen mit dem ignis-Update.
                                            </div>
                                        <?php else: ?>
                                            <div class="text-tertiary-text" style="font-size:0.72rem;font-family:var(--mono);">
                                                SHA256 <?= $plugin['sha256'] !== '' ? htmlspecialchars(substr((string) $plugin['sha256'], 0, 12)) . '…' : 'fehlt' ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ignis-card__footer">
                                        <?php if ($plugin['bundled']): ?>
                                            <span class="ignis-chip ignis-chip--ok">Mitgeliefert<?= $installedVersion !== null ? ' ' . htmlspecialchars((string) $installedVersion) : '' ?></span>
                                        <?php elseif ($installedVersion === null): ?>
                                            <?php if ($plugin['installable']): ?>
                                                <a href="<?= BASE_PATH ?>settings/system/plugins?confirm=catalog&amp;plugin=<?= rawurlencode((string) $plugin['slug']) ?>"
                                                    class="ignis-btn ignis-btn--sm ignis-btn--secondary">Installieren</a>
                                            <?php else: ?>
                                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary" disabled data-ignis-tooltip="Kein SHA256-Digest oder Download hinterlegt">Installieren</button>
                                            <?php endif; ?>
                                        <?php elseif (!($plugin['installed'] ?? false)): ?>
                                            <span class="ignis-chip ignis-chip--warn">Heruntergeladen</span>
                                            <a href="<?= BASE_PATH ?>settings/system/plugins?confirm=install&amp;plugin=<?= rawurlencode((string) $plugin['slug']) ?>"
                                                class="ignis-btn ignis-btn--sm ignis-btn--secondary">Installation bestätigen</a>
                                        <?php elseif ($plugin['update_available']): ?>
                                            <span class="ignis-chip ignis-chip--warn">Update von <?= htmlspecialchars((string) $installedVersion) ?></span>
                                            <?php if ($plugin['installable']): ?>
                                                <a href="<?= BASE_PATH ?>settings/system/plugins?confirm=update&amp;plugin=<?= rawurlencode((string) $plugin['slug']) ?>"
                                                    class="ignis-btn ignis-btn--sm ignis-btn--secondary">Update</a>
                                            <?php else: ?>
                                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary" disabled>Update</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="ignis-chip ignis-chip--ok">Installiert <?= htmlspecialchars((string) $installedVersion) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

            </div>
        </div>
    </div>

    <script>
        // Ein gültiges ZIP direkt prüfen lassen, ohne Umweg über den Knopf.
        // file.js validiert Typ und Größe im selben change-Event, der
        // Timeout schiebt das Absenden dahinter (wie beim System-Logo).
        // Geprüft wird nur, installiert wird erst nach der Bestätigung.
        (function () {
            var form  = document.getElementById('plugin-upload-form');
            var input = document.getElementById('plugin-upload');
            if (!form || !input) return;
            input.addEventListener('change', function () {
                setTimeout(function () {
                    if (!input.files || !input.files[0] || input.getAttribute('aria-invalid') === 'true') return;
                    var button = document.getElementById('plugin-upload-submit');
                    if (button) {
                        button.disabled = true;
                        button.textContent = 'Wird geprüft …';
                    }
                    form.submit();
                }, 0);
            });
        })();
    </script>
