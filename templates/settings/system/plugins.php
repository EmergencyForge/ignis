<?php

/**
 * View: Plugin-Verwaltung
 *
 * @var \PDO                                $pdo
 * @var list<array{id: string, manifest: \App\Plugins\PluginManifest, enabled: bool, active: bool, skipReason: ?string, requiredBy: list<string>}> $rows
 * @var string                              $message
 * @var string                              $messageType
 * @var list<array<string,mixed>>           $catalogRows
 * @var bool                                $catalogStale
 * @var string|null                         $catalogFetchedAt
 * @var string|null                         $catalogError
 */

use App\Security\CsrfProtection;

$csrfToken = CsrfProtection::getToken();
?>
<!DOCTYPE html>
<html lang="de" data-theme="dark">

<head>
    <?php
    $SITE_TITLE = 'Plugins';
    include __DIR__ . '/../../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="mb-6">
                <div class="twplus-page-header mb-4">
                    <div class="twplus-page-header__copy">
                        <p class="twplus-page-header__eyebrow">Erweiterungen</p>
                        <h1>Plugins</h1>
                        <p class="twplus-page-header__description">Installierte Module, Abhängigkeiten und Aktivierungsstatus verwalten.</p>
                    </div>
                    <a href="https://hub.emergencyforge.de/plugins" target="_blank" rel="nofollow"
                        class="ignis-btn ignis-btn--soft-primary ignis-btn--sm">
                        <i class="fa-solid fa-compass mr-1"></i>Plugins erkunden
                        <i class="fa-solid fa-arrow-up-right-from-square ml-1" style="font-size:0.65rem;opacity:0.6;"></i>
                    </a>
                </div>

                <p class="text-gray-400 mb-4" style="max-width: 720px;">
                    Module, die als Plugin ausgeliefert werden, lassen sich hier einzeln
                    aktivieren oder deaktivieren. Beim Deaktivieren verschwinden Navigation,
                    Routen und Berechtigungen des Moduls — <strong>alle Daten und Tabellen
                    bleiben erhalten</strong> und stehen nach dem Reaktivieren unverändert
                    wieder zur Verfügung.
                </p>
                <div class="ignis-alert ignis-alert--warning mb-4">
                    <i class="fa-solid fa-shield-halved ignis-alert__icon"></i>
                    <div class="ignis-alert__body">
                        <div class="ignis-alert__title">Community-Plugins — Nutzung auf eigenes Risiko</div>
                        Nicht offiziell mitgelieferte Plugins bleiben nach dem Hochladen zunächst
                        vollständig inaktiv: Es wird kein Code ausgeführt und keine Migration
                        angewendet, bis die Installation hier ausdrücklich gestartet wird.
                        Für Community-Plugins übernimmt EmergencyForge keine Gewähr — weder für
                        Funktion und Sicherheit noch für mögliche Datenverluste. Support leistet
                        der jeweilige Herausgeber.
                    </div>
                </div>

                <?php if ($message !== ''): ?>
                    <div class="ignis-alert ignis-alert--<?= htmlspecialchars($messageType) ?> mb-4" role="alert">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($rows === []): ?>
                    <div class="twplus-empty">
                        <i class="fa-solid fa-puzzle-piece twplus-empty__icon" aria-hidden="true"></i>
                        <h2 class="twplus-empty__title">Keine Plugins installiert</h2>
                        <p class="twplus-empty__description">Installierte Erweiterungen und deren Status erscheinen hier.</p>
                    </div>
                <?php endif; ?>

                <?php if ($rows !== []): ?><div class="twplus-stacked-list"><?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php $m = $row['manifest']; ?>
                    <div class="twplus-stacked-list__item">
                        <span class="twplus-stacked-list__icon"><i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i></span>
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="flex-1" style="min-width: 260px;">
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong><?= htmlspecialchars($m->name) ?></strong>
                                    <span class="ignis-chip"><?= htmlspecialchars($m->version) ?></span>
                                    <?php if (!$row['installed']): ?>
                                        <span class="ignis-chip ignis-chip--danger">Nicht installiert</span>
                                    <?php elseif ($row['active']): ?>
                                        <span class="ignis-chip ignis-chip--success">Aktiv</span>
                                    <?php elseif ($row['enabled'] && $row['skipReason'] !== null): ?>
                                        <span class="ignis-chip ignis-chip--warning" title="<?= htmlspecialchars($row['skipReason']) ?>">Übersprungen</span>
                                    <?php else: ?>
                                        <span class="ignis-chip">Inaktiv</span>
                                    <?php endif; ?>
                                    <?php if ($row['bundled']): ?>
                                        <span class="ignis-chip ignis-chip--info" title="Offiziell mit ıgnıs ausgeliefert.">Offiziell</span>
                                    <?php endif; ?>
                                    <?php if (!$m->removable): ?>
                                        <span class="ignis-chip ignis-chip--info" title="Dieses Plugin ist fester Bestandteil und kann nicht deaktiviert werden.">Erforderlich</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-gray-400 mt-1" style="font-size: 0.82rem;">
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
                                    <div class="text-gray-500 mt-1" style="font-size: 0.78rem;">
                                        <i class="fa-solid fa-scale-balanced mr-1"></i>Community-Plugin — Nutzung auf eigenes Risiko.
                                        EmergencyForge übernimmt keine Gewähr für Funktion, Sicherheit oder mögliche Datenverluste.
                                        Support leistet ausschließlich der jeweilige Herausgeber.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="shrink-0 flex flex-wrap gap-2">
                                <?php if (!$row['installed']): ?>
                                    <form method="post" class="inline"
                                        onsubmit="event.preventDefault(); showConfirm('<?= htmlspecialchars($m->name, ENT_QUOTES) ?> ist KEIN offiziell mitgeliefertes Plugin. Die Installation führt fremden Code aus und wendet dessen Datenbank-Migrationen an. EmergencyForge übernimmt keinerlei Gewähr für Funktion, Sicherheit oder mögliche Datenverluste — Nutzung auf eigenes Risiko. Erstelle vorher ein Backup und fahre nur fort, wenn du der Quelle vertraust.', {title: 'Community-Plugin installieren', confirmText: 'Jetzt installieren', cancelText: 'Abbrechen', danger: true}).then(result => { if (result) this.submit(); });">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="plugin_action" value="install">
                                        <input type="hidden" name="plugin_id" value="<?= htmlspecialchars($row['id']) ?>">
                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--soft-warning">
                                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>Installieren
                                        </button>
                                    </form>
                                <?php elseif ($row['enabled']): ?>
                                    <?php $blocked = !$m->removable || $row['requiredBy'] !== []; ?>
                                    <form method="post" class="inline"
                                        <?php if (!$blocked): ?>onsubmit="event.preventDefault(); showConfirm('Plugin <?= htmlspecialchars($m->name, ENT_QUOTES) ?> wirklich deaktivieren? Daten bleiben erhalten.', {title: 'Plugin deaktivieren', confirmText: 'Deaktivieren', cancelText: 'Abbrechen', danger: true}).then(result => { if (result) this.submit(); });"<?php endif; ?>>
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="plugin_id" value="<?= htmlspecialchars($row['id']) ?>">
                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--soft-danger" <?= $blocked ? 'disabled' : '' ?>
                                            <?php if (!$m->removable): ?>title="Fester Bestandteil — nicht deaktivierbar"<?php elseif ($row['requiredBy'] !== []): ?>title="Wird von anderen aktiven Plugins benötigt"<?php endif; ?>>
                                            Deaktivieren
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="plugin_id" value="<?= htmlspecialchars($row['id']) ?>">
                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary">
                                            Aktivieren
                                        </button>
                                    </form>
                                    <?php if (!$row['bundled']): ?>
                                        <form method="post" class="inline"
                                            onsubmit="event.preventDefault(); showConfirm('Nur die Plugin-Dateien werden entfernt. Tabellen und vorhandene Daten bleiben erhalten.', {title: 'Plugin-Dateien entfernen', confirmText: 'Dateien entfernen', cancelText: 'Abbrechen', danger: true}).then(result => { if (result) this.submit(); });">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="plugin_action" value="remove">
                                            <input type="hidden" name="plugin_id" value="<?= htmlspecialchars($row['id']) ?>">
                                            <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--soft-danger">Entfernen</button>
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
                            <span class="text-gray-500" style="font-size:0.75rem;">
                                Stand <?= htmlspecialchars((new DateTimeImmutable($catalogFetchedAt))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i')) ?>
                                <?= $catalogStale ? ' · Cache' : '' ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($catalogError !== null): ?>
                        <div class="ignis-alert ignis-alert--warning mb-4" role="status">
                            <?= htmlspecialchars($catalogError) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($catalogRows === []): ?>
                        <div class="twplus-empty">
                            <i class="fa-solid fa-cloud-arrow-down twplus-empty__icon" aria-hidden="true"></i>
                            <h3 class="twplus-empty__title">Kein Katalog verfügbar</h3>
                            <p class="twplus-empty__description">Die installierten Plugins lassen sich weiterhin normal verwalten.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                            <?php foreach ($catalogRows as $plugin): ?>
                                <?php
                                $trustLabels = ['official' => 'Offiziell', 'verified' => 'Geprüft', 'tested' => 'Geprüft', 'untested' => 'Ungetestet'];
                                $trust = (string) ($plugin['trust'] ?? 'untested');
                                $installedVersion = $plugin['installed_version'] ?? null;
                                ?>
                                <article class="ignis-card ignis-card--bordered">
                                    <div class="ignis-card__header">
                                        <div>
                                            <h3 class="ignis-card__title"><?= htmlspecialchars((string) $plugin['name']) ?></h3>
                                            <span class="ignis-card__subtitle">Version <?= htmlspecialchars((string) $plugin['version']) ?></span>
                                        </div>
                                        <span class="ignis-chip <?= $trust === 'untested' ? 'ignis-chip--warning' : 'ignis-chip--info' ?>">
                                            <?= htmlspecialchars($trustLabels[$trust] ?? 'Ungetestet') ?>
                                        </span>
                                    </div>
                                    <div class="ignis-card__body">
                                        <p class="ignis-card__text"><?= htmlspecialchars((string) ($plugin['description'] ?: 'Keine Beschreibung hinterlegt.')) ?></p>
                                        <div class="text-gray-500" style="font-size:0.72rem;font-family:monospace;">
                                            SHA256 <?= $plugin['sha256'] !== '' ? htmlspecialchars(substr((string) $plugin['sha256'], 0, 12)) . '…' : 'fehlt' ?>
                                        </div>
                                    </div>
                                    <div class="ignis-card__footer">
                                        <?php if ($installedVersion === null): ?>
                                            <form method="post" class="inline"
                                                onsubmit="event.preventDefault(); showConfirm('Das ZIP wird von GitHub geladen, gegen den Katalog-Digest geprüft und zunächst nur inaktiv bereitgestellt. Bei ungetesteten Plugins erfolgt die Nutzung auf eigenes Risiko.', {title: 'Plugin aus Katalog laden', confirmText: 'Prüfen und laden', cancelText: 'Abbrechen', danger: <?= $trust === 'untested' ? 'true' : 'false' ?>}).then(result => { if (result) this.submit(); });">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="plugin_action" value="catalog_stage">
                                                <input type="hidden" name="plugin_id" value="<?= htmlspecialchars((string) $plugin['slug']) ?>">
                                                <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary" <?= !$plugin['installable'] ? 'disabled title="Kein SHA256-Digest oder Download hinterlegt"' : '' ?>>
                                                    Installieren
                                                </button>
                                            </form>
                                        <?php elseif (!($plugin['installed'] ?? false)): ?>
                                            <span class="ignis-chip ignis-chip--warning">Geprüft · Installation noch bestätigen</span>
                                        <?php elseif ($plugin['update_available']): ?>
                                            <span class="ignis-chip ignis-chip--warning">Update von <?= htmlspecialchars((string) $installedVersion) ?></span>
                                            <form method="post" class="inline"
                                                onsubmit="event.preventDefault(); showConfirm('Das bestehende Plugin wird gesichert und erst nach Digest- und Manifestprüfung atomar ersetzt.', {title: 'Plugin aktualisieren', confirmText: 'Update installieren', cancelText: 'Abbrechen'}).then(result => { if (result) this.submit(); });">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="plugin_action" value="catalog_update">
                                                <input type="hidden" name="plugin_id" value="<?= htmlspecialchars((string) $plugin['slug']) ?>">
                                                <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--soft-warning" <?= !$plugin['installable'] ? 'disabled' : '' ?>>Update</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="ignis-chip ignis-chip--success">Installiert <?= htmlspecialchars((string) $installedVersion) ?></span>
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
    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>
