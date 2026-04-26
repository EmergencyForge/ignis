<?php
/**
 * View: MANV Fahrzeugverwaltung
 *
 * @var array<string,mixed>            $lage
 * @var int                            $lageId
 * @var array<int,array<string,mixed>> $fahrzeuge
 * @var array<int,array<string,mixed>> $systemFahrzeuge
 * @var \PDO                           $pdo
 */

use App\Helpers\Flash;

$SITE_TITLE = 'Fahrzeugverwaltung - ' . htmlspecialchars($lage['einsatznummer']);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" id="manv-ressourcen" data-page="edivi">
    <?php include __DIR__ . '/../../assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1>Fahrzeugverwaltung</h1>
                    <p class="text-gray-400">MANV-Lage: <?= htmlspecialchars($lage['einsatznummer']) ?></p>
                </div>
                <div class="flex flex-col gap-2 lg:flex-row lg:justify-end">
                    <button type="button" class="ignis-btn ignis-btn--success ignis-btn--icon" data-bs-toggle="modal" data-bs-target="#quickAddModal" title="Schnell hinzufügen">
                        <i class="fas fa-bolt"></i>
                    </button>
                    <button type="button" class="ignis-btn ignis-btn--soft-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fas fa-plus mr-1"></i> Fahrzeug hinzufügen
                    </button>
                </div>
            </div>

            <?php Flash::render(); ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Fahrzeuge (<?= count($fahrzeuge) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($fahrzeuge)): ?>
                        <p class="text-gray-400">Keine Fahrzeuge vorhanden.</p>
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
                                                <button class="ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon mr-1 edit-ressource-btn"
                                                    data-id="<?= (int) $fzg['id'] ?>"
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
                                                <a href="?lage_id=<?= $lageId ?>&delete_id=<?= (int) $fzg['id'] ?>" class="ignis-btn ignis-btn--sm ignis-btn--outline-danger ignis-btn--icon" onclick="event.preventDefault(); showConfirm('Fahrzeug wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Fahrzeug löschen'}).then(result => { if(result) window.location.href=this.href; });">
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
                <a href="<?= BASE_PATH ?>manv/board?id=<?= $lageId ?>" class="ignis-btn ignis-btn--ghost no-underline hover:no-underline">
                    <i class="fas fa-arrow-left mr-2"></i>Zurück zum Board
                </a>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content bg-[rgba(0,0,0,0.3)]">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="ressource_id" id="edit_ressource_id">
                        <div class="modal-header">
                            <h5 class="modal-title">Ressource bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit_typ" class="ignis-field__label">Typ</label>
                                <input type="text" class="ignis-input" id="edit_typ" name="typ" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="edit_bezeichnung" class="ignis-field__label">Bezeichnung *</label>
                                <input type="text" class="ignis-input" id="edit_bezeichnung" name="bezeichnung" required>
                            </div>
                            <div class="mb-3" id="edit_fahrzeugtyp_group">
                                <label for="edit_fahrzeugtyp" class="ignis-field__label">Art</label>
                                <input type="text" class="ignis-input" id="edit_fahrzeugtyp" name="fahrzeugtyp">
                            </div>
                            <div class="mb-3">
                                <label for="edit_lokalisation" class="ignis-field__label">Lokalisation an Einsatzstelle</label>
                                <input type="text" class="ignis-input" id="edit_lokalisation" name="lokalisation">
                            </div>
                            <div class="mb-3">
                                <label for="edit_notizen" class="ignis-field__label">Notizen</label>
                                <textarea class="ignis-input" id="edit_notizen" name="notizen" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="ignis-btn ignis-btn--soft-primary">Speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div class="modal fade" id="createModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content bg-[rgba(0,0,0,0.3)]">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-ambulance mr-2"></i>Fahrzeug hinzufügen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3" style="display: none;">
                                <label for="typ" class="ignis-field__label">Typ</label>
                                <input type="hidden" id="typ" name="typ" value="fahrzeug">
                            </div>

                            <div class="mb-3">
                                <label for="fahrzeug_search" class="ignis-field__label">Fahrzeug suchen und auswählen *</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="ignis-input" id="fahrzeug_search"
                                        placeholder="Suchen nach Funkrufname, Kennzeichen oder Fahrzeugtyp..."
                                        autocomplete="off" required>
                                </div>
                                <input type="hidden" id="fahrzeug_id" name="fahrzeug_id">
                                <small class="text-gray-400">Beginnen Sie zu tippen - Vorschläge werden automatisch angezeigt</small>
                                <div id="search_results" class="list-group mt-2" style="display: none; max-height: 350px; overflow-y: auto; overflow-x: hidden;"></div>
                            </div>

                            <script id="fahrzeug_data" type="application/json">
                                <?= json_encode(array_map(function ($fzg) {
                                    return [
                                        'id'         => $fzg['id'],
                                        'identifier' => $fzg['identifier'],
                                        'name'       => $fzg['name'],
                                        'veh_type'   => $fzg['veh_type'],
                                        'search'     => strtolower($fzg['identifier'] . ' ' . $fzg['name'] . ' ' . $fzg['veh_type']),
                                    ];
                                }, $systemFahrzeuge)) ?>
                            </script>

                            <hr class="my-4">

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <label for="bezeichnung" class="ignis-field__label">Rufname / Kennung *</label>
                                    <input type="text" class="ignis-input" id="bezeichnung" name="bezeichnung" required readonly>
                                    <small class="text-gray-400">Eindeutiger Rufname zur Identifikation</small>
                                </div>
                                <div>
                                    <label for="fahrzeugtyp" class="ignis-field__label">Fahrzeugtyp</label>
                                    <input type="text" class="ignis-input" id="fahrzeugtyp" name="fahrzeugtyp" readonly>
                                    <small class="text-gray-400">Art des Fahrzeugs</small>
                                </div>
                            </div>

                            <div class="mt-3 mb-3">
                                <label for="lokalisation" class="ignis-field__label">Lokalisation / Position</label>
                                <input type="text" class="ignis-input" id="lokalisation" name="lokalisation" placeholder="z.B. Verletztensammelstelle, Haltepunkt Nord...">
                                <small class="text-gray-400">Optional: Wo befindet sich das Fahrzeug an der Einsatzstelle?</small>
                            </div>
                            <div class="mb-3">
                                <label for="notizen" class="ignis-field__label">Notizen</label>
                                <textarea class="ignis-input" id="notizen" name="notizen" rows="2" placeholder="Zusätzliche Informationen..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="ignis-btn ignis-btn--soft-primary btn-lg"><i class="fas fa-plus mr-2"></i>Fahrzeug hinzufügen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Add Modal -->
        <div class="modal fade" id="quickAddModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content bg-[rgba(0,0,0,0.3)]">
                    <form method="POST" action="" id="quickAddForm">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="typ" value="fahrzeug">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-bolt mr-2"></i>Schnell hinzufügen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-gray-400 mb-3">Für schnelles Hinzufügen ohne Systemfahrzeug</p>
                            <div class="mb-3">
                                <label for="quick_bezeichnung" class="ignis-field__label">Rufname / Kennung *</label>
                                <input type="text" class="ignis-input" id="quick_bezeichnung" name="bezeichnung" placeholder="z.B. RTW 1/83-1" required>
                            </div>
                            <div class="mb-3">
                                <label for="quick_fahrzeugtyp" class="ignis-field__label">Fahrzeugtyp *</label>
                                <select class="ignis-input" id="quick_fahrzeugtyp" name="fahrzeugtyp" required>
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
                                <label for="quick_lokalisation" class="ignis-field__label">Lokalisation</label>
                                <input type="text" class="ignis-input" id="quick_lokalisation" name="lokalisation" placeholder="Position an der Einsatzstelle">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="ignis-btn ignis-btn--success"><i class="fas fa-bolt mr-2"></i>Schnell hinzufügen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
    </div>
    <?php include __DIR__ . '/../../assets/components/footer.php'; ?>

    <script>
        const fahrzeugData = JSON.parse(document.getElementById('fahrzeug_data').textContent);
        const searchInput = document.getElementById('fahrzeug_search');
        const searchResults = document.getElementById('search_results');
        const fahrzeugIdInput = document.getElementById('fahrzeug_id');
        const bezeichnungInput = document.getElementById('bezeichnung');
        const fahrzeugtypInput = document.getElementById('fahrzeugtyp');

        let selectedVehicle = null;

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();

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

            const matches = fahrzeugData.filter(fzg => fzg.search.includes(searchTerm)).slice(0, 10);

            if (matches.length === 0) {
                searchResults.innerHTML = '<div class="list-group-item text-center py-3" style="background-color: #2a2a2a; border-color: #444;"><i class="fas fa-info-circle mr-2"></i>Keine Fahrzeuge gefunden</div>';
                searchResults.style.display = 'block';
                return;
            }

            searchResults.innerHTML = matches.map(fzg => `
                <button type="button" class="list-group-item list-group-item-action"
                        data-id="${fzg.id}"
                        data-name="${escapeHtml(fzg.name)}"
                        data-type="${escapeHtml(fzg.veh_type)}"
                        style="background-color: #2a2a2a; border-color: #444; color: #fff; padding: 14px 16px; transition: all 0.2s; overflow: hidden;">
                    <div class="flex justify-between items-center gap-2" style="flex-wrap: nowrap; overflow: hidden;">
                        <div style="min-width: 0; flex: 1; overflow: hidden;">
                            <i class="fas fa-ambulance mr-2" style="color: #0d6efd;"></i>
                            <strong style="font-size: 1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; max-width: 100%;">${escapeHtml(fzg.name)}</strong>
                        </div>
                        <span class="badge" style="background-color: #495057; font-size: 0.85rem; padding: 6px 10px; flex-shrink: 0; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(fzg.veh_type)}</span>
                    </div>
                    ${fzg.identifier ? `<small class="block mt-1 ml-4" style="color: #adb5bd; font-size: 0.875rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(fzg.identifier)}</small>` : ''}
                </button>
            `).join('');

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

            searchResults.querySelectorAll('.list-group-item').forEach(item => {
                item.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    const type = this.dataset.type;

                    fahrzeugIdInput.value = id;
                    bezeichnungInput.value = name;
                    fahrzeugtypInput.value = type;
                    searchInput.value = name;

                    selectedVehicle = { id: id, displayText: name };

                    searchResults.style.display = 'none';
                    searchResults.innerHTML = '';
                });
            });
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });

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

        document.getElementById('createModal').addEventListener('shown.bs.modal', function() {
            searchInput.focus();
        });

        document.getElementById('createModal').addEventListener('hidden.bs.modal', function() {
            searchInput.value = '';
            fahrzeugIdInput.value = '';
            bezeichnungInput.value = '';
            fahrzeugtypInput.value = '';
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            selectedVehicle = null;
        });

        document.getElementById('quickAddModal').addEventListener('shown.bs.modal', function() {
            document.getElementById('quick_bezeichnung').focus();
        });
    </script>
</body>

</html>
