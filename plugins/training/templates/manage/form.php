<?php
/**
 * Lehrgang anlegen oder bearbeiten. Titel und Nummer vergibt das System
 * aus Kürzel, laufender Nummer und Jahr.
 *
 * @var \Plugin\Training\Models\Course|null          $course
 * @var array<string, mixed>                          $form
 * @var list<\Plugin\Training\Models\CourseType>      $types
 * @var list<\App\Models\User>                        $users
 */

$layout     = 'admin';
$bodyId     = 'training';
$SITE_TITLE = $course === null ? 'Lehrgang anlegen' : $course->title . ' bearbeiten';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$userName   = static fn (\App\Models\User $u): string => $u->fullname ?: $u->username;
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>training/manage">Lehrgänge</a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= $course === null ? 'Neu' : htmlspecialchars($course->title) ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1><?= htmlspecialchars($SITE_TITLE) ?></h1>
                    <?php if ($course === null): ?>
                        <p class="twplus-page-header__description">Der Lehrgang startet als „Geplant“. Die Anmeldung öffnest du danach über den Status.</p>
                    <?php endif; ?>
                </div>
            </div>

            <form method="post" action="<?= $base ?>training/manage<?= $course !== null ? '/' . (int) $course->id : '' ?>" class="ignis-card">
                <?= csrf_field() ?>
                <div class="ignis-card__body grid gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="course-type" class="ignis-field__label">Lehrgangsart <span class="ignis-field__required">*</span></label>
                            <select id="course-type" name="course_type_id" class="ignis-input" data-custom-dropdown="true"<?= $course !== null ? ' disabled' : ' required' ?>>
                                <option value="">Bitte wählen</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?= (int) $type->id ?>"<?= (int) $form['course_type_id'] === $type->id ? ' selected' : '' ?>><?= htmlspecialchars($type->name . ' (' . $type->short . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($course !== null): ?><p class="ignis-field__hint">Die Lehrgangsart steht fest, sobald der Lehrgang angelegt ist.</p><?php endif; ?>
                        </div>
                        <div>
                            <label for="course-year" class="ignis-field__label">Jahr <span class="ignis-field__required">*</span></label>
                            <input type="number" id="course-year" name="year" class="ignis-input" min="2000" max="2100" required value="<?= (int) $form['year'] ?>">
                            <p class="ignis-field__hint">Die laufende Nummer im Jahr vergibt ignis, etwa „RS-Lehrgang 3/2026“.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label for="course-starts" class="ignis-field__label">Beginn</label>
                            <input type="date" data-ignis-datepicker id="course-starts" name="starts_on" class="ignis-input" value="<?= htmlspecialchars((string) $form['starts_on']) ?>">
                        </div>
                        <div>
                            <label for="course-ends" class="ignis-field__label">Ende</label>
                            <input type="date" data-ignis-datepicker id="course-ends" name="ends_on" class="ignis-input" value="<?= htmlspecialchars((string) $form['ends_on']) ?>">
                        </div>
                        <div>
                            <label for="course-until" class="ignis-field__label">Anmeldeschluss</label>
                            <input type="datetime-local" data-ignis-datetimepicker id="course-until" name="enroll_until" class="ignis-input" value="<?= htmlspecialchars((string) $form['enroll_until']) ?>">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="course-seats" class="ignis-field__label">Plätze</label>
                            <input type="number" id="course-seats" name="seats" class="ignis-input" min="1" value="<?= htmlspecialchars((string) $form['seats']) ?>">
                            <p class="ignis-field__hint">Leer heißt unbegrenzt. Wer keinen Platz bekommt, landet auf der Warteliste.</p>
                        </div>
                        <div>
                            <label for="course-lead" class="ignis-field__label">Leitung</label>
                            <select id="course-lead" name="lead_user_id" class="ignis-input" data-custom-dropdown="true">
                                <option value="">Noch offen</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= (int) $user->id ?>"<?= (int) $form['lead_user_id'] === $user->id ? ' selected' : '' ?>><?= htmlspecialchars($userName($user)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="ignis-field__hint">Leitung und Ausbilder brauchen das Recht „Lehrgänge leiten und darin ausbilden“.</p>
                        </div>
                    </div>
                    <div>
                        <span class="ignis-field__label" id="course-instructors-label">Ausbilder</span>
                        <div data-ignis-multi-select data-name="instructors[]" aria-labelledby="course-instructors-label" data-placeholder="Konto suchen"
                             data-options="<?= htmlspecialchars((string) json_encode(array_map(static fn ($u): array => ['value' => (int) $u->id, 'label' => $userName($u)], $users), JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
                             data-value="<?= htmlspecialchars(implode(',', array_map('intval', (array) $form['instructors']))) ?>"></div>
                        <p class="ignis-field__hint">Ausbilder sehen Teilnehmer und Fortschritt, setzen Einheiten von Hand und tragen Anwesenheit ein.</p>
                    </div>
                    <div>
                        <span class="ignis-field__label" id="course-examiners-label">Prüfer</span>
                        <div data-ignis-multi-select data-name="examiners[]" aria-labelledby="course-examiners-label" data-placeholder="Konto suchen"
                             data-options="<?= htmlspecialchars((string) json_encode(array_map(static fn ($u): array => ['value' => (int) $u->id, 'label' => $userName($u)], $users), JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
                             data-value="<?= htmlspecialchars(implode(',', array_map('intval', (array) ($form['examiners'] ?? [])))) ?>"></div>
                        <p class="ignis-field__hint">Prüfer bewerten Freitexte und Praxisbögen. Ohne Prüfer bewerten Leitung und Ausbilder.</p>
                    </div>
                </div>
                <div class="ignis-card__footer" data-form-actions>
                    <a href="<?= $base ?>training/manage<?= $course !== null ? '/' . (int) $course->id : '' ?>" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-check" aria-hidden="true"></i> <?= $course === null ? 'Anlegen' : 'Speichern' ?></button>
                </div>
            </form>
        </div>
    </div>
    <script type="module" src="<?= $base ?>assets/js/ui/multi-select.js"></script>
