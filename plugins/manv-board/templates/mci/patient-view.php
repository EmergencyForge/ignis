<?php
/**
 * View: MANV-Patient-Detail
 *
 * @var array<string,mixed>            $patient
 * @var int                            $patientId
 * @var array<string,mixed>            $lage
 * @var array<int,array<string,mixed>> $verfuegbareFahrzeuge
 * @var array<int,array<string,mixed>> $krankenhaeuser
 * @var string|null                    $success
 * @var string|null                    $error
 * @var \PDO                           $pdo
 */

use App\Helpers\Flash;

$skColors = [
    'SK1' => 'danger',
    'SK2' => 'warning',
    'SK3' => 'success',
    'SK4' => 'info',
    'SK5' => 'sk5',
    'SK6' => 'sk6',
    'tot' => 'dark',
];
$skColor = $skColors[$patient['sichtungskategorie'] ?? ''] ?? 'secondary';

$canTransport = !in_array($patient['sichtungskategorie'] ?? '', ['SK4', 'SK5', 'SK6', 'tot'], true);
$SITE_TITLE   = 'Patient ' . htmlspecialchars($patient['patienten_nummer']);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php include dirname(__DIR__, 4) . '/assets/components/_base/admin/head.php'; ?>
    <style>
        .quick-action-btn { margin: 0.25rem; }
        .bg-sk5 { background-color: #000 !important; color: #fff !important; }
        .bg-sk6 { background-color: #9b59b6 !important; color: #fff !important; }
        .patient-header {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .info-box {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body data-bs-theme="dark" id="patient-view" data-page="edivi">
    <?php include dirname(__DIR__, 4) . '/assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <?php Flash::render(); ?>

            <?php if (!empty($success)): ?>
                <div class="ignis-alert ignis-alert--success alert-dismissible fade show">
                    <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="ignis-alert ignis-alert--danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="patient-header mb-6">
                <div class="flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="mb-2"><?= htmlspecialchars($patient['patienten_nummer']) ?></h1>
                        <h4>
                            <?= htmlspecialchars($patient['vorname'] ?? '') ?> <?= htmlspecialchars($patient['name'] ?? 'Unbekannt') ?>
                        </h4>
                        <p class="mb-0 text-gray-400">
                            MANV-Lage: <?= htmlspecialchars($lage['einsatznummer']) ?>
                        </p>
                    </div>
                    <div class="md:text-right">
                        <h2>
                            <span class="ignis-chip ignis-chip--<?= $skColor ?> text-2xl">
                                <?= htmlspecialchars($patient['sichtungskategorie'] ?? 'Ungesichtet') ?>
                            </span>
                        </h2>
                        <small class="text-gray-400">
                            <?php if (!empty($patient['sichtungskategorie_zeit'])): ?>
                                Gesichtet: <?= \App\Helpers\DateTimeHelper::formatShortLocal($patient['sichtungskategorie_zeit']) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>

            <div class="ignis-card mb-4">
                <div class="ignis-card__header">
                    <h5 class="mb-0">Schnell-Sichtung</h5>
                </div>
                <div class="ignis-card__body text-center">
                    <a href="?id=<?= $patientId ?>&quick_sk=SK1" class="ignis-btn ignis-btn--danger quick-action-btn">
                        <i class="fas fa-circle mr-1"></i>SK1 - Rot
                    </a>
                    <a href="?id=<?= $patientId ?>&quick_sk=SK2" class="ignis-btn ignis-btn--warning quick-action-btn">
                        <i class="fas fa-circle mr-1"></i>SK2 - Gelb
                    </a>
                    <a href="?id=<?= $patientId ?>&quick_sk=SK3" class="ignis-btn ignis-btn--success quick-action-btn">
                        <i class="fas fa-circle mr-1"></i>SK3 - Grün
                    </a>
                    <a href="?id=<?= $patientId ?>&quick_sk=SK4" class="ignis-btn ignis-btn--info quick-action-btn">
                        <i class="fas fa-circle mr-1"></i>SK4 - Blau
                    </a>
                    <a href="?id=<?= $patientId ?>&quick_sk=SK5" class="ignis-btn quick-action-btn" style="background-color: #000; color: #fff; border-color: #fff;">
                        <i class="fas fa-circle mr-1"></i>SK5 - Schwarz
                    </a>
                    <a href="?id=<?= $patientId ?>&quick_sk=SK6" class="ignis-btn quick-action-btn" style="background-color: #9b59b6; color: #fff;">
                        <i class="fas fa-circle mr-1"></i>SK6 - Lila
                    </a>
                </div>
            </div>

            <form method="POST" action="">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <div class="ignis-card mb-4">
                            <div class="ignis-card__header">
                                <h5 class="mb-0">Personalien</h5>
                            </div>
                            <div class="ignis-card__body">
                                <div class="mb-3">
                                    <label for="name" class="ignis-field__label">Name</label>
                                    <input type="text" class="ignis-input" id="name" name="name" value="<?= htmlspecialchars($patient['name'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="vorname" class="ignis-field__label">Vorname</label>
                                    <input type="text" class="ignis-input" id="vorname" name="vorname" value="<?= htmlspecialchars($patient['vorname'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="geburtsdatum" class="ignis-field__label">Geburtsdatum</label>
                                    <input type="date" class="ignis-input" id="geburtsdatum" name="geburtsdatum" value="<?= htmlspecialchars($patient['geburtsdatum'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="geschlecht" class="ignis-field__label">Geschlecht</label>
                                    <select class="ignis-input" id="geschlecht" name="geschlecht">
                                        <option value="unbekannt" <?= ($patient['geschlecht'] ?? '') === 'unbekannt' ? 'selected' : '' ?>>Unbekannt</option>
                                        <option value="m" <?= ($patient['geschlecht'] ?? '') === 'm' ? 'selected' : '' ?>>Männlich</option>
                                        <option value="w" <?= ($patient['geschlecht'] ?? '') === 'w' ? 'selected' : '' ?>>Weiblich</option>
                                        <option value="d" <?= ($patient['geschlecht'] ?? '') === 'd' ? 'selected' : '' ?>>Divers</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="ignis-card mb-4">
                            <div class="ignis-card__header">
                                <h5 class="mb-0">Sichtungskategorie</h5>
                            </div>
                            <div class="ignis-card__body">
                                <div class="mb-3">
                                    <label for="sichtungskategorie" class="ignis-field__label">Kategorie</label>
                                    <select class="ignis-input form-control-lg" id="sichtungskategorie" name="sichtungskategorie">
                                        <option value="SK1" <?= ($patient['sichtungskategorie'] ?? '') === 'SK1' ? 'selected' : '' ?> class="text-[#d46b6b]">SK1 - Rot</option>
                                        <option value="SK2" <?= ($patient['sichtungskategorie'] ?? '') === 'SK2' ? 'selected' : '' ?> class="text-[#ddb84a]">SK2 - Gelb</option>
                                        <option value="SK3" <?= ($patient['sichtungskategorie'] ?? '') === 'SK3' ? 'selected' : '' ?> class="text-[#6abf76]">SK3 - Grün</option>
                                        <option value="SK4" <?= ($patient['sichtungskategorie'] ?? '') === 'SK4' ? 'selected' : '' ?> class="text-[#5bb8cc]">SK4 - Blau</option>
                                        <option value="SK5" <?= ($patient['sichtungskategorie'] ?? '') === 'SK5' ? 'selected' : '' ?> style="background-color: #000; color: #fff;">SK5 - Schwarz (Tot)</option>
                                        <option value="SK6" <?= ($patient['sichtungskategorie'] ?? '') === 'SK6' ? 'selected' : '' ?> style="color: #9b59b6;">SK6 - Lila</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="ignis-card mb-4">
                            <div class="ignis-card__header">
                                <h5 class="mb-0"><?= $canTransport ? 'Transport' : 'Fahrzeugzuweisung' ?></h5>
                            </div>
                            <div class="ignis-card__body">
                                <div class="mb-3">
                                    <label for="transportmittel_id" class="ignis-field__label">Zugewiesenes Fahrzeug</label>
                                    <select class="ignis-input" id="transportmittel_id" name="transportmittel_id">
                                        <option value="" data-rdtype="">Noch nicht zugewiesen</option>
                                        <?php foreach ($verfuegbareFahrzeuge as $fzg):
                                            $selected = (($patient['transportmittel_rufname'] ?? '') === $fzg['bezeichnung']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= (int) $fzg['id'] ?>" <?= $selected ?>
                                                data-bezeichnung="<?= htmlspecialchars($fzg['bezeichnung']) ?>"
                                                data-fahrzeugtyp="<?= htmlspecialchars($fzg['fahrzeugtyp'] ?? '') ?>"
                                                data-lokalisation="<?= htmlspecialchars($fzg['lokalisation'] ?? '') ?>"
                                                data-rdtype="<?= isset($fzg['rd_type']) ? (int) $fzg['rd_type'] : '' ?>">
                                                <?= htmlspecialchars($fzg['bezeichnung']) ?> - <?= htmlspecialchars($fzg['fahrzeugtyp'] ?? 'Unbekannt') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="display_fahrzeugtyp" class="ignis-field__label">Art</label>
                                    <input type="text" class="ignis-input" id="display_fahrzeugtyp" value="<?= htmlspecialchars($patient['transportmittel'] ?? '') ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="display_rufname" class="ignis-field__label">Rufname / Kennung</label>
                                    <input type="text" class="ignis-input" id="display_rufname" value="<?= htmlspecialchars($patient['transportmittel_rufname'] ?? '') ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="display_lokalisation" class="ignis-field__label">Fahrzeug-Lokalisation</label>
                                    <input type="text" class="ignis-input" id="display_lokalisation" value="<?= htmlspecialchars($patient['fahrzeug_lokalisation'] ?? '') ?>" readonly>
                                </div>
                                <?php if ($canTransport): ?>
                                    <div class="mb-3">
                                        <label for="transportziel" class="ignis-field__label">Transportziel</label>
                                        <select class="ignis-input" id="transportziel" name="transportziel">
                                            <option value="">Bitte wählen...</option>
                                            <option value="Kein Transport" <?= (($patient['transportziel'] ?? '') === 'Kein Transport') ? 'selected' : '' ?>>Kein Transport</option>
                                            <?php foreach ($krankenhaeuser as $kh): ?>
                                                <option value="<?= htmlspecialchars($kh['name']) ?>" <?= (($patient['transportziel'] ?? '') === $kh['name']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($kh['name']) ?><?= !empty($kh['ort']) ? ' (' . htmlspecialchars($kh['ort']) . ')' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if ($canTransport && !empty($patient['transport_abfahrt'])): ?>
                                    <div class="info-box">
                                        <small class="text-gray-400">
                                            <i class="fas fa-clock mr-1"></i>
                                            Abfahrt: <?= \App\Helpers\DateTimeHelper::formatShortLocal($patient['transport_abfahrt']) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="ignis-card mb-4">
                            <div class="ignis-card__header">
                                <h5 class="mb-0">Medizinische Informationen</h5>
                            </div>
                            <div class="ignis-card__body">
                                <div class="mb-3">
                                    <label for="verletzungen" class="ignis-field__label">Verletzungen</label>
                                    <textarea class="ignis-input" id="verletzungen" name="verletzungen" rows="3"><?= htmlspecialchars($patient['verletzungen'] ?? '') ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="notizen" class="ignis-field__label">Notizen</label>
                                    <textarea class="ignis-input" id="notizen" name="notizen" rows="2"><?= htmlspecialchars($patient['notizen'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4 flex items-center justify-between">
                    <a href="<?= BASE_PATH ?>mci/board?id=<?= (int) $patient['manv_lage_id'] ?>" class="ignis-btn ignis-btn--ghost no-underline hover:no-underline">
                        <i class="fas fa-arrow-left mr-2"></i>Zurück zum Board
                    </a>
                    <button type="submit" class="ignis-btn ignis-btn--soft-primary ignis-btn--lg">
                        <i class="fas fa-save mr-2"></i>Änderungen speichern
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include dirname(__DIR__, 4) . '/assets/components/footer.php'; ?>

    <script>
        document.getElementById('transportmittel_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const transportzielGroup = document.getElementById('transportziel')?.closest('.mb-3');

            if (selectedOption.value) {
                document.getElementById('display_rufname').value = selectedOption.dataset.bezeichnung || '';
                document.getElementById('display_fahrzeugtyp').value = selectedOption.dataset.fahrzeugtyp || '';
                document.getElementById('display_lokalisation').value = selectedOption.dataset.lokalisation || '';

                const rdType = parseInt(selectedOption.dataset.rdtype);
                if (transportzielGroup) {
                    if (rdType >= 1) {
                        transportzielGroup.style.display = 'block';
                    } else {
                        transportzielGroup.style.display = 'none';
                        const tzSel = document.getElementById('transportziel');
                        if (tzSel) tzSel.value = 'Kein Transport';
                    }
                }
            } else {
                document.getElementById('display_rufname').value = '';
                document.getElementById('display_fahrzeugtyp').value = '';
                document.getElementById('display_lokalisation').value = '';
                if (transportzielGroup) {
                    transportzielGroup.style.display = 'block';
                }
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const selectElement = document.getElementById('transportmittel_id');
            if (selectElement) {
                selectElement.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>

</html>
