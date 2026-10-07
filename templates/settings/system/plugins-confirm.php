<?php

/**
 * View: Bestätigung vor der Installation eines Plugins
 *
 * Eine Seite für alle Wege, auf denen fremder Code nach ignis kommt: ein
 * hochgeladenes ZIP, ein Katalog-Eintrag, ein Update oder ein Plugin, das
 * schon inaktiv in plugins/ liegt. Bei Drittanbietern ist das Häkchen
 * `accept_risk` Pflicht; der Server prüft es noch einmal.
 *
 * @var array<string,mixed> $confirm
 */

use App\Security\CsrfProtection;

$csrfToken = CsrfProtection::getToken();

$layout = 'admin';
$bodyId = 'settings';
$SITE_TITLE = 'Plugin installieren';

$kind       = (string) $confirm['kind'];
$isUpdate   = (bool) $confirm['update'];
$thirdParty = (bool) $confirm['third_party'];
$trust      = $confirm['trust'];
$trustLabels = ['official' => 'Offiziell', 'verified' => 'Geprüft', 'tested' => 'Geprüft', 'untested' => 'Ungetestet'];

$heading = $isUpdate ? 'Update einspielen' : 'Plugin installieren';
$submitLabel = $isUpdate ? 'Update einspielen' : 'Installieren';
$backHref = BASE_PATH . 'settings/system/plugins';

