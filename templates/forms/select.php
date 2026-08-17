<?php
/**
 * View: Antragstyp-Auswahl
 *
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\FormType> $typen
 * @var \PDO                                                                  $pdo
 */

use App\Helpers\Flash;

$SITE_TITLE = "Antrag einreichen";
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">

<head>
    <?php include __DIR__ . "/../../assets/components/_base/admin/head.php"; ?>
</head>

<body data-theme="dark" data-page="antrag-select">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>

    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <header class="twplus-page-header mb-6">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Self-Service</p>
                    <h1>Neuen Antrag stellen</h1>
                    <p class="twplus-page-header__description">Wähle den passenden Antragstyp aus. Die verfügbaren Formulare richten sich nach deiner Berechtigung.</p>
                </div>
            </header>

            <?php Flash::render(); ?>

            <?php if ($typen->isEmpty()): ?>
                <div class="twplus-empty">
                    <i class="fa-solid fa-clipboard-list twplus-empty__icon" aria-hidden="true"></i>
                    <h2 class="twplus-empty__title">Keine Antragstypen verfügbar</h2>
                    <p class="twplus-empty__description">Sobald ein Formular für dich freigegeben ist, erscheint es an dieser Stelle.</p>
                </div>
            <?php else: ?>
                <div class="twplus-link-grid">
                    <?php foreach ($typen as $typ): ?>
                        <a href="<?= BASE_PATH . 'forms/create?typ=' . (int) $typ->id ?>"
                            class="twplus-link-card">
                            <span class="twplus-link-card__icon"><i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i></span>
                            <div class="twplus-link-card__body">
                                <h4 class="twplus-link-card__title"><?= htmlspecialchars($typ->name) ?></h4>

                                <?php if (!empty($typ->beschreibung)): ?>
                                    <p class="twplus-link-card__description">
                                        <?= htmlspecialchars($typ->beschreibung) ?>
                                    </p>
                                <?php endif; ?>

                                <div class="mt-4">
                                    <span class="ignis-btn ignis-btn--soft-primary ignis-btn--sm">
                                        <i class="fa-solid fa-arrow-right mr-1"></i>
                                        Antrag stellen
                                    </span>
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-right twplus-link-card__arrow" aria-hidden="true"></i>
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

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>
