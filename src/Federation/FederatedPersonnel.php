<?php

namespace App\Federation;

use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;

/**
 * Unified personnel access: merges local intra_mitarbeiter with
 * cached federation personnel from linked instances.
 *
 * When FEDERATION_ENABLED is false, returns only local data (zero overhead).
 *
 * Die Methoden laufen über die Eloquent-Capsule; der optionale $pdo-Parameter
 * bleibt nur für Alt-Aufrufer erhalten und wird ignoriert.
 */
class FederatedPersonnel
{
    /**
     * Get all personnel: local + remote, grouped by source.
     *
     * Returns:
     * [
     *   ['source' => null, 'source_name' => 'Lokal', 'personnel' => [...]],
     *   ['source' => 'uuid-abc', 'source_name' => 'Rettungsdienst', 'personnel' => [...]],
     * ]
     *
     * Each personnel entry has: id, fullname, dienstnr, dienstgrad_name, dienstgrad_badge,
     * quali_rd, is_remote, federation_id (for remote entries).
     */
    public static function getAllGrouped(?PDO $pdo = null): array
    {
        $groups = [];

        // Local personnel
        $local = Capsule::table('intra_mitarbeiter as m')
            ->leftJoin('intra_mitarbeiter_dienstgrade as d', 'm.dienstgrad', '=', 'd.id')
            ->leftJoin('intra_mitarbeiter_rdquali as rd', 'm.rdquali', '=', 'rd.id')
            ->select([
                'm.id',
                'm.fullname',
                'm.dienstnr',
                'd.name as dienstgrad_name',
                'd.abkuerzung as dienstgrad_badge',
                'rd.name as quali_rd',
                'rd.abkuerzung as quali_rd_short',
            ])
            ->orderBy('m.fullname', 'asc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        foreach ($local as &$p) {
            $p['is_remote'] = false;
            $p['federation_id'] = null;
        }
        unset($p);

        $groups[] = [
            'source' => null,
            'source_name' => 'Lokal',
            'personnel' => $local,
        ];

        // Remote personnel (only if federation is enabled)
        if (FederationMiddleware::isEnabled()) {
            try {
                $remoteAll = Capsule::table('intra_federation_cache_personnel as fcp')
                    ->join('intra_federation_links as fl', function ($join) {
                        $join->on('fl.instance_id', '=', 'fcp.source_instance_id')
                            ->where('fl.is_active', 1);
                    })
                    ->select([
                        'fcp.id',
                        'fcp.source_instance_id',
                        'fcp.remote_id',
                        'fcp.fullname',
                        'fcp.dienstnr',
                        'fcp.dienstgrad_name',
                        'fcp.dienstgrad_badge',
                        'fcp.quali_rd',
                        'fl.instance_name as source_name',
                    ])
                    ->orderBy('fl.instance_name', 'asc')
                    ->orderBy('fcp.fullname', 'asc')
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->all();

                // Group by source instance
                $bySource = [];
                foreach ($remoteAll as $p) {
                    $sid = $p['source_instance_id'];
                    if (!isset($bySource[$sid])) {
                        $bySource[$sid] = [
                            'source' => $sid,
                            'source_name' => $p['source_name'],
                            'personnel' => [],
                        ];
                    }
                    $bySource[$sid]['personnel'][] = [
                        'id' => $p['remote_id'],
                        'fullname' => $p['fullname'],
                        'dienstnr' => $p['dienstnr'],
                        'dienstgrad_name' => $p['dienstgrad_name'],
                        'dienstgrad_badge' => $p['dienstgrad_badge'],
                        'quali_rd' => $p['quali_rd'],
                        'quali_rd_short' => null,
                        'is_remote' => true,
                        'federation_id' => 'fed:' . $sid . ':' . $p['remote_id'],
                    ];
                }

                foreach ($bySource as $group) {
                    $groups[] = $group;
                }
            } catch (\PDOException $e) {
                // Federation cache might not exist yet — silently skip
            }
        }

        return $groups;
    }