$bytes = (int) $confirm['bytes'];
$size = $bytes > 0
    ? ($bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB')
    : null;
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/system/index">System</a></span> <span class="ignis-breadcrumb__item"><a href="<?= $backHref ?>">Plugins</a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= htmlspecialchars($heading) ?></span></nav>

            <div class="mb-6" style="max-width: 760px;">
                <div class="twplus-page-header mb-4">
                    <div class="twplus-page-header__copy">
                        <p class="twplus-page-header__eyebrow">
                            <?= match ($kind) {
                                'upload' => 'Hochgeladenes Plugin',
                                'catalog', 'update' => 'Aus dem Katalog',
                                default => 'Bereitliegendes Plugin',
                            } ?>
                        </p>
                        <h1><?= htmlspecialchars((string) $confirm['name']) ?></h1>
                        <p class="twplus-page-header__description">
                            <?php if ($isUpdate): ?>
                                Version <?= htmlspecialchars((string) ($confirm['installed_version'] ?? '?')) ?> wird durch <?= htmlspecialchars((string) $confirm['version']) ?> ersetzt.
                            <?php else: ?>
                                Version <?= htmlspecialchars((string) $confirm['version']) ?>. Bitte prüfe die Angaben, bevor du installierst.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if ($thirdParty): ?>
                    <div class="ignis-alert ignis-alert--danger mb-4" role="note">
                        <i class="fa-solid fa-triangle-exclamation ignis-alert__icon" aria-hidden="true"></i>
                        <div class="ignis-alert__body">
                            <div class="ignis-alert__title">Plugin eines Drittanbieters</div>
                            <p class="mb-2">
                                <?= $kind === 'install'
                                    ? 'Dieses Plugin wird nicht mit ıgnıs ausgeliefert, und woher es stammt, kann ıgnıs nicht prüfen.'
                                    : 'Dieses Plugin stammt nicht von EmergencyForge.' ?>
                                Ein Plugin läuft mit denselben
                                Rechten wie ıgnıs selbst: Es kann alle Daten lesen und ändern, Dateien auf dem
                                Server schreiben und Verbindungen nach außen aufbauen. Seine Migrationen ändern
                                die Datenbank.
                            </p>
                            <?php if ($kind === 'upload'): ?>
                                <p class="mb-2">
                                    ıgnıs hat nur den Aufbau des Archivs und das Manifest geprüft, nicht, was der
                                    Code tut. Die Angaben zu Name und Herausgeber stehen so im Manifest und sind
                                    nicht bestätigt.
                                </p>
                            <?php elseif ($trust === 'verified' || $trust === 'tested'): ?>
                                <p class="mb-2">
                                    Im Katalog ist diese Version als „Geprüft“ markiert. Der Code bleibt trotzdem
                                    Sache des Herausgebers, und spätere Versionen können anders aussehen.
                                </p>
                            <?php elseif ($trust === 'untested'): ?>
                                <p class="mb-2">
                                    Im Katalog ist dieses Plugin als „Ungetestet“ markiert. EmergencyForge hat den
                                    Code nicht angesehen.
                                </p>
                            <?php endif; ?>
                            <p class="mb-0">
                                EmergencyForge übernimmt keine Gewähr für Funktion, Sicherheit oder mögliche
                                Datenverluste, Support leistet der Herausgeber. Installiere nur, wenn du der
                                Quelle vertraust, und lege vorher ein Backup der Datenbank an.
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="ignis-alert ignis-alert--info mb-4" role="note">
                        <i class="fa-solid fa-circle-info ignis-alert__icon" aria-hidden="true"></i>
                        <div class="ignis-alert__body">
                            Offizielles Plugin von EmergencyForge. Das ZIP wird von GitHub geladen und vor dem
                            Entpacken gegen die SHA256-Prüfsumme aus dem Katalog geprüft.
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($isUpdate): ?>
                    <div class="ignis-alert ignis-alert--warn mb-4" role="note">
                        <i class="fa-solid fa-clock-rotate-left ignis-alert__icon" aria-hidden="true"></i>
                        <div class="ignis-alert__body">
                            Die vorhandene Version wird nach <code>plugins/.backup/</code> gesichert und ersetzt.
                            Ist das Plugin installiert, läuft ab sofort der neue Code, und neue Migrationen
                            werden direkt ausgeführt. Der Aktivierungszustand bleibt, wie er ist.
                        </div>
                    </div>
                <?php endif; ?>

                <article class="ignis-card ignis-card--bordered mb-4">
                    <div class="ignis-card__body">
                        <dl class="mb-0 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1" style="font-size: 0.88rem;">
                            <dt>ID</dt>
                            <dd><code><?= htmlspecialchars((string) $confirm['id']) ?></code></dd>

                            <dt>Version</dt>
                            <dd>
                                <?= htmlspecialchars((string) $confirm['version']) ?>
                                <?php if ($isUpdate && $confirm['installed_version'] !== null): ?>
                                    <span class="text-tertiary-text">(vorhanden: <?= htmlspecialchars((string) $confirm['installed_version']) ?>)</span>
                                <?php endif; ?>
                            </dd>

                            <dt>Herausgeber</dt>
                            <dd><?= htmlspecialchars((string) $confirm['vendor']) ?></dd>

                            <?php if ($trust !== null): ?>
                                <dt>Katalogstatus</dt>
                                <dd>
                                    <span class="ignis-chip <?= $trust === 'untested' ? 'ignis-chip--warn' : 'ignis-chip--info' ?>">
                                        <?= htmlspecialchars($trustLabels[$trust] ?? 'Ungetestet') ?>
                                    </span>
                                </dd>
                            <?php endif; ?>

                            <dt>Quelle</dt>
                            <dd>
                                <?php if ((string) $confirm['source_url'] !== ''): ?>
                                    <a href="<?= htmlspecialchars((string) $confirm['source_url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= htmlspecialchars((string) $confirm['origin']) ?></a>
                                <?php else: ?>
                                    <?= htmlspecialchars((string) $confirm['origin']) ?>
                                <?php endif; ?>
                            </dd>

                            <?php if ($size !== null): ?>
                                <dt>Größe</dt>
                                <dd><?= htmlspecialchars($size) ?></dd>
                            <?php endif; ?>

                            <?php if ((string) $confirm['sha256'] !== ''): ?>
                                <dt>SHA256</dt>
                                <dd><code style="word-break: break-all; font-size: 0.75rem;"><?= htmlspecialchars((string) $confirm['sha256']) ?></code></dd>
                            <?php endif; ?>

                            <?php if ($confirm['requires'] !== null): ?>
                                <dt>Benötigt ıgnıs</dt>
                                <dd><?= htmlspecialchars((string) $confirm['requires']) ?></dd>
                            <?php endif; ?>

                            <?php if ($confirm['depends'] !== []): ?>
                                <dt>Abhängig von</dt>
                                <dd><?= htmlspecialchars(implode(', ', (array) $confirm['depends'])) ?></dd>
                            <?php endif; ?>

                            <?php if ($confirm['permissions'] !== []): ?>
                                <dt>Bringt Rechte mit</dt>
                                <dd><?= htmlspecialchars(implode(', ', (array) $confirm['permissions'])) ?></dd>
                            <?php endif; ?>
                        </dl>
                        <?php if ((string) $confirm['description'] !== ''): ?>
                            <p class="ignis-card__text mt-3 mb-0"><?= htmlspecialchars((string) $confirm['description']) ?></p>
                        <?php endif; ?>
                    </div>
                </article>

                <form method="post" action="<?= $backHref ?>" id="plugin-confirm-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="plugin_action" value="<?= htmlspecialchars((string) $confirm['action']) ?>">
                    <?php foreach ((array) $confirm['fields'] as $field => $value): ?>
                        <input type="hidden" name="<?= htmlspecialchars((string) $field) ?>" value="<?= htmlspecialchars((string) $value) ?>">
                    <?php endforeach; ?>

                    <?php if ($confirm['offer_stage_only']): ?>
                        <div class="ignis-checkbox mb-2">
                            <input type="checkbox" name="install_now" value="1" id="plugin-install-now" checked>
                            <label for="plugin-install-now">Direkt installieren und aktivieren</label>
                        </div>
                        <p class="text-tertiary-text mb-3" style="font-size: 0.78rem;">
                            Ohne Häkchen liegt das Plugin nur geprüft und inaktiv in <code>plugins/</code> bereit.
                            Installieren kannst du es dann später in der Liste.
                        </p>
                    <?php endif; ?>

                    <?php if ($thirdParty): ?>
                        <div class="ignis-checkbox mb-3">
                            <input type="checkbox" name="accept_risk" value="1" id="plugin-accept-risk" required>
                            <label for="plugin-accept-risk">Ich habe den Hinweis gelesen und installiere dieses Plugin eines Drittanbieters auf eigenes Risiko.</label>
                        </div>
                    <?php endif; ?>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="submit" class="ignis-btn ignis-btn--sm <?= $thirdParty ? 'ignis-btn--danger' : 'ignis-btn--primary' ?>">
                            <?= htmlspecialchars($submitLabel) ?>
                        </button>
                        <?php if ($kind === 'upload'): ?>
                            <button type="submit" form="plugin-discard-form" class="ignis-btn ignis-btn--sm ignis-btn--secondary">Verwerfen</button>
                        <?php else: ?>
                            <a href="<?= $backHref ?>" class="ignis-btn ignis-btn--sm ignis-btn--secondary">Abbrechen</a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($kind === 'upload'): ?>
                    <form method="post" action="<?= $backHref ?>" id="plugin-discard-form" hidden>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="plugin_action" value="upload_discard">
                        <input type="hidden" name="upload_token" value="<?= htmlspecialchars((string) ($confirm['fields']['upload_token'] ?? '')) ?>">
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
