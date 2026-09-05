<?php
/**
 * Partial: Antragsinhalt als Raster in der Reihenfolge des Formulars.
 *
 * Felder mit halber Breite teilen sich eine Zeile, Vollbreite-Felder nehmen
 * die ganze. Textbereiche, Mehrfachauswahlen und Anhänge sind immer voll,
 * egal was das Feld sagt, wie schon in create.php. Jede Zelle: gedämpftes
 * Label über dem Wert.
 *
 * @var array<int,\stdClass> $felderMitWerten
 */
?>
<?php if (!empty($felderMitWerten)): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
        <?php foreach ($felderMitWerten as $feld): ?>
            <?php
            $fullWidth = ($feld->breite ?? 'full') !== 'half'
                || in_array($feld->feldtyp, ['textarea', 'multiselect', 'file'], true);
            if ($feld->feldtyp === 'checkbox') {
                $feldWert = $feld->wert ? '<i class="fa-solid fa-square-check text-[var(--ok)]" aria-hidden="true"></i> Ja' : '<i class="fa-regular fa-square text-[var(--text-3)]" aria-hidden="true"></i> Nein';
            } elseif (empty($feld->wert)) {
                $feldWert = '<span class="text-[var(--text-3)]">Keine Angabe</span>';
            } else {
                $feldWert = htmlspecialchars($feld->wert);
            }
            ?>
            <div class="<?= $fullWidth ? 'md:col-span-2 ' : '' ?>min-w-0">
                <div class="text-xs text-[var(--text-3)]"><?= htmlspecialchars($feld->label) ?></div>
                <div class="whitespace-pre-line break-words"><?= $feldWert ?></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="ignis-detail__muted">Keine Felddaten vorhanden.</p>
<?php endif; ?>
