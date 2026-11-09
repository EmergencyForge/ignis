<?php
/**
 * Lehrgänge, die das Konto leitet oder betreut (Verwalter: alle).
 *
 * @var list<\Plugin\Training\Models\Course>                              $courses
 * @var array<int, array{seated?: int, waiting?: int, pending?: int}>     $counts
 * @var bool                                                              $canManage
 */

use Plugin\Training\View;

$layout     = 'admin';
$bodyId     = 'training';
$SITE_TITLE = 'Lehrgänge';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>training">Ausbildung</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Lehrgänge</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Ausbildung</p>
                    <h1>Lehrgänge</h1>
                    <p class="twplus-page-header__description"><?= $canManage ? 'Alle Lehrgänge.' : 'Die Lehrgänge, die du leitest oder als Ausbilder betreust.' ?></p>
                </div>
                <?php if ($canManage): ?>
                    <div class="header-actions twplus-page-header__actions">
                        <a href="<?= $base ?>training/types" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Lehrgangsarten</a>
                        <a href="<?= $base ?>training/manage/create" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Lehrgang anlegen</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="twplus-table-card">
                <?php if ($courses === []): ?>
                    <?php
                    $empty = [
                        'variant' => 'first', 'tone' => 'info', 'icon' => 'fa-graduation-cap', 'ghostColumns' => 5,
                        'title'   => 'Noch keine Lehrgänge',
                        'text'    => $canManage ? 'Ein Lehrgang ist ein Durchgang einer Lehrgangsart, mit Leitung, Plätzen und Terminen.' : 'Sobald du einen Lehrgang leitest oder betreust, steht er hier.',
                        'actions' => $canManage ? [['label' => 'Lehrgang anlegen', 'href' => $base . 'training/manage/create', 'style' => 'secondary', 'icon' => 'fa-plus']] : [],
                    ];
                    require dirname(__DIR__, 4) . '/templates/partials/empty.php';
                    ?>
                <?php else: ?>
                    <div class="twplus-table-card__scroll">
                        <table class="ignis-table" data-ignis-mobile-table>
                            <thead>
                                <tr>
                                    <th scope="col">Lehrgang</th>
                                    <th scope="col">Zeitraum</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Plätze</th>
                                    <th scope="col">Leitung</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): $n = $counts[$course->id] ?? []; ?>
                                    <tr>
                                        <td data-label="Lehrgang" data-mobile-primary>
                                            <a href="<?= $base ?>training/manage/<?= (int) $course->id ?>"><?= htmlspecialchars($course->title) ?></a>
                                            <div class="ignis-detail__muted"><?= htmlspecialchars($course->type->name) ?></div>
                                        </td>
                                        <td data-label="Zeitraum"><?= htmlspecialchars($course->period()) ?></td>
                                        <td data-label="Status">
                                            <span class="ignis-chip <?= View::courseChip($course->state()) ?>"><?= htmlspecialchars($course->state()->label()) ?></span>
                                            <?php if (($n['pending'] ?? 0) > 0): ?>
                                                <span class="ignis-chip ignis-chip--warn"><?= (int) $n['pending'] ?> offen</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Plätze">
                                            <?= (int) ($n['seated'] ?? 0) ?><?= $course->seats !== null ? ' von ' . (int) $course->seats : '' ?>
                                            <?php if (($n['waiting'] ?? 0) > 0): ?><span class="ignis-detail__muted">, <?= (int) $n['waiting'] ?> wartend</span><?php endif; ?>
                                        </td>
                                        <td data-label="Leitung"><?= htmlspecialchars($course->lead !== null ? ($course->lead->fullname ?: $course->lead->username) : 'offen') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
