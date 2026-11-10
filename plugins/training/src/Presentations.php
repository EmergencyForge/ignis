<?php

declare(strict_types=1);

namespace Plugin\Training;

use App\Models\User;
use App\Support\AccountIdentity;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use EmergencyForge\Training\Presentation\Attendance as AttendanceRule;
use EmergencyForge\Training\Presentation\DrawEvent;
use EmergencyForge\Training\Presentation\Liveness;
use EmergencyForge\Training\Presentation\WatchTime;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Plugin\Training\Models\CourseStaff;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Presentation;
use Plugin\Training\Models\PresentationEvent;
use Plugin\Training\Models\PresentationViewer;
use Plugin\Training\Models\Session;
use Plugin\Training\Policies\TrainingPolicy;

/**
 * Live-Vortrag an einem Termin: starten, Zeichnungen mit fortlaufender
 * Nummer ablegen, Seite und Modus führen, Zuschauer zählen, übergeben und
 * beenden. Zeichnungen leben nur während des Vortrags. Die Regeln
 * (Verwaisung, Zählschritt, Ereignisformat) liegen im Paket.
 */
final class Presentations
{
    public const MODES = ['slides', 'pause'];

    /** Zuschauer gelten als da, wenn sie in den letzten Sekunden gefragt haben. */
    public const VIEWER_WINDOW = 10;

    /** Laser sind flüchtig; ältere räumt apply() weg. */
    private const LASER_TTL = 60;

    public static function start(Session $session, int $userId): Presentation
    {
        // Sperre auf dem Termin, damit zwei gleichzeitige Starts nur einen Vortrag anlegen.
        $talk = Capsule::connection()->transaction(static function () use ($session, $userId): Presentation {
            Session::query()->whereKey($session->id)->lockForUpdate()->first();
            $now  = new DateTimeImmutable();
            $live = self::live($session, $now);
            if ($live !== null) {
                return $live;
            }

            return Presentation::query()->create([
                'session_id'        => $session->id,
                'presenter_id'      => $userId,
                'started_at'        => Carbon::instance($now),
                'presenter_seen_at' => Carbon::instance($now),
            ]);
        });
        if ($talk->wasRecentlyCreated) {
            // Nach der Sperre und abgefangen: eine kaputte Benachrichtigung darf den Vortrag nicht verhindern.
            try {
                self::notifyStart($talk);
            } catch (\Throwable $e) {
                error_log('Vortragsstart: Benachrichtigung fehlgeschlagen: ' . $e->getMessage());
            }
        }

        return $talk;
    }

    /**
     * Teilnehmer mit offenen Unterlagen bekommen einen Hinweis, nur sie dürfen folgen.
     * Die Lernenden-Bedingung steht hier direkt, denn `follow()` fragt das Recht des
     * angemeldeten Hosts und ließe jeden durch.
     */
    public static function notifyStart(Presentation $p): void
    {
        $session = Session::query()->with('course')->findOrFail($p->session_id);
        $course  = $session->course;
        if (!$course->state()->materialsOpen()) {
            return;
        }
        $host = User::query()->with('mitarbeiter')->find($p->presenter_id);
        $ids  = [];
        foreach ($course->participations as $participation) {
            if ($participation->personnel_id !== null && $participation->canLearn() && $participation->personnel_id !== $host?->aktenid) {
                $ids[] = (int) $participation->personnel_id;
            }
        }
        Notify::personnel(
            $ids,
            'Vortrag läuft: ' . $session->title,
            'training/courses/' . $course->id . '/sessions/' . $session->id . '/presentation',
            self::identity($host)['name'] . ' zeigt gerade die Folien.',
        );
    }

    /**
     * Anwesenheitsvorschlag eines beendeten Vortrags je Teilnahme. Die Zuschauzeit
     * wird hier erneut gekappt, denn ein später Abruf kann nach dem Ende noch zählen.
     *
     * @return array{minutes:int, need:int, viewers: array<int, array{minutes:int, suggested:bool}>}
     */
    public static function suggestions(Presentation $p): array
    {
        $seconds = $p->talkSeconds();
        $base    = AttendanceRule::wholeMinutes($seconds, 0);
        $out     = ['minutes' => $base['minutes'], 'need' => $base['need'], 'viewers' => []];
        foreach ($p->viewers->whereNotNull('participation_id') as $viewer) {
            $r = AttendanceRule::wholeMinutes($seconds, $viewer->seconds_watched);
            $out['viewers'][$viewer->participation_id] = ['minutes' => $r['watched'], 'suggested' => $r['suggested']];
        }

        return $out;
    }

