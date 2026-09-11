<?php
/**
 * View: Antragstyp-Auswahl
 *
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\FormType> $typen
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
                <div class="twplus-empty">
                    <i class="fa-solid fa-clipboard-list twplus-empty__icon" aria-hidden="true"></i>
                    <h2 class="twplus-empty__title">Keine Antragstypen verfügbar</h2>
                    <p class="twplus-empty__description">Sobald ein Formular für dich freigegeben ist, erscheint es an dieser Stelle.</p>
                </div>
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
                                <span class="text-gray-500 text-xs">Formular öffnen</span>
                                <span class="ignis-btn ignis-btn--soft-primary ignis-btn--sm">
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
