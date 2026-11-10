<?php
/**
 * Lehrgangsarten (`training.manage`).
 *
 * @var list<\Plugin\Training\Models\CourseType> $types
 * @var array<int, int>                          $courses  Lehrgänge je Art
 * @var array<int, int>                          $modules  Module je Art
 */

use Plugin\Training\Models\CourseType;
use Plugin\Training\Policies\TrainingPolicy;
use Plugin\Training\Qualifications;

$layout     = 'admin';
$bodyId     = 'training';
$SITE_TITLE = 'Lehrgangsarten';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>training">Ausbildung</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Lehrgangsarten</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Ausbildung</p>
                    <h1>Lehrgangsarten</h1>
                    <p class="twplus-page-header__description">Die Vorlage für Lehrgänge: Voraussetzungen, Qualifikation bei Beginn, Anmeldung und der Lehrplan mit Modulen und Unterlagen.</p>
                </div>
                <?php if (TrainingPolicy::manage()): ?>
                    <div class="header-actions twplus-page-header__actions">
                        <?php if (TrainingPolicy::exams()): ?>
                            <?php
                            $importUrl    = $base . 'training/types/import';
                            $importAccept = '.json,application/json';
                            $importHint   = 'JSON-Export einer Lehrgangsart aus ignis oder Lex';
                            $importId     = 'type-import';
                            require dirname(__DIR__) . '/partials/import.php';
                            ?>
                        <?php endif; ?>
                        <a href="<?= $base ?>training/types/create" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Lehrgangsart anlegen</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="twplus-table-card">
                <?php if ($types === []): ?>
                    <?php
                    $empty = [
                        'variant' => 'first', 'tone' => 'info', 'icon' => 'fa-layer-group', 'ghostColumns' => 4,
                        'title'   => 'Noch keine Lehrgangsarten',
                        'text'    => 'Eine Lehrgangsart ist etwa „Rettungssanitäter (RS)“: Lehrplan und Regeln, nach denen jeder RS-Lehrgang läuft.',
                        'actions' => [['label' => 'Lehrgangsart anlegen', 'href' => $base . 'training/types/create', 'style' => 'secondary', 'icon' => 'fa-plus']],
                    ];
                    require dirname(__DIR__, 4) . '/templates/partials/empty.php';
                    ?>
                <?php else: ?>
                    <div class="twplus-table-card__scroll">
                        <table class="ignis-table" data-ignis-mobile-table>
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Bereich</th>
                                    <th scope="col">Bei Beginn</th>
                                    <th scope="col">Lehrplan</th>
                                    <th scope="col">Lehrgänge</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($types as $type): ?>
                                    <tr>
                                        <td data-label="Name" data-mobile-primary>
                                            <a href="<?= $base ?>training/types/<?= (int) $type->id ?>"><?= htmlspecialchars($type->name) ?> (<?= htmlspecialchars($type->short) ?>)</a>
                                            <?php if (!$type->is_active): ?> <span class="ignis-chip ignis-chip--sm ignis-chip--secondary">Inaktiv</span><?php endif; ?>
                                        </td>
                                        <td data-label="Bereich"><?= htmlspecialchars(CourseType::AREAS[$type->area] ?? $type->area) ?></td>
                                        <td data-label="Bei Beginn"><?= htmlspecialchars(Qualifications::label($type->start_qualification) ?? '') ?></td>
                                        <td data-label="Lehrplan"><?= (int) ($modules[$type->id] ?? 0) ?> Module</td>
                                        <td data-label="Lehrgänge"><?= (int) ($courses[$type->id] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php if (TrainingPolicy::exams()): ?>
    <script type="module" src="<?= $base ?>assets/js/ui/menu.js"></script>
<?php endif; ?>