    /** Der laufende Vortrag des Termins; ein verwaister wird auf dem Weg beendet. */
    public static function live(Session $session, DateTimeImmutable $now): ?Presentation
    {
        $p = Presentation::query()->where('session_id', $session->id)->whereNull('ended_at')->latest('id')->first();
        if ($p !== null && Liveness::orphaned($p->presenter_seen_at->toDateTimeImmutable(), $now)) {
            // Der Vortrag endete mit dem letzten Lebenszeichen, nicht erst mit dieser Anfrage.
            self::end($p, $p->presenter_seen_at);

            return null;
        }

        return $p;
    }

    /**
     * Legt Ereignisse ab und setzt Seite und Modus in einem Zug unter der Zeilensperre,
     * damit Nummern und Version nie von einem veralteten Modell zurückgeschrieben werden.
     * Ein ungültiges Ereignis lässt alle durchfallen. Die Version steigt einmal, wenn sich
     * Seite oder Modus ändern.
     *
     * @param array<mixed> $events ungeprüft aus dem JSON des Browsers
     * @return int letzte vergebene Nummer
     * @throws InvalidArgumentException
     * @throws DomainException wenn der Vortrag inzwischen beendet ist
     */
    public static function apply(Presentation $p, array $events, int $pageCount, ?int $page, ?string $mode): int
    {
        $clean = [];
        foreach ($events as $raw) {
            $event = is_array($raw) ? DrawEvent::normalize($raw, $pageCount) : null;
            if ($event === null) {
                throw new InvalidArgumentException('event');
            }
            $clean[] = $event;
        }
        if ($mode !== null && !in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException('mode');
        }

        return self::locked($p, static function (Presentation $row) use ($clean, $page, $mode): int {
            if ($row->ended_at !== null) {
                throw new DomainException('ended');
            }
            $seq  = $row->seq;
            $now  = Carbon::now();
            $rows = [];
            foreach ($clean as $event) {
                $kind = (string) $event['kind'];
                unset($event['kind']);
                $rows[] = ['presentation_id' => $row->id, 'seq' => ++$seq, 'kind' => $kind,
                    'data' => (string) json_encode($event, JSON_UNESCAPED_UNICODE), 'created_at' => $now];
            }
            if ($rows !== []) {
                PresentationEvent::query()->insert($rows);
                $row->seq = $seq;
            }
            $changed = false;
            if ($page !== null && $page !== $row->page) {
                $row->page = max(0, $page);
                $changed   = true;
            }
            if ($mode !== null && $mode !== $row->mode) {
                $row->mode = $mode;
                $changed   = true;
            }
            if ($changed) {
                $row->version++;
            }
            $row->save();
            PresentationEvent::query()->where('presentation_id', $row->id)->where('kind', 'laser')
                ->where('created_at', '<', $now->copy()->subSeconds(self::LASER_TTL))->delete();

            return $seq;
        });
    }

    /** @return bool ob dieser Aufruf beendet hat; false, wenn schon jemand anderes beendet hatte */
    public static function end(Presentation $p, ?DateTimeInterface $at = null): bool
    {
        return self::locked($p, static function (Presentation $row) use ($at): bool {
            if ($row->ended_at !== null) {
                return false;
            }
            $row->ended_at    = $at !== null ? Carbon::instance($at) : Carbon::now();
            $row->handover_to = null;
            $row->save();
            PresentationEvent::query()->where('presentation_id', $row->id)->delete();
            // Ein verwaister Vortrag endet rückwirkend; Tabs, die danach noch zählten, kämen sonst über die Vortragsdauer.
            $seconds = $row->talkSeconds();
            PresentationViewer::query()->where('presentation_id', $row->id)->where('seconds_watched', '>', $seconds)
                ->update(['seconds_watched' => $seconds]);

            return true;
        });
    }

    /**
     * Nur Leitung oder Ausbilder des Lehrgangs mit Lehrrecht, und nicht der Host selbst.
     *
     * @throws InvalidArgumentException mit der Meldung `target`
     */
    public static function requestHandover(Presentation $p, int $toUserId): void
    {
        self::locked($p, static function (Presentation $row) use ($toUserId): void {
            // Unter der Sperre, denn der Host kann seit dem Lesen gewechselt haben.
            if ($toUserId === $row->presenter_id || !in_array($toUserId, self::staffIds($row), true) || !TrainingPolicy::canTeach($toUserId)) {
                throw new InvalidArgumentException('target');
            }
            $row->handover_to = $toUserId;
            $row->version++;
            $row->save();
        });
    }

    public static function cancelHandover(Presentation $p): void
    {
        self::locked($p, static function (Presentation $row): void {
            if ($row->handover_to === null) {
                return;
            }
            $row->handover_to = null;
            $row->version++;
            $row->save();
        });
    }

