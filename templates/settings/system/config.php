<?php
/**
 * View: System-Konfiguration bearbeiten
 */

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Config\ConfigManager;
use App\Setup\SetupCheck;
use App\Utils\AuditLogger;

$configManager = new ConfigManager();
$auditLogger = new AuditLogger();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $updates = [];
    $changes = [];

    // Get all configs to check types
    $allConfigs = $configManager->getAllConfig();
    $configTypes = [];
    foreach ($allConfigs as $config) {
        $configTypes[$config['config_key']] = $config['config_type'];
    }

    // Process POST data
    foreach ($allConfigs as $config) {
        if (!$config['is_editable']) continue;

        $key = $config['config_key'];

        // Get the raw database value (string) instead of converted value
        $oldValue = $config['config_value'];

        // Handle different input types
        if ($config['config_type'] === 'boolean') {
            // Checkboxes/switches send 'on' when checked, nothing when unchecked
            $value = isset($_POST[$key]) && $_POST[$key] === 'on' ? 'true' : 'false';
        } else {
            // Skip if not in POST
            if (!isset($_POST[$key])) continue;
            $value = $_POST[$key];
        }

        // Der Farbwähler liefert Hex in Großbuchstaben, gespeichert ist oft Kleinschreibung.
        if ($config['config_type'] === 'color' && strcasecmp((string) $oldValue, $value) === 0) continue;

        // Only update if value changed (strict comparison for type safety)
        if ($oldValue !== $value) {
            $updates[$key] = $value;
            $changes[] = [
                'key' => $key,
                'old' => $oldValue,
                'new' => $value
            ];
        }
    }

    if (!empty($updates)) {
        $result = $configManager->updateMultiple($updates, $_SESSION['userid']);

        if ($result['success']) {
            // Log each change in audit log
            foreach ($changes as $change) {
                $auditLogger->log(
                    $_SESSION['userid'],
                    'Config ' . $change['key'] . ' bearbeitet',
                    'Altere Wert: ' . $change['old'] . ', Neuer Wert: ' . $change['new'],
                    'System',
                    1  // Config updates are global
                );
            }

            Flash::set('success', 'Konfiguration erfolgreich aktualisiert.');
        } else {
            Flash::set('error', 'Fehler beim Aktualisieren der Konfiguration.');
        }
    } else {
        Flash::set('info', 'Keine Änderungen vorgenommen.');
    }

    // Im Einrichtungsmodus nach dem Speichern dort bleiben, damit der Hinweis
    // zeigt, was noch fehlt.
    header("Location: " . BASE_PATH . "settings/system/config.php" . (isset($_GET['setup']) ? '?setup=1' : ''));
    exit();
}

// Nur, was sich hier bearbeiten lässt, dazu API-Schlüssel und
// Installations-ID mit eigener Anzeige (nur lesen). Andere nicht
// editierbare Werte stünden sonst als Beschriftung ohne Feld da; wer sie
// pflegt, hat eine eigene Seite (z. B. /settings/mail).
$configByCategory = array_filter(array_map(
    static fn (array $configs): array => array_values(array_filter(
        $configs,
        static fn (array $config): bool => (bool) $config['is_editable'] || in_array($config['config_key'], ['API_KEY', 'INSTALLATION_ID'], true),
    )),
    $configManager->getConfigByCategory(),
));

// Einrichtungsmodus (?setup=1, verlinkt vom Dashboard und der Übersicht):
// Hinweis oben, offene Felder markiert, die Seite öffnet auf dem Abschnitt
// des ersten offenen Felds. Ohne den Modus öffnet sie auf dem ersten Abschnitt.
$setupMode  = isset($_GET['setup']);
$setupOpen  = $setupMode ? (new SetupCheck($configManager->getAllConfig()))->open() : [];
$setupLevel = array_column($setupOpen, 'level', 'key');
$setupText  = array_column($setupOpen, 'text', 'key');
$setupRequired    = array_values(array_filter($setupOpen, static fn (array $item): bool => $item['level'] === SetupCheck::REQUIRED));
$setupRecommended = array_values(array_filter($setupOpen, static fn (array $item): bool => $item['level'] === SetupCheck::RECOMMENDED));

