<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin', 'pois.manage'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

require __DIR__ . '/../../assets/config/database.php';

// Generate new code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_code'])) {
    $poi_id = $_POST['poi_id'] ?? 0;
    $new_code = trim($_POST['new_code'] ?? '');

    if ($poi_id && $new_code) {
        try {
            // Store code in plaintext (not highly sensitive data)
            $stmt = $pdo->prepare("
                INSERT INTO intra_edivi_hospital_access_codes (poi_id, code)
                VALUES (:poi_id, :code)
                ON DUPLICATE KEY UPDATE code = VALUES(code), updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute(['poi_id' => $poi_id, 'code' => $new_code]);

            Flash::set('success', 'Zugangscode erfolgreich generiert: ' . htmlspecialchars($new_code));
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Generieren des Zugangscodes: ' . $e->getMessage());
        }
    } else {
        Flash::set('error', 'POI ID oder Code fehlt.');
    }
}

// Fetch all hospitals with their access codes
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.ort,
        p.ortsteil,
        p.typ,
        p.active,
        c.code,
        c.created_at as code_created,
        c.updated_at as code_updated,
        (SELECT COUNT(*) FROM intra_edivi_hospital_departments WHERE poi_id = p.id) as dept_count
    FROM intra_edivi_pois p
    LEFT JOIN intra_edivi_hospital_access_codes c ON p.id = c.poi_id
    WHERE p.typ IN ('Krankenhaus', 'Klinik')
    ORDER BY p.name ASC
");
$stmt->execute();
$hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <hr class="text-light my-3">
                    <div class="mb-3">
                        <h1 class="mb-0">Krankenhaus-Zugangscodes</h1>
                        <p class="text-muted mb-0">Verwalten Sie die Zugangscodes für das Verfügbarkeits-Portal</p>
                    </div>

                    <a href="<?= BASE_PATH ?>settings/pois/index.php" class="btn btn-sm btn-ghost mb-3">
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
                                        <td><?= htmlspecialchars($hospital['name']) ?></td>
                                        <td><?= htmlspecialchars($hospital['ort']) ?></td>
                                        <td>
                                            <span class="badge <?= $hospital['dept_count'] > 0 ? 'text-bg-success' : 'text-bg-warning' ?>">
                                                <?= $hospital['dept_count'] ?> Fachrichtung(en)
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($hospital['code']): ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <code class="text-success"><?= htmlspecialchars($hospital['code']) ?></code>
                                                    <button class="btn btn-sm btn-outline-secondary copy-code-btn"
                                                            data-code="<?= htmlspecialchars($hospital['code']) ?>"
                                                            title="Code kopieren">
                                                        <i class="fa-solid fa-copy"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted d-block mt-1">
                                                    Aktualisiert: <?= date('d.m.Y H:i', strtotime($hospital['code_updated'])) ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary">Nicht konfiguriert</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-soft-primary generate-code-btn"
                                                    data-id="<?= $hospital['id'] ?>"
                                                    data-name="<?= htmlspecialchars($hospital['name']) ?>">
                                                <i class="fa-solid fa-key"></i>
                                                <?= $hospital['code_created'] ? 'Code ändern' : 'Code generieren' ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($hospitals)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Keine Krankenhäuser vorhanden</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="fa-solid fa-info-circle me-2"></i>
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
                            <label for="new-code" class="form-label">Zugangscode</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="new_code" id="new-code" required readonly>
                                <button type="button" class="btn btn-outline-secondary" id="regenerate-btn">
                                    <i class="fa-solid fa-rotate"></i> Neu generieren
                                </button>
                            </div>
                            <div class="form-text">
                                Der Code wird im Klartext gespeichert und kann jederzeit eingesehen werden.
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            <strong>Hinweis:</strong> Geben Sie diesen Code an das Krankenhaus weiter. Der Code kann jederzeit neu generiert werden.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-soft-primary">
                            <i class="fa-solid fa-floppy-disk"></i> Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#table-access-codes').DataTable({
                paging: true,
                lengthMenu: [10, 25, 50],
                pageLength: 10,
                order: [[0, 'asc']],
                columnDefs: [{
                    orderable: false,
                    targets: -1
                }],
                language: {
                    "emptyTable": "Keine Krankenhäuser vorhanden",
                    "info": "Zeige _START_ bis _END_ | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_",
                    "lengthMenu": "_MENU_ pro Seite",
                    "search": "Suchen:",
                    "zeroRecords": "Keine Einträge gefunden",
                    "paginate": {
                        "first": "Erste",
                        "last": "Letzte",
                        "next": "Nächste",
                        "previous": "Vorherige"
                    }
                }
            });

            // Generate random code
            function generateRandomCode(length = 12) {
                const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
                let result = '';
                for (let i = 0; i < length; i++) {
                    result += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                return result;
            }

            // Open modal and generate initial code
            $('.generate-code-btn').on('click', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');

                $('#generate-poi-id').val(id);
                $('#generate-hospital-name').text(name);
                $('#new-code').val(generateRandomCode());

                const modal = new bootstrap.Modal($('#generateCodeModal'));
                modal.show();
            });

            // Regenerate code
            $('#regenerate-btn').on('click', function() {
                $('#new-code').val(generateRandomCode());
            });

            // Copy code to clipboard
            $('.copy-code-btn').on('click', function() {
                const code = $(this).data('code');
                navigator.clipboard.writeText(code).then(() => {
                    const originalIcon = $(this).find('i');
                    originalIcon.removeClass('fa-copy').addClass('fa-check');
                    setTimeout(() => {
                        originalIcon.removeClass('fa-check').addClass('fa-copy');
                    }, 2000);
                });
            });
        });
    </script>
    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>
