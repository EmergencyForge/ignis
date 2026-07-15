<?php

/**
 * View: Plugin-Verwaltung
 *
 * @var \PDO                                $pdo
 * @var list<array{id: string, manifest: \App\Plugins\PluginManifest, enabled: bool, active: bool, skipReason: ?string, requiredBy: list<string>}> $rows
 * @var string                              $message
 * @var string                              $messageType
 */

use App\Security\CsrfProtection;

$csrfToken = CsrfProtection::getToken();
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">

<head>
    <?php
    $SITE_TITLE = 'Plugins';
    include __DIR__ . '/../../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6">
                <div class="mb-4 flex items-center justify-between">
                    <h1 class="mb-0">Plugins</h1>
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
                    <div class="alert alert-<?= htmlspecialchars($messageType) ?> mb-4" role="alert">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($rows === []): ?>
                    <div class="intra__tile p-4 text-gray-400">
                        Keine Plugins installiert.
                    </div>
                <?php endif; ?>

                <?php foreach ($rows as $row): ?>
                    <?php $m = $row['manifest']; ?>
                    <div class="intra__tile mb-3 p-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="flex-1" style="min-width: 260px;">
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong><?= htmlspecialchars($m->name) ?></strong>
                                    <span class="badge text-bg-secondary"><?= htmlspecialchars($m->version) ?></span>
                                    <?php if (!$row['installed']): ?>
                                        <span class="badge text-bg-danger">Nicht installiert</span>
                                    <?php elseif ($row['active']): ?>
                                        <span class="badge text-bg-success">Aktiv</span>
                                    <?php elseif ($row['enabled'] && $row['skipReason'] !== null): ?>
                                        <span class="badge text-bg-warning" title="<?= htmlspecialchars($row['skipReason']) ?>">Übersprungen</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-dark">Inaktiv</span>
                                    <?php endif; ?>
                                    <?php if ($row['bundled']): ?>
                                        <span class="badge text-bg-primary" title="Offiziell mit ıgnıs ausgeliefert.">Offiziell</span>
                                    <?php endif; ?>
                                    <?php if (!$m->removable): ?>
                                        <span class="badge text-bg-info" title="Dieses Plugin ist fester Bestandteil und kann nicht deaktiviert werden.">Erforderlich</span>
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
                            <div class="shrink-0">
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
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>
    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>