$activeCategory = (string) array_key_first($configByCategory);
foreach ($setupOpen as $setupItem) {
    foreach ($configByCategory as $category => $configs) {
        if (in_array($setupItem['key'], array_column($configs, 'config_key'), true)) {
            $activeCategory = (string) $category;
            break 2;
        }
    }
}

$layout = 'admin';
$bodyId = 'settings';
$SITE_TITLE = 'System-Konfiguration';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="mb-6">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/system/index">System</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Konfiguration</span></nav>
                    <div class="page-header twplus-page-header mb-4">
                        <div class="twplus-page-header__copy">
                            <p class="twplus-page-header__eyebrow">System</p>
                            <h1>System-Konfiguration</h1>
                            <p class="twplus-page-header__description">Identität, Schnittstellen und Laufzeitverhalten des Systems konfigurieren.</p>
                        </div>
                    </div>

                    <?php if ($setupMode): ?>
                        <?php
                        $setupLinks = static fn (array $items): string => implode(', ', array_map(
                            static fn (array $item): string => '<a href="#cfg-' . htmlspecialchars($item['key']) . '" data-setup-jump>' . htmlspecialchars($item['label']) . '</a>',
                            $items,
                        ));
                        ?>
                        <div class="ignis-alert ignis-alert--<?= $setupRequired === [] ? 'ok' : 'warn' ?> mb-4" id="setup-notice" role="status">
                            <i class="fa-solid <?= $setupRequired === [] ? 'fa-circle-check' : 'fa-flag-checkered' ?> ignis-alert__icon" aria-hidden="true"></i>
                            <div class="ignis-alert__body">
                                <?php if ($setupRequired === []): ?>
                                    <div class="ignis-alert__title">Alles Nötige ist eingetragen</div>
                                    <p class="m-0">Der Schritt Systemdaten auf dem Dashboard ist erledigt.</p>
                                <?php else: ?>
                                    <div class="ignis-alert__title">Noch offen: <?= $setupLinks($setupRequired) ?></div>
                                <?php endif; ?>
                                <?php if ($setupRecommended !== []): ?>
                                    <p class="mt-2 mb-1"><?= $setupRequired === [] ? 'Noch empfohlen:' : 'Außerdem empfohlen:' ?></p>
                                    <ul class="m-0 pl-4 list-disc">
                                        <?php foreach ($setupRecommended as $setupItem): ?>
                                            <li><a href="#cfg-<?= htmlspecialchars($setupItem['key']) ?>" data-setup-jump><?= htmlspecialchars($setupItem['label']) ?></a>: <?= htmlspecialchars($setupItem['text']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="ignis-list-toolbar">
                        <div class="ignis-segmented" id="categoryFilter" role="group" aria-label="Abschnitt">
                            <?php foreach ($configByCategory as $category => $configs): ?>
                                <button type="button" aria-pressed="<?= $category === $activeCategory ? 'true' : 'false' ?>" data-category="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($configManager->getCategoryDisplayName($category)) ?></button>
                            <?php endforeach; ?>
                            <button type="button" aria-pressed="false" data-category="">Alle</button>
                        </div>
                    </div>

                    <form method="post" id="configForm">
                        <?= csrf_field() ?>
                        <?php foreach ($configByCategory as $category => $configs): ?>
                            <div class="config-section" data-config-category="<?= htmlspecialchars($category) ?>"<?= $category === $activeCategory ? '' : ' hidden' ?>>
                                <div class="ignis-card mb-4">
                                    <div class="ignis-card__header">
                                        <h2 class="ignis-card__title"><?= htmlspecialchars($configManager->getCategoryDisplayName($category)) ?></h2>
                                    </div>
                                    <div class="ignis-card__body">
                                        <?php foreach ($configs as $config):
                                            $configLevel = $setupLevel[$config['config_key']] ?? null;
                                            $configHint  = trim((string) ($config['hint'] ?? ''));
                                        ?>
                                            <div class="twplus-form-section<?= $configLevel !== null ? ' twplus-form-section--setup' : '' ?>" id="cfg-<?= htmlspecialchars($config['config_key']) ?>"<?= $configLevel !== null ? ' data-setup="' . $configLevel . '"' : '' ?>>
                                                <div>
                                                    <label for="<?= htmlspecialchars($config['config_key']) ?>" class="twplus-form-section__label">
                                                        <?= htmlspecialchars($config['description']) ?>
                                                    </label>
                                                    <div class="twplus-form-section__key"><?= htmlspecialchars($config['config_key']) ?> · <?= htmlspecialchars($config['config_type']) ?></div>
                                                    <?php if ($configLevel !== null): ?>
                                                        <div class="twplus-form-section__setup">
                                                            <span class="ignis-chip ignis-chip--sm ignis-chip--<?= $configLevel === SetupCheck::REQUIRED ? 'warn' : 'info' ?>"><?= $configLevel === SetupCheck::REQUIRED ? 'Pflicht' : 'Empfohlen' ?></span>
                                                            <?= htmlspecialchars($setupText[$config['config_key']]) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>

                                                <?php if ($config['config_key'] === 'API_KEY'): ?>
                                                    <div class="flex items-center gap-2">
                                                        <input
                                                            type="password"
                                                            class="ignis-input ignis-mono"
                                                            id="<?= htmlspecialchars($config['config_key']) ?>"
                                                            value="<?= htmlspecialchars($config['config_value']) ?>"
                                                            readonly>
                                                        <button
                                                            type="button"
                                                            class="ignis-btn ignis-btn--secondary ignis-btn--icon"
                                                            onclick="toggleApiKeyVisibility()"
                                                            data-ignis-tooltip="API-Schlüssel anzeigen oder verbergen"
                                                            aria-label="API-Schlüssel anzeigen oder verbergen"
                                                            aria-pressed="false"
                                                            id="toggleApiKeyBtn">
                                                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="ignis-btn ignis-btn--secondary ignis-btn--icon"
                                                            onclick="copyApiKey()"
                                                            data-ignis-tooltip="API-Schlüssel kopieren"
                                                            aria-label="API-Schlüssel kopieren">
                                                            <i class="fa-solid fa-copy" aria-hidden="true"></i>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="ignis-btn ignis-btn--ghost-danger ignis-btn--icon"
                                                            onclick="regenerateApiKey(event)"
                                                            data-ignis-tooltip="API-Schlüssel neu generieren"
                                                            aria-label="API-Schlüssel neu generieren">
                                                            <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                                                        </button>
                                                    </div>

                                                <?php elseif ($config['config_key'] === 'INSTALLATION_ID'): ?>
                                                    <input
                                                        type="text"
                                                        class="ignis-input ignis-mono"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        value="<?= htmlspecialchars($config['config_value']) ?>"
                                                        readonly>

                                                <?php elseif ($config['is_editable'] && $config['config_type'] === 'boolean'): ?>
                                                    <label class="ignis-switch" for="<?= htmlspecialchars($config['config_key']) ?>">
                                                        <input
                                                            type="checkbox"
                                                            id="<?= htmlspecialchars($config['config_key']) ?>"
                                                            name="<?= htmlspecialchars($config['config_key']) ?>"
                                                            <?= ($config['config_value'] === 'true' || $config['config_value'] === '1') ? 'checked' : '' ?>>
                                                        <span></span>
                                                    </label>

                                                <?php elseif ($config['is_editable'] && $config['config_type'] === 'color'): ?>
                                                    <input
                                                        type="text"
                                                        data-ignis-colorpicker
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>"
                                                        value="<?= htmlspecialchars($config['config_value']) ?>">
                                                    <?php if ($config['config_key'] === 'SYSTEM_COLOR' && \App\Helpers\Theme::accentLooksLikeDanger((string) $config['config_value'])): ?>
                                                        <div class="ignis-alert ignis-alert--warn mt-2" id="system-color-danger" role="status">
                                                            <i class="fa-solid fa-triangle-exclamation ignis-alert__icon" aria-hidden="true"></i>
                                                            <div class="ignis-alert__body">Diese Farbe ist kaum vom Rot zu unterscheiden, mit dem ignis Gefahr, Alarme und auf dem MANV-Board die Sichtungskategorie 1 anzeigt. Primärknöpfe und Fortschrittsbalken sehen damit wie Warnungen aus. Besser passt ein Ton, der sich klar von Rot abhebt.</div>
                                                        </div>
                                                    <?php endif; ?>

                                                <?php elseif ($config['is_editable'] && $config['config_type'] === 'url' && $config['config_key'] === 'SYSTEM_LOGO'):
                                                    $logoIsDefault = systemLogoIsDefault((string) $config['config_value']);
                                                    $logoCurrent   = $logoIsDefault ? '' : systemLogoUrl((string) $config['config_value']);
                                                ?>
                                                    <div class="ignis-file ignis-file--dropzone ignis-file--photo mb-2" id="system-logo-dropzone" data-ignis-file data-max-bytes="2097152" data-ignis-file-current="<?= htmlspecialchars($logoCurrent, ENT_QUOTES) ?>">
                                                        <input type="file" id="system-logo-upload" accept="image/png,image/jpeg,image/webp" class="ignis-file__input">
                                                        <label for="system-logo-upload" class="ignis-file__zone">
                                                            <span class="ignis-file__icon" aria-hidden="true"><i class="fa-solid fa-image"></i></span>
                                                            <span class="ignis-file__title">Logo hierher ziehen oder <span class="ignis-file__link">auswählen</span></span>
                                                            <span class="ignis-file__hint">PNG, JPEG oder WebP, max. 2 MB</span>
                                                        </label>
                                                        <div class="ignis-file__selected" hidden></div>
                                                        <p class="ignis-file__error" role="alert" hidden></p>
                                                    </div>
                                                    <button type="button" class="ignis-btn ignis-btn--ghost-danger ignis-btn--sm mb-2" id="system-logo-remove" <?= $logoIsDefault ? 'hidden' : '' ?>>
                                                        <i class="fa-solid fa-trash" aria-hidden="true"></i> Logo entfernen
                                                    </button>
                                                    <details class="ignis-field__hint">
                                                        <summary>Stattdessen Pfad oder URL angeben</summary>
                                                        <input
                                                            type="text"
                                                            class="ignis-input mb-2 mt-2"
                                                            id="<?= htmlspecialchars($config['config_key']) ?>"
                                                            name="<?= htmlspecialchars($config['config_key']) ?>"
                                                            value="<?= htmlspecialchars($config['config_value']) ?>"
                                                            oninput="updateLogoPreview(this.value)">
                                                        <div class="mt-2">
                                                            <span class="ignis-field__label block mb-1">Vorschau</span>
                                                            <img
                                                                src="<?= systemLogoUrl((string) $config['config_value']) ?>"
                                                                alt="Vorschau des Logos"
                                                                class="max-h-[100px] max-w-[200px] rounded-md border border-border-subtle bg-surface-2 p-2"
                                                                id="logo_preview"
                                                                onload="this.hidden = false; this.nextElementSibling.hidden = true"
                                                                onerror="this.hidden = true; this.nextElementSibling.hidden = false">
                                                            <span class="ignis-field__hint" hidden>Bild nicht gefunden</span>
                                                        </div>
                                                    </details>

                                                <?php elseif ($config['is_editable'] && $config['config_type'] === 'url' && $config['config_key'] === 'META_IMAGE_URL'): ?>
                                                    <input
                                                        type="text"
                                                        class="ignis-input"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>"
                                                        value="<?= htmlspecialchars($config['config_value']) ?>"
                                                        oninput="updateMetaImagePreview(this.value)">
                                                    <div class="mt-2"<?= trim((string) $config['config_value']) === '' ? ' hidden' : '' ?>>
                                                        <span class="ignis-field__label block mb-1">Vorschau</span>
                                                        <img
                                                            src="<?= htmlspecialchars($config['config_value']) ?>"
                                                            alt="Vorschau des Link-Bildes"
                                                            class="max-h-[100px] max-w-[200px] rounded-md border border-border-subtle bg-surface-2 p-2"
                                                            id="meta_image_preview"
                                                            onload="this.hidden = false; this.nextElementSibling.hidden = true"
                                                            onerror="this.hidden = true; this.nextElementSibling.hidden = false">
                                                        <span class="ignis-field__hint" hidden>Bild nicht gefunden</span>
                                                    </div>

                                                <?php elseif ($config['is_editable'] && $config['config_key'] === 'REGISTRATION_MODE'): ?>
                                                    <select
                                                        class="ignis-input"
                                                        data-custom-dropdown="true"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>">
                                                        <option value="open" <?= $config['config_value'] === 'open' ? 'selected' : '' ?>>Offen für alle</option>
                                                        <option value="code" <?= $config['config_value'] === 'code' ? 'selected' : '' ?>>Nur mit Einladungscode</option>
                                                        <option value="closed" <?= $config['config_value'] === 'closed' ? 'selected' : '' ?>>Geschlossen</option>
                                                    </select>

                                                <?php elseif ($config['is_editable'] && $config['config_key'] === 'ENOTF_BZ_UNIT'): ?>
                                                    <select
                                                        class="ignis-input"
                                                        data-custom-dropdown="true"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>">
                                                        <option value="mg/dl" <?= $config['config_value'] === 'mg/dl' ? 'selected' : '' ?>>mg/dl (Milligramm pro Deziliter)</option>
                                                        <option value="mmol/l" <?= $config['config_value'] === 'mmol/l' ? 'selected' : '' ?>>mmol/l (Millimol pro Liter)</option>
                                                    </select>

                                                <?php elseif ($config['is_editable']): ?>
                                                    <input
                                                        type="text"
                                                        class="ignis-input"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>"
                                                        value="<?= htmlspecialchars($config['config_value']) ?>">
                                                <?php endif; ?>
                                                <?php if ($configHint !== ''): ?>
                                                    <div class="ignis-field__hint"><?= htmlspecialchars($configHint) ?></div>
                                                <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="twplus-sticky-actions mb-6">
                            <button type="submit" name="save_config" class="ignis-btn ignis-btn--primary">
                                <i class="fa-solid fa-save" aria-hidden="true"></i> Änderungen speichern
                            </button>
                        </div>
                    </form>
            </div>
        </div>
    </div>

    <script>
        // Abschnitte: zeigt nur die Karte des gewählten Abschnitts ("Alle" zeigt
        // alle). Ausgeblendete Felder bleiben im Formular und werden mitgespeichert.
        (function () {
            var buttons = document.querySelectorAll('#categoryFilter button');

            function showCategory(cat) {
                buttons.forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.category === cat)); });
                document.querySelectorAll('.config-section').forEach(function (section) {
                    section.hidden = !!cat && section.dataset.configCategory !== cat;
                });
            }

            // Springt zu einem Feld (#cfg-<KEY>), auch wenn sein Abschnitt gerade zu ist.
            function jumpTo(id) {
                var row = document.getElementById(id);
                var section = row && row.closest('.config-section');
                if (!section) return false;
                if (section.hidden) showCategory(section.dataset.configCategory);
                row.scrollIntoView({ block: 'center' });
                var field = row.querySelector('input:not([type=hidden]):not([type=file]), select, textarea');
                if (field) field.focus({ preventScroll: true });
                return true;
            }

            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () { showCategory(btn.dataset.category); });
            });
            document.querySelectorAll('[data-setup-jump]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    var id = link.getAttribute('href').slice(1);
                    if (jumpTo(id)) {
                        event.preventDefault();
                        history.replaceState(null, '', '#' + id);
                    }
                });
            });
            if (location.hash.indexOf('#cfg-') === 0) jumpTo(location.hash.slice(1));
        })();

        function updateLogoPreview(value) {
            document.getElementById('logo_preview').src = value;
        }

        function updateMetaImagePreview(value) {
            var img = document.getElementById('meta_image_preview');
            // Ohne URL keine Vorschau
            img.parentElement.hidden = value.trim() === '';
            img.src = value;
        }

        // System-Logo-Dropzone: eigener fetch()-Upload statt Teil der grossen
        // Config-Form (die bleibt unveraendert), gleiches Muster wie die
        // Profilbild-Dropzone in mitarbeiter-profile.js.
        (function () {
            var wrap      = document.getElementById('system-logo-dropzone');
            var input     = document.getElementById('system-logo-upload');
            var removeBtn = document.getElementById('system-logo-remove');
            if (!wrap || !input) return;

            var basePath = <?= json_encode(BASE_PATH) ?>;
            var apiUrl   = basePath + (basePath.endsWith('/') ? '' : '/') + 'api/system/logo';

            function showDropzoneError(message) {
                var error = wrap.querySelector('.ignis-file__error');
                if (error) {
                    error.hidden = false;
                    error.textContent = message;
                }
                input.setAttribute('aria-invalid', 'true');
            }

            function resetInput() {
                input.value = '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }

            input.addEventListener('change', function () {
                // file.js prueft Typ/Groesse im selben change-Event; der
                // Timeout schiebt den Upload dahinter (siehe mitarbeiter-profile.js).
                setTimeout(function () {
                    var file = input.files && input.files[0];
                    if (!file || input.getAttribute('aria-invalid') === 'true') return;

                    var formData = new FormData();
                    formData.append('logo', file);

                    input.disabled = true;
                    wrap.style.opacity = '0.6';

                    fetch(apiUrl, { method: 'POST', body: formData })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            input.disabled = false;
                            wrap.style.opacity = '1';
                            if (data.success) {
                                wrap.dataset.ignisFileCurrent = data.url + '?t=' + Date.now();
                                document.getElementById('logo_preview').src = data.url;
                                if (removeBtn) removeBtn.hidden = false;
                                showToast('Logo aktualisiert', 'success');
                            } else {
                                resetInput();
                                showDropzoneError(data.message || 'Upload fehlgeschlagen');
                            }
                        })
                        .catch(function () {
                            input.disabled = false;
                            wrap.style.opacity = '1';
                            resetInput();
                            showDropzoneError('Upload fehlgeschlagen');
                        });
                }, 0);
            });

            if (removeBtn) {
                removeBtn.addEventListener('click', async function () {
                    var confirmed = await showConfirm(
                        'Logo wirklich entfernen? Danach erscheint wieder die Standard-Wortmarke.', {
                            title: 'Logo entfernen',
                            confirmText: 'Entfernen',
                            cancelText: 'Abbrechen',
                            danger: true,
                        }
                    );
                    if (!confirmed) return;

                    fetch(apiUrl + '/remove', { method: 'POST' })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.success) {
                                wrap.dataset.ignisFileCurrent = '';
                                document.getElementById('logo_preview').src = data.url;
                                removeBtn.hidden = true;
                                showToast('Logo zurückgesetzt', 'success');
                            } else {
                                showToast(data.message || 'Fehler beim Entfernen', 'danger');
                            }
                        })
                        .catch(function () {
                            showToast('Fehler beim Entfernen', 'danger');
                        });
                });
            }
        })();

        function toggleApiKeyVisibility() {
            const input = document.getElementById('API_KEY');
            const button = document.getElementById('toggleApiKeyBtn');
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                button.setAttribute('aria-pressed', 'true');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                button.setAttribute('aria-pressed', 'false');
            }
        }

        async function copyApiKey() {
            const input = document.getElementById('API_KEY');
            try {
                await navigator.clipboard.writeText(input.value);
                showToast('API-Schlüssel kopiert', 'success');
            } catch (err) {
                showToast('Fehler beim Kopieren: ' + err, 'danger');
            }
        }

        async function regenerateApiKey(event) {
            const confirmed = await showConfirm(
                'Möchten Sie wirklich einen neuen API-Schlüssel generieren?\n\nWARNUNG: Dies macht alle bestehenden Integrationen ungültig, die den aktuellen API-Schlüssel verwenden!', {
                    title: 'API-Schlüssel neu generieren',
                    confirmText: 'Ja, neu generieren',
                    cancelText: 'Abbrechen',
                    danger: true
                }
            );

            if (!confirmed) {
                return;
            }

            // Show loading indicator
            const button = event.target.closest('button');
            const originalContent = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Wird generiert...';

            // Send request to regenerate API key
            const basePath = <?= json_encode(BASE_PATH) ?>;
            const url = basePath + (basePath.endsWith('/') ? '' : '/') + 'api/system/regenerate-api-key';
            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the input field with new API key
                        document.getElementById('API_KEY').value = data.api_key;
                        // Reset to password type after regeneration for security
                        const input = document.getElementById('API_KEY');
                        const button = document.getElementById('toggleApiKeyBtn');
                        const icon = button.querySelector('i');
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                        button.setAttribute('aria-pressed', 'false');

                        showAlert('API-Schlüssel wurde erfolgreich neu generiert!', {
                            title: 'Erfolg',
                            type: 'success'
                        });
                    } else {
                        showAlert('Fehler beim Generieren des API-Schlüssels: ' + (data.message || 'Unbekannter Fehler'), {
                            title: 'Fehler',
                            type: 'error'
                        });
                    }
                })
                .catch(error => {
                    showAlert('Fehler beim Generieren des API-Schlüssels: ' + error, {
                        title: 'Fehler',
                        type: 'error'
                    });
                })
                .finally(() => {
                    button.disabled = false;
                    button.innerHTML = originalContent;
                });
        }
    </script>
