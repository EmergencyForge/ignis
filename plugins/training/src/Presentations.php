<?php

declare(strict_types=1);

namespace Plugin\Training;

use App\Auth\FabricaClient;
use App\Models\User;
use App\Support\AccountIdentity;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use EmergencyForge\Training\Presentation\Attendance as AttendanceRule;
use EmergencyForge\Training\Presentation\DrawEvent;
use EmergencyForge\Training\Presentation\Liveness;
use EmergencyForge\Training\Presentation\Question;
use EmergencyForge\Training\Presentation\Signal;
use EmergencyForge\Training\Presentation\Visit;
use EmergencyForge\Training\Presentation\WatchTime;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseStaff;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Presentation;
use Plugin\Training\Models\PresentationEvent;
use Plugin\Training\Models\PresentationQuestion;
use Plugin\Training\Models\PresentationViewer;
use Plugin\Training\Models\PresentationVisit;
use Plugin\Training\Models\Session;
use Plugin\Training\Models\Unit;
use Plugin\Training\Policies\TrainingPolicy;

/**
 * Live-Vortrag an einem Termin: starten, Zeichnungen mit fortlaufender
 * Nummer ablegen, Seite und Modus führen, Zuschauer zählen, Fragen annehmen,
 * Besuche führen, übergeben und beenden. Zeichnungen und Fragen leben nur
 * während des Vortrags, die Besuche bleiben. Die Regeln (Verwaisung,
 * Zählschritt, Ereignisformat, Fragenlänge, Besuchslücke) liegen im Paket.
 */
final class Presentations
{
    /** Was der Host direkt setzen darf; `screen` gibt es nur über startScreen(). */
    public const MODES = ['slides', 'pause'];

    /** Zuschauer gelten als da, wenn sie in den letzten Sekunden gefragt haben. */
    public const VIEWER_WINDOW = 10;

    /** Laser und Signale sind flüchtig; ältere räumt jedes Schreiben weg. */
    private const LASER_TTL = 60;

    /** Ohne LiveKit lädt der Host je Zuschauer einmal hoch; mehr trägt eine normale Leitung nicht. */
    public const SCREEN_LIMIT = 15;

    /** Wer im direkten Weg welches Signal senden darf. */
    private const HOST_SIGNALS   = ['offer', 'full', 'bye'];
    private const VIEWER_SIGNALS = ['ready', 'answer', 'bye'];

    /** Ein Verbindungsaufbau braucht drei Signale; mehr als das je Minute und Gegenüber ist ein Fehler oder Absicht. */
    private const SIGNAL_LIMIT  = 20;
    private const SIGNAL_WINDOW = 60;

