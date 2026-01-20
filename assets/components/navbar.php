<?php

use App\Auth\Permissions;
use App\Notifications\NotificationManager;

$unreadCount = 0;
$recentNotifications = [];
try {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/database.php';
    }
    if (isset($pdo)) {
        $notificationManager = new NotificationManager($pdo);
        $unreadCount = $notificationManager->getUnreadCount($_SESSION['userid']);
        $recentNotifications = $notificationManager->getAll($_SESSION['userid'], 5);
    }
} catch (Exception $e) {
    error_log("Notification count error: " . $e->getMessage());
}
?>

<style>
    /* #intra-nav {
        background-color: var(--body-bg-darker);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        border-bottom: 2px solid var(--main-color);
    } */

    #intra-nav .nav-icon {
        font-size: 1rem;
        margin-right: 0.5rem;
        color: rgba(255, 255, 255, 0.7);
    }

    #intra-nav .nav-link {
        color: rgba(255, 255, 255, 0.85);
        transition: all 0.2s;
        border-radius: 8px;
        margin: 0 0.15rem;
        padding: 0.5rem 0.75rem;
    }

    #intra-nav .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: var(--white);
    }

    #intra-nav .nav-link:hover .nav-icon {
        color: var(--white);
    }

    #intra-nav .nav-link.active {
        background-color: rgba(255, 255, 255, 0.2);
        color: var(--white);
    }

    #intra-nav .nav-link.active .nav-icon {
        color: var(--white);
    }

    #intra-nav .nav-link.menu-open {
        background-color: rgba(255, 255, 255, 0.15);
        color: var(--white);
    }

    #intra-nav .nav-link.menu-open .nav-icon {
        color: rgba(255, 255, 255, 0.7);
    }

    .mega-menu {
        position: absolute;
        width: 100%;
        left: 0;
        top: 100%;
        background-color: #191919;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        display: none;
        z-index: 1000;
        /* border-top: 1px solid var(--darkgray); */
    }

    .mega-menu.show {
        display: block;
    }

    .mega-menu-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        padding: 2rem;
        max-width: 1400px;
        margin: 0 auto;
    }

    .mega-menu-section h6 {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #fff;
        margin-bottom: 0.75rem;
        font-weight: 600;
    }

    .mega-menu-section a {
        display: block;
        padding: 0.5rem 0;
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        transition: all 0.2s;
        font-size: 0.95rem;
    }

    .mega-menu-section a:hover {
        color: var(--white);
        padding-left: 0.5rem;
    }

    .mega-menu-section a i {
        width: 20px;
        margin-right: 0.5rem;
        color: rgba(255, 255, 255, 0.5);
    }

    .mega-menu-section a:hover i {
        color: var(--main-color);
    }

    #intra-nav .dropdown-menu {
        background-color: var(--body-bg);
        border-bottom: 2px solid var(--darkgray);
        border-radius: 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    }

    #intra-nav .dropdown-item {
        color: rgba(255, 255, 255, 0.85);
        padding: 0.5rem 1rem;
        transition: all 0.2s;
    }

    #intra-nav .dropdown-item:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: var(--white);
    }

    #intra-nav .dropdown-item i {
        width: 20px;
        margin-right: 0.5rem;
        color: rgba(255, 255, 255, 0.5);
    }

    #intra-nav .dropdown-item:hover i {
        color: var(--main-color);
    }

    #intra-nav .dropdown-divider {
        border-color: var(--darkgray);
    }

    .notification-badge {
        position: absolute;
        top: -4px;
        right: -8px;
        min-width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        padding: 0 4px;
    }

    .notification-dropdown {
        min-width: 380px;
        max-width: 400px;
        max-height: 500px;
        overflow-y: auto;
        background-color: var(--body-bg-lighter);
        border: 1px solid var(--darkgray);
    }

    .notification-dropdown .border-bottom {
        border-color: var(--darkgray) !important;
    }

    .notification-item {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: background 0.2s;
        color: rgba(255, 255, 255, 0.85);
        max-width: 100%;
        overflow: hidden;
    }

    .notification-item:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }

    .notification-item.unread {
        background-color: rgba(209, 0, 0, 0.15);
        border-left: 3px solid var(--main-color);
    }

    .notification-item .text-muted {
        color: rgba(255, 255, 255, 0.5) !important;
    }

    .notification-dropdown {
        max-width: 400px;
        min-width: 350px;
    }

    .offcanvas-menu {
        width: 320px !important;
        background-color: var(--body-bg-darker);
        border-left: 1px solid var(--darkgray);
    }

    .offcanvas-menu .offcanvas-header {
        background-color: var(--body-bg-darker);
        border-bottom: 1px solid var(--darkgray);
        color: var(--white);
    }

    .offcanvas-menu .btn-close {
        filter: invert(1);
    }

    .offcanvas-section {
        padding: 1rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .offcanvas-section:last-child {
        border-bottom: none;
    }

    .offcanvas-section-title {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--main-color);
        margin-bottom: 0.5rem;
        padding: 0 1rem;
        font-weight: 600;
    }

    .offcanvas-link {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        transition: all 0.2s;
    }

    .offcanvas-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: var(--white);
    }

    .offcanvas-link i {
        width: 24px;
        margin-right: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
    }

    .offcanvas-link:hover i {
        color: var(--main-color);
    }

    .user-info-header {
        background-color: var(--body-bg-darker);
        color: var(--white);
    }

    .user-info-header small {
        color: rgba(255, 255, 255, 0.6);
    }

    .offcanvas-start {
        background-color: var(--body-bg-darker);
        border-right: 1px solid var(--darkgray);
    }

    .notification-dropdown::-webkit-scrollbar {
        width: 6px;
    }

    .notification-dropdown::-webkit-scrollbar-track {
        background: var(--body-bg-darker);
    }

    .notification-dropdown::-webkit-scrollbar-thumb {
        background: var(--darkgray);
        border-radius: 3px;
    }

    .notification-dropdown::-webkit-scrollbar-thumb:hover {
        background: var(--main-color);
    }

    @media (max-width: 991.98px) {
        .mega-menu-content {
            grid-template-columns: 1fr;
            gap: 1.5rem;
            padding: 1.5rem;
        }

        .notification-dropdown {
            min-width: 320px;
        }
    }

    @media (max-width: 575.98px) {
        .navbar-brand img {
            height: 40px !important;
        }

        .notification-dropdown {
            min-width: 280px;
        }
    }
