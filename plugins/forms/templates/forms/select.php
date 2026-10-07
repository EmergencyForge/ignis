<?php
/**
 * View: Antragstyp-Auswahl
 *
 * @var \Illuminate\Database\Eloquent\Collection<int, \Plugin\Forms\Models\FormType> $typen
 */


$SITE_TITLE = "Antrag einreichen";

$layout = 'admin';
$bodyId = 'antrag-select';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <header class="twplus-page-header mb-6">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Self-Service</p>
                    <h1>Neuen Antrag stellen</h1>
                    <p class="twplus-page-header__description">Wähle den passenden Antragstyp aus. Die verfügbaren Formulare richten sich nach deiner Berechtigung.</p>
                </div>
            </header>


            <?php if ($typen->isEmpty()): ?>
                <?php
                $empty = [
                    'variant' => 'sm',
                    'icon'    => 'fa-clipboard-list',
                    'heading' => 2,
                    'title'   => 'Keine Antragstypen verfügbar',
                    'text'    => 'Sobald die Verwaltung einen Antragstyp für dich freigibt, erscheint er hier.',
                ];
                require dirname(__DIR__, 4) . '/templates/partials/empty.php';
                ?>
            <?php else: ?>
                <div class="twplus-resource-grid">
                    <?php foreach ($typen as $typ): ?>
                        <a href="<?= BASE_PATH . 'forms/create?typ=' . (int) $typ->id ?>"
                            class="twplus-resource-card no-underline">
                            <div class="flex items-start gap-3">
                                <span class="twplus-link-card__icon"><i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i></span>
                                <div class="min-w-0 flex-1">
                                    <h4 class="twplus-link-card__title"><?= htmlspecialchars($typ->name) ?></h4>

                                    <?php if (!empty($typ->beschreibung)): ?>
                                        <p class="twplus-link-card__description">
                                            <?= htmlspecialchars($typ->beschreibung) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between">
                                <span class="text-tertiary-text text-xs">Formular öffnen</span>
                                <span class="ignis-btn ignis-btn--secondary ignis-btn--sm">
                                    <i class="fa-solid fa-arrow-right mr-1"></i>
                                    Antrag stellen
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="mt-6">
                <a href="<?= BASE_PATH ?>index" class="ignis-btn ignis-btn--ghost">
                    <i class="fas fa-arrow-left mr-2"></i>Zurück zum Dashboard
                </a>
            </div>
        </div>
    </div>