    /**
     * Mit EF Sync (`$client`) bekommt der Vortrag einen Raum; der Name kommt vom Server und
     * nie vom Browser. Den Schlüssel dafür holt erst roomToken().
     */
    public static function start(Session $session, int $userId, ?FabricaClient $client = null): Presentation
    {
        // Sperre auf dem Termin, damit zwei gleichzeitige Starts nur einen Vortrag anlegen.
        $talk = Capsule::connection()->transaction(static function () use ($session, $userId, $client): Presentation {
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
                'room'              => $client !== null ? bin2hex(random_bytes(16)) : null,
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
     * Anwesenheitsvorschlag je Teilnahme über alle Läufe eines Termins, damit ein
     * neu gestarteter Vortrag die Zeit des abgebrochenen nicht verdrängt. Die
     * Zuschauzeit wird je Lauf gekappt, denn ein später Abruf kann nach dem Ende
     * noch zählen; die Minutenregel gilt einmal auf die Summen.
     *
     * @param iterable<Presentation> $runs beendete Läufe eines Termins, Zuschauer vorab geladen
     * @return array{minutes:int, need:int, viewers: array<int, array{minutes:int, suggested:bool}>}
     */
    public static function suggestions(iterable $runs): array
    {
        $seconds = 0;
        $watched = [];
        foreach ($runs as $p) {
            $talk     = $p->talkSeconds();
            $seconds += $talk;
            foreach ($p->viewers->whereNotNull('participation_id') as $viewer) {
                $watched[$viewer->participation_id] = ($watched[$viewer->participation_id] ?? 0) + min($viewer->seconds_watched, $talk);
            }
        }
        $base = AttendanceRule::wholeMinutes($seconds, 0);
        $out  = ['minutes' => $base['minutes'], 'need' => $base['need'], 'viewers' => []];
        foreach ($watched as $participationId => $sum) {
            $r = AttendanceRule::wholeMinutes($seconds, $sum);
            $out['viewers'][$participationId] = ['minutes' => $r['watched'], 'suggested' => $r['suggested']];
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
     * Ob Teilnehmer des Lehrgangs die Folien einer Präsentation sehen: nach dem Vortrag
     * nur mit dem Schalter an der Einheit, sonst solange der Termin der Einheit in
     * diesem Lehrgang läuft. Die Einheit gehört der Lehrgangsart, ein Vortrag in einem
     * anderen Lehrgang öffnet nichts. Die einzige Stelle für diese Regel; Ausbilder
     * prüfen die Controller vorher.
     */
    public static function slidesOpen(Unit $unit, Course $course, DateTimeImmutable $now): bool
    {
        return $unit->slides_after_talk || self::liveSessionFor($unit, $course, $now) !== null;
    }

    /** Der Termin der Einheit in diesem Lehrgang, an dem gerade ein Vortrag läuft. */
    public static function liveSessionFor(Unit $unit, Course $course, DateTimeImmutable $now): ?Session
    {
        foreach (Session::query()->where('unit_id', $unit->id)->where('course_id', $course->id)->get() as $session) {
            if (self::live($session, $now) !== null) {
                return $session;
            }
        }

        return null;
    }

    /** Ob an irgendeinem Termin der Einheit, in jedem Lehrgang, ein Vortrag läuft; die Folien sind für alle dieselbe Datei. */
    public static function liveAnywhere(Unit $unit, DateTimeImmutable $now): bool
    {
        foreach (Session::query()->where('unit_id', $unit->id)->get() as $session) {
            if (self::live($session, $now) !== null) {
                return true;
            }
        }

        return false;
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
            if ($mode !== null) {
                self::stopScreen($row);
            }
            if ($changed) {
                $row->version++;
            }
            $row->save();
            self::prune($row, $now);

            return $seq;
        });
    }

    private static function prune(Presentation $row, Carbon $now): void
    {
        PresentationEvent::query()->where('presentation_id', $row->id)->whereIn('kind', ['laser', 'signal'])
            ->where('created_at', '<', $now->copy()->subSeconds(self::LASER_TTL))->delete();
    }

    /** Teilen endet auf den Folien. Der Raum gehört dem Vortrag und bleibt. */
    private static function stopScreen(Presentation $row): void
    {
        if ($row->mode === 'screen') {
            $row->mode = 'slides';
        }
        $row->screen_via = null;
    }

    /**
     * Teilen beginnen. Mit Raum über LiveKit; scheitert die Konsole oder soll es
     * direkt gehen (`$client` leer), entfällt der Raum und es geht direkt weiter,
     * solange der Host die Zuschauer allein versorgen kann.
     *
     * @return array{via:'livekit'|'direct', url?:string, token?:string}
     * @throws DomainException `too_many` ohne LiveKit bei mehr als SCREEN_LIMIT Zuschauern, `ended`, `not_host`
     */
    public static function startScreen(Presentation $p, int $userId, ?FabricaClient $client): array
    {
        $room  = $p->room;
        $token = null;
        if ($client !== null && $room !== null) {
            // Vor der Sperre, damit eine langsame Konsole die Zeile nicht festhält.
            try {
                $token = $client->liveToken($room, 'u' . $userId, self::identity(User::query()->with('mitarbeiter')->find($userId))['name'], true);
            } catch (\Throwable $e) {
                // 503: die Konsole hat kein LiveKit eingerichtet. Das ist der Normalfall, keine Störung.
                if ($e->getCode() !== 503) {
                    error_log('LiveKit-Schlüssel fehlgeschlagen, Bildschirm geht direkt: ' . $e->getMessage());
                }
            }
        }

        return self::locked($p, static function (Presentation $row) use ($userId, $room, $token): array {
            if ($row->ended_at !== null) {
                throw new DomainException('ended');
            }
            if ($row->presenter_id !== $userId) {
                throw new DomainException('not_host');
            }
            // Zwischen Anfrage und Sperre kann ein anderer den Raum fallen gelassen haben.
            $key  = $row->room === $room ? $token : null;
            $live = $key !== null;
            if (!$live && PresentationViewer::query()->where('presentation_id', $row->id)->where('user_id', '!=', $row->presenter_id)
                ->where('last_seen_at', '>=', Carbon::now()->subSeconds(self::VIEWER_WINDOW))->count() > self::SCREEN_LIMIT) {
                throw new DomainException('too_many');
            }
            $row->mode       = 'screen';
            $row->screen_via = $live ? 'livekit' : 'direct';
            if (!$live) {
                // Ohne Raum für den Host geht auch für die Zuschauer nichts darüber.
                $row->room = null;
            }
            $row->version++;
            // Wer noch bei Nummer 0 steht, fragt mit after=0 und bekäme keine Signale; so steht jeder danach über 0.
            $row->seq++;
            $row->save();

            return $key !== null ? ['via' => 'livekit'] + $key : ['via' => 'direct'];
        });
    }

    /**
     * Raum-Schlüssel für einen Zuschauer oder den Host. Scheitert die Konsole, fällt der
     * Raum des Vortrags weg und alles läuft über die Abfrage weiter; die Version steigt,
     * damit alle Fenster es merken.
     *
     * @return array{url:string, token:string}
     * @throws DomainException `ended`, `no_room` ohne Raum, ohne Konsole oder wenn sie nicht liefert
     */
    public static function roomToken(Presentation $p, int $userId, ?FabricaClient $client): array
    {
        if ($p->ended_at !== null) {
            throw new DomainException('ended');
        }
        $room = $p->room;
        if ($room === null || $client === null) {
            throw new DomainException('no_room');
        }

        $host = $userId === $p->presenter_id;
        // Zuschauer je Tab eindeutig: kommt dieselbe Identität zweimal, wirft LiveKit die ältere Verbindung hinaus.
        $identity = 'u' . $userId . ($host ? '' : '-' . bin2hex(random_bytes(4)));
        try {
            return $client->liveToken($room, $identity, self::identity(User::query()->with('mitarbeiter')->find($userId))['name'], $host);
        } catch (\RuntimeException $e) {
            // 503: die Konsole hat kein LiveKit eingerichtet. Das ist der Normalfall, keine Störung.
            if ($e->getCode() !== 503) {
                error_log('LiveKit-Schlüssel fehlgeschlagen, der Vortrag läuft ohne Raum: ' . $e->getMessage());
            }
            self::locked($p, static function (Presentation $row) use ($room): void {
                // Nur den Raum fallen lassen, der gescheitert ist; ein anderer Aufruf war vielleicht schneller.
                if ($row->room === $room) {
                    $row->room = null;
                    $row->version++;
                    $row->save();
                }
            });
            throw new DomainException('no_room');
        }
    }

    /**
     * Signal für den direkten Verbindungsaufbau. Der Host schreibt an einen
     * Zuschauer, ein Zuschauer immer an den Host; die Version bleibt, damit
     * andere Zuschauer nicht neu laden.
     *
     * @param array<mixed> $raw ungeprüft aus dem JSON des Browsers
     * @return int vergebene Nummer
     * @throws InvalidArgumentException ungültiges Signal oder Empfänger
     * @throws DomainException wenn nicht direkt geteilt wird; `too_many_signals` ab SIGNAL_LIMIT in der letzten Minute
     */
    public static function signal(Presentation $p, int $fromUserId, ?int $toUserId, array $raw): int
    {
        $signal = Signal::normalize($raw);
        if ($signal === null) {
            throw new InvalidArgumentException('Ungültiges Signal.');
        }

        return self::locked($p, static function (Presentation $row) use ($signal, $fromUserId, $toUserId): int {
            if ($row->ended_at !== null || $row->mode !== 'screen' || $row->screen_via !== 'direct') {
                throw new DomainException('Gerade wird nicht direkt geteilt.');
            }
            if ($fromUserId === $row->presenter_id) {
                $viewer = $toUserId !== null && $toUserId !== $fromUserId
                    && PresentationViewer::query()->where('presentation_id', $row->id)->where('user_id', $toUserId)->exists();
                if (!$viewer || !in_array($signal['type'], self::HOST_SIGNALS, true)) {
                    throw new InvalidArgumentException('Ungültiges Signal.');
                }
            } else {
                if (!in_array($signal['type'], self::VIEWER_SIGNALS, true)) {
                    throw new InvalidArgumentException('Ungültiges Signal.');
                }
                $toUserId = $row->presenter_id;
            }
            $now = Carbon::now();
            // Je Absender und Empfänger: der Host schreibt an bis zu 15 Zuschauer und darf dabei nicht an der Grenze hängen.
            // Der Absender steht immer vorn im JSON, siehe insert() unten.
            $recent = PresentationEvent::query()->where('presentation_id', $row->id)->where('kind', 'signal')->where('to_user_id', $toUserId)
                ->where('data', 'like', '{"from":' . $fromUserId . ',%')
                ->where('created_at', '>=', $now->copy()->subSeconds(self::SIGNAL_WINDOW))->count();
            if ($recent >= self::SIGNAL_LIMIT) {
                throw new DomainException('too_many_signals');
            }
            PresentationEvent::query()->insert(['presentation_id' => $row->id, 'seq' => ++$row->seq, 'kind' => 'signal', 'to_user_id' => $toUserId,
                'data' => (string) json_encode(['from' => $fromUserId] + $signal, JSON_UNESCAPED_UNICODE), 'created_at' => $now]);
            $row->save();
            self::prune($row, $now);

            return $row->seq;
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
            self::stopScreen($row);
            $row->save();
            PresentationEvent::query()->where('presentation_id', $row->id)->delete();
            // Fragen leben nur im Vortrag. Offene Besuche enden mit ihrem letzten Lebenszeichen und bleiben als Protokoll.
            PresentationQuestion::query()->where('presentation_id', $row->id)->delete();
            PresentationVisit::query()->where('presentation_id', $row->id)->whereNull('left_at')->update(['left_at' => new Expression('last_seen_at')]);
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
            // Der Stream gehört dem alten Host.
            self::stopScreen($row);
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

    /**
     * Host: Lebenszeichen. Alle anderen: Zeit zählen, je Anfrage höchstens ein Zählschritt.
     * Jeder, auch der Host, führt dabei seinen Besuch.
     */
    public static function heartbeat(Presentation $p, int $userId, ?int $participationId, DateTimeImmutable $now): void
    {
        self::visit($p, $userId, $now);
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
     * Besuch verlängern oder nach einer Lücke einen neuen beginnen. Der Normalfall, ein laufender
     * Besuch, ist ein einziges Update ohne Sperre; nur Eröffnen und Schließen laufen unter der
     * Sperre des Vortrags, damit zwei Tabs im selben Augenblick nur einen Besuch eröffnen.
     */
    private static function visit(Presentation $p, int $userId, DateTimeImmutable $now): void
    {
        $at   = Carbon::instance($now);
        $last = PresentationVisit::query()->where('presentation_id', $p->id)->where('user_id', $userId)->whereNull('left_at')->latest('id')->first();
        // Nur ein noch offener Besuch wird verlängert; hat das Ende ihn inzwischen geschlossen, trifft das Update nichts.
        if ($last !== null && Visit::continues($last->last_seen_at->toDateTimeImmutable(), $now)
            && PresentationVisit::query()->whereKey($last->id)->whereNull('left_at')->update(['last_seen_at' => $at]) > 0) {
            return;
        }
        self::locked($p, static function (Presentation $row) use ($userId, $at, $now): void {
            if ($row->ended_at !== null) {
                return;
            }
            $last = PresentationVisit::query()->where('presentation_id', $row->id)->where('user_id', $userId)->whereNull('left_at')->latest('id')->first();
            if ($last !== null) {
                if (Visit::continues($last->last_seen_at->toDateTimeImmutable(), $now)) {
                    $last->update(['last_seen_at' => $at]);

                    return;
                }
                $last->update(['left_at' => $last->last_seen_at]);
            }
            PresentationVisit::query()->create(['presentation_id' => $row->id, 'user_id' => $userId, 'joined_at' => $at, 'last_seen_at' => $at]);
        });
    }

    /**
     * Neue Frage eines Folgenden an die Ausbilder.
     *
     * @throws DomainException `ended`, `invalid` bei leerem oder zu langem Text, `too_many` ab Question::LIMIT in der letzten Minute
     */
    public static function ask(Presentation $p, int $userId, string $raw): PresentationQuestion
    {
        $text = Question::text($raw);
        if ($text === null) {
            throw new DomainException('invalid');
        }

        return self::locked($p, static function (Presentation $row) use ($userId, $text): PresentationQuestion {
            if ($row->ended_at !== null) {
                throw new DomainException('ended');
            }
            $now = Carbon::now();
            if (PresentationQuestion::query()->where('presentation_id', $row->id)->where('user_id', $userId)
                ->where('asked_at', '>', $now->copy()->subSeconds(60))->count() >= Question::LIMIT) {
                throw new DomainException('too_many');
            }
            $question = PresentationQuestion::query()->create(['presentation_id' => $row->id, 'user_id' => $userId, 'body' => $text, 'asked_at' => $now]);
            $row->q_version++;
            $row->save();

            return $question;
        });
    }

    /**
     * Antwort eines Ausbilders. Ohne Text ist die Frage nur abgehakt (mündlich beantwortet).
     *
     * @throws DomainException `ended`, `not_found`, `invalid` bei zu langem Text
     */
    public static function answer(Presentation $p, int $questionId, int $byUserId, ?string $answer): void
    {
        $text = null;
        if ($answer !== null && trim($answer) !== '') {
            $text = Question::text($answer);
            if ($text === null) {
                throw new DomainException('invalid');
            }
        }

        self::locked($p, static function (Presentation $row) use ($questionId, $byUserId, $text): void {
            if ($row->ended_at !== null) {
                throw new DomainException('ended');
            }
            $question = PresentationQuestion::query()->where('presentation_id', $row->id)->whereKey($questionId)->first();
            if ($question === null) {
                throw new DomainException('not_found');
            }
            $now = Carbon::now();
            if ($text !== null) {
                $question->answer      = $text;
                $question->answered_by = $byUserId;
                $question->answered_at = $now;
            } else {
                $question->done_at = $now;
            }
            $question->save();
            $row->q_version++;
            $row->save();
        });
    }

    /**
     * Frage als beantwortet abhaken.
     *
     * @throws DomainException `ended`, `not_found`
     */
    public static function done(Presentation $p, int $questionId, int $byUserId): void
    {
        self::answer($p, $questionId, $byUserId, null);
    }

    /**
     * Fragen für die Abfrage, serverseitig nach Rolle gefiltert: Ausbilder sehen alle,
     * jeder andere nur die eigenen.
     *
     * @return list<array{id:int, user_id:int, name:string, body:string, asked_at:string, answer:?string, done:bool}>
     */
    public static function questions(Presentation $p, int $userId, bool $staff): array
    {
        $query = PresentationQuestion::query()->with('user.mitarbeiter')->where('presentation_id', $p->id);
        if (!$staff) {
            $query->where('user_id', $userId);
        }
        $out = [];
        foreach ($query->oldest('asked_at')->oldest('id')->get() as $q) {
            $out[] = [
                'id'       => $q->id,
                'user_id'  => $q->user_id,
                'name'     => self::identity($q->user)['name'],
                'body'     => $q->body,
                'asked_at' => $q->asked_at->toIso8601String(),
                'answer'   => $q->answer,
                'done'     => $q->done_at !== null,
            ];
        }

        return $out;
    }

    /**
     * Besuche der Läufe, nach Kommen sortiert. Ein offener Besuch, der keine Lebenszeichen
     * mehr bekommt, gilt ab dem letzten als beendet, auch bevor jemand ihn schließt.
     *
     * @param iterable<Presentation> $runs
     * @return list<array{user_id:int, name:string, joined_at:string, left_at:?string}>
     */
    public static function visits(iterable $runs): array
    {
        $ids = [];
        foreach ($runs as $run) {
            $ids[] = $run->id;
        }
        if ($ids === []) {
            return [];
        }
        $now = new DateTimeImmutable();
        $out = [];
        foreach (PresentationVisit::query()->with('user.mitarbeiter')->whereIn('presentation_id', $ids)->oldest('joined_at')->oldest('id')->get() as $visit) {
            $left = $visit->left_at;
            if ($left === null && !Visit::continues($visit->last_seen_at->toDateTimeImmutable(), $now)) {
                $left = $visit->last_seen_at;
            }
            $out[] = [
                'user_id'   => $visit->user_id,
                'name'      => self::identity($visit->user)['name'],
                'joined_at' => $visit->joined_at->toIso8601String(),
                'left_at'   => $left?->toIso8601String(),
            ];
        }

        return $out;
    }

    /**
     * Wem der Host den Vortrag übergeben kann, nach Namen.
     *
     * @return list<array{id:int, name:string, initials:string, role:string}>
     */
    public static function targets(Presentation $p): array
    {
        $ids = array_values(array_filter(
            self::staffIds($p),
            static fn (int $id): bool => $id !== $p->presenter_id && TrainingPolicy::canTeach($id),
        ));
        $lead = Session::query()->with('course')->findOrFail($p->session_id)->course->lead_user_id;
        $out  = [];
        foreach (User::query()->with('mitarbeiter')->whereKey($ids)->get() as $user) {
            $who   = self::identity($user);
            $out[] = ['id' => (int) $user->id, 'name' => $who['name'], 'initials' => $who['initials'], 'role' => (int) $user->id === $lead ? 'Leitung' : 'Ausbilder'];
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
