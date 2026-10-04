<?php

/**
 * View: Verwaltungsübersicht
 *
 * Ersetzt die ~20 einzelnen Sidebar-Zeilen unter „Einstellungen" durch
 * Kacheln in Abschnitten: je Gruppe mit placement=settings aus
 * config/navigation.php ein <section>, gebaut aus derselben rechte-
 * gefilterten Liste (App\Helpers\Navigation::groups()), die auch die
 * Sidebar zeigt. Sidebar und Übersicht laufen so nie auseinander.
 *
 * Das Suchfeld filtert die Kacheln beim Tippen nach Titel, Beschreibung und
 * Abschnitt (data-settings-search), blendet leere Abschnitte aus und zeigt
 * ohne Treffer den Leerzustand.
 *
 * @var list<array<string,mixed>> $settingsSections
 * @var list<array{key: string, label: string, level: string, text: string}> $setupRequired
 */

$layout = 'admin';
$bodyId = 'settings-index';
$SITE_TITLE = 'Einstellungen';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 min-w-0 px-3">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Einstellungen</span></nav>
                    <div class="page-header twplus-page-header mb-4">
                        <div class="twplus-page-header__copy">
                            <h1>Einstellungen</h1>
                            <p class="twplus-page-header__description">Alles, was ignis für deine Organisation einrichtet.</p>
                        </div>
                    </div>

                    <?php if ($setupRequired !== []): ?>
                        <div class="ignis-alert ignis-alert--warn mb-4 items-center" id="setup-notice" role="status">
                            <i class="fa-solid fa-flag-checkered ignis-alert__icon" aria-hidden="true"></i>
                            <div class="ignis-alert__body">
                                <div class="ignis-alert__title">Die Einrichtung ist noch nicht fertig</div>
                                Noch offen: <?= htmlspecialchars(implode(', ', array_column($setupRequired, 'label'))) ?>.
                            </div>
                            <a class="ignis-btn ignis-btn--primary ignis-btn--sm" href="<?= BASE_PATH ?>settings/system/config?setup=1">Jetzt einrichten</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($settingsSections === []): ?>
                        <?php
                        $empty = [
                            'tone'  => 'warn',
                            'icon'  => 'fa-lock',
                            'title' => 'Keine Bereiche freigeschaltet',
                            'text'  => 'Dein Konto hat für keinen Verwaltungsbereich Rechte. Eine Person mit Admin-Rechten kann sie unter Benutzer freischalten.',
                        ];
                        require __DIR__ . '/../partials/empty.php';
                        ?>
                    <?php else: ?>
                        <div class="mb-5">
                            <div class="ignis-list-toolbar" role="search">
                                <label class="ignis-list-toolbar__search">
                                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                    <input class="ignis-input" type="search" id="settings-search" placeholder="Einstellung suchen" aria-label="Einstellungen durchsuchen" autocomplete="off">
                                </label>
                            </div>
                        </div>

                        <?php foreach ($settingsSections as $section): ?>
                            <section class="mb-5" data-settings-section>
                                <h2><?= htmlspecialchars((string) $section['label']) ?></h2>
                                <div class="twplus-link-grid">
                                    <?php foreach ($section['items'] as $item): ?>
                                        <a href="<?= htmlspecialchars((string) $item['href']) ?>" class="twplus-link-card" data-settings-search="<?= htmlspecialchars(mb_strtolower($item['label'] . ' ' . ($item['description'] ?? '') . ' ' . $section['label'])) ?>">
                                            <span class="twplus-link-card__icon"><i class="<?= htmlspecialchars((string) $item['icon']) ?>" aria-hidden="true"></i></span>
                                            <span class="twplus-link-card__body">
                                                <span class="twplus-link-card__title"><?= htmlspecialchars((string) $item['label']) ?></span>
                                                <?php if (!empty($item['description'])): ?>
                                                    <span class="twplus-link-card__description"><?= htmlspecialchars((string) $item['description']) ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <i class="fa-solid fa-chevron-right twplus-link-card__arrow" aria-hidden="true"></i>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>

                        <div id="settings-search-empty" role="status" hidden>
                            <?php
                            $empty = [
                                'variant' => 'sm',
                                'icon'    => 'fa-magnifying-glass',
                                'title'   => 'Keine Einstellung gefunden',
                                'text'    => 'Versuch es mit einem anderen Begriff, zum Beispiel PIN, Rollen oder Mail.',
                                'query'   => ['term' => ' '],
                            ];
                            require __DIR__ . '/../partials/empty.php';
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Kachelsuche: filtert beim Tippen nach Titel und Beschreibung,
        // blendet leere Abschnitte aus und zeigt ohne Treffer den Leerzustand.
        (function () {
            var input = document.getElementById('settings-search');
            var empty = document.getElementById('settings-search-empty');
            if (!input || !empty) return;
            var term = empty.querySelector('.ignis-empty__term');

            input.addEventListener('input', function () {
                var query = input.value.trim().toLowerCase();
                var hits = 0;
                document.querySelectorAll('[data-settings-section]').forEach(function (section) {
                    var visible = 0;
                    section.querySelectorAll('[data-settings-search]').forEach(function (tile) {
                        var match = query === '' || tile.dataset.settingsSearch.indexOf(query) !== -1;
                        tile.hidden = !match;
                        if (match) visible++;
                    });
                    section.hidden = visible === 0;
                    hits += visible;
                });
                empty.hidden = hits > 0;
                if (term) term.textContent = '„' + input.value.trim() + '“';
            });
        })();
    </script>
