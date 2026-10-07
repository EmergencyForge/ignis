<?php
/**
 * View: Krankenhaus-Zugangscodes
 *
 * @var array<int,array<string,mixed>> $hospitals
 */

use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include dirname(__DIR__, 5) . '/assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include dirname(__DIR__, 5) . '/assets/components/navbar.php'; ?>
    <main class="ignis-main">
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 min-w-0 mb-5 px-3">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/pois/index">POIs</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Krankenhaus-Zugänge</span></nav>
                    <div class="page-header mb-4">
                        <h1>Krankenhaus-Zugangscodes</h1>
                        <div class="header-actions">
                            <a href="<?= BASE_PATH ?>settings/pois/index" class="ignis-btn ignis-btn--ghost">
                                <i class="fa-solid fa-arrow-left"></i> Zurück zur POI-Verwaltung
                            </a>
                        </div>
                    </div>

                    <?php Flash::render(); ?>

                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-access-codes">
                            <thead>
                                <tr>
                                    <th scope="col">Krankenhaus</th>
                                    <th scope="col">Ort</th>
                                    <th scope="col">Fachrichtungen</th>
                                    <th scope="col">Zugangscode</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <tr>
                                        <td>
                                            <span data-poi-card="<?= (int) $hospital['id'] ?>" style="cursor:help;">
                                                <?= htmlspecialchars($hospital['name']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($hospital['ort']) ?></td>
                                        <td>
                                            <span class="ignis-chip <?= (int)$hospital['dept_count'] > 0 ? 'ignis-chip--ok' : 'ignis-chip--warn' ?>">
                                                <?= (int)$hospital['dept_count'] ?> Fachrichtung(en)
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($hospital['code']): ?>
                                                <div class="flex items-center gap-2">
                                                    <code class="text-ok-text"><?= htmlspecialchars($hospital['code']) ?></code>
                                                    <button class="ignis-btn ignis-btn--sm ignis-btn--secondary copy-code-btn"
                                                            data-code="<?= htmlspecialchars($hospital['code']) ?>"
                                                            title="Code kopieren">
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
                                        <td>
                                            <button class="ignis-btn ignis-btn--sm ignis-btn--secondary generate-code-btn"
                                                    data-id="<?= (int)$hospital['id'] ?>"
                                                    data-name="<?= htmlspecialchars($hospital['name']) ?>">
                                                <i class="fa-solid fa-key"></i>
                                                <?= $hospital['code_created'] ? 'Code ändern' : 'Code generieren' ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
        </div>
    </div>

    <!-- Form-Body als inertes <template>; Dialog wird in JS programmatisch erstellt -->
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
            <div class="ignis-alert__body"><strong>Hinweis:</strong> Geben Sie diesen Code an das Krankenhaus weiter. Der Code kann jederzeit neu generiert werden.</div>
        </div>
    </template>

    <script>
        $(document).ready(function() {
            $('#table-access-codes').DataTable({
                paging: true, lengthMenu: [10, 25, 50], pageLength: 10,
                order: [[0, 'asc']], columnDefs: [{ orderable: false, targets: -1 }],
                language: { ...window.IgnisDataTableLang('Einträge'), emptyTable: 'Keine Krankenhäuser vorhanden' }
            });

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
                    formAction:   '',
                    hiddenFields: { generate_code: '1', poi_id: String(id) },
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
    <?php include dirname(__DIR__, 5) . '/assets/components/footer.php'; ?>
    </main>
</body>

</html>
