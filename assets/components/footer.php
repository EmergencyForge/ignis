<!-- Footer für das Intranet-System -->
<?php
// Aktuelle Version aus storage/version.json (wird vom Release-Build bzw.
// Updater gepflegt); fehlt die Datei, wird schlicht keine Version angezeigt.
$__footerVersionFile = dirname(__DIR__, 2) . '/storage/version.json';
$__footerVersionInfo = is_file($__footerVersionFile) ? json_decode((string) file_get_contents($__footerVersionFile), true) : null;
$__footerVersion = is_array($__footerVersionInfo) && !empty($__footerVersionInfo['version']) ? (string) $__footerVersionInfo['version'] : null;
$__footerBasePath = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
<footer class="footer mt-auto py-3">
    <div class="container mx-auto">
        <div class="grid grid-cols-1 gap-3 items-end md:grid-cols-3">
            <div>
                <?php // Zeichen und Schriftzug als Masken (siehe _shell.scss), damit das Logo in beiden Themes steht. ?>
                <span class="ignis-lockup" role="img" aria-label="ignis"><span class="ignis-lockup__mark" style="--logo: url('<?= htmlspecialchars(rtrim($__footerBasePath, '/') . '/assets/img/ignis-mark.svg', ENT_QUOTES) ?>')"></span><span class="ignis-lockup__word" style="--logo: url('<?= htmlspecialchars(rtrim($__footerBasePath, '/') . '/assets/img/ignis-wordmark.svg', ENT_QUOTES) ?>')"></span></span>
                <p class="text-sm">Verwaltungsportal der <?php echo RP_ORGTYPE . " " . SERVER_CITY ?></p>
            </div>
            <div class="text-center">
                <p class="text-sm">&copy; 2024-<?php echo date("Y") ?> <em><strong>ıgnıs</strong></em> by <a href="https://emergencyforge.de" target="_blank" rel="nofollow">EmergencyForge</a>. Alle Rechte vorbehalten.</p>
                <?php if ($__footerVersion !== null): ?>
                    <button type="button" class="footer-version-btn" data-dialog-target="#ignis-about" data-ignis-tooltip="Über ıgnıs">
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
                    <a href="<?= htmlspecialchars($impressumUrl) ?>" target="_blank" class="text-sm">Impressum</a>
                <?php endif; ?>
                <?php if ($datenschutzUrl !== ''): ?>
                    <?php if ($impressumUrl !== ''): ?>
                        <span class="text-sm mx-1">|</span>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($datenschutzUrl) ?>" target="_blank" class="text-sm">Datenschutz</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>

