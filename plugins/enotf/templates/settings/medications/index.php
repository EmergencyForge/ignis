<?php
/**
 * View: Medikamentenverwaltung
 *
 * Suche, Sortierung, Aktiv-Filter und Seiten laufen über den Server
 * (App\Support\ListQuery, MedikamenteController::index). Anlegen und
 * Bearbeiten öffnen einen Dialog, Löschen steckt im Bearbeiten-Dialog.
 *
 * @var \Illuminate\Support\Collection<int, array<string,mixed>> $medikamente  Zeilen der aktuellen Seite
 * @var \App\Support\ListQuery                                    $list
 * @var array<int|string,int>                                     $counts       Medikamente je Aktiv-Wert, '' sind alle
 */

use App\Auth\Permissions;

$layout     = 'admin';
$bodyId     = 'settings';
$SITE_TITLE = 'Medikamente';
$pgPath     = 'settings/medications/index';
$pgLabel    = 'Medikamenten';
$canManage  = Permissions::check('admin');
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Medikamente</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">eNOTF</p>
                    <h1>Medikamentenverwaltung</h1>
                    <p class="twplus-page-header__description">Wirkstoffe und Dosierungen, die im eNOTF zur Auswahl stehen.</p>
                </div>
                <?php if ($canManage): ?>
                    <div class="header-actions twplus-page-header__actions">
                        <button type="button" class="ignis-btn ignis-btn--primary" data-medikament-create>
                            <i class="fa-solid fa-plus"></i> Medikament erstellen
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <form class="ignis-list-toolbar" method="get" action="<?= BASE_PATH . $pgPath ?>" role="search">
                <?= $list->hiddenFields(['q']) ?>
                <label class="ignis-list-toolbar__search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Wirkstoff, Hersteller oder Dosierung" aria-label="Medikamente suchen">
                </label>
                <button type="submit" class="ignis-btn ignis-btn--secondary">Suchen</button>
                <?php if ($list->q !== ''): ?>
                    <a class="ignis-btn ignis-btn--ghost" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'page' => null])) ?>">Zurücksetzen</a>
                <?php endif; ?>
                <span class="ignis-list-toolbar__spacer"></span>
                <nav class="ignis-segmented" aria-label="Aktiv">
                    <?php foreach (['' => 'Alle', '1' => 'Aktiv', '0' => 'Inaktiv'] as $activeKey => $activeLabel): ?>
                        <a href="<?= htmlspecialchars($list->url($pgPath, ['active' => $activeKey === '' ? null : $activeKey, 'page' => null])) ?>"<?= $list->filter('active') === (string) $activeKey ? ' class="is-active" aria-current="true"' : '' ?>><?= $activeLabel ?> <span class="ignis-segmented__count"><?= $counts[$activeKey] ?? 0 ?></span></a>
                    <?php endforeach; ?>
                </nav>
            </form>

            <div class="twplus-table-card">
                <div class="twplus-table-card__scroll">
                    <table class="ignis-table" id="table-medikamente">
                        <thead>
                            <tr>
                                <?= $list->th('priority', 'Priorität', $pgPath, 'ignis-table__num') ?>
                                <?= $list->th('wirkstoff', 'Wirkstoff', $pgPath) ?>
                                <?= $list->th('herstellername', 'Herstellername', $pgPath) ?>
                                <th scope="col">Dosierungen</th>
                                <?= $list->th('active', 'Aktiv?', $pgPath) ?>
                                <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($medikamente->isEmpty()): ?>
                                <?php
                                if ($list->hasFilters()) {
                                    $empty = [
                                        'variant' => 'sm',
                                        'icon'    => 'fa-magnifying-glass',
                                        'title'   => 'Keine Medikamente gefunden',
                                        'text'    => 'Mit den gesetzten Filtern passt kein Medikament.',
                                        'query'   => [
                                            'term'    => $list->q,
                                            'filters' => $list->filter('active') !== ''
                                                ? [['label' => $list->filter('active') === '1' ? 'Aktiv' : 'Inaktiv', 'removeHref' => $list->url($pgPath, ['active' => null, 'page' => null])]]
                                                : [],
                                        ],
                                        'actions' => [['label' => 'Filter zurücksetzen', 'href' => $list->url($pgPath, ['q' => null, 'active' => null, 'page' => null]), 'style' => 'secondary']],
                                    ];
                                } else {
                                    $empty = [
                                        'variant'      => 'first',
                                        'tone'         => 'info',
                                        'icon'         => 'fa-pills',
                                        'ghostColumns' => 5,
                                        'title'        => 'Noch keine Medikamente',
                                        'text'         => 'Medikamente wählt die Besatzung im eNOTF bei der Medikation aus.',
                                        'actions'      => $canManage
                                            ? [['label' => 'Medikament erstellen', 'style' => 'secondary', 'icon' => 'fa-plus', 'attrs' => ['data-medikament-create' => '']]]
                                            : [],
                                    ];
                                }
                                ?>
                                <tr><td colspan="6"><?php require dirname(__DIR__, 5) . '/templates/partials/empty.php'; ?></td></tr>
                            <?php endif; ?>
                            <?php foreach ($medikamente as $row):
                                $isActive    = (int) $row['active'] !== 0;
                                $dosierungen = (string) ($row['dosierungen'] ?? '');
                            ?>
                                <tr<?= $isActive ? '' : ' class="is-muted"' ?>>
                                    <td class="ignis-table__num"><?= (int) $row['priority'] ?></td>
                                    <td><?= htmlspecialchars((string) $row['wirkstoff']) ?></td>
                                    <td><?= ($row['herstellername'] ?? '') !== '' ? htmlspecialchars((string) $row['herstellername']) : '<span class="text-tertiary-text">-</span>' ?></td>
                                    <td><?= $dosierungen !== '' ? htmlspecialchars(implode(', ', array_map('trim', explode(',', $dosierungen)))) : '<span class="text-tertiary-text">-</span>' ?></td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="ignis-chip ignis-chip--dot ignis-chip--ok">Ja</span>
                                        <?php else: ?>
                                            <span class="ignis-chip ignis-chip--dot ignis-chip--danger">Nein</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ignis-table__actions">
                                        <?php if ($canManage): ?>
                                            <div class="ignis-row-actions">
                                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" onclick="openEditMedikamentModal(this)" data-ignis-tooltip="Medikament bearbeiten" aria-label="Medikament bearbeiten"
                                                    data-id="<?= (int) $row['id'] ?>"
                                                    data-wirkstoff="<?= htmlspecialchars((string) $row['wirkstoff'], ENT_QUOTES) ?>"
                                                    data-herstellername="<?= htmlspecialchars((string) ($row['herstellername'] ?? ''), ENT_QUOTES) ?>"
                                                    data-dosierungen="<?= htmlspecialchars($dosierungen, ENT_QUOTES) ?>"
                                                    data-priority="<?= (int) $row['priority'] ?>"
                                                    data-active="<?= (int) $row['active'] ?>"><i class="fa-solid fa-pen"></i></button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php require dirname(__DIR__, 5) . '/templates/partials/pagination.php'; ?>
            </div>
        </div>
    </div>

    <?php if ($canManage): ?>
        <template id="medikamentFormTemplate">
            <div class="mb-3">
                <label for="medikament-wirkstoff" class="ignis-field__label">Wirkstoff</label>
                <input type="text" class="ignis-input" name="wirkstoff" id="medikament-wirkstoff" maxlength="255" required>
            </div>
            <div class="mb-3">
                <label for="medikament-herstellername" class="ignis-field__label">Herstellername <small class="form-hint">(optional, z.B. "ASS" für Acetylsalicylsäure)</small></label>
                <input type="text" class="ignis-input" name="herstellername" id="medikament-herstellername" maxlength="255">
            </div>
            <div class="mb-3">
                <label for="medikament-dosierungen" class="ignis-field__label">Vordefinierte Dosierungen <small class="form-hint">(kommagetrennt, z.B. "100 mg,250 mg,500 mg")</small></label>
                <input type="text" class="ignis-input" name="dosierungen" id="medikament-dosierungen" placeholder="100 mg,250 mg,500 mg">
            </div>
            <div class="mb-3">
                <label for="medikament-priority" class="ignis-field__label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                <input type="number" class="ignis-input" name="priority" id="medikament-priority" value="0" required>
            </div>
            <label class="ignis-checkbox" for="medikament-active"><input type="checkbox" name="active" id="medikament-active"><span>Aktiv?</span></label>
        </template>

        <form id="delete-medikament-form" action="<?= BASE_PATH ?>settings/medications/delete" method="POST" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="medikament-delete-id">
        </form>

        <script>
            document.querySelectorAll('[data-medikament-create]').forEach(function (btn) {
                btn.addEventListener('click', openCreateMedikamentModal);
            });

            function openCreateMedikamentModal() {
                Dialog.form({
                    title:        'Neues Medikament anlegen',
                    template:     'medikamentFormTemplate',
                    formAction:   '<?= BASE_PATH ?>settings/medications/create',
                    submitLabel:  'Erstellen',
                    submitVariant:'success',
                    onOpen: function (dlg) {
                        // Ein neues Medikament ist meist sofort in Gebrauch.
                        $(dlg.element).find('#medikament-active').prop('checked', true);
                    },
                });
            }

            function openEditMedikamentModal(btn) {
                var data = btn.dataset;
                document.getElementById('medikament-delete-id').value = data.id;

                Dialog.form({
                    title:        'Medikament bearbeiten',
                    template:     'medikamentFormTemplate',
                    formAction:   '<?= BASE_PATH ?>settings/medications/update',
                    hiddenFields: { id: data.id },
                    submitLabel:  'Speichern',
                    submitVariant:'soft-primary',
                    dangerAction: {
                        label:   'Löschen',
                        onClick: function () {
                            showConfirm('Möchtest du dieses Medikament wirklich löschen?', {
                                danger:      true,
                                confirmText: 'Löschen',
                                title:       'Medikament löschen',
                            }).then(function (ok) {
                                if (ok) document.getElementById('delete-medikament-form').submit();
                            });
                        },
                    },
                    onOpen: function (dlg) {
                        var $body = $(dlg.element);
                        $body.find('#medikament-wirkstoff').val(data.wirkstoff);
                        $body.find('#medikament-herstellername').val(data.herstellername);
                        $body.find('#medikament-dosierungen').val(data.dosierungen);
                        $body.find('#medikament-priority').val(data.priority);
                        $body.find('#medikament-active').prop('checked', data.active == 1);
                    },
                });
            }
        </script>
    <?php endif; ?>
