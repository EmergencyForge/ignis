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

// Generate initials from username
$sidebarUsername = $_SESSION['cirs_username'] ?? 'U';
$sidebarInitials = strtoupper(substr($sidebarUsername, 0, 2));

// Bootstrap color name to hex mapping for role dot
$roleColorMap = [
    'primary'   => '#0d6efd',
    'secondary' => '#6c757d',
    'success'   => '#198754',
    'danger'    => '#dc3545',
    'warning'   => '#ffc107',
    'info'      => '#0dcaf0',
    'light'     => '#f8f9fa',
    'dark'      => '#212529',
];
$roleColor = $_SESSION['role_color'] ?? 'secondary';
$roleHex = $roleColorMap[$roleColor] ?? '#6c757d';
?>

<style>
    /* ========================================
       SIDEBAR STYLES
       ======================================== */
    .intra-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: var(--sidebar-bg);
        z-index: 1040;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: transform 0.3s ease;
    }

    /* Logo */
    .sidebar-logo {
        padding: 1rem 1.25rem 0.5rem;
        flex-shrink: 0;
    }
    .sidebar-logo img {
        height: 38px;
        width: auto;
    }

    /* User Info */
    .sidebar-user {
        display: flex;
        align-items: center;
        padding: 0.45rem 0.75rem;
        flex-shrink: 0;
        margin: 0.4rem 0.6rem 0.6rem;
        background: #2c2c34;
        border-radius: 10px;
    }
    .sidebar-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--sidebar-avatar-bg);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.8rem;
        flex-shrink: 0;
        margin-right: 0.6rem;
        letter-spacing: 0.5px;
    }
    .sidebar-user-info {
        overflow: hidden;
        min-width: 0;
    }
    .sidebar-username {
        color: #fff;
        font-weight: 500;
        display: block;
        font-size: 0.85rem;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sidebar-role {
        color: var(--sidebar-role-text);
        font-size: 0.75rem;
        display: flex;
        align-items: center;
    }
    .sidebar-role-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
        flex-shrink: 0;
    }

    /* Navigation */
    .sidebar-nav {
        flex: 1;
        min-height: 0;
        padding: 0.25rem 0;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--darkgray) transparent;
    }
    .sidebar-nav::-webkit-scrollbar {
        width: 4px;
    }
    .sidebar-nav::-webkit-scrollbar-track {
        background: transparent;
    }
    .sidebar-nav::-webkit-scrollbar-thumb {
        background: var(--darkgray);
        border-radius: 2px;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        padding: 0.55rem 1.25rem;
        color: var(--sidebar-link-color);
        text-decoration: none;
        transition: background 0.15s ease;
        margin: 0.2rem 0.5rem;
        border-radius: 8px;
        font-size: 0.9rem;
        position: relative;
    }
    .sidebar-link:hover,
    .sidebar-link.active {
        background: var(--sidebar-hover-bg);
        color: #fff;
        text-decoration: none;
    }
    .sidebar-link i:first-child {
        width: 22px;
        color: var(--sidebar-icon-color);
        margin-right: 0.75rem;
        text-align: center;
        font-size: 0.95rem;
        flex-shrink: 0;
    }
    .sidebar-link:hover i:first-child,
    .sidebar-link.active i:first-child {
        color: #fff;
    }

    /* Chevron for toggleable items */
    .sidebar-chevron {
        margin-left: auto;
        font-size: 0.65rem;
        transition: transform 0.25s ease;
        color: var(--sidebar-icon-color);
    }
    .sidebar-toggle.open .sidebar-chevron {
        transform: rotate(180deg);
    }

    /* Submenu */
    .sidebar-submenu {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    .sidebar-submenu.open {
        max-height: 600px;
    }

    .sidebar-section-title {
        display: block;
        padding: 0.5rem 0.75rem 0.15rem 1.75rem;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-dimmed);
        font-weight: 500;
        margin-top: 0.15rem;
    }
    .sidebar-section-title:first-child {
        padding-top: 0.3rem;
        margin-top: 0;
    }

    .sidebar-sublink {
        display: flex;
        align-items: center;
        padding: 0.35rem 0.75rem 0.35rem 1.75rem;
        color: var(--sidebar-icon-color);
        text-decoration: none;
        font-size: 0.82rem;
        transition: all 0.15s ease;
        margin: 1px 0.5rem;
        border-radius: 8px;
        position: relative;
    }
    .sidebar-sublink:hover {
        color: #fff;
        background: var(--sidebar-hover-bg);
        text-decoration: none;
    }
    .sidebar-sublink.active-sub {
        color: #fff;
        background: var(--sidebar-hover-bg);
    }
    .sidebar-sublink.active-sub::before {
        content: '';
        position: absolute;
        left: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: var(--main-color);
    }
    .sidebar-sublink i {
        width: 16px;
        margin-right: 0.45rem;
        text-align: center;
        font-size: 0.75rem;
    }

    /* Bottom section */
    .sidebar-bottom {
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        padding: 0.5rem 0;
        flex-shrink: 0;
    }

    .sidebar-notification-badge {
        background: var(--main-color);
        color: #fff;
        font-size: 0.65rem;
        font-weight: 600;
        padding: 0.15rem 0.45rem;
        border-radius: 10px;
        margin-left: auto;
        min-width: 20px;
        text-align: center;
        line-height: 1.2;
    }

    /* ========================================
       MOBILE TOPBAR
       ======================================== */
    .sidebar-mobile-topbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 56px;
        background: var(--sidebar-bg);
        z-index: 1030;
        display: none;
        align-items: center;
        padding: 0 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }
    .sidebar-mobile-topbar img {
        height: 32px;
        width: auto;
    }

    .sidebar-toggle-btn {
        background: none;
        border: none;
        color: #fff;
        font-size: 1.3rem;
        padding: 0.5rem;
        margin-right: 0.75rem;
        cursor: pointer;
        border-radius: 8px;
        transition: background 0.15s;
    }
    .sidebar-toggle-btn:hover {
        background: var(--sidebar-hover-bg);
    }

    .sidebar-mobile-right {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .sidebar-mobile-right .sidebar-notification-badge {
        font-size: 0.6rem;
        padding: 0.1rem 0.35rem;
    }

    /* Overlay */
    .sidebar-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1035;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }
    .sidebar-overlay.active {
        opacity: 1;
        pointer-events: auto;
    }

    /* ========================================
       RESPONSIVE
       ======================================== */
    @media (max-width: 991.98px) {
        .intra-sidebar {
            transform: translateX(-100%);
            z-index: 1045;
        }
        .intra-sidebar.open {
            transform: translateX(0);
        }
        .sidebar-mobile-topbar {
            display: flex;
        }
    }

    @media (min-width: 992px) {
        .sidebar-mobile-topbar {
            display: none !important;
        }
        .sidebar-overlay {
            display: none !important;
        }
    }
