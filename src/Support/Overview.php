<?php

declare(strict_types=1);

namespace App\Support;

use App\Auth\Gate;
use App\Models\Vehicle;
use App\Plugins\PluginLoader;
use DateTimeImmutable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;

/**
 * Zahlen der Übersicht (index.php). Jede Methode liefert null, wenn der
 * Betrachter die Daten nicht sehen darf oder ignis sie nicht hat. Dann
 * entfällt die Kachel oder Schale, eine Zahl wird nie erfunden. Hilfsfrist
 * und Besetzung je Schicht aus der Vorlage fehlen deshalb ganz: ignis kennt
 * weder eine Frist für Eintreffzeiten noch eine Sollstärke je Schicht.
 *
 * Die Zeitspalten (sendezeit, started_at, status_updated_at) schreibt die
 * Datenbank in Ortszeit, verglichen wird mit der Ortszeit von PHP.
 */
final class Overview
{
    private const DAYS   = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
    private const MONTHS = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    /** FMS-Status => Ton des Chips, 6 ist neutral und trägt ein Icon. */
    private const STATUS_TONES = ['0' => 'danger', '1' => 'ok', '2' => 'ok', '3' => 'info', '4' => 'info', '5' => 'warn', '7' => 'info', '8' => 'info'];

    /** „Donnerstag, 1. Oktober“ */
    public static function dateLabel(DateTimeImmutable $now): string
    {
        return self::DAYS[(int) $now->format('w')] . ', ' . $now->format('j') . '. ' . self::MONTHS[(int) $now->format('n') - 1];
    }

    /** „40 min“, „26 Std.“, „3 Tagen“, für Sätze mit „seit“. */
    public static function since(DateTimeImmutable $from, DateTimeImmutable $now): string
    {
        $minutes = max(0, intdiv($now->getTimestamp() - $from->getTimestamp(), 60));
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        $hours = intdiv($minutes, 60);
        if ($hours < 48) {
            return $hours . ' Std.';
        }

        return intdiv($hours, 24) . ' Tagen';
    }

    /**
     * Einsätze aus den eNOTF-Protokollen (Rettungsdienst) und aus fireTab,
     * jeweils nur, wenn der Betrachter deren Liste sehen darf. `hours` geht
     * von 0 Uhr bis zur laufenden Stunde, `days` über die letzten sieben
     * Tage bis heute. `yesterday` zählt gestern bis zur selben Uhrzeit.
     *
     * @return array{today:int, rd:?int, fire:?int, yesterday:int, hours:list<int>, days:list<int>}|null
     */
    public static function incidents(PluginLoader $plugins, DateTimeImmutable $now): ?array
    {
        $sources = [];
        if ($plugins->isActive('enotf') && Gate::allows('enotf.viewAdminList')) {
            $sources['rd'] = [Capsule::table('intra_edivi')->where('hidden', '<>', 1)->where('hidden_user', '<>', 1), 'sendezeit'];
        }
        if ($plugins->isActive('firetab') && \Plugin\Firetab\Policies\FireIncidentPolicy::viewAdminList()) {
            $sources['fire'] = [Capsule::table('intra_fire_incidents')->where('archived', 0), 'started_at'];
        }
        if ($sources === []) {
            return null;
        }

        $today = $now->setTime(0, 0);
        $first = $today->modify('-6 days');
        $hours = array_fill(0, (int) $now->format('G') + 1, 0);
        $days  = array_fill(0, 7, 0);
        $counts = ['rd' => 0, 'fire' => 0];
        $yesterday = 0;

        foreach ($sources as $key => [$query, $column]) {
            /** @var Builder $query */
            $rows = (clone $query)
                ->where($column, '>=', $first->format('Y-m-d H:i:s'))
                ->where($column, '<=', $now->format('Y-m-d H:i:s'))
                ->selectRaw("DATE($column) AS day, HOUR($column) AS hour, COUNT(*) AS n")
                ->groupByRaw("DATE($column), HOUR($column)")
                ->get();
            foreach ($rows as $row) {
                $offset = (int) $first->diff(new DateTimeImmutable((string) $row->day))->days;
                $days[$offset] += (int) $row->n;
                if ($offset === 6) {
                    $hours[(int) $row->hour] += (int) $row->n;
                    $counts[$key] += (int) $row->n;
                }
            }
            $yesterday += (clone $query)
                ->where($column, '>=', $today->modify('-1 day')->format('Y-m-d H:i:s'))
                ->where($column, '<=', $now->modify('-1 day')->format('Y-m-d H:i:s'))
                ->count();
        }

        return [
            'today'     => $counts['rd'] + $counts['fire'],
            'rd'        => isset($sources['rd']) ? $counts['rd'] : null,
            'fire'      => isset($sources['fire']) ? $counts['fire'] : null,
            'yesterday' => $yesterday,
            'hours'     => $hours,
            'days'      => $days,
        ];
    }

