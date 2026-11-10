<?php
/**
 * Reiter Fragenpool: Themen links, Fragen rechts. Suche und Art-Filter
 * filtern im Browser, das Thema über die Adresse.
 *
 * @var \Plugin\Training\Models\CourseType       $type
 * @var list<\Plugin\Training\Models\Topic>      $topics
 * @var int                                      $topic
 * @var list<\Plugin\Training\Models\Question>   $questions
 * @var array<int, int>                          $counts  Thema (0 = ohne) => Fragen
 * @var array<int, int>                          $usage
 * @var string                                   $typeUrl
 */

use EmergencyForge\Training\Question\QuestionType;
use Plugin\Training\Exams;

$current     = null;
foreach ($topics as $candidate) {
    if ($candidate->id === $topic) {
        $current = $candidate;
    }
}
$topicNames  = [];
foreach ($topics as $t) {
    $topicNames[$t->id] = $t->name;
}
$shown       = $current === null ? $questions : array_values(array_filter($questions, static fn ($q): bool => $q->topic_id === $current->id));
$createUrl   = $typeUrl . '/questions/create' . ($current !== null ? '?topic=' . $current->id . '&' : '?');
?>
<div class="tr-split">
    <div class="ignis-card">
        <div class="ignis-card__body tr-topics">
            <a href="<?= $typeUrl ?>#fragenpool"<?= $current === null ? ' class="is-current" aria-current="true"' : '' ?>><span>Alle Themen</span><small><?= count($questions) ?></small></a>
            <?php foreach ($topics as $t): ?>
                <a href="<?= $typeUrl ?>?topic=<?= (int) $t->id ?>#fragenpool"<?= $current?->id === $t->id ? ' class="is-current" aria-current="true"' : '' ?>><span><?= htmlspecialchars($t->name) ?></span><small><?= (int) ($counts[$t->id] ?? 0) ?></small></a>
            <?php endforeach; ?>
            <?php if (($counts[0] ?? 0) > 0): ?>
                <p class="tr-meta mt-2 mb-0 px-2"><?= (int) $counts[0] ?> ohne Thema</p>
            <?php endif; ?>
            <details class="tr-topics__add">
                <summary><i class="fa-solid fa-plus" aria-hidden="true"></i> Thema anlegen</summary>
                <form method="post" action="<?= $typeUrl ?>/topics" class="flex gap-2 mt-2">
                    <?= csrf_field() ?>
                    <label for="topic-new" class="ignis-sr-only">Name des Themas</label>
                    <input type="text" id="topic-new" name="name" class="ignis-input" maxlength="150" required placeholder="z. B. Recht">
                    <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--icon" aria-label="Anlegen" data-ignis-tooltip="Anlegen"><i class="fa-solid fa-check" aria-hidden="true"></i></button>
                </form>
            </details>
        </div>
    </div>

    <div>
        <?php if ($current !== null): ?>
            <div class="flex items-center gap-2 mb-3 flex-wrap">
                <form method="post" action="<?= $base ?>training/topics/<?= (int) $current->id ?>" class="flex gap-2 items-center">
                    <?= csrf_field() ?>
                    <label for="topic-name" class="ignis-sr-only">Name des Themas</label>
                    <input type="text" id="topic-name" name="name" class="ignis-input" maxlength="150" required value="<?= htmlspecialchars($current->name) ?>">
                    <button type="submit" class="ignis-btn ignis-btn--ghost ignis-btn--sm">Umbenennen</button>
                </form>
                <form method="post" action="<?= $base ?>training/topics/<?= (int) $current->id ?>/delete" onsubmit="<?= confirm_attr('Thema „' . $current->name . '“ löschen?') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="ignis-btn ignis-btn--ghost-danger ignis-btn--sm"><i class="fa-solid fa-trash" aria-hidden="true"></i> Thema löschen</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="tr-toolbar">
            <label for="pool-search" class="ignis-sr-only">Fragen durchsuchen</label>
            <input type="search" id="pool-search" class="ignis-input" placeholder="Fragen durchsuchen" data-tr-filter-text="#pool-table">
            <select id="pool-kind" class="ignis-input" data-custom-dropdown="true" data-tr-filter-kind="#pool-table" aria-label="Art">
                <option value="">Alle Arten</option>
                <?php foreach (QuestionType::cases() as $kind): ?>
                    <option value="<?= $kind->value ?>"><?= htmlspecialchars($kind->label()) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="flex-1"></span>
            <?php
            $importUrl    = $typeUrl . '/questions/import';
            $importAccept = '.csv,.json,text/csv,application/json';
            $importHint   = 'CSV mit einer Zeile je Frage oder JSON aus einem Export, bis 5 MB';
            $importId     = 'pool-import';
            require dirname(__DIR__) . '/partials/import.php';
            ?>
            <details class="ignis-menu ignis-menu--right" data-ignis-menu>
                <summary class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-file-export" aria-hidden="true"></i> Exportieren</summary>
                <div class="ignis-menu__panel" role="menu">
                    <a class="ignis-menu__item" role="menuitem" href="<?= $typeUrl ?>/questions/export?format=csv">Als CSV (Excel)</a>
                    <a class="ignis-menu__item" role="menuitem" href="<?= $typeUrl ?>/questions/export?format=json">Als JSON</a>
                </div>
            </details>
            <details class="ignis-menu ignis-menu--right" data-ignis-menu>
                <summary class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Frage</summary>
                <div class="ignis-menu__panel" role="menu">
                    <?php foreach (QuestionType::cases() as $kind): ?>
                        <a class="ignis-menu__item" role="menuitem" href="<?= htmlspecialchars($createUrl . 'type=' . $kind->value) ?>"><?= htmlspecialchars($kind->label()) ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>

        <?php if ($shown === []): ?>
            <div class="ignis-card"><div class="ignis-card__body">
                <p class="m-0">Noch keine Fragen<?= $current !== null ? ' in diesem Thema' : '' ?>. Lege eine an oder importiere eine CSV-Datei.</p>
            </div></div>
        <?php else: ?>
            <div class="ignis-card ignis-card--table">
                <table class="ignis-table" id="pool-table">
                    <thead><tr><th scope="col">Frage</th><th scope="col">Art</th><th scope="col" class="ignis-table__num">Punkte</th><th scope="col" class="ignis-table__num">Version</th><th scope="col" class="ignis-table__num">Gestellt</th><th scope="col">Aktiv</th><th scope="col"><span class="ignis-sr-only">Aktionen</span></th></tr></thead>
                    <tbody>
                        <?php foreach ($shown as $question): ?>
                            <?php $kind = $question->questionType(); ?>
                            <tr data-kind="<?= $kind->value ?>" data-text="<?= htmlspecialchars(mb_strtolower($question->body . ' ' . ($topicNames[$question->topic_id] ?? ''))) ?>">
                                <td>
                                    <div class="tr-q"><a href="<?= $base ?>training/questions/<?= (int) $question->id ?>/edit"><?= htmlspecialchars($question->body) ?></a></div>
                                    <div class="tr-meta"><?= htmlspecialchars($topicNames[$question->topic_id] ?? 'Ohne Thema') ?></div>
                                </td>
                                <td><span class="ignis-chip ignis-chip--sm <?= $kind === QuestionType::FreeText ? 'ignis-chip--warn' : 'ignis-chip--secondary' ?>"><?= htmlspecialchars($kind->label()) ?></span></td>
                                <td class="ignis-table__num"><?= htmlspecialchars(Exams::number($question->points)) ?></td>
                                <td class="ignis-table__num">v<?= (int) $question->version ?></td>
                                <td class="ignis-table__num"><?= (int) ($usage[$question->id] ?? 0) ?></td>
                                <td><span class="ignis-switch"><input type="checkbox" aria-label="Aktiv" data-tr-question-active="<?= $base ?>training/questions/<?= (int) $question->id ?>/active"<?= $question->is_active ? ' checked' : '' ?>><span></span></span></td>
                                <td class="ignis-table__actions"><a href="<?= $base ?>training/questions/<?= (int) $question->id ?>/edit" class="ignis-btn ignis-btn--ghost ignis-btn--icon ignis-btn--sm" aria-label="Bearbeiten" data-ignis-tooltip="Bearbeiten"><i class="fa-solid fa-pen" aria-hidden="true"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p class="ignis-detail__muted mt-2">CSV: Spalten Thema; Art; Frage; Antworten; Punkte; Erklärung. Antworten mit <code>|</code> trennen, richtige mit <code>*</code> markieren, Zuordnung als <code>links=rechts</code>, Zahl als <code>Wert|Toleranz|Einheit</code>.</p>
    </div>
</div>