</style>

<!-- ===================== -->
<!-- SIDEBAR (Desktop)     -->
<!-- ===================== -->
<aside class="intra-sidebar" id="intraSidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <a href="<?= BASE_PATH ?>index.php">
            <img src="<?= SYSTEM_LOGO ?>" alt="<?= SYSTEM_NAME ?>">
        </a>
    </div>

    <!-- User Info -->
    <div class="sidebar-user">
        <div class="sidebar-avatar"><?= htmlspecialchars($sidebarInitials) ?></div>
        <div class="sidebar-user-info">
            <span class="sidebar-username"><?= htmlspecialchars($sidebarUsername) ?></span>
            <span class="sidebar-role">
                <span class="sidebar-role-dot" style="background:<?= $roleHex ?>"></span>
                <?= htmlspecialchars($_SESSION['role_name'] ?? 'Benutzer') ?>
            </span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <a href="<?= BASE_PATH ?>index.php" class="sidebar-link" data-page="dashboard">
            <i class="fa-solid fa-home"></i><span>Dashboard</span>
        </a>

        <!-- Personal -->
        <?php if (Permissions::check(['admin', 'users.view', 'personnel.view'])): ?>
            <a href="#" class="sidebar-link sidebar-toggle" data-page="personal" data-menu="personal">
                <i class="fa-solid fa-users"></i><span>Personal</span>
                <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
            </a>
            <div class="sidebar-submenu" data-submenu="personal">
                <?php if (Permissions::check(['admin', 'users.view'])): ?>
                    <span class="sidebar-section-title">Benutzer</span>
                    <a href="<?= BASE_PATH ?>benutzer/list.php" class="sidebar-sublink"><i class="fa-solid fa-list"></i> Übersicht</a>
                    <?php if (Permissions::check(['admin', 'users.create'])): ?>
                        <a href="<?= BASE_PATH ?>benutzer/registration-codes.php" class="sidebar-sublink"><i class="fa-solid fa-key"></i> Registrierungscodes</a>
                    <?php endif; ?>
                    <a href="<?= BASE_PATH ?>benutzer/rollen/index.php" class="sidebar-sublink"><i class="fa-solid fa-user-tag"></i> Rollenverwaltung</a>
                    <?php if (Permissions::check(['admin', 'audit.view'])): ?>
                        <a href="<?= BASE_PATH ?>benutzer/auditlog.php" class="sidebar-sublink"><i class="fa-solid fa-history"></i> Audit-Log</a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (Permissions::check(['admin', 'personnel.view'])): ?>
                    <span class="sidebar-section-title">Mitarbeiter</span>
                    <a href="<?= BASE_PATH ?>mitarbeiter/list.php" class="sidebar-sublink"><i class="fa-solid fa-list"></i> Übersicht</a>
                    <?php if (Permissions::check(['admin', 'personnel.edit'])): ?>
                        <a href="<?= BASE_PATH ?>mitarbeiter/create.php" class="sidebar-sublink"><i class="fa-solid fa-plus"></i> Erstellen</a>
                    <?php endif; ?>
                    <?php if (Permissions::check(['admin', 'application.view'])): ?>
                        <a href="<?= BASE_PATH ?>antrag/admin/list.php" class="sidebar-sublink"><i class="fa-solid fa-clipboard-check"></i> Anträge bearbeiten</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Protokolle -->
        <a href="#" class="sidebar-link sidebar-toggle" data-page="protokolle" data-menu="protokolle">
            <i class="fa-solid fa-file-medical"></i><span>Protokolle</span>
            <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
        </a>
        <div class="sidebar-submenu" data-submenu="protokolle">
            <span class="sidebar-section-title">eNOTF</span>
            <a href="<?= BASE_PATH ?>enotf/" target="_blank" class="sidebar-sublink"><i class="fa-solid fa-external-link"></i> eNOTF öffnen</a>
            <?php if (Permissions::check(['admin', 'edivi.view'])): ?>
                <a href="<?= BASE_PATH ?>enotf/admin/list.php" class="sidebar-sublink"><i class="fa-solid fa-clipboard-list"></i> Prüfliste</a>
            <?php endif; ?>

            <?php if (Permissions::check(['admin', 'manv.manage'])): ?>
                <span class="sidebar-section-title">MANV-Board</span>
                <a href="<?= BASE_PATH ?>manv/index.php" class="sidebar-sublink"><i class="fa-solid fa-house-medical"></i> MANV-Board</a>
            <?php endif; ?>

            <span class="sidebar-section-title">FW Einsatzprotokolle</span>
            <a href="<?= BASE_PATH ?>einsatz/create.php" target="_blank" class="sidebar-sublink"><i class="fa-solid fa-plus"></i> fireTab öffnen</a>
            <?php if (Permissions::check(['admin', 'fire.incident.qm'])): ?>
                <a href="<?= BASE_PATH ?>einsatz/admin/list.php" class="sidebar-sublink"><i class="fa-solid fa-list-check"></i> Qualitätsmanagement</a>
            <?php endif; ?>
        </div>

        <!-- Wissensdatenbank -->
        <a href="<?= BASE_PATH ?>wissensdb/index.php" class="sidebar-link" data-page="wissensdb">
            <i class="fa-solid fa-book-medical"></i><span>Wissensdatenbank</span>
        </a>

        <!-- Einstellungen -->
        <?php if (Permissions::check(['admin', 'personnel.view', 'vehicles.view', 'edivi.view', 'dashboard.manage'])): ?>
            <a href="#" class="sidebar-link sidebar-toggle" data-page="settings" data-menu="settings">
                <i class="fa-solid fa-sliders"></i><span>Einstellungen</span>
                <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
            </a>
            <div class="sidebar-submenu" data-submenu="settings">
                <?php if (Permissions::check(['admin', 'personnel.view'])): ?>
                    <span class="sidebar-section-title">Personal</span>
                    <a href="<?= BASE_PATH ?>settings/personal/dienstgrade/index.php" class="sidebar-sublink"><i class="fa-solid fa-medal"></i> Dienstgrade</a>
                    <a href="<?= BASE_PATH ?>settings/personal/qualifw/index.php" class="sidebar-sublink"><i class="fa-solid fa-fire"></i> FW Qualifikationen</a>
                    <a href="<?= BASE_PATH ?>settings/personal/qualird/index.php" class="sidebar-sublink"><i class="fa-solid fa-truck-medical"></i> RD Qualifikationen</a>
                    <a href="<?= BASE_PATH ?>settings/personal/qualifd/index.php" class="sidebar-sublink"><i class="fa-solid fa-user-graduate"></i> Fachdienste</a>
                    <?php if (Permissions::check(['admin'])): ?>
                        <a href="<?= BASE_PATH ?>settings/documents/templates.php" class="sidebar-sublink"><i class="fa-solid fa-file-lines"></i> Dokumente</a>
                        <a href="<?= BASE_PATH ?>settings/antrag/list.php" class="sidebar-sublink"><i class="fa-solid fa-clipboard"></i> Antragstypen</a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (Permissions::check(['admin', 'vehicles.view'])): ?>
                    <span class="sidebar-section-title">Fahrzeuge</span>
                    <a href="<?= BASE_PATH ?>settings/fahrzeuge/fahrzeuge/index.php" class="sidebar-sublink"><i class="fa-solid fa-truck"></i> Fahrzeuge bearbeiten</a>
                    <a href="<?= BASE_PATH ?>settings/fahrzeuge/beladelisten/index.php" class="sidebar-sublink"><i class="fa-solid fa-list-check"></i> Beladelisten</a>
                <?php endif; ?>

                <?php if (Permissions::check(['admin', 'edivi.view', 'pois.view'])): ?>
                    <span class="sidebar-section-title">eNOTF</span>
                    <?php if (Permissions::check(['admin', 'pois.view'])): ?>
                        <a href="<?= BASE_PATH ?>settings/pois/index.php" class="sidebar-sublink"><i class="fa-solid fa-map-marker-alt"></i> POIs</a>
                    <?php endif; ?>
                    <?php if (Permissions::check(['admin', 'edivi.view'])): ?>
                        <a href="<?= BASE_PATH ?>settings/medikamente/index.php" class="sidebar-sublink"><i class="fa-solid fa-pills"></i> Medikamente</a>
                        <a href="<?= BASE_PATH ?>settings/enotf/index.php" class="sidebar-sublink"><i class="fa-solid fa-link"></i> Schnellzugriff</a>
                    <?php endif; ?>
                <?php endif; ?>

                <span class="sidebar-section-title">System</span>
                <?php if (Permissions::check(['admin', 'dashboard.manage'])): ?>
                    <a href="<?= BASE_PATH ?>settings/dashboard/index.php" class="sidebar-sublink"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
                <?php endif; ?>
                <?php if (Permissions::check(['admin'])): ?>
                    <a href="<?= BASE_PATH ?>settings/system/config.php" class="sidebar-sublink"><i class="fa-solid fa-gear"></i> Konfiguration</a>
                    <a href="<?= BASE_PATH ?>settings/system/index.php" class="sidebar-sublink"><i class="fa-solid fa-download"></i> Updater</a>
                    <a href="<?= BASE_PATH ?>settings/system/telemetry.php" class="sidebar-sublink"><i class="fa-solid fa-wifi"></i> Telemetrie</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </nav>

    <!-- Bottom: Notifications + Logout -->
    <div class="sidebar-bottom">
        <a href="<?= BASE_PATH ?>benachrichtigungen/index.php" class="sidebar-link">
            <i class="fa-solid fa-bell"></i><span>Benachrichtigungen</span>
            <?php if ($unreadCount > 0): ?>
                <span class="sidebar-notification-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= BASE_PATH ?>logout.php" class="sidebar-link">
            <i class="fa-solid fa-right-from-bracket"></i><span>Abmelden</span>
        </a>
    </div>
