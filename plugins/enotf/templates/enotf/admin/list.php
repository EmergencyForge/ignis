<?php
/**
 * View: eNOTF-Prüfliste (QM)
 *
 * Sortierung, Suche und Seiten laufen über den Server (App\Support\ListQuery,
 * EnotfAdminController::listAction). Das Segment steht in `?view=0|1|2`,
 * Suche, Sortierung und Seiten tragen es weiter; das Dashboard verlinkt
 * `?view=2`. Protokolle aus dem Verbund stehen nur lesend dabei. Die
 * QM-Dialoge und das Löschen leerer Protokolle stecken in
 * assets/js/modules/enotf-admin-list.js.
 *
 * @var array<int,array<string,mixed>>                  $protocols
 * @var \App\Support\ListQuery                          $list
 * @var int                                             $segment  `?view=`: 0 alle, 1 unbearbeitet, 2 nicht freigegeben
 * @var array{all:int, unprocessed:int, unreleased:int} $counts   Protokolle je Segment, mit der Suche
 * @var bool                                            $canEdit  QM-Aktionen und Löschen (admin, edivi.edit)
 */

use Plugin\Enotf\Helpers\EnotfUrl;

$layout = 'admin';
$bodyId = 'protokolle';
$SITE_TITLE = 'eNOTF-QM';

$pgPath  = 'enotf/admin/list';
$pgLabel = 'Protokollen';

