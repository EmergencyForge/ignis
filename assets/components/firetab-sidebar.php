<?php
/**
 * Einsatz (fireTab) Sidebar Component
 *
 * Required variables:
 *   $einsatzActivePage (string) - 'create', 'list', 'statusmeldungen', 'view', 'admin'
 *
 * Optional variables:
 *   $einsatzExtraNav (string) - Additional HTML inserted into nav (for view.php tabs)
 */

use App\Auth\Permissions;

$einsatzActivePage = $einsatzActivePage ?? '';
$einsatzExtraNav = $einsatzExtraNav ?? '';
?>
<button type="button" class="einsatz-burger" data-einsatz-menu aria-label="Menü" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
<div class="einsatz-menu-bg" data-einsatz-menu-bg hidden></div>
<script>
    // Jeder Knopf mit data-einsatz-menu klappt die Leiste auf und zu, auch der in der Atemschutz-Kopfzeile
    (function () {
        var bg = document.querySelector('[data-einsatz-menu-bg]');
        function toggle(open) {
            document.body.classList.toggle('einsatz-menu-open', open);
            bg.hidden = !open;
            document.querySelectorAll('[data-einsatz-menu]').forEach(function (b) { b.setAttribute('aria-expanded', String(open)); });
        }
        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-einsatz-menu]')) toggle(!document.body.classList.contains('einsatz-menu-open'));
            else if (e.target === bg) toggle(false);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !bg.hidden) toggle(false);
        });
    })();
</script>
<div class="einsatz-sidebar">
    <div class="einsatz-sidebar-logo">
        <img src="<?= systemLogoUrl() ?>" alt="<?= htmlspecialchars((string) SYSTEM_NAME, ENT_QUOTES) ?>">
    </div>

    <?php if (isset($_SESSION['einsatz_vehicle_name'])): ?>
        <div class="einsatz-sidebar-vehicle">
            <div class="einsatz-sidebar-vehicle-icon">
                <i class="fas fa-truck"></i>
            </div>
            <div class="einsatz-sidebar-vehicle-info">
                <span class="einsatz-sidebar-vehicle-name"><?= htmlspecialchars($_SESSION['einsatz_vehicle_name']) ?></span>
                <?php if (isset($_SESSION['einsatz_operator_name'])): ?>
                    <span class="einsatz-sidebar-operator">
                        <i class="fas fa-user mr-1"></i><?= htmlspecialchars($_SESSION['einsatz_operator_name']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <nav class="einsatz-sidebar-nav">
        <a href="<?= BASE_PATH ?>firetab/create" class="sidebar-link <?= $einsatzActivePage === 'create' ? 'active' : '' ?>">
            <i class="fa-solid fa-plus"></i><span>Neuer Einsatz</span>
        </a>
        <a href="<?= BASE_PATH ?>firetab/list" class="sidebar-link <?= $einsatzActivePage === 'list' ? 'active' : '' ?>">
            <i class="fa-solid fa-list"></i><span>Meine Einsätze</span>
        </a>
        <?php if (isset($_SESSION['einsatz_vehicle_id'])): ?>
            <a href="<?= BASE_PATH ?>firetab/status-reports" class="sidebar-link <?= $einsatzActivePage === 'statusmeldungen' ? 'active' : '' ?>">
                <i class="fa-solid fa-signal"></i><span>Statusmeldungen</span>
            </a>
        <?php endif; ?>
        <?php if (app(\App\Plugins\PluginLoader::class)->isActive('logbook')): ?>
        <a href="<?= BASE_PATH ?>firetab/logbook" class="sidebar-link <?= $einsatzActivePage === 'fahrtenbuch' ? 'active' : '' ?>">
            <i class="fa-solid fa-book"></i><span>Fahrtenbuch</span>
        </a>
        <?php endif; ?>

        <?php if (Permissions::check(['admin', 'fire.incident.qm'])): ?>
            <span class="einsatz-sidebar-section">Verwaltung</span>
            <a href="<?= BASE_PATH ?>firetab/admin/list" class="sidebar-link <?= $einsatzActivePage === 'admin' ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-alt"></i><span>Alle Einsätze</span>
            </a>
        <?php endif; ?>

        <?= $einsatzExtraNav ?>
    </nav>

    <div class="einsatz-sidebar-bottom">
        <a href="<?= BASE_PATH ?>firetab/login-vehicle?logout=1" class="sidebar-link">
            <i class="fa-solid fa-right-from-bracket"></i><span>Abmelden</span>
        </a>
    </div>
</div>