</style>

<nav class="navbar navbar-expand-lg" id="intra-nav">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand" href="<?= BASE_PATH ?>index.php">
            <img src="<?php echo SYSTEM_LOGO ?>" alt="<?php echo SYSTEM_NAME ?>" style="height:48px;width:auto">
        </a>

        <div class="d-flex align-items-center order-lg-2">
            <div class="dropdown me-2">
                <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="notification-badge badge rounded-pill bg-danger"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end notification-dropdown p-0">
                    <li class="px-3 py-2 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-white">Benachrichtigungen</h6>
                            <?php if ($unreadCount > 0): ?><small class="text-muted"><?= $unreadCount ?> neu</small><?php endif; ?>
                        </div>
                    </li>
                    <?php if (empty($recentNotifications)): ?>
                        <li class="notification-item text-center text-muted py-4">
                            <i class="fa-solid fa-bell-slash mb-2 d-block" style="font-size: 2rem; opacity: 0.3;"></i>
                            <small>Keine Benachrichtigungen</small>
                        </li>
                        <?php else: foreach ($recentNotifications as $n):
                            $isUnread = $n['is_read'] == 0;
                            $dt = new DateTime($n['created_at'], new DateTimeZone('Europe/Berlin'));
                            $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
                            $diff = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->diff($dt);
                            $timeAgo = $diff->invert == 0 ? 'jetzt' : ($diff->days > 0 ? $diff->days . 'd' : ($diff->h > 0 ? $diff->h . 'h' : ($diff->i > 0 ? $diff->i . 'm' : 'jetzt')));
                            $icons = ['antrag' => 'fa-file', 'protokoll' => 'fa-truck-medical', 'dokument' => 'fa-folder-open', 'fire_protocol' => 'fa-fire', 'system' => 'fa-gears'];
                            $icon = $icons[$n['type'] ?? ''] ?? 'fa-bell';
                        ?>
                            <li><a href="<?= htmlspecialchars($n['link'] ?: BASE_PATH . 'benachrichtigungen/index.php') ?>" class="notification-item d-flex text-decoration-none <?= $isUnread ? 'unread' : '' ?>">
                                    <div class="flex-shrink-0"><i class="fa-solid <?= $icon ?>"></i></div>
                                    <div class="flex-grow-1 ms-2 min-w-0" style="overflow: hidden;">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <strong class="<?= $isUnread ? 'text-white' : '' ?> text-truncate" style="font-size: 0.9rem;"><?= htmlspecialchars($n['title']) ?></strong>
                                            <small class="text-muted ms-2 flex-shrink-0"><?= $timeAgo ?></small>
                                        </div>
                                        <?php if ($n['message']): ?><small class="text-muted d-block" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($n['message']) ?></small><?php endif; ?>
                                    </div>
                                    <?php if ($isUnread): ?>
                                        <button class="btn btn-sm btn-link p-0 ms-2 mark-as-read-btn" data-notification-id="<?= $n['id'] ?>" title="Als gelesen markieren" onclick="event.preventDefault(); event.stopPropagation();">
                                            <i class="fa-solid fa-check" style="color: var(--main-color);"></i>
                                        </button>
                                    <?php endif; ?>
                                </a></li>
                    <?php endforeach;
                    endif; ?>
                    <li class="border-top"><a class="dropdown-item text-center py-2 small" href="<?= BASE_PATH ?>benachrichtigungen/index.php">Alle anzeigen</a></li>
                </ul>
            </div>

            <div class="dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                    <i class="fa-solid fa-user-circle me-2"></i>
                    <span class="d-none d-md-inline"><?= $_SESSION['cirs_username'] ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-2 border-bottom user-info-header">
                        <small class="d-block">Angemeldet als</small>
                        <strong><?= $_SESSION['cirs_username'] ?></strong>
                        <div class="mt-1"><span class="badge text-bg-<?= $_SESSION['role_color'] ?>"><?= $_SESSION['role_name'] ?></span></div>
                    </li>
                    <li><a class="dropdown-item" href="<?= BASE_PATH ?>logout.php"><i class="fa-solid fa-right-from-bracket"></i> Abmelden</a></li>
                </ul>
            </div>

            <button class="navbar-toggler border-0 ms-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse order-lg-1" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= BASE_PATH ?>index.php" data-page="dashboard"><i class="fa-solid fa-home nav-icon"></i><span>Dashboard</span></a></li>

                <?php if (Permissions::check(['admin', 'users.view', 'personnel.view'])): ?>
                    <li class="nav-item dropdown position-static">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-page="personal">
                            <i class="fa-solid fa-users nav-icon"></i><span>Personal</span>
                        </a>
                        <div class="dropdown-menu mega-menu w-100">
                            <div class="mega-menu-content">
                                <?php if (Permissions::check(['admin', 'users.view'])): ?>
                                    <div class="mega-menu-section">
                                        <h6>Benutzer</h6>
                                        <ul class="list-unstyled">
                                            <li><a href="<?= BASE_PATH ?>benutzer/list.php"><i class="fa-solid fa-list"></i> Übersicht</a></li>
                                            <?php if (Permissions::check(['admin', 'users.create'])): ?>
                                                <li><a href="<?= BASE_PATH ?>benutzer/registration-codes.php"><i class="fa-solid fa-key"></i> Registrierungscodes</a></li>
                                            <?php endif; ?>
                                            <li><a href="<?= BASE_PATH ?>benutzer/rollen/index.php"><i class="fa-solid fa-user-tag"></i> Rollenverwaltung</a></li>
                                            <?php if (Permissions::check(['admin', 'audit.view'])): ?>
                                                <li><a href="<?= BASE_PATH ?>benutzer/auditlog.php"><i class="fa-solid fa-history"></i> Audit-Log</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif;
                                if (Permissions::check(['admin', 'personnel.view'])): ?>
                                    <div class="mega-menu-section">
                                        <h6>Mitarbeiter</h6>
                                        <ul class="list-unstyled">
                                            <li><a href="<?= BASE_PATH ?>mitarbeiter/list.php"><i class="fa-solid fa-list"></i> Übersicht</a></li>
                                            <?php if (Permissions::check(['admin', 'personnel.edit'])): ?>
                                                <li><a href="<?= BASE_PATH ?>mitarbeiter/create.php"><i class="fa-solid fa-plus"></i> Erstellen</a></li>
                                            <?php endif;
                                            if (Permissions::check(['admin', 'application.view'])): ?>
                                                <li><a href="<?= BASE_PATH ?>antrag/admin/list.php"><i class="fa-solid fa-clipboard-check"></i> Anträge bearbeiten</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endif; ?>

                <li class="nav-item dropdown position-static">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-page="protokolle">
                        <i class="fa-solid fa-file-medical nav-icon"></i><span>Protokolle</span>
                    </a>
                    <div class="dropdown-menu mega-menu w-100">
                        <div class="mega-menu-content">
                            <div class="mega-menu-section">
                                <h6>eNOTF</h6>
                                <ul class="list-unstyled">
                                    <li><a href="<?= BASE_PATH ?>enotf/" target="_blank"><i class="fa-solid fa-external-link"></i> eNOTF öffnen</a></li>
                                    <?php if (Permissions::check(['admin', 'edivi.view'])): ?>
                                        <li><a href="<?= BASE_PATH ?>enotf/admin/list.php"><i class="fa-solid fa-clipboard-list"></i> Prüfliste</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php if (Permissions::check(['admin', 'manv.manage'])): ?>
                                <div class="mega-menu-section">
                                    <h6>MANV-Board</h6>
                                    <ul class="list-unstyled">
                                        <li><a href="<?= BASE_PATH ?>manv/index.php"><i class="fa-solid fa-house-medical"></i> MANV-Board</a></li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <div class="mega-menu-section">
                                <h6>FW Einsatzprotokolle</h6>
                                <ul class="list-unstyled">
                                    <li><a href="<?= BASE_PATH ?>einsatz/create.php" target="_blank"><i class="fa-solid fa-plus"></i> fireTab öffnen</a></li>
                                    <?php if (Permissions::check(['admin', 'fire.incident.qm'])): ?>
                                        <li><a href="<?= BASE_PATH ?>einsatz/admin/list.php"><i class="fa-solid fa-list-check"></i> Qualitätsmanagement</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </li>

                <li class="nav-item"><a class="nav-link" href="<?= BASE_PATH ?>wissensdb/index.php" data-page="wissensdb"><i class="fa-solid fa-book-medical nav-icon"></i><span>Wissensdatenbank</span></a></li>

                <?php if (Permissions::check(['admin', 'personnel.view', 'vehicles.view', 'edivi.view', 'dashboard.manage'])): ?>
                    <li class="nav-item dropdown position-static">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-page="settings">
                            <i class="fa-solid fa-sliders nav-icon"></i><span>Einstellungen</span>
                        </a>
                        <div class="dropdown-menu mega-menu w-100">
                            <div class="mega-menu-content">
                                <?php if (Permissions::check(['admin', 'personnel.view'])): ?>
                                    <div class="mega-menu-section">
                                        <h6>Personal</h6>
                                        <ul class="list-unstyled">
                                            <li><a href="<?= BASE_PATH ?>settings/personal/dienstgrade/index.php"><i class="fa-solid fa-medal"></i> Dienstgrade</a></li>
                                            <li><a href="<?= BASE_PATH ?>settings/personal/qualifw/index.php"><i class="fa-solid fa-fire"></i> FW Qualifikationen</a></li>
                                            <li><a href="<?= BASE_PATH ?>settings/personal/qualird/index.php"><i class="fa-solid fa-truck-medical"></i> RD Qualifikationen</a></li>
                                            <li><a href="<?= BASE_PATH ?>settings/personal/qualifd/index.php"><i class="fa-solid fa-user-graduate"></i> Fachdienste</a></li>
                                            <?php if (Permissions::check(['admin'])): ?>
                                                <li><a href="<?= BASE_PATH ?>settings/documents/templates.php"><i class="fa-solid fa-file-lines"></i> Dokumente</a></li>
                                                <li><a href="<?= BASE_PATH ?>settings/antrag/list.php"><i class="fa-solid fa-clipboard"></i> Antragstypen</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <?php if (Permissions::check(['admin', 'vehicles.view'])): ?>
                                    <div class="mega-menu-section">
                                        <h6>Fahrzeuge</h6>
                                        <ul class="list-unstyled">
                                            <li><a href="<?= BASE_PATH ?>settings/fahrzeuge/fahrzeuge/index.php"><i class="fa-solid fa-truck"></i> Fahrzeuge bearbeiten</a></li>
                                            <li><a href="<?= BASE_PATH ?>settings/fahrzeuge/beladelisten/index.php"><i class="fa-solid fa-list-check"></i> Beladelisten</a></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <?php if (Permissions::check(['admin', 'edivi.view'])): ?>
                                    <div class="mega-menu-section">
                                        <h6>eNOTF</h6>
                                        <ul class="list-unstyled">
                                            <li><a href="<?= BASE_PATH ?>settings/pois/index.php"><i class="fa-solid fa-map-marker-alt"></i> POIs</a></li>
                                            <li><a href="<?= BASE_PATH ?>settings/medikamente/index.php"><i class="fa-solid fa-pills"></i> Medikamente</a></li>
                                            <li><a href="<?= BASE_PATH ?>settings/enotf/index.php"><i class="fa-solid fa-link"></i> Schnellzugriff</a></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <div class="mega-menu-section">
                                    <h6>System</h6>
                                    <ul class="list-unstyled">
                                        <?php if (Permissions::check(['admin', 'dashboard.manage'])): ?>
                                            <li><a href="<?= BASE_PATH ?>settings/dashboard/index.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a></li>
                                        <?php endif; ?>
                                        <?php if (Permissions::check(['admin'])): ?>
                                            <li><a href="<?= BASE_PATH ?>settings/system/config.php"><i class="fa-solid fa-gear"></i> Konfiguration</a></li>
                                            <li><a href="<?= BASE_PATH ?>settings/system/index.php"><i class="fa-solid fa-download"></i> Updater</a></li>
                                            <li><a href="<?= BASE_PATH ?>settings/system/telemetry.php"><i class="fa-solid fa-wifi"></i> Telemetrie</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php include __DIR__ . '/global-announcements.php'; ?>

