<?php
/**
 * View: Fahrzeug-Login (FireTab)
 *
 * @var array<int,array<string,mixed>> $vehicles
 * @var array<int,array<string,mixed>> $personnel
 * @var bool                            $charLocked
 * @var array<string,mixed>|null        $lockedOperator
 */

use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">

<head>
    <?php include dirname(__DIR__, 4) . '/assets/components/_base/admin/head.php'; ?>
    <!-- CitizenFX: Session-ID an FiveM-Client senden -->
    <script>
    (function() {
        if (navigator.userAgent.includes('CitizenFX')) {
            fetch('<?= BASE_PATH ?>api/character/get-session-id')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.session_id) {
                        var target = (window.parent !== window) ? window.parent : window;
                        target.postMessage({ type: 'ignis_session', session_id: data.session_id }, '*');
                    }
                })
                .catch(function() {});
        }
    })();
    </script>

    <style>
        html::-webkit-scrollbar,
        body::-webkit-scrollbar,
        .sidebar-nav::-webkit-scrollbar { width: 8px; }
        html::-webkit-scrollbar-track,
        body::-webkit-scrollbar-track,
        .sidebar-nav::-webkit-scrollbar-track { background: var(--surface-2); }
        html::-webkit-scrollbar-thumb,
        body::-webkit-scrollbar-thumb,
        .sidebar-nav::-webkit-scrollbar-thumb { background: var(--fill-3); border-radius: 4px; }
        html::-webkit-scrollbar-thumb:hover,
        body::-webkit-scrollbar-thumb:hover,
        .sidebar-nav::-webkit-scrollbar-thumb:hover { background: var(--fill-4); }

        .login-container { max-width: 600px; margin: 100px auto; }
    </style>
</head>

<body data-theme="dark">
    <div class="container mx-auto">
        <div class="login-container">
            <?php if (isset($_SESSION['einsatz_vehicle_id'])): ?>
                <!-- Already logged in -->
                <div class="ignis-card">
                    <div class="ignis-card__body text-center">
                        <h3 class="mb-4">
                            <i class="fa-solid fa-truck text-[var(--info)] mr-2" aria-hidden="true"></i>
                            Angemeldet
                        </h3>
                        <div class="ignis-alert ignis-alert--success">
                            <strong>Fahrzeug:</strong> <?= htmlspecialchars($_SESSION['einsatz_vehicle_name']) ?><br>
                            <strong>Besatzung:</strong> <?= htmlspecialchars($_SESSION['einsatz_operator_name']) ?>
                        </div>
                        <div class="flex flex-col gap-2">
                            <a href="<?= BASE_PATH ?>firetab/list" class="ignis-btn ignis-btn--primary ignis-btn--lg">
                                <i class="fa-solid fa-list mr-2"></i>Zur Einsatzliste
                            </a>
                            <a href="<?= BASE_PATH ?>firetab/login-vehicle?logout=1" class="ignis-btn ignis-btn--secondary ignis-btn--lg">
                                <i class="fa-solid fa-sign-out-alt mr-2"></i>Abmelden
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Login form -->
                <div class="ignis-card">
                    <div class="ignis-card__body">
                        <h3 class="mb-4 text-center">
                            <i class="fa-solid fa-truck mr-2"></i>
                            Fahrzeug-Anmeldung
                        </h3>
                        <p class="mb-4 text-center text-[var(--text-3)]">
                            Bitte melden Sie sich auf einem Fahrzeug an, um Einsätze zu erstellen oder anzuzeigen.
                        </p>

                        <?php Flash::render(); ?>

                        <?php if (empty($vehicles)): ?>
                            <div class="ignis-alert ignis-alert--warning">
                                <i class="fa-solid fa-exclamation-triangle mr-2"></i>
                                Keine Einsatzfahrzeuge verfügbar.
                            </div>
                        <?php elseif (empty($personnel)): ?>
                            <div class="ignis-alert ignis-alert--warning">
                                <i class="fa-solid fa-exclamation-triangle mr-2"></i>
                                Keine Mitarbeiter hinterlegt.
                            </div>
                        <?php else: ?>
                            <form method="post">
                                <div class="mb-4">
                                    <label class="ignis-field__label">
                                        <i class="fa-solid fa-truck mr-1"></i>
                                        Fahrzeug auswählen *
                                    </label>
                                    <select name="vehicle_id" id="vehicleSelect" class="ignis-input" required data-custom-dropdown="true" data-search-threshold="5">
                                        <option value="">-- Bitte Fahrzeug wählen --</option>
                                        <?php foreach ($vehicles as $v): ?>
                                            <option value="<?= (int) $v['id'] ?>">
                                                <?= htmlspecialchars($v['name']) ?>
                                                <?php if (!empty($v['identifier'])): ?>
                                                    (<?= htmlspecialchars($v['identifier']) ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="ignis-field__label">
                                        <i class="fa-solid fa-user mr-1"></i>
                                        Besatzung / Name *
                                    </label>
                                    <?php if ($charLocked && $lockedOperator): ?>
                                        <input type="hidden" name="operator_id" value="<?= (int) $lockedOperator['id'] ?>">
                                        <input type="text" class="ignis-input" value="<?= htmlspecialchars($lockedOperator['fullname']) ?>" readonly>
                                        <small class="form-hint"><i class="fa-solid fa-lock mr-1" aria-hidden="true"></i>Charakter-Sperre aktiv</small>
                                    <?php else: ?>
                                        <select name="operator_id" id="operatorSelect" class="ignis-input" required data-custom-dropdown="true" data-search-threshold="5">
                                            <option value="">-- Bitte Mitarbeiter wählen --</option>
                                            <?php foreach ($personnel as $p): ?>
                                                <option value="<?= (int) $p['id'] ?>">
                                                    <?= htmlspecialchars($p['fullname']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-hint">Wähle deinen Namen aus der Liste</small>
                                    <?php endif; ?>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <button type="submit" class="ignis-btn ignis-btn--primary ignis-btn--lg">
                                        <i class="fa-solid fa-sign-in-alt mr-2"></i>
                                        Anmelden
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                eNOTFCustomDropdown.init();
            });
        } else {
            eNOTFCustomDropdown.init();
        }
    </script>
</body>

</html>