<?php if ($__footerVersion !== null): ?>
    <?php
    // Über ignis: läuft über den gemeinsamen Dialog (data-dialog-target am
    // Versionsknopf), Farben nur aus den Theme-Tokens.
    $__aboutCredits = [
        ['Tiptap', 'https://tiptap.dev/'],
        ['Font Awesome', 'https://fontawesome.com/'],
        ['Geist', 'https://vercel.com/font'],
        ['Chart.js', 'https://www.chartjs.org/'],
        ['SortableJS', 'https://github.com/SortableJS/Sortable'],
        ['Leaflet', 'https://leafletjs.com/'],
        ['Taktische Zeichen', 'https://taktische-zeichen.dev/'],
    ];
    ?>
    <div hidden>
        <div class="ignis-about" id="ignis-about" aria-label="Über ıgnıs">
            <div class="ignis-about__head">
                <span class="ignis-lockup ignis-about__lockup" role="img" aria-label="ignis"><span class="ignis-lockup__mark" style="--logo: url('<?= htmlspecialchars(rtrim($__footerBasePath, '/') . '/assets/img/ignis-mark.svg', ENT_QUOTES) ?>')"></span><span class="ignis-lockup__word" style="--logo: url('<?= htmlspecialchars(rtrim($__footerBasePath, '/') . '/assets/img/ignis-wordmark.svg', ENT_QUOTES) ?>')"></span></span>
                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-dialog-dismiss aria-label="Schließen"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <p class="ignis-about__tagline">Struktur für jeden Einsatz.</p>
            <dl class="ignis-about__facts">
                <div><dt>Version</dt><dd class="ignis-about__mono"><?= htmlspecialchars($__footerVersion) ?></dd></div>
                <div><dt>Lizenz</dt><dd>GPL-3.0</dd></div>
                <div><dt>Entwicklung</dt><dd><a href="https://emergencyforge.de" target="_blank" rel="nofollow">EmergencyForge</a> mit der Community</dd></div>
            </dl>
            <div class="ignis-about__section">
                <span class="ignis-about__label">Team</span>
                <div class="ignis-about__chips">
                    <span class="ignis-about__chip">hypax</span>
                    <span class="ignis-about__chip">QuitScope</span>
                    <span class="ignis-about__chip">bitsystem</span>
                    <span class="ignis-about__chip"><i class="fa-solid fa-heart" aria-hidden="true"></i>Community</span>
                </div>
            </div>
            <div class="ignis-about__section">
                <span class="ignis-about__label">Baut auf Open Source</span>
                <div class="ignis-about__chips">
                    <?php foreach ($__aboutCredits as [$__name, $__url]): ?>
                        <a class="ignis-about__chip ignis-about__chip--link" href="<?= htmlspecialchars($__url) ?>" target="_blank" rel="nofollow"><?= htmlspecialchars($__name) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="ignis-about__actions">
                <a class="ignis-btn ignis-btn--sm ignis-btn--secondary" href="https://github.com/EmergencyForge/ignis" target="_blank" rel="nofollow"><i class="fa-brands fa-github" aria-hidden="true"></i>Quellcode</a>
                <a class="ignis-btn ignis-btn--sm ignis-btn--secondary" href="https://github.com/EmergencyForge/ignis/blob/main/LICENSE.md" target="_blank" rel="nofollow"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>Lizenz lesen</a>
            </div>
        </div>
    </div>
    <style>
        .footer-version-btn {
            background: none;
            border: none;
            padding: 0;
            font-size: 0.75rem;
            color: var(--text-3);
            cursor: pointer;
            text-decoration: underline dotted;
        }

        .footer-version-btn:hover {
            color: var(--text);
        }

        .ignis-about {
            display: grid;
            gap: 16px;
            padding: 20px;
        }

        .ignis-about__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .ignis-about__lockup {
            --lockup-h: 30px;
        }

        .ignis-about__tagline {
            margin: -6px 0 0;
            color: var(--text-3);
            font-size: 0.85rem;
        }

        .ignis-about__facts {
            display: grid;
            gap: 1px;
            margin: 0;
            border: 1px solid var(--hairline);
            border-radius: var(--radius-3);
            overflow: hidden;
            background: var(--hairline);
        }

        .ignis-about__facts > div {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 12px;
            background: var(--surface);
            font-size: 0.85rem;
        }

        .ignis-about__facts dt {
            color: var(--text-3);
        }

        .ignis-about__facts dd {
            margin: 0;
            text-align: right;
        }

        .ignis-about__mono {
            font-family: var(--mono);
            font-variant-numeric: tabular-nums;
        }

        .ignis-about__section {
            display: grid;
            gap: 8px;
        }

        .ignis-about__label {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-3);
        }

        .ignis-about__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .ignis-about__chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--fill-2);
            color: var(--text-2);
            font-size: 0.8rem;
            text-decoration: none;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .ignis-about__chip i {
            font-size: 0.7rem;
            color: var(--accent);
        }

        .ignis-about__chip--link:hover,
        .ignis-about__chip--link:focus-visible {
            background: var(--fill-3);
            color: var(--text);
        }

        .ignis-about__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
    </style>
<?php endif; ?>