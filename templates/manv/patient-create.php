<?php
/**
 * View: Neuen MANV-Patient anlegen
 *
 * @var array<string,mixed>            $lage
 * @var int                            $lageId
 * @var array<int,array<string,mixed>> $fahrzeuge
 * @var array<int,array<string,mixed>> $krankenhaeuser
 * @var string|null                    $error
 * @var \PDO                           $pdo
 */

use App\Helpers\Flash;

$SITE_TITLE = 'Neuer Patient - ' . htmlspecialchars($lage['einsatznummer']);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" id="patient-create" data-page="edivi">
    <?php include __DIR__ . '/../../assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6">
                <h1>Neuer Patient</h1>
                <p class="text-gray-400">MANV-Lage: <?= htmlspecialchars($lage['einsatznummer']) ?> - <?= htmlspecialchars($lage['einsatzort']) ?></p>
            </div>

            <?php Flash::render(); ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Personalien</h5>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name">
                            </div>
                            <div>
                                <label for="vorname" class="form-label">Vorname</label>
                                <input type="text" class="form-control" id="vorname" name="vorname">
                            </div>
                            <div>
                                <label for="geburtsdatum" class="form-label">Geburtsdatum</label>
                                <input type="date" class="form-control" id="geburtsdatum" name="geburtsdatum">
                            </div>
                            <div>
                                <label for="geschlecht" class="form-label">Geschlecht</label>
                                <select class="form-control" id="geschlecht" name="geschlecht">
                                    <option value="unbekannt">Unbekannt</option>
                                    <option value="m">Männlich</option>
                                    <option value="w">Weiblich</option>
                                    <option value="d">Divers</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Sichtungskategorie</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <label for="sichtungskategorie" class="form-label">Kategorie *</label>
                            <select class="form-control form-control-lg" id="sichtungskategorie" name="sichtungskategorie" required>
                                <option value="">Bitte wählen...</option>
                                <option value="SK1" class="text-danger">SK1 - Rot (Akute Lebensgefahr)</option>
                                <option value="SK2" class="text-warning">SK2 - Gelb (Nicht auszuschließende schwere Folgeschäden)</option>
                                <option value="SK3" class="text-success">SK3 - Grün (Spätere Behandlung)</option>
                                <option value="SK4" class="text-info">SK4 - Blau (Akute Lebensgefahr ohne zeitnahe Versorgung)</option>
                                <option value="SK5" style="background-color: #000; color: #fff;">SK5 - Schwarz (Tot)</option>
                                <option value="SK6" style="color: #9b59b6;">SK6 - Lila (Beteiligter ohne Verletzung)</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <small>
                                <strong>SK1 (Rot):</strong> Akute Lebensgefahr - Sofortbehandlung und sofortiger Transport nach Stabilisierung, wenn keine Transportkapazität vorhanden dann → SK4<br>
                                <strong>SK2 (Gelb):</strong> Nicht auszuschließende schwere Folgeschäden oder akutes, nicht lebensbedrohliches Problem - Behandlung und Transport nach individueller Dringlichkeit<br>
                                <strong>SK3 (Grün):</strong> Spätere Behandlung bei nicht akuten Problemen ohne erwartbare Folgeschäden - Transport nach Verfügbarkeiten<br>
                                <strong>SK4 (Blau):</strong> Akute Lebensgefahr ohne Möglichkeit der zeitnahen Versorgung (prä-)klinisch oder keine erwartbare Überlebenschance - Betreuung und ggf. Sedierung<br>
                                <strong>SK5 (Schwarz):</strong> Verstorbene Person - Keine medizinische Maßnahme erforderlich, Leichnam sichern und dokumentieren<br>
                                <strong>SK6 (Lila):</strong> Beteiligter / Betroffener ohne Verletzung / Erkrankung - ggf. Betreuung oder Unterbringung
                            </small>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Transport</h5>
                    </div>
                    <div class="card-body space-y-4">
                        <div>
                            <label for="transportmittel_id" class="form-label">Zugewiesenes Fahrzeug</label>
                            <select class="form-control" id="transportmittel_id" name="transportmittel_id">
                                <option value="">Noch nicht zugewiesen</option>
                                <?php foreach ($fahrzeuge as $fzg): ?>
                                    <option value="<?= (int) $fzg['id'] ?>"
                                        data-bezeichnung="<?= htmlspecialchars($fzg['bezeichnung']) ?>"
                                        data-fahrzeugtyp="<?= htmlspecialchars($fzg['fahrzeugtyp'] ?? '') ?>"
                                        data-lokalisation="<?= htmlspecialchars($fzg['lokalisation'] ?? '') ?>">
                                        <?= htmlspecialchars($fzg['bezeichnung']) ?> - <?= htmlspecialchars($fzg['fahrzeugtyp'] ?? 'Unbekannt') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label for="display_fahrzeugtyp" class="form-label">Art</label>
                                <input type="text" class="form-control" id="display_fahrzeugtyp" readonly>
                            </div>
                            <div>
                                <label for="display_rufname" class="form-label">Rufname / Kennung</label>
                                <input type="text" class="form-control" id="display_rufname" readonly>
                            </div>
                            <div>
                                <label for="display_lokalisation" class="form-label">Fahrzeug-Lokalisation</label>
                                <input type="text" class="form-control" id="display_lokalisation" readonly>
                            </div>
                        </div>
                        <div>
                            <label for="transportziel" class="form-label">Transportziel</label>
                            <select class="form-control" id="transportziel" name="transportziel">
                                <option value="">Bitte wählen...</option>
                                <option value="Kein Transport">Kein Transport</option>
                                <?php foreach ($krankenhaeuser as $kh): ?>
                                    <option value="<?= htmlspecialchars($kh['name']) ?>">
                                        <?= htmlspecialchars($kh['name']) ?><?= !empty($kh['ort']) ? ' (' . htmlspecialchars($kh['ort']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Medizinische Informationen</h5>
                    </div>
                    <div class="card-body space-y-4">
                        <div>
                            <label for="verletzungen" class="form-label">Verletzungen / Diagnose</label>
                            <textarea class="form-control" id="verletzungen" name="verletzungen" rows="3" placeholder="Beschreibung der Verletzungen..."></textarea>
                        </div>
                        <div>
                            <label for="notizen" class="form-label">Notizen</label>
                            <textarea class="form-control" id="notizen" name="notizen" rows="2" placeholder="Zusätzliche Notizen..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="mb-4 flex items-center justify-between">
                    <a href="<?= BASE_PATH ?>manv/board.php?id=<?= $lageId ?>" class="btn btn-ghost no-underline hover:no-underline">
                        <i class="fas fa-arrow-left me-2"></i>Zurück zum Board
                    </a>
                    <button type="submit" class="btn btn-soft-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Patient anlegen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../../assets/components/footer.php'; ?>

    <script>
        document.getElementById('transportmittel_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                document.getElementById('display_rufname').value = selectedOption.dataset.bezeichnung || '';
                document.getElementById('display_fahrzeugtyp').value = selectedOption.dataset.fahrzeugtyp || '';
                document.getElementById('display_lokalisation').value = selectedOption.dataset.lokalisation || '';
            } else {
                document.getElementById('display_rufname').value = '';
                document.getElementById('display_fahrzeugtyp').value = '';
                document.getElementById('display_lokalisation').value = '';
            }
        });
    </script>
</body>

</html>