    /**
     * Get a flat list of all fullnames (local + remote).
     * Used for simple name dropdowns (e.g., eNOTF Fahrer/Beifahrer).
     *
     * Returns:
     * [
     *   ['fullname' => 'Max Mustermann', 'source_name' => null],
     *   ['fullname' => 'Anna Schmidt', 'source_name' => 'Rettungsdienst'],
     * ]
     */
    public static function getAllNames(?PDO $pdo = null): array
    {
        $names = [];

        // Local
        $localNames = Capsule::table('intra_mitarbeiter')
            ->orderBy('fullname', 'asc')
            ->pluck('fullname');
        foreach ($localNames as $name) {
            $names[] = ['fullname' => $name, 'source_name' => null];
        }

        // Remote
        if (FederationMiddleware::isEnabled()) {
            try {
                $remote = Capsule::table('intra_federation_cache_personnel as fcp')
                    ->join('intra_federation_links as fl', function ($join) {
                        $join->on('fl.instance_id', '=', 'fcp.source_instance_id')
                            ->where('fl.is_active', 1);
                    })
                    ->select(['fcp.fullname', 'fl.instance_name as source_name'])
                    ->orderBy('fl.instance_name', 'asc')
                    ->orderBy('fcp.fullname', 'asc')
                    ->get();
                foreach ($remote as $row) {
                    $names[] = (array) $row;
                }
            } catch (\PDOException $e) {
                // Silently skip
            }
        }

        return $names;
    }

    /**
     * Get all personnel as options for a leader dropdown.
     * Returns local IDs as integers, remote IDs as "fed:{instance_id}:{remote_id}".
     *
     * @return array[] Each: ['id' => int|string, 'fullname' => string, 'source_name' => string|null]
     */
    public static function getLeaderOptions(?PDO $pdo = null): array
    {
        $options = [];

        // Local
        $localRows = Capsule::table('intra_mitarbeiter')
            ->select(['id', 'fullname'])
            ->orderBy('fullname', 'asc')
            ->get();
        foreach ($localRows as $row) {
            $options[] = [
                'id' => (int) $row->id,
                'fullname' => $row->fullname,
                'source_name' => null,
            ];
        }

        // Remote
        if (FederationMiddleware::isEnabled()) {
            try {
                $remote = Capsule::table('intra_federation_cache_personnel as fcp')
                    ->join('intra_federation_links as fl', function ($join) {
                        $join->on('fl.instance_id', '=', 'fcp.source_instance_id')
                            ->where('fl.is_active', 1);
                    })
                    ->select([
                        'fcp.remote_id',
                        'fcp.source_instance_id',
                        'fcp.fullname',
                        'fl.instance_name as source_name',
                    ])
                    ->orderBy('fl.instance_name', 'asc')
                    ->orderBy('fcp.fullname', 'asc')
                    ->get();
                foreach ($remote as $row) {
                    $options[] = [
                        'id' => 'fed:' . $row->source_instance_id . ':' . $row->remote_id,
                        'fullname' => $row->fullname,
                        'source_name' => $row->source_name,
                    ];
                }
            } catch (\PDOException $e) {
                // Silently skip
            }
        }

        return $options;
    }

    /**
     * Resolve a leader ID (local int or "fed:..." string) to a display name.
     *
     * Signatur-Hinweis: $pdo wird ignoriert (siehe Klassen-Doc), $leaderId
     * ist das zweite Argument, damit Alt-Aufrufer kompatibel bleiben.
     *
     * @return string|null The fullname, or null if not found
     */
    public static function resolveName(?PDO $pdo = null, string|int|null $leaderId = null): ?string
    {
        if ($leaderId === null || $leaderId === '' || $leaderId === 0) {
            return null;
        }

        // Federation ID
        if (is_string($leaderId) && str_starts_with($leaderId, 'fed:')) {
            $parts = explode(':', $leaderId, 3);
            if (count($parts) !== 3) {
                return null;
            }

            [, $instanceId, $remoteId] = $parts;

            try {
                $row = Capsule::table('intra_federation_cache_personnel as fcp')
                    ->join('intra_federation_links as fl', 'fl.instance_id', '=', 'fcp.source_instance_id')
                    ->where('fcp.source_instance_id', $instanceId)
                    ->where('fcp.remote_id', (int) $remoteId)
                    ->select(['fcp.fullname', 'fl.instance_name'])
                    ->first();

                if ($row) {
                    return $row->fullname . ' [' . $row->instance_name . ']';
                }
            } catch (\PDOException $e) {
                // Fall through
            }

            return null;
        }

        // Local ID
        try {
            $name = Capsule::table('intra_mitarbeiter')
                ->where('id', (int) $leaderId)
                ->value('fullname');
            return $name ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }
}