    /**
     * Aktive Fahrzeuge mit gemeldetem FMS-Status. Ohne einen einzigen
     * Status gibt es nichts zu zeigen, 0 von 12 einsatzbereit wäre falsch.
     * `rows` sind die fünf zuletzt geänderten, `down` alle in Status 6.
     *
     * @return array{total:int, ready:int, unknown:int, rows:list<array{name:string, rd_type:int, status:string, since:?DateTimeImmutable, station:string}>, down:list<array{name:string, since:?DateTimeImmutable}>}|null
     */
    public static function vehicles(): ?array
    {
        if (!Gate::allows('vehicle.view')) {
            return null;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Vehicle> $vehicles */
        $vehicles = Vehicle::query()->with('stationierung')->where('active', 1)
            ->orderByDesc('status_updated_at')->orderBy('priority')->get();
        $rows = [];
        $unknown = 0;
        foreach ($vehicles as $vehicle) {
            $status = trim((string) $vehicle->getAttribute('current_status'));
            if ($status === '') {
                $unknown++;
                continue;
            }
            $changed = $vehicle->getAttribute('status_updated_at');
            $rows[] = [
                'name'    => $vehicle->name,
                'rd_type' => $vehicle->rd_type,
                'status'  => $status,
                'since'   => $changed !== null ? new DateTimeImmutable((string) $changed) : null,
                'station' => $vehicle->stationierungsort(),
            ];
        }
        if ($rows === []) {
            return null;
        }

        $ready = count(array_filter($rows, static fn (array $row): bool => in_array($row['status'], ['1', '2'], true)));
        $down  = array_values(array_map(
            static fn (array $row): array => ['name' => $row['name'], 'since' => $row['since']],
            array_filter($rows, static fn (array $row): bool => $row['status'] === '6'),
        ));

        return ['total' => count($rows), 'ready' => $ready, 'unknown' => $unknown, 'rows' => array_slice($rows, 0, 5), 'down' => $down];
    }

    /**
     * Ton und Beschriftung eines FMS-Status für den Chip.
     *
     * @return array{tone:?string, label:string}
     */
    public static function status(string $status): array
    {
        return [
            'tone'  => self::STATUS_TONES[$status] ?? null,
            'label' => Vehicle::STATUS_LABELS[$status] ?? 'Status ' . $status,
        ];
    }

    /**
     * eNOTF-Protokolle, die die Besatzung noch nicht freigegeben hat, dazu
     * der Anteil freigegebener Protokolle seit Montag.
     *
     * @return array{open:int, oldest:?DateTimeImmutable, week:int, weekReleased:int}|null
     */
    public static function openProtocols(PluginLoader $plugins, DateTimeImmutable $now): ?array
    {
        if (!$plugins->isActive('enotf') || !Gate::allows('enotf.viewAdminList')) {
            return null;
        }

        $base = Capsule::table('intra_edivi')->where('hidden', '<>', 1)->where('hidden_user', '<>', 1);
        $open = (clone $base)->where('freigegeben', 0);
        $oldest = (clone $open)->min('sendezeit');
        $week = (clone $base)->where('sendezeit', '>=', $now->modify('monday this week')->setTime(0, 0)->format('Y-m-d H:i:s'))
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(freigegeben = 1), 0) AS released')->first();

        return [
            'open'         => $open->count(),
            'oldest'       => is_string($oldest) ? new DateTimeImmutable($oldest) : null,
            'week'         => (int) ($week->total ?? 0),
            'weekReleased' => (int) ($week->released ?? 0),
        ];
    }
}
