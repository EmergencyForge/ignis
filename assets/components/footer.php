<!-- Footer für das Intranet-System -->
<?php
// Aktuelle Version aus storage/version.json (wird vom Release-Build bzw.
// Updater gepflegt); fehlt die Datei, wird schlicht keine Version angezeigt.
$__footerVersionFile = dirname(__DIR__, 2) . '/storage/version.json';
$__footerVersionInfo = is_file($__footerVersionFile) ? json_decode((string) file_get_contents($__footerVersionFile), true) : null;
$__footerVersion = is_array($__footerVersionInfo) && !empty($__footerVersionInfo['version']) ? (string) $__footerVersionInfo['version'] : null;
?>
<footer class="footer mt-auto py-3 text-white">
    <div class="container mx-auto">
        <div class="grid grid-cols-1 gap-3 items-end md:grid-cols-3">
            <div>
                <img src="/assets/img/defaultLogo.webp" alt="Logo" height="48px" width="auto">
                <p class="text-sm">Verwaltungsportal der <?php echo RP_ORGTYPE . " " . SERVER_CITY ?></p>
            </div>
            <div class="text-center">
                <p class="text-sm">&copy; 2024-<?php echo date("Y") ?> <em><strong>ıgnıs</strong></em> by <a href="https://emergencyforge.de" target="_blank" rel="nofollow">EmergencyForge</a>. Alle Rechte vorbehalten.</p>
                <?php if ($__footerVersion !== null): ?>
                    <button type="button" class="footer-version-btn" onclick="document.getElementById('ignis-about-dialog').showModal()" title="Über ıgnıs">
                        <?= htmlspecialchars($__footerVersion) ?>
                    </button>
                <?php endif; ?>
            </div>
            <div class="md:text-right">
                <?php
                $impressumUrl = defined('LEGAL_IMPRESSUM_URL') ? LEGAL_IMPRESSUM_URL : '';
                $datenschutzUrl = defined('LEGAL_DATENSCHUTZ_URL') ? LEGAL_DATENSCHUTZ_URL : '';
                ?>
                <?php if ($impressumUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($impressumUrl) ?>" target="_blank" class="text-white text-sm">Impressum</a>
                <?php endif; ?>
                <?php if ($datenschutzUrl !== ''): ?>
                    <?php if ($impressumUrl !== ''): ?>
                        <span class="text-white text-sm mx-1">|</span>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($datenschutzUrl) ?>" target="_blank" class="text-white text-sm">Datenschutz</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>

<?php if ($__footerVersion !== null): ?>
    <dialog id="ignis-about-dialog" class="ignis-about-dialog">
        <div class="ignis-about-head">
            <strong>ıgnıs <?= htmlspecialchars($__footerVersion) ?></strong>
            <button type="button" onclick="this.closest('dialog').close()" aria-label="Schließen">✕</button>
        </div>
        <div class="ignis-about-body">
            <p>
                <em><strong>ıgnıs</strong></em> wird von
                <a href="https://emergencyforge.de" target="_blank" rel="nofollow">EmergencyForge</a>
                unter Mitarbeit von hypax, QuitScope, bitsystem und der Community entwickelt.
            </p>
            <p>
                Lizenziert unter der
                <a href="https://github.com/EmergencyForge/ignis/blob/main/LICENSE.md" target="_blank" rel="nofollow">GNU General Public License v3.0</a>
                — Quellcode auf
                <a href="https://github.com/EmergencyForge/ignis" target="_blank" rel="nofollow">GitHub</a>.
            </p>
            <p class="ignis-about-credits">
                Baut auf großartiger Open-Source-Arbeit auf:
                <a href="https://fontawesome.com/" target="_blank" rel="nofollow">Font Awesome</a> ·
                <a href="https://ckeditor.com/" target="_blank" rel="nofollow">CKEditor</a> ·
                <a href="https://fonts.google.com/" target="_blank" rel="nofollow">Google Fonts</a> ·
                <a href="https://www.chartjs.org/" target="_blank" rel="nofollow">Chart.js</a> ·
                <a href="https://github.com/SortableJS/Sortable" target="_blank" rel="nofollow">SortableJS</a> ·
                <a href="https://taktische-zeichen.dev/" target="_blank" rel="nofollow">Taktische Zeichen</a> ·
                <a href="https://leafletjs.com/" target="_blank" rel="nofollow">Leaflet</a>
            </p>
        </div>
    </dialog>
    <style>
        .footer-version-btn {
            background: none;
            border: none;
            padding: 0;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.55);
            cursor: pointer;
            text-decoration: underline dotted;
        }

        .footer-version-btn:hover {
            color: #fff;
        }

        .ignis-about-dialog {
            margin: auto;
            /* globale Resets nullen sonst das zentrierende UA-margin */
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            background: #1c1c1e;
            color: #e1e1e1;
            padding: 0;
            max-width: 26rem;
            width: calc(100vw - 2rem);
        }

        .ignis-about-dialog::backdrop {
            background: rgba(0, 0, 0, 0.6);
        }

        .ignis-about-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .ignis-about-head button {
            background: none;
            border: none;
            color: #9a9a9a;
            cursor: pointer;
            font-size: 1rem;
        }

        .ignis-about-head button:hover {
            color: #fff;
        }

        .ignis-about-body {
            padding: 1rem 1.25rem 1.25rem;
            font-size: 0.875rem;
            line-height: 1.6;
        }

        .ignis-about-body p+p {
            margin-top: 0.75rem;
        }

        .ignis-about-credits {
            font-size: 0.78rem;
            color: #9a9a9a;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 0.75rem;
        }

        .ignis-about-body a {
            color: inherit;
            text-decoration: underline;
        }
    </style>
<?php endif; ?>