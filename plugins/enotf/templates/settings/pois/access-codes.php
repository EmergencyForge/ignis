<?php
/**
 * View: Krankenhaus-Zugangscodes
 *
 * Suche, Sortierung und Seiten laufen über den Server (App\Support\ListQuery,
 * PoiController::accessCodes). Der Dialog erzeugt den Code im Browser und
 * schickt ihn an PoiController::accessCodeStore.
 *
 * @var \Illuminate\Support\Collection<int, array<string,mixed>> $hospitals  Zeilen der aktuellen Seite
 * @var \App\Support\ListQuery                                    $list
 */

$layout     = 'admin';
$bodyId     = 'settings';
$SITE_TITLE = 'Krankenhaus-Zugänge';
$pgPath     = 'settings/pois/access-codes';
$pgLabel    = 'Krankenhäusern';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/pois/index">POIs</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Krankenhaus-Zugänge</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">eNOTF</p>
                    <h1>Krankenhaus-Zugangscodes</h1>
                    <p class="twplus-page-header__description">Mit seinem Zugangscode meldet ein Krankenhaus die Verfügbarkeit seiner Fachrichtungen über das Portal.</p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>settings/pois/index" class="ignis-btn ignis-btn--ghost">
                        <i class="fa-solid fa-arrow-left"></i> Zurück zur POI-Verwaltung
                    </a>
                </div>
            </div>

            <form class="ignis-list-toolbar" method="get" action="<?= BASE_PATH . $pgPath ?>" role="search">
                <?= $list->hiddenFields(['q']) ?>
                <label class="ignis-list-toolbar__search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Krankenhaus oder Ort" aria-label="Krankenhäuser suchen">
                </label>
                <button type="submit" class="ignis-btn ignis-btn--secondary">Suchen</button>
                <?php if ($list->q !== ''): ?>
                    <a class="ignis-btn ignis-btn--ghost" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'page' => null])) ?>">Zurücksetzen</a>
                <?php endif; ?>
            </form>

            <div class="twplus-table-card">
                <div class="twplus-table-card__scroll">
                    <table class="ignis-table" id="table-access-codes">
                        <thead>
                            <tr>
                                <?= $list->th('name', 'Krankenhaus', $pgPath) ?>
                                <?= $list->th('ort', 'Ort', $pgPath) ?>
                                <?= $list->th('depts', 'Fachrichtungen', $pgPath) ?>
                                <th scope="col">Zugangscode</th>
                                <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($hospitals->isEmpty()): ?>
                                <?php
                                $empty = $list->q !== ''
                                    ? [
                                        'variant' => 'sm',
                                        'icon'    => 'fa-magnifying-glass',
                                        'title'   => 'Kein Krankenhaus gefunden',
                                        'text'    => 'Zu dieser Suche passt kein Krankenhaus.',
                                        'query'   => ['term' => $list->q],
                                        'actions' => [['label' => 'Suche zurücksetzen', 'href' => $list->url($pgPath, ['q' => null, 'page' => null]), 'style' => 'secondary']],
                                    ]
                                    : [
                                        'variant'      => 'first',
                                        'tone'         => 'info',
                                        'icon'         => 'fa-key',
                                        'ghostColumns' => 4,
                                        'title'        => 'Noch keine Krankenhäuser',
                                        'text'         => 'Zugangscodes gibt es für POIs vom Typ Krankenhaus oder Ärztliche Praxis / Klinik.',
                                        'actions'      => [['label' => 'Zur POI-Verwaltung', 'href' => BASE_PATH . 'settings/pois/index', 'style' => 'secondary']],
                                    ];
                                ?>
                                <tr><td colspan="5"><?php require dirname(__DIR__, 5) . '/templates/partials/empty.php'; ?></td></tr>
                            <?php endif; ?>
                            <?php foreach ($hospitals as $hospital): ?>
                                <tr>
                                    <td><span data-poi-card="<?= (int) $hospital['id'] ?>" style="cursor:help;"><?= htmlspecialchars((string) $hospital['name']) ?></span></td>
                                    <td><?= htmlspecialchars((string) $hospital['ort']) ?></td>
                                    <td>
                                        <span class="ignis-chip <?= (int) $hospital['dept_count'] > 0 ? 'ignis-chip--ok' : 'ignis-chip--warn' ?>">
                                            <?= (int) $hospital['dept_count'] ?> Fachrichtung(en)
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($hospital['code']): ?>
                                            <div class="flex items-center gap-2">
                                                <code class="text-ok-text"><?= htmlspecialchars((string) $hospital['code']) ?></code>
                                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon copy-code-btn" data-code="<?= htmlspecialchars((string) $hospital['code'], ENT_QUOTES) ?>" data-ignis-tooltip="Code kopieren" aria-label="Code kopieren">
                                                    <i class="fa-solid fa-copy"></i>
                                                </button>
                                            </div>
                                            <small class="text-tertiary-text block mt-1">
                                                Aktualisiert: <?= \App\Helpers\DateTimeHelper::formatShortLocal($hospital['code_updated']) ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="ignis-chip">Nicht konfiguriert</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ignis-table__actions">
                                        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary generate-code-btn"
                                                data-id="<?= (int) $hospital['id'] ?>"
                                                data-name="<?= htmlspecialchars((string) $hospital['name'], ENT_QUOTES) ?>">
                                            <i class="fa-solid fa-key"></i>
                                            <?= $hospital['code_created'] ? 'Code ändern' : 'Code generieren' ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php require dirname(__DIR__, 5) . '/templates/partials/pagination.php'; ?>
            </div>

            <div class="ignis-alert ignis-alert--info mt-3">
                <i class="fa-solid fa-info-circle ignis-alert__icon" aria-hidden="true"></i>
                <div class="ignis-alert__body">
                    <strong>Hinweis:</strong> Die generierten Zugangscodes ermöglichen es Krankenhäusern, ihre Verfügbarkeiten über das externe Portal zu melden.
                    Der Link zum Portal ist: <code class="break-all"><?= BASE_PATH ?>enotf/schnittstelle/hospital-availability</code>
                </div>
            </div>
        </div>
    </div>

    <template id="generateCodeFormTemplate">
        <p>Krankenhaus: <strong id="generate-hospital-name"></strong></p>
        <div class="mb-3">
            <label for="new-code" class="ignis-field__label">Zugangscode</label>
            <div class="input-group">
                <input type="text" class="ignis-input" name="new_code" id="new-code" required readonly>
                <button type="button" class="ignis-btn ignis-btn--secondary" id="regenerate-btn">
                    <i class="fa-solid fa-rotate"></i> Neu generieren
                </button>
            </div>
            <div class="ignis-field__hint">Der Code wird im Klartext gespeichert und kann jederzeit eingesehen werden.</div>
        </div>
        <div class="ignis-alert ignis-alert--info">
            <i class="fa-solid fa-info-circle ignis-alert__icon" aria-hidden="true"></i>
            <div class="ignis-alert__body"><strong>Hinweis:</strong> Gib diesen Code an das Krankenhaus weiter. Der Code kann jederzeit neu generiert werden.</div>
        </div>
    </template>

    <script>
        $(document).ready(function() {
            function generateRandomCode(length = 12) {
                const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
                let result = '';
                for (let i = 0; i < length; i++) {
                    result += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                return result;
            }

            $('.generate-code-btn').on('click', function() {
                const id   = $(this).data('id');
                const name = $(this).data('name');

                Dialog.form({
                    title:        'Zugangscode generieren',
                    template:     'generateCodeFormTemplate',
                    formAction:   '<?= BASE_PATH ?>settings/pois/access-codes',
                    hiddenFields: { poi_id: String(id) },
                    submitLabel:  'Speichern',
                    submitIcon:   'fa-solid fa-floppy-disk',
                    submitVariant:'soft-primary',
                    onOpen:       function (dlg) {
                        // Felder im frisch geklonten Template-Inhalt befüllen
                        // und den Regenerate-Handler binden, beides muss
                        // pro Open neu passieren, weil der Body bei jedem
                        // Open neu erstellt wird.
                        const $body = $(dlg.element);
                        $body.find('#generate-hospital-name').text(name);
                        $body.find('#new-code').val(generateRandomCode());
                        $body.find('#regenerate-btn').on('click', function () {
                            $body.find('#new-code').val(generateRandomCode());
                        });
                    },
                });
            });

            $('.copy-code-btn').on('click', function() {
                const code = $(this).data('code');
                navigator.clipboard.writeText(code).then(() => {
                    showToast('Code kopiert', 'success');
                });
            });
        });
    </script>
