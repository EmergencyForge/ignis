<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\MANV\MANVRessource;
use App\MANV\MANVLage;
use App\MANV\MANVLog;
use App\Auth\Permissions;

$lageId = $_GET['lage_id'] ?? null;
if (!$lageId) {
    header('Location: index.php');
    exit;
}

if (!Permissions::check(['admin', 'manv.manage'])) {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$manvRessource = new MANVRessource($pdo);
$manvLage = new MANVLage($pdo);
$manvLog = new MANVLog($pdo);

$lage = $manvLage->getById((int)$lageId);
if (!$lage) {
    header('Location: index.php');
    exit;
}

// Bearbeiten einer Ressource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $data = [
        'typ' => $_POST['typ'] ?? 'fahrzeug',
        'bezeichnung' => $_POST['bezeichnung'],
        'rufname' => $_POST['rufname'] ?? null,
        'fahrzeugtyp' => $_POST['fahrzeugtyp'] ?? null,
        'lokalisation' => $_POST['lokalisation'] ?? null,
        'notizen' => $_POST['notizen'] ?? null
    ];

    try {
        $manvRessource->update((int)$_POST['ressource_id'], $data);
        $manvLog->log(
            (int)$lageId,
            'ressource_bearbeitet',
            'Ressource ' . $data['bezeichnung'] . ' wurde bearbeitet',
            $_SESSION['user_id'] ?? null,
            $_SESSION['username'] ?? null,
            'ressource',
            (int)$_POST['ressource_id']
        );
        header('Location: ressourcen.php?lage_id=' . $lageId);
        exit;
    } catch (Exception $e) {
        $error = 'Fehler beim Bearbeiten: ' . $e->getMessage();
    }
}

// Erstellen einer neuen Ressource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $data = [
        'manv_lage_id' => (int)$lageId,
        'typ' => $_POST['typ'] ?? 'fahrzeug',
        'bezeichnung' => $_POST['bezeichnung'], // Rufname/Identifier - wird zur Identifikation verwendet
        'rufname' => $_POST['rufname'] ?? null,
        'fahrzeugtyp' => $_POST['fahrzeugtyp'] ?? null,
        'lokalisation' => $_POST['lokalisation'] ?? null,
        'status' => $_POST['status'] ?? 'verfuegbar',
        'besatzung' => $_POST['besatzung'] ?? null,
        'notizen' => $_POST['notizen'] ?? null
    ];

    // Prüfe ob Fahrzeug (per Rufname/Identifier) bereits existiert
    $checkStmt = $pdo->prepare("
        SELECT id, bezeichnung 
        FROM intra_manv_ressourcen 
        WHERE manv_lage_id = ? 
        AND bezeichnung = ?
    ");
    $checkStmt->execute([(int)$lageId, $data['bezeichnung']]);
    $existingFahrzeug = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingFahrzeug) {
        $error = 'Das Fahrzeug ' . htmlspecialchars($data['bezeichnung']) . ' wurde bereits zu dieser MANV-Lage hinzugefügt.';
    } else {
        try {
            $ressourceId = $manvRessource->create($data);
            $manvLog->log(
                (int)$lageId,
                'ressource_erstellt',
                'Ressource ' . $data['bezeichnung'] . ' wurde erstellt',
                $_SESSION['user_id'] ?? null,
                $_SESSION['username'] ?? null,
                'ressource',
                $ressourceId
            );
            $success = 'Ressource erfolgreich erstellt.';
        } catch (Exception $e) {
            $error = 'Fehler beim Erstellen: ' . $e->getMessage();
        }
    }
}

// Löschen einer Ressource
if (isset($_GET['delete_id'])) {
    try {
        $manvRessource->delete((int)$_GET['delete_id']);
        $manvLog->log(
            (int)$lageId,
            'ressource_geloescht',
            'Ressource wurde gelöscht',
            $_SESSION['user_id'] ?? null,
            $_SESSION['username'] ?? null
        );
        header('Location: ressourcen.php?lage_id=' . $lageId);
        exit;
    } catch (Exception $e) {
        $error = 'Fehler beim Löschen: ' . $e->getMessage();
    }
}

