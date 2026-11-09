<?php

declare(strict_types=1);

namespace Plugin\Training;

use App\Personnel\PersonalLogManager;
use EmergencyForge\Training\Progress\PageCoverage;
use EmergencyForge\Training\Progress\Progress;
use EmergencyForge\Training\Progress\ProgressUnit;
use EmergencyForge\Training\UnitKind;
use Illuminate\Support\Carbon;
use Plugin\Training\Models\Attendance;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\Module;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Session;
use Plugin\Training\Models\Unit;
use Plugin\Training\Models\UnitProgress;

/**
 * Der Lehrplan eines Lehrgangs und der Stand der Teilnehmer darin.
 *
 * Der Lehrplan ist der der Lehrgangsart, gefolgt von den Zusatz-Modulen
 * des Lehrgangs. Er wird nicht kopiert: ersetzt die Leitung eine Datei in
 * der Lehrgangsart, sehen laufende Lehrgänge die neue Fassung, und der
 * Fortschritt bleibt, weil er an der Einheit hängt.
 *
 * Eine Einheit „Termin“ ist erledigt, sobald die Anwesenheit eingetragen
 * ist, gleich welche. Unentschuldigtes Fehlen zählt die Zulassung zur
 * Prüfung gesondert.
 */
final class Curriculum
{
    /** @var array<int, int> unit_id => Modulnummer ab 1 */
    private array $moduleNumbers = [];

    /** @var array<int, Session> unit_id => Termin */
    private array $sessions = [];

    /**
     * @param list<Module> $modules
     */
    private function __construct(public readonly Course $course, public readonly array $modules)
    {
        foreach ($modules as $index => $module) {
            foreach ($module->units as $unit) {
                $this->moduleNumbers[$unit->id] = $index + 1;
            }
        }
        foreach (Session::query()->where('course_id', $course->id)->whereNotNull('unit_id')->get() as $session) {
            $this->sessions[(int) $session->unit_id] = $session;
        }
    }

    public static function for(Course $course): self
    {
        /** @var list<Module> $modules */
        $modules = [
            ...Module::query()->with('units.file')->where('course_type_id', $course->course_type_id)->orderBy('position')->orderBy('id')->get()->all(),
            ...Module::query()->with('units.file')->where('course_id', $course->id)->orderBy('position')->orderBy('id')->get()->all(),
        ];

        return new self($course, $modules);
    }

    /** @return list<Unit> in Lehrplan-Reihenfolge */
    public function units(): array
    {
        $units = [];
        foreach ($this->modules as $module) {
            foreach ($module->units as $unit) {
                if ($unit->type() !== UnitKind::Check) {
                    $units[] = $unit;
                }
            }
        }

        return $units;
    }

    public function unit(int $unitId): ?Unit
    {
        foreach ($this->units() as $unit) {
            if ($unit->id === $unitId) {
                return $unit;
            }
        }

        return null;
    }

    public function moduleNumber(Unit $unit): int
    {
        return $this->moduleNumbers[$unit->id] ?? 0;
    }

    public function session(Unit $unit): ?Session
    {
        return $this->sessions[$unit->id] ?? null;
    }

    /** @return array<int, Session> */
    public function sessions(): array
    {
        return $this->sessions;
    }

