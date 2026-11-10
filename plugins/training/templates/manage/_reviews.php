<?php
/**
 * Reiter Bewertungen: Versuche, die auf den angemeldeten Prüfer warten.
 *
 * @var list<\Plugin\Training\Models\Attempt> $reviews
 */

use Plugin\Training\Models\Attempt;
?>
<div class="twplus-table-card">
    <?php if ($reviews === []): ?>
        <p class="ignis-detail__muted p-4 m-0">Nichts zu bewerten.</p>
    <?php else: ?>
        <div class="twplus-table-card__scroll">
            <table class="ignis-table" data-ignis-mobile-table>
                <thead><tr><th scope="col">Teilnehmer</th><th scope="col">Prüfung</th><th scope="col">Abgegeben</th><th scope="col">Wartet auf</th><th scope="col"><span class="ignis-sr-only">Aktionen</span></th></tr></thead>
                <tbody>
                    <?php foreach ($reviews as $attempt): ?>
                        <?php $url = $base . 'training/attempts/' . (int) $attempt->id . ($attempt->exam->isTheory() ? '/review' : '/sheet'); ?>
                        <tr>
                            <td data-label="Teilnehmer" data-mobile-primary><a href="<?= $url ?>"><?= htmlspecialchars($attempt->participation->name()) ?></a></td>
                            <td data-label="Prüfung"><?= htmlspecialchars($attempt->exam->title) ?> · Versuch <?= (int) $attempt->number ?></td>
                            <td data-label="Abgegeben"><?= $attempt->submitted_at !== null ? htmlspecialchars($attempt->submitted_at->format('d.m.Y, H:i')) : '' ?></td>
                            <td data-label="Wartet auf"><span class="ignis-chip ignis-chip--sm ignis-chip--warn"><?= $attempt->status === Attempt::COSIGN ? 'Gegenzeichnung' : 'Bewertung' ?></span></td>
                            <td class="ignis-table__actions"><a href="<?= $url ?>" class="ignis-btn ignis-btn--secondary ignis-btn--sm"><?= $attempt->status === Attempt::COSIGN ? 'Gegenzeichnen' : 'Bewerten' ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<p class="ignis-detail__muted mt-2">Freitexte und nicht erkannte Kurzantworten bewertet ein Prüfer von Hand. Die eigene Prüfung bewertet niemand selbst, und wer bewertet hat, zeichnet nicht gegen.</p>
