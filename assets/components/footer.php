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
            <div>
                <span class="ignis-about-brand"><em><strong>ıgnıs</strong></em></span>
                <span class="ignis-about-version"><?= htmlspecialchars($__footerVersion) ?></span>
                <div class="ignis-about-tagline">Struktur für jeden Einsatz.</div>
            </div>
            <button type="button" onclick="this.closest('dialog').close()" aria-label="Schließen">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="ignis-about-body">
            <p>
                Entwickelt von
                <a href="https://emergencyforge.de" target="_blank" rel="nofollow">EmergencyForge</a>
                unter Mitarbeit der Community.
            </p>
            <div class="ignis-about-chips">
                <span class="ignis-about-chip"><i class="fa-solid fa-code" aria-hidden="true"></i> hypax</span>
                <span class="ignis-about-chip"><i class="fa-solid fa-code" aria-hidden="true"></i> QuitScope</span>
                <span class="ignis-about-chip"><i class="fa-solid fa-code" aria-hidden="true"></i> bitsystem</span>
                <span class="ignis-about-chip"><i class="fa-solid fa-heart" aria-hidden="true"></i> Community</span>
            </div>
            <div class="ignis-about-actions">
                <a class="ignis-about-action" href="https://github.com/EmergencyForge/ignis" target="_blank" rel="nofollow">
                    <i class="fa-brands fa-github" aria-hidden="true"></i> Quellcode
                </a>
                <a class="ignis-about-action" href="https://github.com/EmergencyForge/ignis/blob/main/LICENSE.md" target="_blank" rel="nofollow">
                    <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> GPL-3.0-Lizenz
                </a>
            </div>
        </div>
        <div class="ignis-about-credits">
            <div class="ignis-about-credits-label">Baut auf großartiger Open-Source-Arbeit</div>
            <div class="ignis-about-chips">
                <a class="ignis-about-chip" href="https://fontawesome.com/" target="_blank" rel="nofollow">Font Awesome</a>
                <a class="ignis-about-chip" href="https://ckeditor.com/" target="_blank" rel="nofollow">CKEditor</a>
                <a class="ignis-about-chip" href="https://fonts.google.com/" target="_blank" rel="nofollow">Google Fonts</a>
                <a class="ignis-about-chip" href="https://www.chartjs.org/" target="_blank" rel="nofollow">Chart.js</a>
                <a class="ignis-about-chip" href="https://github.com/SortableJS/Sortable" target="_blank" rel="nofollow">SortableJS</a>
                <a class="ignis-about-chip" href="https://taktische-zeichen.dev/" target="_blank" rel="nofollow">Taktische Zeichen</a>
                <a class="ignis-about-chip" href="https://leafletjs.com/" target="_blank" rel="nofollow">Leaflet</a>
            </div>
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
            border: 1px solid var(--darkgray, #3d3a44);
            border-radius: 14px;
            background: #29282f;
            color: #e1e1e1;
            padding: 0;
            max-width: 27rem;
            width: calc(100vw - 2rem);
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }

        .ignis-about-dialog::backdrop {
            background: rgba(0, 0, 0, 0.6);
        }

        .ignis-about-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 1.1rem 1.25rem 1rem;
            border-bottom: 1px solid var(--darkgray, #3d3a44);
        }

        .ignis-about-brand {
            font-size: 1.35rem;
            color: var(--main-color, #d3572f);
            letter-spacing: 0.01em;
        }

        .ignis-about-version {
            display: inline-block;
            margin-left: 0.5rem;
            padding: 0.1rem 0.5rem;
            border: 1px solid var(--darkgray, #3d3a44);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.04);
            font-family: ui-monospace, monospace;
            font-size: 0.72rem;
            color: #b7b7bd;
            vertical-align: 0.25em;
        }

        .ignis-about-tagline {
            margin-top: 0.15rem;
            font-size: 0.78rem;
            color: #8f8f97;
        }

        .ignis-about-head button {
            background: none;
            border: none;
            color: #9a9a9a;
            cursor: pointer;
            font-size: 1rem;
            padding: 0.25rem;
            line-height: 1;
        }

        .ignis-about-head button:hover {
            color: #fff;
        }

        .ignis-about-body {
            padding: 1.1rem 1.25rem;
            font-size: 0.875rem;
            line-height: 1.6;
        }

        .ignis-about-body p {
            margin-bottom: 0.6rem;
        }

        .ignis-about-body a {
            color: inherit;
            text-decoration: underline;
        }

        .ignis-about-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .ignis-about-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.6rem;
            border: 1px solid var(--darkgray, #3d3a44);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.04);
            font-size: 0.75rem;
            color: #c9c9cf;
            text-decoration: none !important;
        }

        .ignis-about-chip i {
            font-size: 0.65rem;
            color: var(--main-color, #d3572f);
        }

        a.ignis-about-chip:hover {
            border-color: var(--main-color, #d3572f);
            color: #fff;
        }

        .ignis-about-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .ignis-about-action {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.4rem 0.85rem;
            border: 1px solid var(--darkgray, #3d3a44);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.04);
            font-size: 0.8rem;
            color: #e1e1e1;
            text-decoration: none !important;
            transition: border-color 0.15s ease, background 0.15s ease;
        }

        .ignis-about-action:hover {
            border-color: var(--main-color, #d3572f);
            background: rgba(255, 255, 255, 0.07);
        }

        .ignis-about-credits {
            padding: 0.9rem 1.25rem 1.1rem;
            border-top: 1px solid var(--darkgray, #3d3a44);
            background: rgba(0, 0, 0, 0.15);
        }

        .ignis-about-credits-label {
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #8f8f97;
            margin-bottom: 0.5rem;
        }
    </style>
<?php endif; ?>