<div class="offcanvas offcanvas-start offcanvas-menu" tabindex="-1" id="mobileMenu">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Menü</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <a href="<?= BASE_PATH ?>index.php" class="offcanvas-link" data-page="dashboard"><i class="fa-solid fa-home"></i> Dashboard</a>
        <?php if (Permissions::check(['admin', 'users.view'])): ?>
            <div class="offcanvas-section">
                <div class="offcanvas-section-title">Benutzer</div>
                <a href="<?= BASE_PATH ?>benutzer/list.php" class="offcanvas-link"><i class="fa-solid fa-list"></i> Übersicht</a>
                <?php if (Permissions::check(['admin', 'users.create'])): ?>
                    <a href="<?= BASE_PATH ?>benutzer/registration-codes.php" class="offcanvas-link"><i class="fa-solid fa-key"></i> Registrierungscodes</a>
                <?php endif; ?>
                <a href="<?= BASE_PATH ?>benutzer/rollen/index.php" class="offcanvas-link"><i class="fa-solid fa-user-tag"></i> Rollenverwaltung</a>
                <?php if (Permissions::check(['admin', 'audit.view'])): ?>
                    <a href="<?= BASE_PATH ?>benutzer/auditlog.php" class="offcanvas-link"><i class="fa-solid fa-history"></i> Audit-Log</a>
                <?php endif; ?>
            </div>
        <?php endif;
        if (Permissions::check(['admin', 'personnel.view'])): ?>
            <div class="offcanvas-section">
                <div class="offcanvas-section-title">Mitarbeiter</div>
                <a href="<?= BASE_PATH ?>mitarbeiter/list.php" class="offcanvas-link"><i class="fa-solid fa-list"></i> Übersicht</a>
                <?php if (Permissions::check(['admin', 'personnel.edit'])): ?>
                    <a href="<?= BASE_PATH ?>mitarbeiter/create.php" class="offcanvas-link"><i class="fa-solid fa-plus"></i> Erstellen</a>
                <?php endif;
                if (Permissions::check(['admin', 'application.view'])): ?>
                    <a href="<?= BASE_PATH ?>antrag/admin/list.php" class="offcanvas-link"><i class="fa-solid fa-clipboard-check"></i> Anträge</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="offcanvas-section">
            <div class="offcanvas-section-title">Protokolle</div>
            <a href="<?= BASE_PATH ?>enotf/" target="_blank" class="offcanvas-link"><i class="fa-solid fa-external-link"></i> eNOTF öffnen</a>
            <?php if (Permissions::check(['admin', 'edivi.view'])): ?>
                <a href="<?= BASE_PATH ?>enotf/admin/list.php" class="offcanvas-link"><i class="fa-solid fa-clipboard-list"></i> Prüfliste</a>
            <?php endif; ?>
            <?php if (Permissions::check(['admin', 'manv.manage'])): ?>
                <a href="<?= BASE_PATH ?>manv/index.php" class="offcanvas-link"><i class="fa-solid fa-house-medical"></i> MANV-Board</a>
            <?php endif; ?>
            <a href="<?= BASE_PATH ?>einsatz/create.php" class="offcanvas-link" target="_blank"><i class="fa-solid fa-plus"></i> Einsatz erstellen</a>
            <?php if (Permissions::check(['admin', 'fire.incident.qm'])): ?>
                <a href="<?= BASE_PATH ?>einsatz/admin/list.php" class="offcanvas-link"><i class="fa-solid fa-list-check"></i> Qualitätsmanagement</a>
            <?php endif; ?>
        </div>
        <a href="<?= BASE_PATH ?>wissensdb/index.php" class="offcanvas-link" data-page="wissensdb"><i class="fa-solid fa-book-medical"></i> Wissensdatenbank</a>
        <?php if (Permissions::check(['admin', 'personnel.view', 'vehicles.view', 'edivi.view', 'dashboard.manage'])): ?>
            <div class="offcanvas-section">
                <div class="offcanvas-section-title">Einstellungen</div>
                <?php if (Permissions::check(['admin', 'personnel.view'])): ?>
                    <a href="<?= BASE_PATH ?>settings/personal/dienstgrade/index.php" class="offcanvas-link"><i class="fa-solid fa-medal"></i> Dienstgrade</a>
                    <a href="<?= BASE_PATH ?>settings/personal/qualifw/index.php" class="offcanvas-link"><i class="fa-solid fa-fire"></i> FW Qualifikationen</a>
                    <a href="<?= BASE_PATH ?>settings/personal/qualird/index.php" class="offcanvas-link"><i class="fa-solid fa-truck-medical"></i> RD Qualifikationen</a>
                    <a href="<?= BASE_PATH ?>settings/personal/qualifd/index.php" class="offcanvas-link"><i class="fa-solid fa-user-graduate"></i> Fachdienste</a>
                    <?php if (Permissions::check(['admin'])): ?>
                        <a href="<?= BASE_PATH ?>settings/documents/templates.php" class="offcanvas-link"><i class="fa-solid fa-file-lines"></i> Dokumente</a>
                        <a href="<?= BASE_PATH ?>settings/antrag/list.php" class="offcanvas-link"><i class="fa-solid fa-clipboard"></i> Antragstypen</a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (Permissions::check(['admin', 'vehicles.view'])): ?>
                    <a href="<?= BASE_PATH ?>settings/fahrzeuge/fahrzeuge/index.php" class="offcanvas-link"><i class="fa-solid fa-truck"></i> Fahrzeuge</a>
                    <a href="<?= BASE_PATH ?>settings/fahrzeuge/beladelisten/index.php" class="offcanvas-link"><i class="fa-solid fa-list-check"></i> Beladelisten</a>
                <?php endif; ?>
                <?php if (Permissions::check(['admin', 'edivi.view'])): ?>
                    <a href="<?= BASE_PATH ?>settings/pois/index.php" class="offcanvas-link"><i class="fa-solid fa-map-marker-alt"></i> POIs</a>
                    <a href="<?= BASE_PATH ?>settings/medikamente/index.php" class="offcanvas-link"><i class="fa-solid fa-pills"></i> Medikamente</a>
                    <a href="<?= BASE_PATH ?>settings/enotf/index.php" class="offcanvas-link"><i class="fa-solid fa-link"></i> Schnellzugriff</a>
                <?php endif; ?>
                <?php if (Permissions::check(['admin', 'dashboard.manage'])): ?>
                    <a href="<?= BASE_PATH ?>settings/dashboard/index.php" class="offcanvas-link"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
                <?php endif; ?>
                <?php if (Permissions::check(['admin'])): ?>
                    <a href="<?= BASE_PATH ?>settings/system/config.php" class="offcanvas-link"><i class="fa-solid fa-gear"></i> Konfiguration</a>
                    <a href="<?= BASE_PATH ?>settings/system/index.php" class="offcanvas-link"><i class="fa-solid fa-download"></i> Updater</a>
                    <a href="<?= BASE_PATH ?>settings/system/telemetry.php" class="offcanvas-link"><i class="fa-solid fa-wifi"></i> Telemetrie</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    $(document).ready(function() {
        var currentPage = $("body").data("page");

        // Mapping von Unterseiten zu Hauptkategorien
        var pageMapping = {
            'benutzer': 'personal',
            'mitarbeiter': 'personal',
            'edivi': 'protokolle',
            'settings': 'settings'
        };

        $(".nav-link").removeClass("active");

        // Direkte Übereinstimmung
        $(".nav-link[data-page='" + currentPage + "']").addClass("active");

        // Wenn die Seite zu einer übergeordneten Kategorie gehört
        if (pageMapping[currentPage]) {
            $(".nav-link[data-page='" + pageMapping[currentPage] + "']").addClass("active");
        }

        // Handle mega menu clicks
        $('.nav-item.dropdown .nav-link.dropdown-toggle').on('click', function(e) {
            const $dropdown = $(this).closest('.dropdown');
            const $megaMenu = $dropdown.find('.mega-menu');

            if ($megaMenu.length > 0) {
                e.preventDefault();
                e.stopPropagation();

                // Toggle the mega menu
                if ($megaMenu.hasClass('show')) {
                    $megaMenu.removeClass('show');
                    $(this).removeClass('menu-open');
                } else {
                    // Close all other mega menus
                    $('.mega-menu').removeClass('show');
                    $('.nav-link.dropdown-toggle').removeClass('menu-open');

                    // Open this mega menu
                    $megaMenu.addClass('show');
                    $(this).addClass('menu-open');
                }
            }
        });

        // Close mega menu when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.nav-item.dropdown').length) {
                $('.mega-menu').removeClass('show');
                $('.nav-link.dropdown-toggle').removeClass('menu-open');
            }
        });

        // Prevent mega menu from closing when clicking inside it
        $('.mega-menu').on('click', function(e) {
            e.stopPropagation();
        });

        $('.mark-as-read-btn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const notificationId = $(this).data('notification-id');
            const $button = $(this);
            const $item = $button.closest('.notification-item');

            fetch('<?= BASE_PATH ?>benachrichtigungen/mark-read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: notificationId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        $item.removeClass('unread');
                        $button.fadeOut(200);
                        const $badge = $('.notification-badge');
                        const currentCount = parseInt($badge.text()) || 0;
                        const newCount = Math.max(0, currentCount - 1);
                        if (newCount > 0) {
                            $badge.text(newCount > 9 ? '9+' : newCount);
                        } else {
                            $badge.remove();
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
        });
    });

    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));
</script>