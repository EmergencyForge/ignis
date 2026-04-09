<?php
/**
 * View: Antragstyp-Auswahl
 *
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\AntragTyp> $typen
 * @var \PDO                                                                  $pdo
 */

use App\Helpers\Flash;

$SITE_TITLE = "Antrag einreichen";
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . "/../../assets/components/_base/admin/head.php"; ?>
    <style>
        .antrag-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid rgba(var(--bs-light-rgb), 0.1);
            background: rgba(var(--bs-dark-rgb), 0.5);
        }

        .antrag-card h4 {
            color: var(--white);
        }

        .antrag-card:hover {
            transform: translateY(-5px);
            border-color: var(--main-color);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        }

        .antrag-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--main-color);
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="antrag-select">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <h1>Neuen Antrag stellen</h1>

                    <?php Flash::render(); ?>

                    <?php if ($typen->isEmpty()): ?>
                        <div class="alert alert-info">
                            <i class="fa-solid fa-circle-info me-2"></i>
                            Aktuell sind keine Antragstypen verfügbar.
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($typen as $typ): ?>
                                <div class="col-md-6 col-lg-4">
                                    <a href="<?= BASE_PATH . 'antrag/create.php?typ=' . (int) $typ->id ?>"
                                        class="text-decoration-none">
                                        <div class="antrag-card p-4 rounded text-center h-100">
                                            <h4 class="mb-3"><?= htmlspecialchars($typ->name) ?></h4>

                                            <?php if (!empty($typ->beschreibung)): ?>
                                                <p class="text-muted small mb-3">
                                                    <?= htmlspecialchars($typ->beschreibung) ?>
                                                </p>
                                            <?php endif; ?>

                                            <div class="mt-3">
                                                <button class="btn btn-soft-primary btn-sm">
                                                    <i class="fa-solid fa-arrow-right me-1"></i>
                                                    Antrag stellen
                                                </button>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <a href="<?= BASE_PATH ?>index.php" class="btn btn-ghost">
                            <i class="fas fa-arrow-left me-2"></i>Zurück zum Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>
