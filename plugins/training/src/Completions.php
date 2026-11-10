<?php

declare(strict_types=1);

namespace Plugin\Training;

use DateTimeImmutable;
use EmergencyForge\Training\Completion\Completion;
use EmergencyForge\Training\Completion\CompletionPlan;
use EmergencyForge\Training\Completion\CompletionRunner;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Training\Completion\CompletionTable;
use Plugin\Training\Completion\Steps;
use Plugin\Training\Models\Participation;

/**
 * Startet die Abschluss-Kette einer Teilnahme und führt sie weiter.
 *
 * Gestartet wird in der Anfrage, die das Ergebnis setzt; was dort
 * scheitert, holt `training:complete` nach. Damit Cron und „Erneut
 * versuchen“ nicht gleichzeitig dieselbe Urkunde ausstellen, läuft jede
 * Teilnahme unter einer Datenbank-Sperre und mit frisch gelesenem Stand.
 */
final class Completions
{
    public function __construct(private readonly CompletionTable $table = new CompletionTable())
    {
    }

    /** Legt den Abschluss an, falls es ihn noch nicht gibt. Läuft noch nicht. */
    public function start(Participation $participation, bool $passed): void
    {
        if ($this->table->find($participation->id) !== null) {
            return;
        }
        $plan = CompletionPlan::fromArray($participation->course->type->completion_plan);
        $this->table->save(Completion::start($participation->id, $passed, $plan));
    }

    /** `$force` übergeht die Wartezeit nach einem Fehlschlag. */
    public function run(int $participationId, bool $force = false, ?DateTimeImmutable $now = null): ?Completion
    {
        $lock = 'ignis_training_completion_' . $participationId;
        if ((int) Capsule::connection()->selectOne('SELECT GET_LOCK(?, 0) AS l', [$lock])->l !== 1) {
            return null;
        }
        try {
            $completion = $this->table->find($participationId);
            if ($completion === null || $completion->isFinished()) {
                return $completion;
            }

            return (new CompletionRunner($this->table, (new Steps($this->table))->handlers()))->run($completion, $now ?? new DateTimeImmutable(), $force);
        } finally {
            Capsule::connection()->selectOne('SELECT RELEASE_LOCK(?) AS l', [$lock]);
        }
    }

    public function startAndRun(Participation $participation, bool $passed): void
    {
        $this->start($participation, $passed);
        $this->run($participation->id);
    }

    /** Für den Cron: fällige Abschlüsse weiterführen. */
    public function runPending(?DateTimeImmutable $now = null, int $limit = 50): int
    {
        $now ??= new DateTimeImmutable();
        $count = 0;
        foreach ($this->table->pending($limit) as $completion) {
            if ($completion->isDue($now)) {
                $this->run($completion->participationId, false, $now);
                $count++;
            }
        }

        return $count;
    }
}