</aside>

<?php include __DIR__ . '/global-announcements.php'; ?>

<!-- ===================== -->
<!-- MOBILE TOPBAR         -->
<!-- ===================== -->
<div class="sidebar-mobile-topbar">
    <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Menü öffnen">
        <i class="fa-solid fa-bars"></i>
    </button>
    <a href="<?= BASE_PATH ?>index.php">
        <img src="<?= SYSTEM_LOGO ?>" alt="<?= SYSTEM_NAME ?>">
    </a>
    <div class="sidebar-mobile-right">
        <?php if ($unreadCount > 0): ?>
            <a href="<?= BASE_PATH ?>benachrichtigungen/index.php" class="sidebar-link" style="padding:0.4rem;margin:0;">
                <i class="fa-solid fa-bell" style="margin-right:0;width:auto;"></i>
                <span class="sidebar-notification-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
            </a>
        <?php endif; ?>
    </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    $(document).ready(function() {
        var currentPage = $("body").data("page");
        var STORAGE_KEY = 'intra_sidebar_state';
        var SCROLL_KEY = 'intra_sidebar_scroll';

        // Mapping von Unterseiten zu Hauptkategorien
        var pageMapping = {
            'benutzer': 'personal',
            'mitarbeiter': 'personal',
            'edivi': 'protokolle',
            'settings': 'settings'
        };

        // Load saved sidebar state from localStorage
        function loadSidebarState() {
            try {
                var saved = localStorage.getItem(STORAGE_KEY);
                return saved ? JSON.parse(saved) : {};
            } catch (e) {
                return {};
            }
        }

        // Save sidebar state to localStorage
        function saveSidebarState() {
            var state = {};
            $(".sidebar-toggle[data-menu]").each(function() {
                var menu = $(this).data("menu");
                state[menu] = $(this).hasClass("open");
            });
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
            } catch (e) {}
        }

        // Restore saved state
        var savedState = loadSidebarState();
        var parentPage = pageMapping[currentPage] || null;

        // First: apply saved state for all menus
        $(".sidebar-toggle[data-menu]").each(function() {
            var menu = $(this).data("menu");
            if (savedState[menu]) {
                $(this).addClass("open");
                $(this).next(".sidebar-submenu").addClass("open");
            }
        });

        // Then: always ensure active page's parent is open (overrides saved state)
        if (parentPage) {
            $(".sidebar-toggle[data-menu='" + parentPage + "']")
                .addClass("active open")
                .next(".sidebar-submenu").addClass("open");
        }

        // Highlight active top-level link
        $(".sidebar-link[data-page='" + currentPage + "']").addClass("active");

        // Highlight active sublink based on current URL (best/longest match wins)
        var currentPath = window.location.pathname;
        var bestMatch = null;
        var bestLen = 0;
        $(".sidebar-sublink").each(function() {
            var href = $(this).attr("href");
            if (!href) return;
            var normalized = href.replace(/^\.\.\/|^\//, '/').split('?')[0];
            if (currentPath.indexOf(normalized) !== -1 && normalized.length > bestLen) {
                bestMatch = $(this);
                bestLen = normalized.length;
            }
        });
        if (bestMatch) bestMatch.addClass("active-sub");

        // Scroll position persistence
        var $sidebarNav = $(".sidebar-nav");

        // Restore after submenu transitions finish (max-height: 0.3s)
        setTimeout(function() {
            try {
                var savedScroll = parseInt(localStorage.getItem(SCROLL_KEY), 10);
                if (savedScroll > 0) $sidebarNav.scrollTop(savedScroll);
            } catch (e) {}
        }, 350);

        // Save on scroll (debounced) + on page unload
        var scrollTimer;
        function saveScroll() {
            try { localStorage.setItem(SCROLL_KEY, $sidebarNav.scrollTop()); } catch (e) {}
        }
        $sidebarNav.on("scroll", function() {
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(saveScroll, 150);
        });
        $(window).on("beforeunload", saveScroll);

        // Toggle submenu expand/collapse with state saving
        $(".sidebar-toggle").on("click", function(e) {
            e.preventDefault();
            $(this).toggleClass("open");
            $(this).next(".sidebar-submenu").toggleClass("open");
            saveSidebarState();
        });

        // Mobile sidebar toggle
        $("#sidebarToggle").on("click", function() {
            $("#intraSidebar").toggleClass("open");
            $("#sidebarOverlay").toggleClass("active");
        });

        // Close sidebar on overlay click
        $("#sidebarOverlay").on("click", function() {
            $("#intraSidebar").removeClass("open");
            $(this).removeClass("active");
        });

        // Tooltips
        var tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(function(el) {
            new bootstrap.Tooltip(el);
        });
    });
</script>
