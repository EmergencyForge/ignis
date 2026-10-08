<?php
/**
 * View: POI-Verwaltung
 *
 * Suche, Sortierung, Filter (Typ, aktiv) und Seiten laufen über den Server
 * (App\Support\ListQuery, PoiController::index). Anlegen und Bearbeiten
 * öffnen einen Dialog, Löschen steckt im Bearbeiten-Dialog.
 *
 * @var \Illuminate\Support\Collection<int, array<string,mixed>> $pois           Zeilen der aktuellen Seite
 * @var \App\Support\ListQuery                                    $list
 * @var array<int|string,int>                                     $counts         POIs je Aktiv-Wert, '' sind alle
 * @var list<string>                                              $hospitalTypes  Typen mit Fachrichtungen
 */

use App\Auth\Permissions;

$layout     = 'admin';
$bodyId     = 'settings';
$SITE_TITLE = 'POIs';
$pgPath     = 'settings/pois/index';
$pgLabel    = 'POIs';
$canManage  = Permissions::check(['admin', 'pois.manage']);
$typFilter  = $list->filter('typ');
$poiTypes   = [
    'Polizeiwache'  => 'Polizeiwache',
    'Rettungswache' => 'Rettungswache',
    'Feuerwache'    => 'Feuerwache',
    'Krankenhaus'   => 'Krankenhaus',
    'Klinik'        => 'Ärztliche Praxis / Klinik',
    'Behörde'       => 'Behörde',
    'Schule'        => 'Schule / Bildungseinrichtung',
    'Sonstiges'     => 'Sonstiges',
];
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item" aria-current="page">POIs</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">eNOTF</p>
                    <h1>POI-Verwaltung</h1>
                    <p class="twplus-page-header__description">Adressen und Transportziele für das eNOTF, dazu Fachrichtungen und Portal-Zugänge der Krankenhäuser.</p>
                </div>
                <?php if ($canManage): ?>
                    <div class="header-actions twplus-page-header__actions">
                        <a href="<?= BASE_PATH ?>settings/pois/access-codes" class="ignis-btn ignis-btn--secondary">
                            <i class="fa-solid fa-key"></i> Krankenhaus-Zugänge
                        </a>
                        <button type="button" class="ignis-btn ignis-btn--primary" data-poi-create>
                            <i class="fa-solid fa-plus"></i> POI erstellen
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <form class="ignis-list-toolbar" method="get" action="<?= BASE_PATH . $pgPath ?>" role="search">
                <?= $list->hiddenFields(['q', 'typ']) ?>
                <label class="ignis-list-toolbar__search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Name, Straße oder Ort" aria-label="POIs suchen">
                </label>
                <label class="ignis-filter">
                    <span class="ignis-filter__label">Typ</span>
                    <select class="ignis-input" data-custom-dropdown="true" name="typ">
                        <option value="">Alle</option>
                        <?php foreach ($poiTypes as $typValue => $typLabel): ?>
                            <option value="<?= htmlspecialchars($typValue) ?>"<?= $typFilter === $typValue ? ' selected' : '' ?>><?= htmlspecialchars($typLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="ignis-btn ignis-btn--secondary" data-ignis-filter-submit>Suchen</button>
                <?php if ($list->q !== '' || $typFilter !== ''): ?>
                    <a class="ignis-btn ignis-btn--ghost" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'typ' => null, 'page' => null])) ?>">Zurücksetzen</a>
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
                    <table class="ignis-table" id="table-pois">
                        <thead>
                            <tr>
                                <?= $list->th('name', 'Name', $pgPath) ?>
                                <?= $list->th('strasse', 'Straße', $pgPath) ?>
                                <?= $list->th('hnr', 'HNR', $pgPath) ?>
                                <?= $list->th('ort', 'Ort', $pgPath) ?>
                                <?= $list->th('ortsteil', 'Ortsteil', $pgPath) ?>
                                <?= $list->th('typ', 'Typ', $pgPath) ?>
                                <?= $list->th('active', 'Aktiv?', $pgPath) ?>
                                <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pois->isEmpty()): ?>
                                <?php
                                if ($list->hasFilters()) {
                                    $emptyFilters = [];
                                    if ($typFilter !== '') {
                                        $emptyFilters[] = ['label' => $poiTypes[$typFilter] ?? $typFilter, 'removeHref' => $list->url($pgPath, ['typ' => null, 'page' => null])];
                                    }
                                    if ($list->filter('active') !== '') {
                                        $emptyFilters[] = ['label' => $list->filter('active') === '1' ? 'Aktiv' : 'Inaktiv', 'removeHref' => $list->url($pgPath, ['active' => null, 'page' => null])];
                                    }
                                    $empty = [
                                        'variant' => 'sm',
                                        'icon'    => 'fa-magnifying-glass',
                                        'title'   => 'Keine POIs gefunden',
                                        'text'    => 'Mit den gesetzten Filtern passt kein POI.',
                                        'query'   => ['term' => $list->q, 'filters' => $emptyFilters],
                                        'actions' => [['label' => 'Filter zurücksetzen', 'href' => $list->url($pgPath, ['q' => null, 'typ' => null, 'active' => null, 'page' => null]), 'style' => 'secondary']],
                                    ];
                                } else {
                                    $empty = [
                                        'variant'      => 'first',
                                        'tone'         => 'info',
                                        'icon'         => 'fa-location-dot',
                                        'ghostColumns' => 7,
                                        'title'        => 'Noch keine POIs',
                                        'text'         => 'POIs stehen im eNOTF als Adressen und Transportziele zur Auswahl.',
                                        'actions'      => $canManage
                                            ? [['label' => 'POI erstellen', 'style' => 'secondary', 'icon' => 'fa-plus', 'attrs' => ['data-poi-create' => '']]]
                                            : [],
                                    ];
                                }
                                ?>
                                <tr><td colspan="8"><?php require dirname(__DIR__, 5) . '/templates/partials/empty.php'; ?></td></tr>
                            <?php endif; ?>
                            <?php foreach ($pois as $row):
                                $isActive = (int) $row['active'] !== 0;
                                $rowId    = (int) $row['id'];
                            ?>
                                <tr<?= $isActive ? '' : ' class="is-muted"' ?>>
                                    <td><span data-poi-card="<?= $rowId ?>" style="cursor:help;"><?= htmlspecialchars((string) $row['name']) ?></span></td>
                                    <?php foreach (['strasse', 'hnr', 'ort', 'ortsteil', 'typ'] as $col): ?>
                                        <td><?= ($row[$col] ?? '') !== '' ? htmlspecialchars((string) $row[$col]) : '<span class="text-tertiary-text">-</span>' ?></td>
                                    <?php endforeach; ?>
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
                                                <?php if (in_array($row['typ'], $hospitalTypes, true)): ?>
                                                    <a href="<?= BASE_PATH ?>settings/pois/departments?poi_id=<?= $rowId ?>" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="Fachrichtungen verwalten" aria-label="Fachrichtungen verwalten"><i class="fa-solid fa-hospital"></i></a>
                                                <?php endif; ?>
                                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" onclick="openEditPoiModal(this)" data-ignis-tooltip="POI bearbeiten" aria-label="POI bearbeiten"
                                                    data-id="<?= $rowId ?>"
                                                    data-name="<?= htmlspecialchars((string) $row['name'], ENT_QUOTES) ?>"
                                                    data-strasse="<?= htmlspecialchars((string) ($row['strasse'] ?? ''), ENT_QUOTES) ?>"
                                                    data-hnr="<?= htmlspecialchars((string) ($row['hnr'] ?? ''), ENT_QUOTES) ?>"
                                                    data-ort="<?= htmlspecialchars((string) $row['ort'], ENT_QUOTES) ?>"
                                                    data-ortsteil="<?= htmlspecialchars((string) ($row['ortsteil'] ?? ''), ENT_QUOTES) ?>"
                                                    data-typ="<?= htmlspecialchars((string) ($row['typ'] ?? ''), ENT_QUOTES) ?>"
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
        <template id="poiFormTemplate">
            <div class="mb-3"><label for="poi-name" class="ignis-field__label">Name / Objekt / Einrichtung *</label><input type="text" class="ignis-input" name="name" id="poi-name" maxlength="255" required></div>
            <div class="mb-3"><label for="poi-strasse" class="ignis-field__label">Straße</label><input type="text" class="ignis-input" name="strasse" id="poi-strasse" maxlength="255"></div>
            <div class="mb-3"><label for="poi-hnr" class="ignis-field__label">Hausnummer / Postal</label><input type="text" class="ignis-input" name="hnr" id="poi-hnr" maxlength="50"></div>
            <div class="mb-3"><label for="poi-ort" class="ignis-field__label">Ort *</label><input type="text" class="ignis-input" name="ort" id="poi-ort" maxlength="255" required></div>
            <div class="mb-3"><label for="poi-ortsteil" class="ignis-field__label">Ortsteil</label><input type="text" class="ignis-input" name="ortsteil" id="poi-ortsteil" maxlength="255"></div>
            <div class="mb-3">
                <label for="poi-typ" class="ignis-field__label">Typ</label>
                <select class="ignis-input" name="typ" id="poi-typ" data-custom-dropdown="true">
                    <option value="">Kein Typ</option>
                    <?php foreach ($poiTypes as $typValue => $typLabel): ?>
                        <option value="<?= htmlspecialchars($typValue) ?>"><?= htmlspecialchars($typLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="ignis-checkbox" for="poi-active"><input type="checkbox" name="active" id="poi-active"><span>Aktiv?</span></label>
        </template>

        <form id="delete-poi-form" action="<?= BASE_PATH ?>settings/pois/delete" method="POST" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="poi-delete-id">
        </form>

        <script>
            document.querySelectorAll('[data-poi-create]').forEach(function (btn) {
                btn.addEventListener('click', openCreatePoiModal);
            });

            function openCreatePoiModal() {
                Dialog.form({
                    title:        'Neuen POI anlegen',
                    template:     'poiFormTemplate',
                    formAction:   '<?= BASE_PATH ?>settings/pois/create',
                    submitLabel:  'Erstellen',
                    submitVariant:'success',
                    onOpen: function (dlg) {
                        $(dlg.element).find('#poi-active').prop('checked', true);
                    },
                });
            }

            function openEditPoiModal(btn) {
                var data = btn.dataset;
                document.getElementById('poi-delete-id').value = data.id;

                Dialog.form({
                    title:        'POI bearbeiten (ID: ' + data.id + ')',
                    template:     'poiFormTemplate',
                    formAction:   '<?= BASE_PATH ?>settings/pois/update',
                    hiddenFields: { id: data.id },
                    submitLabel:  'Speichern',
                    submitVariant:'soft-primary',
                    dangerAction: {
                        label:   'Löschen',
                        onClick: function () {
                            showConfirm('Möchtest du diesen POI wirklich löschen?', {
                                danger:      true,
                                confirmText: 'Löschen',
                                title:       'POI löschen',
                            }).then(function (ok) {
                                if (ok) document.getElementById('delete-poi-form').submit();
                            });
                        },
                    },
                    onOpen: function (dlg) {
                        var $body = $(dlg.element);
                        $body.find('#poi-name').val(data.name);
                        $body.find('#poi-strasse').val(data.strasse || '');
                        $body.find('#poi-hnr').val(data.hnr || '');
                        $body.find('#poi-ort').val(data.ort);
                        $body.find('#poi-ortsteil').val(data.ortsteil || '');
                        $body.find('#poi-typ').val(data.typ || '');
                        $body.find('#poi-active').prop('checked', data.active == 1);
                    },
                });
            }
        </script>
    <?php endif; ?>
