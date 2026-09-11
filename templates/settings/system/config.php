<?php
/**
 * View: System-Konfiguration bearbeiten
 */

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Config\ConfigManager;
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

    header("Location: " . BASE_PATH . "settings/system/config.php");
    exit();
}

$configByCategory = $configManager->getConfigByCategory();

$layout = 'admin';
$bodyId = 'settings';
$SITE_TITLE = 'System-Konfiguration';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="mb-6">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/system/index">System</a></span> <span class="ignis-breadcrumb__item is-active">Konfiguration</span></nav>
                    <div class="page-header twplus-page-header mb-4">
                        <div class="twplus-page-header__copy">
                            <p class="twplus-page-header__eyebrow">System</p>
                            <h1>System-Konfiguration</h1>
                            <p class="twplus-page-header__description">Identität, Schnittstellen und Laufzeitverhalten des Systems konfigurieren.</p>
                        </div>
                    </div>

                    <div class="ignis-list-toolbar">
                        <nav class="ignis-filter-links" id="categoryFilter" aria-label="Kategorie">
                            <button type="button" class="is-active" data-category="">Alle</button>
                            <?php foreach ($configByCategory as $category => $configs): ?>
                                <button type="button" data-category="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($configManager->getCategoryDisplayName($category)) ?></button>
                            <?php endforeach; ?>
                        </nav>
                    </div>

                    <form method="post" id="configForm">
                        <?php foreach ($configByCategory as $category => $configs): ?>
                            <div class="config-section" data-config-category="<?= htmlspecialchars($category) ?>">
                                <div class="ignis-card mb-4">
                                    <div class="ignis-card__header">
                                        <h2 class="ignis-card__title"><?= htmlspecialchars($configManager->getCategoryDisplayName($category)) ?></h2>
                                    </div>
                                    <div class="ignis-card__body">
                                        <?php foreach ($configs as $config): ?>
                                            <div class="twplus-form-section">
                                                <div>
                                                    <label for="<?= htmlspecialchars($config['config_key']) ?>" class="twplus-form-section__label">
                                                        <?= htmlspecialchars($config['description']) ?>
                                                    </label>
                                                    <div class="twplus-form-section__hint">
                                                        <?= htmlspecialchars($config['config_key']) ?> · <?= htmlspecialchars($config['config_type']) ?>
                                                    </div>
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
                                                            title="API-Schlüssel anzeigen"
                                                            aria-label="API-Schlüssel anzeigen oder verbergen"
                                                            id="toggleApiKeyBtn">
                                                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="ignis-btn ignis-btn--secondary ignis-btn--icon"
                                                            onclick="copyApiKey()"
                                                            title="API-Schlüssel kopieren"
                                                            aria-label="API-Schlüssel kopieren">
                                                            <i class="fa-solid fa-copy" aria-hidden="true"></i>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="ignis-btn ignis-btn--ghost-danger ignis-btn--icon"
                                                            onclick="regenerateApiKey(event)"
                                                            title="API-Schlüssel neu generieren"
                                                            aria-label="API-Schlüssel neu generieren">
                                                            <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                                                        </button>
                                                    </div>
                                                    <div class="ignis-field__hint">Dieser API-Schlüssel wird für externe Schnittstellen verwendet. Ein neuer Schlüssel macht alte Integrationen ungültig.</div>

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
                                                    <div class="flex items-center gap-2">
                                                        <input
                                                            type="color"
                                                            class="h-10 w-14 shrink-0 cursor-pointer rounded-md border border-[var(--border)] bg-transparent p-0"
                                                            id="<?= htmlspecialchars($config['config_key']) ?>_picker"
                                                            aria-label="Farbe wählen"
                                                            value="<?= htmlspecialchars($config['config_value']) ?>"
                                                            onchange="updateColorValue('<?= htmlspecialchars($config['config_key']) ?>', this.value)">
                                                        <input
                                                            type="text"
                                                            class="ignis-input ignis-mono"
                                                            id="<?= htmlspecialchars($config['config_key']) ?>"
                                                            name="<?= htmlspecialchars($config['config_key']) ?>"
                                                            value="<?= htmlspecialchars($config['config_value']) ?>"
                                                            pattern="^#[0-9A-Fa-f]{6}$"
                                                            placeholder="#000000"
                                                            title="6-stelliger Hex-Farbcode (z.B. #ff0000)"
                                                            oninput="updateColorPicker('<?= htmlspecialchars($config['config_key']) ?>', this.value)">
                                                    </div>
                                                    <div class="ignis-field__hint">Wählen Sie eine Farbe aus oder geben Sie einen Hex-Farbcode ein.</div>

                                                <?php elseif ($config['is_editable'] && $config['config_type'] === 'url' && $config['config_key'] === 'SYSTEM_LOGO'): ?>
                                                    <input
                                                        type="text"
                                                        class="ignis-input mb-2"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>"
                                                        value="<?= htmlspecialchars($config['config_value']) ?>"
                                                        oninput="updateLogoPreview(this.value)">
                                                    <div class="ignis-field__hint">Relativer Pfad oder vollständige URL zum Logo.</div>
                                                    <div class="mt-2">
                                                        <span class="ignis-field__label block mb-1">Vorschau</span>
                                                        <img
                                                            src="<?= htmlspecialchars($config['config_value']) ?>"
                                                            alt="Vorschau des Logos"
                                                            class="max-h-[100px] max-w-[200px] rounded-md border border-[var(--border)] bg-[var(--surface-2)] p-2"
                                                            id="logo_preview"
                                                            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22100%22%3E%3Crect fill=%22%23ddd%22 width=%22200%22 height=%22100%22/%3E%3Ctext fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22%3EBild nicht gefunden%3C/text%3E%3C/svg%3E'">
                                                    </div>

                                                <?php elseif ($config['is_editable'] && $config['config_type'] === 'url' && $config['config_key'] === 'META_IMAGE_URL'): ?>
                                                    <input
                                                        type="text"
                                                        class="ignis-input mb-2"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>"
                                                        value="<?= htmlspecialchars($config['config_value']) ?>"
                                                        oninput="updateMetaImagePreview(this.value)">
                                                    <div class="ignis-field__hint">Vollständige URL zum Bild für Link-Vorschau.</div>
                                                    <div class="mt-2">
                                                        <span class="ignis-field__label block mb-1">Vorschau</span>
                                                        <img
                                                            src="<?= htmlspecialchars($config['config_value']) ?>"
                                                            alt="Vorschau des Link-Bildes"
                                                            class="max-h-[100px] max-w-[200px] rounded-md border border-[var(--border)] bg-[var(--surface-2)] p-2"
                                                            id="meta_image_preview"
                                                            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22100%22%3E%3Crect fill=%22%23ddd%22 width=%22200%22 height=%22100%22/%3E%3Ctext fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22%3EBild nicht gefunden%3C/text%3E%3C/svg%3E'">
                                                    </div>

                                                <?php elseif ($config['is_editable'] && $config['config_key'] === 'REGISTRATION_MODE'): ?>
                                                    <select
                                                        class="ignis-input"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>">
                                                        <option value="open" <?= $config['config_value'] === 'open' ? 'selected' : '' ?>>Offen (für jeden möglich)</option>
                                                        <option value="code" <?= $config['config_value'] === 'code' ? 'selected' : '' ?>>Mit Code (nur mit Registrierungscode)</option>
                                                        <option value="closed" <?= $config['config_value'] === 'closed' ? 'selected' : '' ?>>Geschlossen (keine Registrierung)</option>
                                                    </select>
                                                    <div class="ignis-field__hint"><?= htmlspecialchars($config['description']) ?></div>

                                                <?php elseif ($config['is_editable'] && $config['config_key'] === 'ENOTF_BZ_UNIT'): ?>
                                                    <select
                                                        class="ignis-input"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>">
                                                        <option value="mg/dl" <?= $config['config_value'] === 'mg/dl' ? 'selected' : '' ?>>mg/dl (Milligramm pro Deziliter)</option>
                                                        <option value="mmol/l" <?= $config['config_value'] === 'mmol/l' ? 'selected' : '' ?>>mmol/l (Millimol pro Liter)</option>
                                                    </select>
                                                    <div class="ignis-field__hint">Blutzuckerwerte werden automatisch umgerechnet (1 mg/dl = 0,0555 mmol/l)</div>

                                                <?php elseif ($config['is_editable']): ?>
                                                    <input
                                                        type="text"
                                                        class="ignis-input"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>"
                                                        value="<?= htmlspecialchars($config['config_value']) ?>">
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
        // Kategorie-Filter: blendet die Karten der anderen Kategorien aus.
        document.querySelectorAll('#categoryFilter button').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#categoryFilter button').forEach(function(b) { b.classList.remove('is-active'); });
                this.classList.add('is-active');
                var cat = this.dataset.category;
                document.querySelectorAll('.config-section').forEach(function(section) {
                    section.hidden = !!cat && section.dataset.configCategory !== cat;
                });
            });
        });

        function updateColorValue(key, value) {
            document.getElementById(key).value = value;
            document.getElementById(key + '_picker').value = value;
        }

        function updateColorPicker(key, value) {
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                document.getElementById(key + '_picker').value = value;
            }
        }

        function updateLogoPreview(value) {
            document.getElementById('logo_preview').src = value;
        }

        function updateMetaImagePreview(value) {
            document.getElementById('meta_image_preview').src = value;
        }

        function toggleApiKeyVisibility() {
            const input = document.getElementById('API_KEY');
            const button = document.getElementById('toggleApiKeyBtn');
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                button.title = 'API-Schlüssel verbergen';
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                button.title = 'API-Schlüssel anzeigen';
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
                        button.title = 'API-Schlüssel anzeigen';

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
