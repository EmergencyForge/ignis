<?php
/**
 * Liste der Briefbögen.
 *
 * Erwartete Variablen (LetterheadController::index()):
 *   @var \Illuminate\Support\Collection<int,\App\Models\Letterhead> $letterheads
 *   @var array<int,int> $usage  Briefbogen-ID auf Zahl der Vorlagen, die ihn nutzen
 */

use App\Security\CsrfProtection;

$csrfToken  = CsrfProtection::getToken();
$layout     = 'admin';
$bodyId     = 'settings';
$SITE_TITLE = 'Briefbögen';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb">
                <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span>
                <span class="ignis-breadcrumb__item" aria-current="page">Briefbögen</span>
            </nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Dokumente</p>
                    <h1>Briefbögen</h1>
                    <p class="twplus-page-header__description">
                        Seitenränder, Kopf- und Fußzeilen, die mehrere Vorlagen teilen. Eine Änderung gilt für
                        alle Vorlagen, die den Briefbogen nutzen.
                    </p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>settings/documents/letterheads/create" class="ignis-btn ignis-btn--primary">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Neuer Briefbogen
                    </a>
                </div>
            </div>

            <div class="twplus-table-card">
                <table class="ignis-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Genutzt von</th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($letterheads as $letterhead): ?>
                            <?php $count = $usage[$letterhead->id] ?? 0; ?>
                            <tr>
                                <td><?= htmlspecialchars($letterhead->name) ?></td>
                                <td><?= $count === 1 ? '1 Vorlage' : $count . ' Vorlagen' ?></td>
                                <td>
                                    <div class="flex gap-1 items-center">
                                        <a href="<?= BASE_PATH ?>settings/documents/letterheads/<?= (int) $letterhead->id ?>"
                                           class="ignis-btn ignis-btn--secondary" data-ignis-tooltip="Bearbeiten" aria-label="Bearbeiten">
                                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                        </a>
                                        <?php if ($count === 0): ?>
                                            <form method="POST"
                                                  action="<?= BASE_PATH ?>settings/documents/letterheads/<?= (int) $letterhead->id ?>/delete"
                                                  onsubmit="<?= confirm_attr('Briefbogen "' . $letterhead->name . '" wirklich löschen?') ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <button type="submit" class="ignis-btn ignis-btn--ghost" data-ignis-tooltip="Löschen" aria-label="Löschen">
                                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($letterheads->isEmpty()): ?>
                    <?php
                    $empty = [
                        'variant' => 'sm',
                        'tone'    => 'info',
                        'icon'    => 'fa-envelope-open-text',
                        'heading' => 2,
                        'title'   => 'Noch keine Briefbögen',
                        'text'    => 'Ein Briefbogen hält Kopf- und Fußzeilen, die mehrere Vorlagen teilen.',
                        'actions' => [['label' => 'Neuer Briefbogen', 'href' => BASE_PATH . 'settings/documents/letterheads/create', 'style' => 'secondary', 'icon' => 'fa-plus']],
                    ];
                    require dirname(__DIR__, 2) . '/partials/empty.php';
                    ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
