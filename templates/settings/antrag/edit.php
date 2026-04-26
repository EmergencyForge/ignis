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

<body data-bs-theme="dark" data-page="antragstyp-edit">
    <?php include __DIR__ . '/../../../assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <h1><?= htmlspecialchars($typ['name']) ?> bearbeiten</h1>

            <?php Flash::render(); ?>

            <!-- Grundeinstellungen -->
            <div class="intra__tile mb-4 p-4">
                <h4 class="mb-4">Grundeinstellungen</h4>
                <form method="post">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="name" class="ignis-field__label font-bold">Name <span class="text-red-500">*</span></label>
                            <input type="text" class="ignis-input" id="name" name="name"
                                value="<?= htmlspecialchars($typ['name']) ?>" required>
                        </div>
                        <div>
                            <label for="sortierung" class="ignis-field__label font-bold">Sortierung</label>
                            <input type="number" class="ignis-input" id="sortierung" name="sortierung"
                                value="<?= (int)$typ['sortierung'] ?>" min="0">
                        </div>
                    </div>

                    <div class="mt-4 mb-4">
                        <label for="beschreibung" class="ignis-field__label font-bold">Beschreibung</label>
                        <textarea class="ignis-input" id="beschreibung" name="beschreibung"
                            rows="2"><?= htmlspecialchars($typ['beschreibung']) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="ignis-switch" for="aktiv"><input type="checkbox" id="aktiv" name="aktiv"
                                <?= $typ['aktiv'] ? 'checked' : '' ?>><span><strong>Antragstyp aktiviert</strong></span></label>
                    </div>

                    <button type="submit" name="update_typ" class="ignis-btn ignis-btn--soft-primary">
                        <i class="fa-solid fa-save mr-2"></i>Speichern
                    </button>
                </form>
            </div>

            <!-- Felder Verwaltung -->
            <div class="intra__tile mb-4 p-4">
                <div class="mb-4 flex items-center justify-between">
                    <h4><i class="fa-solid fa-list mr-2"></i>Formularfelder (<?= count($felder) ?>)</h4>
                    <button type="button" class="ignis-btn ignis-btn--success ignis-btn--sm" data-bs-toggle="modal" data-bs-target="#addFeldModal">
                        <i class="fa-solid fa-plus mr-1"></i>Feld hinzufügen
                    </button>
                </div>

                <?php if (empty($felder)): ?>
                    <div class="ignis-alert ignis-alert--info">
                        <i class="fa-solid fa-info-circle mr-2"></i>
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
                                                    class="ignis-input ignis-input--sm"
                                                    style="width: 60px;">
                                            </td>
                                            <td><code><?= htmlspecialchars($feld['feldname']) ?></code></td>
                                            <td><?= htmlspecialchars($feld['label']) ?></td>
                                            <td>
                                                <span class="ignis-chip"><?= htmlspecialchars($feld['feldtyp']) ?></span>
                                                <?php if ($feld['auto_fill']): ?>
                                                    <span class="ignis-chip ignis-chip--info" title="Auto-Fill">
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
                                                <?= $feld['pflichtfeld'] ? '<i class="fa-solid fa-check text-[#6abf76]"></i>' : '<i class="fa-solid fa-times text-gray-400"></i>' ?>
                                            </td>
                                            <td class="text-center">
                                                <?= $feld['readonly'] ? '<i class="fa-solid fa-lock text-[#ddb84a]"></i>' : '<i class="fa-solid fa-unlock text-gray-400"></i>' ?>
                                            </td>
                                            <td class="text-right">
                                                <a href="?id=<?= (int)$id ?>&delete_feld=<?= (int)$feld['id'] ?>"
                                                    class="ignis-btn ignis-btn--outline-danger ignis-btn--sm btn-icon no-underline hover:no-underline"
                                                    onclick="event.preventDefault(); showConfirm('Feld wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Feld löschen'}).then(result => { if(result) window.location.href = this.href; });">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" name="update_felder_sortierung" class="ignis-btn ignis-btn--soft-primary mt-2">
                            <i class="fa-solid fa-save mr-2"></i>Sortierung speichern
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <a href="<?= BASE_PATH ?>settings/antrag/list" class="ignis-btn ignis-btn--ghost mb-6 no-underline hover:no-underline">
                <i class="fa-solid fa-arrow-left mr-2"></i>Zurück zur Übersicht
            </a>
        </div>
    </div>

    <!-- Modal: Feld hinzufügen -->
    <div class="modal fade" id="addFeldModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-[rgba(0,0,0,0.3)]">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa-solid fa-plus mr-2"></i>Neues Feld hinzufügen</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="feldname" class="ignis-field__label font-bold">Feldname (technisch) <span class="text-red-500">*</span></label>
                                <input type="text" class="ignis-input" id="feldname" name="feldname" placeholder="z.B. von_datum, grund" required>
                                <small class="mt-1 block text-xs text-gray-400">Nur Kleinbuchstaben, Zahlen und Unterstriche</small>
                            </div>
                            <div>
                                <label for="label" class="ignis-field__label font-bold">Label (Anzeige) <span class="text-red-500">*</span></label>
                                <input type="text" class="ignis-input" id="label" name="label" placeholder="z.B. Urlaub von" required>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label for="feldtyp" class="ignis-field__label font-bold">Feldtyp <span class="text-red-500">*</span></label>
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
                            <div>
                                <label for="breite" class="ignis-field__label font-bold">Feldbreite</label>
                                <select class="form-select" id="breite" name="breite">
                                    <option value="full">Volle Breite</option>
                                    <option value="half">Halbe Breite</option>
                                </select>
                            </div>
                            <div>
                                <label for="auto_fill" class="ignis-field__label font-bold">Auto-Fill</label>
                                <select class="form-select" id="auto_fill" name="auto_fill">
                                    <option value="">Kein Auto-Fill</option>
                                    <option value="fullname_dienstnr">Name + Dienstnr.</option>
                                    <option value="fullname">Name</option>
                                    <option value="dienstnr">Dienstnummer</option>
                                    <option value="dienstgrad">Dienstgrad</option>
                                    <option value="discordtag">Discord-Tag</option>
                                </select>
                                <small class="mt-1 block text-xs text-gray-400">Automatisch ausfüllen</small>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="platzhalter" class="ignis-field__label font-bold">Platzhalter-Text</label>
                            <input type="text" class="ignis-input" id="platzhalter" name="platzhalter" placeholder="z.B. TT.MM.JJJJ">
                        </div>

                        <div class="mt-4" id="optionen-container" style="display: none;">
                            <label for="optionen" class="ignis-field__label font-bold">Optionen (für Select)</label>
                            <textarea class="ignis-input" id="optionen" name="optionen" rows="3" placeholder="Eine Option pro Zeile"></textarea>
                            <small class="mt-1 block text-xs text-gray-400">Jede Zeile wird zu einer Auswahloption</small>
                        </div>

                        <div class="mt-4">
                            <label for="hinweistext" class="ignis-field__label font-bold">Hinweistext</label>
                            <textarea class="ignis-input" id="hinweistext" name="hinweistext" rows="2" placeholder="Optionaler Hinweis, der unter dem Feld angezeigt wird"></textarea>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <label class="ignis-checkbox" for="pflichtfeld"><input type="checkbox" id="pflichtfeld" name="pflichtfeld"><span>
                                    <strong>Pflichtfeld</strong>
                                </span></label>
                            <label class="ignis-checkbox" for="readonly"><input type="checkbox" id="readonly" name="readonly"><span>
                                    <strong>Nur lesbar (Readonly)</strong>
                                </span></label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" name="add_feld" class="ignis-btn ignis-btn--success">
                            <i class="fa-solid fa-plus mr-2"></i>Feld hinzufügen
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
