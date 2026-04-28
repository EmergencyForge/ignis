<?php
/**
 * View: Krankenhaus-Zugangscodes
 *
 * @var array<int,array<string,mixed>> $hospitals
 * @var \PDO                           $pdo
 */

use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . '/../../../assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <div class="mb-3">
                        <h1 class="mb-0">Krankenhaus-Zugangscodes</h1>
                        <p class="text-[var(--text-dimmed,#818189)] mb-0">Verwalten Sie die Zugangscodes für das Verfügbarkeits-Portal</p>
                    </div>

                    <a href="<?= BASE_PATH ?>settings/pois/index" class="ignis-ignis-btn ignis-btn--sm ignis-btn--ghost mb-3">
                        <i class="fa-solid fa-arrow-left"></i> Zurück zur POI-Verwaltung
                    </a>

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
                                            <span class="badge <?= (int)$hospital['dept_count'] > 0 ? 'text-bg-success' : 'text-bg-warning' ?>">
                                                <?= (int)$hospital['dept_count'] ?> Fachrichtung(en)
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($hospital['code']): ?>
                                                <div class="flex items-center gap-2">
                                                    <code class="text-[#6abf76]"><?= htmlspecialchars($hospital['code']) ?></code>
                                                    <button class="ignis-ignis-btn ignis-btn--sm ignis-btn--outline-secondary copy-code-ignis-btn"
                                                            data-code="<?= htmlspecialchars($hospital['code']) ?>"
                                                            title="Code kopieren">
                                                        <i class="fa-solid fa-copy"></i>
                                                    </button>
                                                </div>
                                                <small class="text-[var(--text-dimmed,#818189)] block mt-1">
                                                    Aktualisiert: <?= \App\Helpers\DateTimeHelper::formatShortLocal($hospital['code_updated']) ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="ignis-chip">Nicht konfiguriert</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="ignis-ignis-btn ignis-btn--sm ignis-btn--soft-primary generate-code-ignis-btn"
                                                    data-id="<?= (int)$hospital['id'] ?>"
                                                    data-name="<?= htmlspecialchars($hospital['name']) ?>">
                                                <i class="fa-solid fa-key"></i>
                                                <?= $hospital['code_created'] ? 'Code ändern' : 'Code generieren' ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($hospitals)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-[var(--text-dimmed,#818189)]">Keine Krankenhäuser vorhanden</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="ignis-alert ignis-alert--info mt-3">
                        <i class="fa-solid fa-info-circle mr-2"></i>
                        <strong>Hinweis:</strong> Die generierten Zugangscodes ermöglichen es Krankenhäusern, ihre Verfügbarkeiten über das externe Portal zu melden.
                        Der Link zum Portal ist: <code><?= BASE_PATH ?>enotf/schnittstelle/hospital-availability.php</code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Generate Code Modal -->
    <div class="modal fade" id="generateCodeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="generate_code" value="1">
                    <input type="hidden" name="poi_id" id="generate-poi-id">
                    <div class="modal-header">
                        <h5 class="modal-title">Zugangscode generieren</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Krankenhaus: <strong id="generate-hospital-name"></strong></p>
                        <div class="mb-3">
                            <label for="new-code" class="ignis-field__label">Zugangscode</label>
                            <div class="input-group">
                                <input type="text" class="ignis-input" name="new_code" id="new-code" required readonly>
                                <button type="button" class="ignis-ignis-btn ignis-btn--outline-secondary" id="regenerate-ignis-btn">
                                    <i class="fa-solid fa-rotate"></i> Neu generieren
                                </button>
                            </div>
                            <div class="ignis-field__hint">Der Code wird im Klartext gespeichert und kann jederzeit eingesehen werden.</div>
                        </div>
                        <div class="ignis-alert ignis-alert--info">
                            <i class="fa-solid fa-info-circle mr-2"></i>
                            <strong>Hinweis:</strong> Geben Sie diesen Code an das Krankenhaus weiter. Der Code kann jederzeit neu generiert werden.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ignis-ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="ignis-ignis-btn ignis-btn--soft-primary">
                            <i class="fa-solid fa-floppy-disk"></i> Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#table-access-codes').DataTable({
                paging: true, lengthMenu: [10, 25, 50], pageLength: 10,
                order: [[0, 'asc']], columnDefs: [{ orderable: false, targets: -1 }],
                language: window.IgnisDataTableLang('Einträge')
            });

            function generateRandomCode(length = 12) {
                const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
                let result = '';
                for (let i = 0; i < length; i++) {
                    result += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                return result;
            }

            $('.generate-code-ignis-btn').on('click', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                $('#generate-poi-id').val(id);
                $('#generate-hospital-name').text(name);
                $('#new-code').val(generateRandomCode());
                new bootstrap.Modal($('#generateCodeModal')).show();
            });

            $('#regenerate-ignis-btn').on('click', function() {
                $('#new-code').val(generateRandomCode());
            });

            $('.copy-code-ignis-btn').on('click', function() {
                const code = $(this).data('code');
                navigator.clipboard.writeText(code).then(() => {
                    showToast('Code kopiert', 'success');
                });
            });
        });
    </script>
    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
