<?php
/**
 * Einsatz (fireTab) Sidebar Component
 *
 * Required variables:
 *   $einsatzActivePage (string) - 'create', 'list', 'statusmeldungen', 'asu', 'view', 'admin'
 *
 * Optional variables:
 *   $einsatzExtraNav (string) - Additional HTML inserted into nav (for view.php tabs)
 */

use App\Auth\Permissions;

$einsatzActivePage = $einsatzActivePage ?? '';
$einsatzExtraNav = $einsatzExtraNav ?? '';
?>
<div class="einsatz-sidebar">
    <div class="einsatz-sidebar-logo">
        <img src="<?= SYSTEM_LOGO ?>" alt="<?= SYSTEM_NAME ?>">
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
                        <i class="fas fa-user me-1"></i><?= htmlspecialchars($_SESSION['einsatz_operator_name']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <nav class="einsatz-sidebar-nav">
        <a href="<?= BASE_PATH ?>einsatz/create.php" class="sidebar-link <?= $einsatzActivePage === 'create' ? 'active' : '' ?>">
            <i class="fa-solid fa-plus"></i><span>Neuer Einsatz</span>
        </a>
        <a href="<?= BASE_PATH ?>einsatz/list.php" class="sidebar-link <?= $einsatzActivePage === 'list' ? 'active' : '' ?>">
            <i class="fa-solid fa-list"></i><span>Meine Einsätze</span>
        </a>
        <?php if (isset($_SESSION['einsatz_vehicle_id'])): ?>
            <a href="<?= BASE_PATH ?>einsatz/statusmeldungen.php" class="sidebar-link <?= $einsatzActivePage === 'statusmeldungen' ? 'active' : '' ?>">
                <i class="fa-solid fa-signal"></i><span>Statusmeldungen</span>
            </a>
        <?php endif; ?>
        <a href="<?= BASE_PATH ?>einsatz/asu.php" class="sidebar-link <?= $einsatzActivePage === 'asu' ? 'active' : '' ?>">
            <i class="fa-solid fa-mask-ventilator"></i><span>AS-Überwachung</span>
        </a>

        <?php if (Permissions::check(['admin', 'fire.incident.qm'])): ?>
            <span class="einsatz-sidebar-section">Verwaltung</span>
            <a href="<?= BASE_PATH ?>einsatz/admin/list.php" class="sidebar-link <?= $einsatzActivePage === 'admin' ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-alt"></i><span>Alle Einsätze</span>
            </a>
        <?php endif; ?>

        <?= $einsatzExtraNav ?>
    </nav>

    <div class="einsatz-sidebar-bottom">
        <a href="<?= BASE_PATH ?>einsatz/login-fahrzeug.php?logout=1" class="sidebar-link">
            <i class="fa-solid fa-right-from-bracket"></i><span>Abmelden</span>
        </a>
    </div>
</div>