// Fahrzeuge aus System laden (nur nicht bereits hinzugefügte)
$systemFahrzeugeStmt = $pdo->prepare("
    SELECT f.id, f.name, f.identifier, f.veh_type 
    FROM intra_fahrzeuge f
    WHERE f.active = 1 
    AND f.name NOT IN (
        SELECT bezeichnung 
        FROM intra_manv_ressourcen 
        WHERE manv_lage_id = ?
    )
    ORDER BY f.priority ASC
");
$systemFahrzeugeStmt->execute([(int)$lageId]);
$systemFahrzeuge = $systemFahrzeugeStmt->fetchAll(PDO::FETCH_ASSOC);

// Benutzer für LNA/OrgL laden
$usersStmt = $pdo->prepare("
    SELECT u.id, COALESCE(m.fullname, u.fullname) as fullname 
    FROM intra_users u 
    LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag 
    WHERE COALESCE(m.fullname, u.fullname) IS NOT NULL
    ORDER BY COALESCE(m.fullname, u.fullname) ASC
");
$usersStmt->execute();
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$fahrzeuge = $manvRessource->getByLage((int)$lageId, 'fahrzeug');
$personal = $manvRessource->getByLage((int)$lageId, 'personal');
$material = $manvRessource->getByLage((int)$lageId, 'material');
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = 'Fahrzeugverwaltung - ' . htmlspecialchars($lage['einsatznummer']);
    include __DIR__ . '/../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" id="manv-ressourcen" data-page="edivi">
    <?php include __DIR__ . '/../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <hr class="text-light my-3">
            <div class="row mb-5 align-items-end">
                <div class="col-lg-8 col-12">
                    <h1>Fahrzeugverwaltung</h1>
                    <p class="text-muted">MANV-Lage: <?= htmlspecialchars($lage['einsatznummer']) ?></p>
                </div>
                <div class="col-lg-4 col-12">
                    <div class="d-flex flex-column flex-lg-row gap-2 justify-content-lg-end mt-3 mt-lg-0">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#quickAddModal" title="Schnell hinzufügen">
                            <i class="fas fa-bolt"></i>
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                            <i class="fas fa-plus me-1"></i> Fahrzeug hinzufügen
                        </button>
                    </div>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Fahrzeuge -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Fahrzeuge (<?= count($fahrzeuge) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($fahrzeuge)): ?>
                        <p class="text-muted">Keine Fahrzeuge vorhanden.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Bezeichnung</th>
                                        <th>Art</th>
                                        <th>Lokalisation</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fahrzeuge as $fzg): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($fzg['bezeichnung']) ?></strong></td>
                                            <td><?= htmlspecialchars($fzg['fahrzeugtyp'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($fzg['lokalisation'] ?? '-') ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary me-1 edit-ressource-btn"
                                                    data-id="<?= $fzg['id'] ?>"
                                                    data-typ="<?= htmlspecialchars($fzg['typ']) ?>"
                                                    data-bezeichnung="<?= htmlspecialchars($fzg['bezeichnung']) ?>"
                                                    data-rufname="<?= htmlspecialchars($fzg['rufname'] ?? '') ?>"
                                                    data-fahrzeugtyp="<?= htmlspecialchars($fzg['fahrzeugtyp'] ?? '') ?>"
                                                    data-lokalisation="<?= htmlspecialchars($fzg['lokalisation'] ?? '') ?>"
                                                    data-notizen="<?= htmlspecialchars($fzg['notizen'] ?? '') ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?lage_id=<?= $lageId ?>&delete_id=<?= $fzg['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Wirklich löschen?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-4">
                <a href="<?= BASE_PATH ?>manv/board.php?id=<?= $lageId ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Zurück zum Board
                </a>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content bg-dark">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="ressource_id" id="edit_ressource_id">
                        <div class="modal-header">
                            <h5 class="modal-title">Ressource bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit_typ" class="form-label">Typ</label>
                                <input type="text" class="form-control" id="edit_typ" name="typ" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="edit_bezeichnung" class="form-label">Bezeichnung *</label>
                                <input type="text" class="form-control" id="edit_bezeichnung" name="bezeichnung" required>
                            </div>
                            <div class="mb-3" id="edit_fahrzeugtyp_group">
                                <label for="edit_fahrzeugtyp" class="form-label">Art</label>
                                <input type="text" class="form-control" id="edit_fahrzeugtyp" name="fahrzeugtyp">
                            </div>
                            <div class="mb-3">
                                <label for="edit_lokalisation" class="form-label">Lokalisation an Einsatzstelle</label>
                                <input type="text" class="form-control" id="edit_lokalisation" name="lokalisation">
                            </div>
                            <div class="mb-3">
                                <label for="edit_notizen" class="form-label">Notizen</label>
                                <textarea class="form-control" id="edit_notizen" name="notizen" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div class="modal fade" id="createModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content bg-dark">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-ambulance me-2"></i>Fahrzeug hinzufügen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3" style="display: none;">
                                <label for="typ" class="form-label">Typ</label>
                                <input type="hidden" id="typ" name="typ" value="fahrzeug">
                            </div>

                            <!-- Intelligentes Suchfeld für Fahrzeuge -->
                            <div class="mb-3">
                                <label for="fahrzeug_search" class="form-label">Fahrzeug suchen und auswählen *</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="fahrzeug_search"
                                        placeholder="Suchen nach Funkrufname, Kennzeichen oder Fahrzeugtyp..."
                                        autocomplete="off" required>
                                </div>
                                <input type="hidden" id="fahrzeug_id" name="fahrzeug_id">
                                <small class="text-muted">Beginnen Sie zu tippen - Vorschläge werden automatisch angezeigt</small>

                                <!-- Suchergebnisse / Autocomplete -->
                                <div id="search_results" class="list-group mt-2" style="display: none; max-height: 350px; overflow-y: auto; overflow-x: hidden;"></div>
                            </div>

                            <!-- Versteckte Datenquelle für JavaScript -->
                            <script id="fahrzeug_data" type="application/json">
                                <?= json_encode(array_map(function ($fzg) {
                                    return [
                                        'id' => $fzg['id'],
                                        'identifier' => $fzg['identifier'],
                                        'name' => $fzg['name'],
                                        'veh_type' => $fzg['veh_type'],
                                        'search' => strtolower($fzg['identifier'] . ' ' . $fzg['name'] . ' ' . $fzg['veh_type'])
                                    ];
                                }, $systemFahrzeuge)) ?>
                            </script>

                            <hr class="my-4">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="bezeichnung" class="form-label">Rufname / Kennung *</label>
                                        <input type="text" class="form-control" id="bezeichnung" name="bezeichnung" required readonly>
                                        <small class="text-muted">Eindeutiger Rufname zur Identifikation</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="fahrzeugtyp" class="form-label">Fahrzeugtyp</label>
                                        <input type="text" class="form-control" id="fahrzeugtyp" name="fahrzeugtyp" readonly>
                                        <small class="text-muted">Art des Fahrzeugs</small>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="lokalisation" class="form-label">Lokalisation / Position</label>
                                <input type="text" class="form-control" id="lokalisation" name="lokalisation" placeholder="z.B. Verletztensammelstelle, Haltepunkt Nord...">
                                <small class="text-muted">Optional: Wo befindet sich das Fahrzeug an der Einsatzstelle?</small>
                            </div>
                            <div class="mb-3">
                                <label for="notizen" class="form-label">Notizen</label>
                                <textarea class="form-control" id="notizen" name="notizen" rows="2" placeholder="Zusätzliche Informationen..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-plus me-2"></i>Fahrzeug hinzufügen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Add Modal -->
        <div class="modal fade" id="quickAddModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content bg-dark">
                    <form method="POST" action="" id="quickAddForm">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="typ" value="fahrzeug">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-bolt me-2"></i>Schnell hinzufügen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted mb-3">Für schnelles Hinzufügen ohne Systemfahrzeug</p>
                            <div class="mb-3">
                                <label for="quick_bezeichnung" class="form-label">Rufname / Kennung *</label>
                                <input type="text" class="form-control" id="quick_bezeichnung" name="bezeichnung" placeholder="z.B. RTW 1/83-1" required>
                            </div>
                            <div class="mb-3">
                                <label for="quick_fahrzeugtyp" class="form-label">Fahrzeugtyp *</label>
                                <select class="form-control" id="quick_fahrzeugtyp" name="fahrzeugtyp" required>
                                    <option value="">Bitte wählen...</option>
                                    <option value="RTW">RTW - Rettungswagen</option>
                                    <option value="NAW">NAW - Notarztwagen</option>
                                    <option value="NEF">NEF - Notarzteinsatzfahrzeug</option>
                                    <option value="KTW">KTW - Krankentransportwagen</option>
                                    <option value="RTH">RTH - Rettungshubschrauber</option>
                                    <option value="ITH">ITH - Intensivtransporthubschrauber</option>
                                    <option value="GW-SAN">GW-SAN - Gerätewagen Sanität</option>
                                    <option value="ELW">ELW - Einsatzleitwagen</option>
                                    <option value="Sonstiges">Sonstiges</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="quick_lokalisation" class="form-label">Lokalisation</label>
                                <input type="text" class="form-control" id="quick_lokalisation" name="lokalisation" placeholder="Position an der Einsatzstelle">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-success"><i class="fas fa-bolt me-2"></i>Schnell hinzufügen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
    </div>
    <?php include __DIR__ . '/../assets/components/footer.php'; ?>

    <script>
        // Fahrzeugdaten laden
        const fahrzeugData = JSON.parse(document.getElementById('fahrzeug_data').textContent);
        const searchInput = document.getElementById('fahrzeug_search');
        const searchResults = document.getElementById('search_results');
        const fahrzeugIdInput = document.getElementById('fahrzeug_id');
        const bezeichnungInput = document.getElementById('bezeichnung');
        const fahrzeugtypInput = document.getElementById('fahrzeugtyp');

        let selectedVehicle = null;

        // Live-Suche mit Autocomplete
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();

            // Reset selection if user changes input
            if (selectedVehicle && this.value !== selectedVehicle.displayText) {
                selectedVehicle = null;
                fahrzeugIdInput.value = '';
                bezeichnungInput.value = '';
                fahrzeugtypInput.value = '';
            }

            if (searchTerm.length === 0) {
                searchResults.style.display = 'none';
                searchResults.innerHTML = '';
                return;
            }

            // Filter Fahrzeuge
            const matches = fahrzeugData.filter(fzg =>
                fzg.search.includes(searchTerm)
            ).slice(0, 10); // Maximal 10 Ergebnisse

            if (matches.length === 0) {
                searchResults.innerHTML = '<div class="list-group-item text-center py-3" style="background-color: #2a2a2a; border-color: #444;"><i class="fas fa-info-circle me-2"></i>Keine Fahrzeuge gefunden</div>';
                searchResults.style.display = 'block';
                return;
            }

            // Ergebnisse anzeigen
            searchResults.innerHTML = matches.map(fzg => `
                <button type="button" class="list-group-item list-group-item-action" 
                        data-id="${fzg.id}"
                        data-name="${escapeHtml(fzg.name)}"
                        data-type="${escapeHtml(fzg.veh_type)}"
                        style="background-color: #2a2a2a; border-color: #444; color: #fff; padding: 14px 16px; transition: all 0.2s; overflow: hidden;">
                    <div class="d-flex justify-content-between align-items-center gap-2" style="flex-wrap: nowrap; overflow: hidden;">
                        <div style="min-width: 0; flex: 1; overflow: hidden;">
                            <i class="fas fa-ambulance me-2" style="color: #0d6efd;"></i>
                            <strong style="font-size: 1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; max-width: 100%;">${escapeHtml(fzg.name)}</strong>
                        </div>
                        <span class="badge" style="background-color: #495057; font-size: 0.85rem; padding: 6px 10px; flex-shrink: 0; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(fzg.veh_type)}</span>
                    </div>
                    ${fzg.identifier ? `<small class="d-block mt-1 ms-4" style="color: #adb5bd; font-size: 0.875rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(fzg.identifier)}</small>` : ''}
                </button>
            `).join('');

            // Hover-Effekt per CSS
            const style = document.createElement('style');
            style.textContent = `
                #search_results .list-group-item:hover {
                    background-color: #343a40 !important;
                    border-color: #0d6efd !important;
                    transform: translateX(4px);
                }
                #search_results .list-group-item:active {
                    background-color: #495057 !important;
                }
            `;
            if (!document.getElementById('search-results-style')) {
                style.id = 'search-results-style';
                document.head.appendChild(style);
            }

            searchResults.style.display = 'block';

            // Event-Listener für Auswahl
            searchResults.querySelectorAll('.list-group-item').forEach(item => {
                item.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    const type = this.dataset.type;

                    // Setze Werte
                    fahrzeugIdInput.value = id;
                    bezeichnungInput.value = name;
                    fahrzeugtypInput.value = type;
                    searchInput.value = name;

                    selectedVehicle = {
                        id: id,
                        displayText: name
                    };

                    // Verstecke Ergebnisse
                    searchResults.style.display = 'none';
                    searchResults.innerHTML = '';
                });
            });
        });

        // Schließe Suchergebnisse bei Klick außerhalb
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });

        // HTML Escape Funktion
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        // Edit modal population
        document.querySelectorAll('.edit-ressource-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_ressource_id').value = this.dataset.id;
                document.getElementById('edit_typ').value = this.dataset.typ;
                document.getElementById('edit_bezeichnung').value = this.dataset.bezeichnung;
                document.getElementById('edit_lokalisation').value = this.dataset.lokalisation || '';
                document.getElementById('edit_notizen').value = this.dataset.notizen || '';
                document.getElementById('edit_fahrzeugtyp').value = this.dataset.fahrzeugtyp || '';
            });
        });

        // Focus search on modal open
        document.getElementById('createModal').addEventListener('shown.bs.modal', function() {
            searchInput.focus();
        });

        // Clear on modal close
        document.getElementById('createModal').addEventListener('hidden.bs.modal', function() {
            searchInput.value = '';
            fahrzeugIdInput.value = '';
            bezeichnungInput.value = '';
            fahrzeugtypInput.value = '';
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            selectedVehicle = null;
        });

        // Quick Add: Focus on first field when modal opens
        document.getElementById('quickAddModal').addEventListener('shown.bs.modal', function() {
            document.getElementById('quick_bezeichnung').focus();
        });
    </script>
</body>

</html>