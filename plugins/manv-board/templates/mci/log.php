<?php
/**
 * View: MANV-Aktionslog
 *
 * @var array<string,mixed>            $lage
 * @var array<int,array<string,mixed>> $logEntries
 */

$lageId     = (int) $lage['id'];
$SITE_TITLE = 'Aktionslog - ' . htmlspecialchars($lage['einsatznummer']);

$layout = 'admin';
$bodyId = 'manv-log';
$bodyPage = 'edivi';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <header class="twplus-page-header mb-6">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Ereignisverlauf</p>
                    <h1>Aktionslog</h1>
                    <p class="twplus-page-header__description">MANV-Lage: <?= htmlspecialchars($lage['einsatznummer']) ?></p>
                </div>
                <div class="twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>mci/board?id=<?= $lageId ?>" class="ignis-btn ignis-btn--ghost no-underline hover:no-underline">
                        <i class="fas fa-arrow-left"></i>Zurück
                    </a>
                </div>
            </header>

            <div class="twplus-section-card">
                <div class="twplus-section-card__body">
                    <?php if (empty($logEntries)): ?>
                        <?php
                        $empty = [
                            'variant' => 'sm',
                            'icon'    => 'fa-clock-rotate-left',
                            'heading' => 2,
                            'title'   => 'Noch keine Logeinträge',
                            'text'    => 'Aktionen an dieser Lage erscheinen hier chronologisch.',
                        ];
                        require dirname(__DIR__, 4) . '/templates/partials/empty.php';
                        ?>
                    <?php else: ?>
                        <div class="twplus-feed">
                            <?php foreach ($logEntries as $entry): ?>
                                <div class="twplus-feed__item">
                                    <span class="twplus-feed__icon"><i class="fas fa-clock-rotate-left"></i></span>
                                    <div class="twplus-feed__body">
                                        <div class="twplus-feed__content"><span class="ignis-chip ignis-chip--primary"><?= htmlspecialchars($entry['aktion']) ?></span> <?= htmlspecialchars($entry['beschreibung'] ?? '-') ?></div>
                                        <div class="twplus-feed__meta">
                                            <span><i class="fas fa-clock"></i> <?= date('d.m.Y H:i:s', strtotime($entry['timestamp'])) ?></span>
                                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($entry['benutzer_name'] ?? 'System') ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
