<?php

/**
 * View: Verwaltungsübersicht
 *
 * Ersetzt die ~20 einzelnen Sidebar-Zeilen unter „Einstellungen" durch
 * Kacheln in Abschnitten: je Gruppe mit placement=settings aus
 * config/navigation.php ein <section>, gebaut aus derselben rechte-
 * gefilterten Liste (App\Helpers\Navigation::groups()), die auch die
 * Sidebar zeigt — Sidebar und Übersicht laufen so nie auseinander.
 *
 * @var list<array<string,mixed>> $settingsSections
 */

$layout = 'admin';
$bodyId = 'settings-index';
$SITE_TITLE = 'Einstellungen';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Einstellungen</span></nav>
                    <div class="page-header twplus-page-header mb-4">
                        <div class="twplus-page-header__copy">
                            <h1>Einstellungen</h1>
                            <p class="twplus-page-header__description">Alles, was ignis für deine Organisation einrichtet.</p>
                        </div>
                    </div>

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
                        <?php foreach ($settingsSections as $section): ?>
                            <section class="mb-5">
                                <h2><?= htmlspecialchars((string) $section['label']) ?></h2>
                                <div class="twplus-link-grid">
                                    <?php foreach ($section['items'] as $item): ?>
                                        <a href="<?= htmlspecialchars((string) $item['href']) ?>" class="twplus-link-card">
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
