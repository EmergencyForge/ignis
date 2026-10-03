<?php
/**
 * Liste der Vorlagen des Dokumenten-Editors.
 *
 * Erwartete Variablen (EditorTemplateController::index()):
 *   @var \Illuminate\Support\Collection<int,\App\Models\EditorTemplate> $templates
 *
 * Gelöscht wird nur, was noch kein Dokument benutzt, sonst deaktiviert der
 * Controller die Vorlage. Die Rückfrage sagt vorher, welcher der beiden
 * Fälle eintritt.
 */

use App\Security\CsrfProtection;

$csrfToken  = CsrfProtection::getToken();
$layout     = 'admin';
$bodyId     = 'settings';
$SITE_TITLE = 'Dokumentvorlagen';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb">
                <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span>
                <span class="ignis-breadcrumb__item" aria-current="page">Dokumentvorlagen</span>
            </nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Dokumente</p>
                    <h1>Dokumentvorlagen</h1>
                    <p class="twplus-page-header__description">
                        Vorlagen mit gesperrten und freien Abschnitten für ausgestellte Dokumente.
                    </p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>settings/documents/editor-templates/create" class="ignis-btn ignis-btn--primary">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Neue Vorlage
                    </a>
                </div>
            </div>

            <div class="twplus-table-card">
                <table class="ignis-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Kategorie</th>
                            <th scope="col">Status</th>
                            <th scope="col">Dokumente</th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template): ?>
                            <?php
                            $usage   = (int) $template->documents_count;
                            $confirm = $usage > 0
                                ? 'Vorlage "' . $template->name . '" wird noch von ' . $usage
                                    . ' Dokument(en) verwendet und kann nicht gelöscht werden. Stattdessen deaktivieren?'
                                : 'Vorlage "' . $template->name . '" wirklich löschen?';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($template->name) ?></td>
                                <td><?= htmlspecialchars($template->category ?? '-') ?></td>
                                <td>
                                    <?php if ($template->is_active): ?>
                                        <span class="ignis-chip ignis-chip--ok">Aktiv</span>
                                    <?php else: ?>
                                        <span class="ignis-chip ignis-chip--secondary">Deaktiviert</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $usage ?></td>
                                <td>
                                    <div class="flex gap-1 items-center">
                                        <a href="<?= BASE_PATH ?>settings/documents/editor-templates/<?= (int) $template->id ?>"
                                           class="ignis-btn ignis-btn--secondary" data-ignis-tooltip="Bearbeiten" aria-label="Bearbeiten">
                                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                        </a>
                                        <form method="POST"
                                              action="<?= BASE_PATH ?>settings/documents/editor-templates/<?= (int) $template->id ?>/deactivate"
                                              onsubmit="<?= confirm_attr($confirm) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <button type="submit" class="ignis-btn ignis-btn--ghost" data-ignis-tooltip="Löschen" aria-label="Löschen">
                                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($templates->isEmpty()): ?>
                    <?php
                    $empty = [
                        'variant' => 'sm',
                        'tone'    => 'info',
                        'icon'    => 'fa-file-lines',
                        'heading' => 2,
                        'title'   => 'Noch keine Vorlagen',
                        'text'    => 'Vorlagen legen Briefkopf und feste Abschnitte der Dokumente fest.',
                        'actions' => [['label' => 'Neue Vorlage', 'href' => BASE_PATH . 'settings/documents/editor-templates/create', 'style' => 'secondary', 'icon' => 'fa-plus']],
                    ];
                    require dirname(__DIR__, 2) . '/partials/empty.php';
                    ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