    /** @throws InvalidArgumentException wenn die Übergabe inzwischen nicht mehr diesem Nutzer gilt */
    public static function acceptHandover(Presentation $p, int $userId): void
    {
        self::locked($p, static function (Presentation $row) use ($userId): void {
            if ($row->handover_to !== $userId) {
                throw new InvalidArgumentException('handover');
            }
            $row->presenter_id      = $userId;
            $row->handover_to       = null;
            // Sonst gälte der Vortrag beim neuen Host gleich als verwaist, wenn der alte lange still war.
            $row->presenter_seen_at = Carbon::now();
            $row->version++;
            $row->save();
        });
    }

    /** @throws InvalidArgumentException wenn die Übergabe inzwischen nicht mehr diesem Nutzer gilt */
    public static function declineHandover(Presentation $p, int $userId): void
    {
        self::locked($p, static function (Presentation $row) use ($userId): void {
            if ($row->handover_to !== $userId) {
                throw new InvalidArgumentException('handover');
            }
            $row->handover_to = null;
            $row->version++;
            $row->save();
        });
    }

    /** Host: Lebenszeichen. Alle anderen: Zeit zählen, je Anfrage höchstens ein Zählschritt. */
    public static function heartbeat(Presentation $p, int $userId, ?int $participationId, DateTimeImmutable $now): void
    {
        if ($userId === $p->presenter_id) {
            Presentation::query()->whereKey($p->id)->update(['presenter_seen_at' => Carbon::instance($now)]);

            return;
        }
        // Zwei Tabs desselben Zuschauers können gleichzeitig zum ersten Mal fragen; der zweite fällt auf den Unique-Index.
        for ($try = 0; ; $try++) {
            $viewer = PresentationViewer::query()->where('presentation_id', $p->id)->where('user_id', $userId)->first();
            try {
                PresentationViewer::query()->updateOrCreate(
                    ['presentation_id' => $p->id, 'user_id' => $userId],
                    [
                        'participation_id' => $participationId,
                        'first_seen_at'    => $viewer->first_seen_at ?? Carbon::instance($now),
                        'last_seen_at'     => Carbon::instance($now),
                        'seconds_watched'  => WatchTime::add($viewer->seconds_watched ?? 0, $viewer?->last_seen_at->toDateTimeImmutable(), $now),
                    ],
                );

                return;
            } catch (QueryException $e) {
                // Nur der Unique-Index ist einen zweiten Versuch wert.
                if ($try >= 1 || $e->getCode() !== '23000' || ($e->errorInfo[1] ?? null) !== 1062) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Wem der Host den Vortrag übergeben kann, nach Namen.
     *
     * @return list<array{id:int, name:string}>
     */
    public static function targets(Presentation $p): array
    {
        $ids = array_values(array_filter(
            self::staffIds($p),
            static fn (int $id): bool => $id !== $p->presenter_id && TrainingPolicy::canTeach($id),
        ));
        $out = [];
        foreach (User::query()->with('mitarbeiter')->whereKey($ids)->get() as $user) {
            $out[] = ['id' => (int) $user->id, 'name' => self::identity($user)['name']];
        }
        usort($out, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * Name und Initialen wie in der Kopfleiste. Das Foto bleibt hier ungenutzt.
     *
     * @return array{name:string, initials:string}
     */
    public static function identity(?User $user): array
    {
        if ($user === null) {
            return ['name' => '', 'initials' => ''];
        }
        $identity = AccountIdentity::resolve($user->mitarbeiter, (string) $user->username);

        return ['name' => $identity['name'], 'initials' => $identity['initials']];
    }

    /**
     * Leitung und Ausbilder des Lehrgangs zum Vortrag.
     *
     * @return list<int>
     */
    private static function staffIds(Presentation $p): array
    {
        $course = Session::query()->with('course')->findOrFail($p->session_id)->course;
        $ids    = CourseStaff::query()->where('course_id', $course->id)->where('role', 'instructor')
            ->pluck('user_id')->map(static fn ($id): int => (int) $id)->all();
        if ($course->lead_user_id !== null) {
            $ids[] = $course->lead_user_id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Die Zeile unter Sperre neu lesen, ändern und das Modell des Aufrufers nachziehen.
     *
     * @template T
     * @param callable(Presentation): T $fn
     * @return T
     */
    private static function locked(Presentation $p, callable $fn): mixed
    {
        $result = Capsule::connection()->transaction(
            static fn (): mixed => $fn(Presentation::query()->whereKey($p->id)->lockForUpdate()->firstOrFail()),
        );
        $p->refresh();

        return $result;
    }
}