    public function usesFile(int $fileId): bool
    {
        foreach ($this->units() as $unit) {
            if ($unit->file_id === $fileId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stand je Teilnahme und Einheit: 'done' oder 'started', dazu ob von
     * Hand gesetzt, bei PDFs die Seiten, bei Terminen die Anwesenheit.
     *
     * @param list<int> $participationIds
     * @return array<int, array<int, array{state: string, manual: bool, by: ?int, pages: ?array<string, mixed>, attendance: ?string}>>
     */
    public function states(array $participationIds): array
    {
        $states = array_fill_keys($participationIds, []);
        if ($participationIds === []) {
            return $states;
        }

        foreach (UnitProgress::query()->whereIn('participation_id', $participationIds)->get() as $row) {
            $states[$row->participation_id][$row->unit_id] = [
                'state'      => $row->status,
                'manual'     => $row->completed_by !== null,
                'by'         => $row->completed_by,
                'pages'      => $row->pages,
                'attendance' => null,
            ];
        }

        $unitBySession = [];
        foreach ($this->sessions as $unitId => $session) {
            $unitBySession[$session->id] = $unitId;
        }
        if ($unitBySession !== []) {
            $rows = Attendance::query()->whereIn('session_id', array_keys($unitBySession))->whereIn('participation_id', $participationIds)->get();
            foreach ($rows as $row) {
                $states[$row->participation_id][$unitBySession[$row->session_id]] = [
                    'state'      => 'done',
                    'manual'     => false,
                    'by'         => $row->recorded_by,
                    'pages'      => null,
                    'attendance' => $row->state,
                ];
            }
        }

        return $states;
    }

    /** @param array<int, array{state: string}> $state Stand einer Teilnahme aus states() */
    public function progress(array $state): Progress
    {
        $units = array_map(static fn (Unit $unit): ProgressUnit => new ProgressUnit($unit->id, $unit->module_id, $unit->required), $this->units());
        $done  = array_keys(array_filter($state, static fn (array $s): bool => $s['state'] === 'done'));

        return new Progress($units, $done, (bool) $this->course->type->sequential);
    }

    /** Unentschuldigt versäumte Pflichttermine, für die Zulassung zur Prüfung. */
    public function unexcusedAbsences(int $participationId): int
    {
        $required = array_map(static fn (Session $s): int => $s->id, array_filter($this->sessions, static fn (Session $s): bool => $s->required));
        if ($required === []) {
            return 0;
        }

        return Attendance::query()->whereIn('session_id', $required)->where('participation_id', $participationId)->where('state', 'unexcused')->count();
    }

    /**
     * „Modul 2 · Eigensicherung (PDF, Seite 12 von 40)“
     *
     * @param array{state: string, pages: ?array<string, mixed>}|null $state
     */
    public function resumeLabel(Unit $unit, ?array $state): string
    {
        $detail = $unit->type()->label();
        if ($unit->file !== null) {
            $detail = $unit->file->label();
            $pages  = $state['pages'] ?? null;
            if ($unit->file->isPdf() && is_array($pages) && (int) ($pages['total'] ?? 0) > 0) {
                $detail .= sprintf(', Seite %d von %d', max(1, (int) ($pages['last'] ?? 1)), (int) $pages['total']);
            }
        }

        return sprintf('Modul %d · %s (%s)', $this->moduleNumber($unit), $unit->title, $detail);
    }

    /** Einheit als erledigt vermerken. Mit `$byUserId` von Hand durch einen Ausbilder. */
    public static function markDone(Participation $participation, Unit $unit, ?int $byUserId = null): void
    {
        $row = UnitProgress::query()->firstOrNew(['participation_id' => $participation->id, 'unit_id' => $unit->id]);
        if ($row->status === 'done') {
            return;
        }
        $row->status       = 'done';
        $row->completed_at = Carbon::now();
        $row->completed_by = $byUserId;
        $row->save();
    }

    /**
     * Ein Ausbilder hakt eine Einheit ab oder nimmt seinen Haken zurück.
     * Beides steht im Verlauf des Mitarbeiters.
     */
    public static function setByHand(Participation $participation, Unit $unit, bool $done, int $userId, string $editor): void
    {
        $row = UnitProgress::query()->where('participation_id', $participation->id)->where('unit_id', $unit->id)->first();
        if ($done) {
            if ($row !== null && $row->status === 'done') {
                return;
            }
            self::markDone($participation, $unit, $userId);
        } else {
            if ($row === null || $row->completed_by === null) {
                return;
            }
            if ($row->pages === null) {
                $row->delete();
            } else {
                $row->status       = 'started';
                $row->completed_at = null;
                $row->completed_by = null;
                $row->save();
            }
        }

        if ($participation->personnel_id !== null) {
            (new PersonalLogManager())->addEntry(
                $participation->personnel_id,
                PersonalLogManager::TYPE_TRAINING,
                sprintf(
                    '%s: Einheit <strong>%s</strong> %s',
                    htmlspecialchars($participation->course->title),
                    htmlspecialchars($unit->title),
                    $done ? 'von Hand als erledigt gesetzt.' : 'wieder auf offen gesetzt.',
                ),
                $editor,
                ['change_type' => 'training_unit', 'course_id' => $participation->course_id, 'unit_id' => $unit->id, 'done' => $done],
            );
        }
    }

    /**
     * Seitenmeldung aus dem PDF-Betrachter. Alle Seiten gesehen heißt
     * erledigt.
     *
     * @param list<int> $pages
     * @return array{seen: int, total: int, done: bool}
     */
    public static function recordPages(Participation $participation, Unit $unit, array $pages, int $total): array
    {
        $row      = UnitProgress::query()->firstOrNew(['participation_id' => $participation->id, 'unit_id' => $unit->id]);
        $stored   = is_array($row->pages) ? $row->pages : [];
        $coverage = PageCoverage::fromRanges(is_array($stored['seen'] ?? null) ? $stored['seen'] : [])->add(...$pages);
        $total    = $total > 0 ? $total : (int) ($stored['total'] ?? 0);

        $row->pages = [
            'seen'  => $coverage->toRanges(),
            'total' => $total,
            'last'  => $pages !== [] ? max($pages) : (int) ($stored['last'] ?? 1),
        ];
        if ($row->status !== 'done') {
            $row->status = $coverage->isComplete($total) ? 'done' : 'started';
            if ($row->status === 'done') {
                $row->completed_at = Carbon::now();
            }
        }
        $row->save();

        return ['seen' => $coverage->seen($total), 'total' => $total, 'done' => $row->status === 'done'];
    }

    /**
     * „1-11,14“ für data-seen des Betrachters.
     *
     * @param array<string, mixed>|null $pages
     */
    public static function seenAttribute(?array $pages): string
    {
        $ranges = is_array($pages['seen'] ?? null) ? PageCoverage::fromRanges($pages['seen'])->toRanges() : [];

        return implode(',', array_map(static fn (array $r): string => $r[0] === $r[1] ? (string) $r[0] : $r[0] . '-' . $r[1], $ranges));
    }
}
