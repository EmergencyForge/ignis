<?php

namespace App\Federation;

use PDO;

/**
 * Unified personnel access: merges local intra_mitarbeiter with
 * cached federation personnel from linked instances.
 *
 * When FEDERATION_ENABLED is false, returns only local data (zero overhead).
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
    public static function getAllGrouped(PDO $pdo): array
    {
        $groups = [];

        // Local personnel
        $stmt = $pdo->query("
            SELECT
                m.id,
                m.fullname,
                m.dienstnr,
                d.name AS dienstgrad_name,
                d.abkuerzung AS dienstgrad_badge,
                rd.name AS quali_rd,
                rd.abkuerzung AS quali_rd_short
            FROM intra_mitarbeiter m
            LEFT JOIN intra_mitarbeiter_dienstgrade d ON m.dienstgrad = d.id
            LEFT JOIN intra_mitarbeiter_rdquali rd ON m.rdquali = rd.id
            ORDER BY m.fullname ASC
        ");
        $local = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        if (defined('FEDERATION_ENABLED') && FEDERATION_ENABLED) {
            try {
                $stmt = $pdo->query("
                    SELECT
                        fcp.id,
                        fcp.source_instance_id,
                        fcp.remote_id,
                        fcp.fullname,
                        fcp.dienstnr,
                        fcp.dienstgrad_name,
                        fcp.dienstgrad_badge,
                        fcp.quali_rd,
                        fl.instance_name AS source_name
                    FROM intra_federation_cache_personnel fcp
                    JOIN intra_federation_links fl ON fl.instance_id = fcp.source_instance_id AND fl.is_active = 1
                    ORDER BY fl.instance_name ASC, fcp.fullname ASC
                ");
                $remoteAll = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    public static function getAllNames(PDO $pdo): array
    {
        $names = [];

        // Local
        $stmt = $pdo->query("SELECT fullname FROM intra_mitarbeiter ORDER BY fullname ASC");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $names[] = ['fullname' => $name, 'source_name' => null];
        }

        // Remote
        if (defined('FEDERATION_ENABLED') && FEDERATION_ENABLED) {
            try {
                $stmt = $pdo->query("
                    SELECT fcp.fullname, fl.instance_name AS source_name
                    FROM intra_federation_cache_personnel fcp
                    JOIN intra_federation_links fl ON fl.instance_id = fcp.source_instance_id AND fl.is_active = 1
                    ORDER BY fl.instance_name ASC, fcp.fullname ASC
                ");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $names[] = $row;
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
    public static function getLeaderOptions(PDO $pdo): array
    {
        $options = [];

        // Local
        $stmt = $pdo->query("SELECT id, fullname FROM intra_mitarbeiter ORDER BY fullname ASC");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $options[] = [
                'id' => (int) $row['id'],
                'fullname' => $row['fullname'],
                'source_name' => null,
            ];
        }

        // Remote
        if (defined('FEDERATION_ENABLED') && FEDERATION_ENABLED) {
            try {
                $stmt = $pdo->query("
                    SELECT fcp.remote_id, fcp.source_instance_id, fcp.fullname, fl.instance_name AS source_name
                    FROM intra_federation_cache_personnel fcp
                    JOIN intra_federation_links fl ON fl.instance_id = fcp.source_instance_id AND fl.is_active = 1
                    ORDER BY fl.instance_name ASC, fcp.fullname ASC
                ");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $options[] = [
                        'id' => 'fed:' . $row['source_instance_id'] . ':' . $row['remote_id'],
                        'fullname' => $row['fullname'],
                        'source_name' => $row['source_name'],
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
    public static function resolveName(PDO $pdo, string|int|null $leaderId): ?string
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
                $stmt = $pdo->prepare("
                    SELECT fcp.fullname, fl.instance_name
                    FROM intra_federation_cache_personnel fcp
                    JOIN intra_federation_links fl ON fl.instance_id = fcp.source_instance_id
                    WHERE fcp.source_instance_id = ? AND fcp.remote_id = ?
                ");
                $stmt->execute([$instanceId, (int) $remoteId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    return $row['fullname'] . ' [' . $row['instance_name'] . ']';
                }
            } catch (\PDOException $e) {
                // Fall through
            }

            return null;
        }

        // Local ID
        try {
            $stmt = $pdo->prepare("SELECT fullname FROM intra_mitarbeiter WHERE id = ?");
            $stmt->execute([(int) $leaderId]);
            return $stmt->fetchColumn() ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }
}
