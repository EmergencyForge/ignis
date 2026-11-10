<?php
/**
 * Reiter Abschluss einer Lehrgangsart: welche Schritte nach dem Bestehen
 * laufen, mit Vorlage, Unterschrift, Bekanntgabe und Folgeaufgabe.
 *
 * @var \Plugin\Training\Models\CourseType $type
 * @var string                             $typeUrl
 * @var array{plan: \EmergencyForge\Training\Completion\CompletionPlan, settings: array{announce_channel: string, announce_webhook: string, announce_text: string, follow_up_target: string, follow_up_title: string, follow_up_days: int}, templates: list<\App\Models\EditorTemplate>, users: array<int, string>, roles: array<int, string>} $completion
 */

use App\Documents\Editor\SystemTemplates;
use EmergencyForge\Training\Completion\CompletionStep;
use Plugin\Training\Completion\Settings;
use Plugin\Training\Qualifications;

['plan' => $plan, 'settings' => $settings, 'templates' => $templates, 'users' => $users, 'roles' => $roles] = $completion;
$passName   = Qualifications::label($type->pass_qualification);
$validity   = $type->validity_months !== null ? ', gültig ' . ($type->validity_months % 12 === 0 ? ($type->validity_months / 12) . ' Jahre' : $type->validity_months . ' Monate') : '';
$templateId = $type->certificate_template_id;
if ($templateId === null) {
    foreach ($templates as $template) {
        if ($template->name === SystemTemplates::TRAINING_COURSE) {
            $templateId = (int) $template->id;
        }
    }
}
$option = static fn (string $value, string $label, string $current): string => '<option value="' . htmlspecialchars($value) . '"' . ($value === $current ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
$step   = static function (CompletionStep $step, string $title, string $text) use ($plan): string {
    $id = 'completion-' . $step->value;
    if ($step === CompletionStep::Result) {
        $control = '<span class="ignis-chip ignis-chip--sm ignis-chip--secondary">immer</span>';
    } else {
        $control = '<span class="ignis-switch"><input type="checkbox" id="' . $id . '" name="steps[' . $step->value . ']" value="1"' . ($plan->has($step) ? ' checked' : '') . ' data-training-step="' . $step->value . '"><span></span></span>';
    }

    return '<div class="tr-chain__head"><div><label for="' . $id . '" class="tr-chain__title">' . htmlspecialchars($title) . '</label><p class="tr-chain__text">' . htmlspecialchars($text) . '</p></div><div>' . $control . '</div></div>';
};
?>
<p class="ignis-detail__muted mb-3">Was passiert, sobald jemand bestanden hat. Bei „Nicht bestanden“ laufen nur Ergebnis, Verlauf und Benachrichtigung.</p>
<form method="post" action="<?= $typeUrl ?>/completion" class="ignis-card">
    <?= csrf_field() ?>
    <div class="ignis-card__header"><h3 class="ignis-card__title">Schritte nach dem Bestehen</h3></div>
    <div class="ignis-card__body tr-chain">
        <div class="tr-chain__row">
            <?= $step(CompletionStep::Result, 'Ergebnis festhalten', 'Abschlussdatum, Prozent und Note an der Teilnahme.') ?>
        </div>
        <div class="tr-chain__row">
            <?= $step(CompletionStep::Qualification, 'Qualifikation eintragen', $passName !== null
                ? $passName . $validity . '. Ersetzt die Qualifikation bei Beginn, eine höhere bleibt. Festgelegt unter Allgemein.'
                : 'Unter Allgemein ist keine Qualifikation bei Bestehen festgelegt, der Schritt wird übersprungen.') ?>
        </div>
        <div class="tr-chain__row">
            <?= $step(CompletionStep::Certificate, 'Urkunde ausstellen', 'Kommt in die Dokumente des Mitarbeiters. Hat die Vorlage Pflichtfelder ohne Wert, entsteht ein Entwurf und die Leitung bekommt eine Meldung.') ?>
            <div class="tr-chain__more" data-training-more="certificate">
                <div>
                    <label for="completion-template" class="ignis-field__label">Vorlage</label>
                    <select id="completion-template" name="certificate_template_id" class="ignis-input" data-custom-dropdown="true">
                        <option value="">Keine</option>
                        <?php foreach ($templates as $template): ?>
                            <?= $option((string) $template->id, $template->name . ($template->category !== null ? ' · ' . $template->category : ''), (string) $templateId) ?>
                        <?php endforeach; ?>
                    </select>
                    <p class="ignis-field__hint">Die Fassungen „(Lehrgang)“ füllen sich aus dem Lehrgang.</p>
                </div>
                <div>
                    <label for="completion-signer" class="ignis-field__label">Unterschrift</label>
                    <select id="completion-signer" name="signer_mode" class="ignis-input" data-custom-dropdown="true" data-training-signer>
                        <?php foreach (Settings::SIGNERS as $value => $label): ?>
                            <?= $option($value, $label, $type->signer_mode) ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div data-training-fixed<?= $type->signer_mode === Settings::SIGNER_FIXED ? '' : ' hidden' ?>>
                    <label for="completion-signer-user" class="ignis-field__label">Feste Person</label>
                    <select id="completion-signer-user" name="signer_user_id" class="ignis-input" data-custom-dropdown="true">
                        <option value="">Bitte wählen</option>
                        <?php foreach ($users as $id => $name): ?>
                            <?= $option((string) $id, $name, (string) $type->signer_user_id) ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="tr-chain__row">
            <?= $step(CompletionStep::Journal, 'Im Verlauf vermerken', 'Eintrag im Verlauf des Mitarbeiters mit Link auf Lehrgang und Urkunde.') ?>
        </div>
        <div class="tr-chain__row">
            <?= $step(CompletionStep::Notify, 'Benachrichtigen', '„Glückwunsch, du hast den ' . $type->short . '-Lehrgang bestanden.“ als Benachrichtigung, mit Link auf die Urkunde.') ?>
        </div>
        <div class="tr-chain__row">
            <?= $step(CompletionStep::Announce, 'Bekannt geben', 'Kurze Meldung für alle, wer bestanden hat.') ?>
            <div class="tr-chain__more" data-training-more="announce">
                <div>
                    <label for="completion-channel" class="ignis-field__label">Wo</label>
                    <select id="completion-channel" name="announce_channel" class="ignis-input" data-custom-dropdown="true">
                        <?php foreach (Settings::CHANNELS as $value => $label): ?>
                            <?= $option($value, $label, $settings['announce_channel']) ?>
                        <?php endforeach; ?>
                    </select>
                    <p class="ignis-field__hint">Den Hinweis sehen alle auf dem Dashboard, er läuft nach <?= Settings::ANNOUNCE_DAYS ?> Tagen ab.</p>
                </div>
                <div>
                    <label for="completion-webhook" class="ignis-field__label">Webhook-URL</label>
                    <input type="url" id="completion-webhook" name="announce_webhook" class="ignis-input" value="<?= htmlspecialchars($settings['announce_webhook']) ?>" placeholder="https://discord.com/api/webhooks/…">
                    <p class="ignis-field__hint">Nur für Discord. Leer lassen beim Dashboard.</p>
                </div>
                <div>
                    <label for="completion-text" class="ignis-field__label">Text</label>
                    <input type="text" id="completion-text" name="announce_text" class="ignis-input" maxlength="500" value="<?= htmlspecialchars($settings['announce_text']) ?>">
                    <p class="ignis-field__hint">{person.name}, {lehrgang.titel}, {lehrgang.art}, {qualifikation.name}</p>
                </div>
            </div>
        </div>
        <div class="tr-chain__row">
            <?= $step(CompletionStep::FollowUp, 'Folgeaufgabe anlegen', 'Eine Aufgabe zum Mitarbeiter, zum Beispiel für eine Beförderung. Sie steht bei den Empfängern unter „Für dich offen“ und auf der Profilkarte.') ?>
            <div class="tr-chain__more" data-training-more="follow_up">
                <div>
                    <label for="completion-target" class="ignis-field__label">An</label>
                    <select id="completion-target" name="follow_up_target" class="ignis-input" data-custom-dropdown="true">
                        <option value="">Bitte wählen</option>
                        <optgroup label="Rolle">
                            <?php foreach ($roles as $id => $name): ?>
                                <?= $option('role:' . $id, 'Rolle: ' . $name, $settings['follow_up_target']) ?>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Person">
                            <?php foreach ($users as $id => $name): ?>
                                <?= $option('user:' . $id, 'Person: ' . $name, $settings['follow_up_target']) ?>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label for="completion-task" class="ignis-field__label">Aufgabe</label>
                    <input type="text" id="completion-task" name="follow_up_title" class="ignis-input" maxlength="200" value="<?= htmlspecialchars($settings['follow_up_title']) ?>">
                </div>
                <div>
                    <label for="completion-days" class="ignis-field__label">Frist in Tagen</label>
                    <input type="number" id="completion-days" name="follow_up_days" class="ignis-input" min="0" max="365" value="<?= (int) $settings['follow_up_days'] ?>">
                    <p class="ignis-field__hint">0 heißt ohne Frist.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="ignis-card__footer" data-form-actions>
        <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Speichern</button>
    </div>
</form>
