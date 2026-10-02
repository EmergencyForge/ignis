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
     * Get a flat list of all fullnames (local + remote).
     * Used for simple name dropdowns (e.g., eNOTF Fahrer/Beifahrer).
     *
     * Returns:
     * [
     *   ['fullname' => 'Max Mustermann', 'source_name' => null],
     *   ['fullname' => 'Anna Schmidt', 'source_name' => 'Rettungsdienst'],
     * ]
     *
     * @return list<array{fullname: string, source_name: string|null}>
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
     * @return list<array{id: int|string, fullname: string, source_name: string|null}> Each: ['id' => int|string, 'fullname' => string, 'source_name' => string|null]
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
     * @return string|null The fullname, or null if not found
     */
    public static function resolveName(string|int|null $leaderId): ?string
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
