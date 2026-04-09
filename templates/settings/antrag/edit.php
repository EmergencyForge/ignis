<?php
/**
 * View: Antragstyp bearbeiten
 *
 * @var int                            $id
 * @var array<string,mixed>            $typ
 * @var array<int,array<string,mixed>> $felder
 * @var \PDO                           $pdo
 */

use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark">
    <?php include __DIR__ . '/../../../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <h1><?= htmlspecialchars($typ['name']) ?> bearbeiten</h1>

                    <?php Flash::render(); ?>

                    <!-- Grundeinstellungen -->
                    <div class="intra__tile p-4 mb-4">
                        <h4 class="mb-3">Grundeinstellungen</h4>
                        <form method="post">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label fw-bold">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name"
                                        value="<?= htmlspecialchars($typ['name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="sortierung" class="form-label fw-bold">Sortierung</label>
                                    <input type="number" class="form-control" id="sortierung" name="sortierung"
                                        value="<?= (int)$typ['sortierung'] ?>" min="0">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="beschreibung" class="form-label fw-bold">Beschreibung</label>
                                <textarea class="form-control" id="beschreibung" name="beschreibung"
                                    rows="2"><?= htmlspecialchars($typ['beschreibung']) ?></textarea>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="aktiv" name="aktiv"
                                        <?= $typ['aktiv'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="aktiv">
                                        <strong>Antragstyp aktiviert</strong>
                                    </label>
                                </div>
                            </div>

                            <button type="submit" name="update_typ" class="btn btn-soft-primary">
                                <i class="fa-solid fa-save me-2"></i>Speichern
                            </button>
                        </form>
                    </div>

                    <!-- Felder Verwaltung -->
                    <div class="intra__tile p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4><i class="fa-solid fa-list me-2"></i>Formularfelder (<?= count($felder) ?>)</h4>
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addFeldModal">
                                <i class="fa-solid fa-plus me-1"></i>Feld hinzufügen
                            </button>
                        </div>

                        <?php if (empty($felder)): ?>
                            <div class="alert alert-info">
                                <i class="fa-solid fa-info-circle me-2"></i>
                                Noch keine Felder definiert. Fügen Sie jetzt Ihr erstes Feld hinzu!
                            </div>
                        <?php else: ?>
                            <form method="post">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th style="width: 60px;">Sort.</th>
                                                <th>Feldname</th>
                                                <th>Label</th>
                                                <th>Typ</th>
                                                <th class="text-center">Breite</th>
                                                <th class="text-center">Pflicht</th>
                                                <th class="text-center">Readonly</th>
                                                <th style="width: 100px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($felder as $feld): ?>
                                                <tr>
                                                    <td>
                                                        <input type="number"
                                                            name="feld_sortierung[<?= (int)$feld['id'] ?>]"
                                                            value="<?= (int)$feld['sortierung'] ?>"
                                                            class="form-control form-control-sm"
                                                            style="width: 60px;">
                                                    </td>
                                                    <td><code><?= htmlspecialchars($feld['feldname']) ?></code></td>
                                                    <td><?= htmlspecialchars($feld['label']) ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?= htmlspecialchars($feld['feldtyp']) ?></span>
                                                        <?php if ($feld['auto_fill']): ?>
                                                            <span class="badge bg-info" title="Auto-Fill">
                                                                <i class="fa-solid fa-magic"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?= $feld['breite'] === 'full' ? 'primary' : 'warning' ?>">
                                                            <?= $feld['breite'] === 'full' ? 'Voll' : 'Halb' ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= $feld['pflichtfeld'] ? '<i class="fa-solid fa-check text-success"></i>' : '<i class="fa-solid fa-times text-muted"></i>' ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= $feld['readonly'] ? '<i class="fa-solid fa-lock text-warning"></i>' : '<i class="fa-solid fa-unlock text-muted"></i>' ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="?id=<?= (int)$id ?>&delete_feld=<?= (int)$feld['id'] ?>"
                                                            class="btn btn-outline-danger btn-sm btn-icon"
                                                            onclick="event.preventDefault(); showConfirm('Feld wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Feld löschen'}).then(result => { if(result) window.location.href = this.href; });">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" name="update_felder_sortierung" class="btn btn-soft-primary mt-2">
                                    <i class="fa-solid fa-save me-2"></i>Sortierung speichern
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <a href="<?= BASE_PATH ?>settings/antrag/list.php" class="btn btn-ghost mb-5">
                        <i class="fa-solid fa-arrow-left me-2"></i>Zurück zur Übersicht
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Feld hinzufügen -->
    <div class="modal fade" id="addFeldModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa-solid fa-plus me-2"></i>Neues Feld hinzufügen</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="feldname" class="form-label fw-bold">Feldname (technisch) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="feldname" name="feldname" placeholder="z.B. von_datum, grund" required>
                                <small class="text-muted">Nur Kleinbuchstaben, Zahlen und Unterstriche</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="label" class="form-label fw-bold">Label (Anzeige) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="label" name="label" placeholder="z.B. Urlaub von" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="feldtyp" class="form-label fw-bold">Feldtyp <span class="text-danger">*</span></label>
                                <select class="form-select" id="feldtyp" name="feldtyp" required>
                                    <option value="text">Text (einzeilig)</option>
                                    <option value="textarea">Textarea (mehrzeilig)</option>
                                    <option value="number">Zahl</option>
                                    <option value="date">Datum</option>
                                    <option value="time">Uhrzeit</option>
                                    <option value="email">E-Mail</option>
                                    <option value="tel">Telefon</option>
                                    <option value="select">Auswahlfeld</option>
                                    <option value="checkbox">Checkbox</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="breite" class="form-label fw-bold">Feldbreite</label>
                                <select class="form-select" id="breite" name="breite">
                                    <option value="full">Volle Breite</option>
                                    <option value="half">Halbe Breite</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="auto_fill" class="form-label fw-bold">Auto-Fill</label>
                                <select class="form-select" id="auto_fill" name="auto_fill">
                                    <option value="">Kein Auto-Fill</option>
                                    <option value="fullname_dienstnr">Name + Dienstnr.</option>
                                    <option value="fullname">Name</option>
                                    <option value="dienstnr">Dienstnummer</option>
                                    <option value="dienstgrad">Dienstgrad</option>
                                    <option value="discordtag">Discord-Tag</option>
                                </select>
                                <small class="text-muted">Automatisch ausfüllen</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="platzhalter" class="form-label fw-bold">Platzhalter-Text</label>
                            <input type="text" class="form-control" id="platzhalter" name="platzhalter" placeholder="z.B. TT.MM.JJJJ">
                        </div>

                        <div class="mb-3" id="optionen-container" style="display: none;">
                            <label for="optionen" class="form-label fw-bold">Optionen (für Select)</label>
                            <textarea class="form-control" id="optionen" name="optionen" rows="3" placeholder="Eine Option pro Zeile"></textarea>
                            <small class="text-muted">Jede Zeile wird zu einer Auswahloption</small>
                        </div>

                        <div class="mb-3">
                            <label for="hinweistext" class="form-label fw-bold">Hinweistext</label>
                            <textarea class="form-control" id="hinweistext" name="hinweistext" rows="2" placeholder="Optionaler Hinweis, der unter dem Feld angezeigt wird"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="pflichtfeld" name="pflichtfeld">
                                    <label class="form-check-label" for="pflichtfeld">
                                        <strong>Pflichtfeld</strong>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="readonly" name="readonly">
                                    <label class="form-check-label" for="readonly">
                                        <strong>Nur lesbar (Readonly)</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" name="add_feld" class="btn btn-success">
                            <i class="fa-solid fa-plus me-2"></i>Feld hinzufügen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $('#feldtyp').on('change', function() {
            if ($(this).val() === 'select') {
                $('#optionen-container').show();
            } else {
                $('#optionen-container').hide();
            }
        });
    </script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