$statusMap = [
    0 => ['secondary', 'Ungesehen'],
    1 => ['warn', 'in Prüfung'],
    2 => ['ok', 'Geprüft'],
    4 => ['secondary', 'Ausgeblendet'],
];
$segmentAttr = static fn (int $value): string => $segment === $value ? ' class="is-active" aria-current="true"' : '';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Protokolle</span> <span class="ignis-breadcrumb__item" aria-current="page">eNOTF QM</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">eNOTF</p>
                    <h1>Protokollübersicht</h1>
                    <p class="twplus-page-header__description">Alle Protokolle mit Prüfstand und Freigabe.</p>
                </div>
                <?php if ($canEdit): ?>
                    <div class="header-actions twplus-page-header__actions">
                        <button type="button" class="ignis-btn ignis-btn--secondary" onclick="showBulkDeleteModal()">
                            <i class="fa-solid fa-trash-can"></i> Leere Protokolle löschen
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <form class="ignis-list-toolbar" method="get" action="<?= BASE_PATH . $pgPath ?>" role="search">
                <?= $list->hiddenFields(['q']) ?>
                <label class="ignis-list-toolbar__search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Einsatznummer, Patient oder Protokollant" aria-label="Protokolle suchen">
                </label>
                <button type="submit" class="ignis-btn ignis-btn--secondary">Suchen</button>
                <?php if ($list->q !== ''): ?>
                    <a class="ignis-btn ignis-btn--ghost" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'page' => null])) ?>">Zurücksetzen</a>
                <?php endif; ?>
                <span class="ignis-list-toolbar__spacer"></span>
                <nav class="ignis-segmented" aria-label="Status">
                    <a href="<?= htmlspecialchars($list->url($pgPath, ['view' => null, 'page' => null])) ?>"<?= $segmentAttr(0) ?>>Alle <span class="ignis-segmented__count"><?= $counts['all'] ?></span></a>
                    <a href="<?= htmlspecialchars($list->url($pgPath, ['view' => '1', 'page' => null])) ?>"<?= $segmentAttr(1) ?>><i class="fa-solid fa-triangle-exclamation" data-tone="warn" aria-hidden="true"></i>Unbearbeitet <span class="ignis-segmented__count"><?= $counts['unprocessed'] ?></span></a>
                    <a href="<?= htmlspecialchars($list->url($pgPath, ['view' => '2', 'page' => null])) ?>"<?= $segmentAttr(2) ?>><i class="fa-solid fa-circle-xmark" data-tone="danger" aria-hidden="true"></i>Nicht freigegeben <span class="ignis-segmented__count"><?= $counts['unreleased'] ?></span></a>
                </nav>
            </form>

            <div class="twplus-table-card">
                <div class="twplus-table-card__scroll">
                    <table class="ignis-table" id="table-protokoll">
                        <thead>
                            <tr>
                                <?= $list->th('nr', 'Einsatznummer', $pgPath) ?>
                                <?= $list->th('patient', 'Patient', $pgPath) ?>
                                <?= $list->th('created', 'Angelegt am', $pgPath) ?>
                                <?= $list->th('author', 'Protokollant', $pgPath) ?>
                                <?= $list->th('status', 'Status', $pgPath) ?>
                                <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($protocols === []): ?>
                                <?php
                                if ($list->q !== '') {
                                    $empty = [
                                        'variant' => 'sm',
                                        'icon'    => 'fa-magnifying-glass',
                                        'title'   => 'Keine Protokolle gefunden',
                                        'text'    => 'Mit dieser Suche passt kein Protokoll.',
                                        'query'   => ['term' => $list->q],
                                        'actions' => [['label' => 'Suche zurücksetzen', 'href' => $list->url($pgPath, ['q' => null, 'page' => null]), 'style' => 'secondary']],
                                    ];
                                } elseif ($segment === 1) {
                                    $empty = ['variant' => 'sm', 'tone' => 'ok', 'icon' => 'fa-circle-check', 'title' => 'Nichts unbearbeitet', 'text' => 'Alle Protokolle sind geprüft.'];
                                } elseif ($segment === 2) {
                                    $empty = ['variant' => 'sm', 'tone' => 'ok', 'icon' => 'fa-circle-check', 'title' => 'Alles freigegeben', 'text' => 'Kein Protokoll wartet auf die Freigabe.'];
                                } else {
                                    $empty = ['variant' => 'sm', 'icon' => 'fa-file-medical', 'title' => 'Noch keine Protokolle', 'text' => 'Protokolle aus dem eNOTF erscheinen hier, sobald jemand eines anlegt.'];
                                }
                                ?>
                                <tr><td colspan="6"><?php require dirname(__DIR__, 5) . '/templates/partials/empty.php'; ?></td></tr>
                            <?php endif; ?>
                            <?php foreach ($protocols as $row): ?>
                                <?php
                                $isFederated = $row['federation_source'] !== null;
                                [$statusTone, $statusText] = $statusMap[(int) $row['protokoll_status']] ?? ['danger', 'Ungenügend'];
                                $checker = (int) $row['protokoll_status'] !== 0 ? 'Prüfer: ' . ($row['bearbeiter'] ?? '') : '';
                                $sent = $row['sendezeit'] ? (new DateTime((string) $row['sendezeit']))->format('d.m.Y | H:i') : '';
                                ?>
                                <tr<?= $isFederated ? ' class="is-muted"' : '' ?>>
                                    <td>
                                        <?= htmlspecialchars((string) ($row['enr'] ?? '')) ?>
                                        <?php if ($isFederated): ?>
                                            <span class="ignis-chip ignis-chip--secondary"><?= htmlspecialchars((string) $row['federation_source']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($row['patname'] ?? 'Unbekannt')) ?></td>
                                    <td><?= htmlspecialchars($sent) ?></td>
                                    <td>
                                        <?= htmlspecialchars((string) ($row['pfname'] ?? '')) ?>
                                        <?php if ((int) $row['hidden_user'] === 1): ?>
                                            <span class="ignis-chip ignis-chip--sm ignis-chip--danger" data-ignis-tooltip="<?= htmlspecialchars('Gelöscht: ' . ($row['freigeber_name'] ?? ''), ENT_QUOTES) ?>">G</span>
                                        <?php elseif ((int) $row['freigegeben'] === 1): ?>
                                            <span class="ignis-chip ignis-chip--sm ignis-chip--ok" data-ignis-tooltip="<?= htmlspecialchars('Freigeber: ' . ($row['freigeber_name'] ?? ''), ENT_QUOTES) ?>">F</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="ignis-chip ignis-chip--dot ignis-chip--<?= $statusTone ?>"<?= $checker !== '' ? ' data-ignis-tooltip="' . htmlspecialchars($checker, ENT_QUOTES) . '"' : '' ?>><?= $statusText ?></span></td>
                                    <td class="ignis-table__actions">
                                        <?php if ($isFederated): ?>
                                            <span class="ignis-list-meta">nur lesen</span>
                                        <?php else: ?>
                                            <div class="ignis-row-actions">
                                                <a class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" href="<?= htmlspecialchars(EnotfUrl::protokoll((string) $row['enr']), ENT_QUOTES) ?>" target="_blank" data-ignis-tooltip="Protokoll ansehen" aria-label="Protokoll ansehen"><i class="fa-solid fa-eye" aria-hidden="true"></i></a>
                                                <?php if ($canEdit): ?>
                                                    <?php $qmData = ' data-id="' . (int) $row['id'] . '" data-enr="' . htmlspecialchars((string) $row['enr'], ENT_QUOTES) . '" data-patname="' . htmlspecialchars((string) ($row['patname'] ?? 'Unbekannt'), ENT_QUOTES) . '"'; ?>
                                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-enotf-qm="actions"<?= $qmData ?> data-ignis-tooltip="QM-Aktionen öffnen" aria-label="QM-Aktionen öffnen"><i class="fa-solid fa-exclamation" aria-hidden="true"></i></button>
                                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-enotf-qm="log"<?= $qmData ?> data-ignis-tooltip="QM-Log öffnen" aria-label="QM-Log öffnen"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></button>
                                                    <?php // Fester Pfad: EnotfUrl::admin() liefert die .php-Variante, die bei POST erst intern umgeschrieben wird. ?>
                                                    <form method="post" action="<?= htmlspecialchars(BASE_PATH . 'enotf/admin/delete', ENT_QUOTES) ?>" class="inline" onsubmit="event.preventDefault(); var f = this; showConfirm('Protokoll wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Protokoll löschen'}).then(function (ok) { if (ok) f.submit(); });">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                        <input type="hidden" name="return" value="<?= htmlspecialchars(substr($list->url($pgPath), strlen(BASE_PATH)), ENT_QUOTES) ?>">
                                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon" data-ignis-tooltip="Protokoll löschen" aria-label="Protokoll löschen"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                                    </form>
                                                <?php endif; ?>
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

    <?php // Dialoge und Skript braucht nur, wer QM-Aktionen und Löschen sieht. ?>
    <?php if ($canEdit): ?>
        <!-- QM Actions Modal -->
        <div data-dialog-source class="modal" id="qmActionsModal" tabindex="-1" aria-labelledby="qmActionsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="qmActionsModalLabel">QM-Funktionen</h5>
                        <button type="button" class="btn-close" data-dialog-dismiss aria-label="Schließen"></button>
                    </div>
                    <div class="modal-body" id="qmActionsContent"></div>
                </div>
            </div>
        </div>

        <!-- QM Log Modal -->
        <div data-dialog-source class="modal" id="qmLogModal" tabindex="-1" aria-labelledby="qmLogModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="qmLogModalLabel">QM-Log</h5>
                        <button type="button" class="btn-close" data-dialog-dismiss aria-label="Schließen"></button>
                    </div>
                    <div class="modal-body" id="qmLogContent"></div>
                </div>
            </div>
        </div>

        <!-- Bulk Delete Empty Protocols Modal -->
        <div data-dialog-source class="modal" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bulkDeleteModalLabel">Leere Protokolle löschen</h5>
                        <button type="button" class="btn-close" data-dialog-dismiss aria-label="Schließen"></button>
                    </div>
                    <div class="modal-body" id="bulkDeleteContent"></div>
                    <div class="modal-footer" id="bulkDeleteFooter" style="display: none;">
                        <button type="button" class="ignis-btn ignis-btn--ghost" data-dialog-dismiss>Abbrechen</button>
                        <button type="button" class="ignis-btn ignis-btn--ghost-danger" onclick="executeBulkDelete(this)">
                            <i class="fa-solid fa-trash"></i> Jetzt löschen
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script src="<?= BASE_PATH ?>assets/js/modules/enotf-admin-list.js"></script>
        <script>
            initEnotfAdminListPage({
                qmActionsApi:  '<?= BASE_PATH ?>enotf/admin/qm-actions-modal',
                qmLogApi:      '<?= BASE_PATH ?>enotf/admin/qm-log-modal',
                bulkDeleteApi: '<?= BASE_PATH ?>api/enotf/bulk-delete-empty',
            });
        </script>
    <?php endif; ?>
