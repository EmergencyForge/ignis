<?php

declare(strict_types=1);

namespace App\Search\Sources;

use App\Policies\VehiclePolicy;
use App\Search\SearchSourceInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * Mängel nach Titel, Beschreibung oder Fahrzeug; das Ziel ist die
 * Mängelliste, auf das Fahrzeug gefiltert.
 */
final class DefectSource implements SearchSourceInterface
{
    private const STATUS = [
        'open'        => 'Offen',
        'in_progress' => 'In Bearbeitung',
        'deferred'    => 'Aufgeschoben',
        'resolved'    => 'Gelöst',
    ];

    public function key(): string
    {
        return 'defects';
    }

    public function label(): string
    {
        return 'Mängel';
    }

    public function allowed(): bool
    {
        return VehiclePolicy::view();
    }

    public function search(string $q, int $limit): array
    {
        $like = '%' . ignis_like_prefix($q) . '%';
        $base = search_base_path();

        try {
            $rows = Capsule::table('intra_fahrzeuge_defects as d')
                ->join('intra_fahrzeuge as f', 'd.vehicle_id', '=', 'f.id')
                ->select('d.id', 'd.vehicle_id', 'd.title', 'd.status', 'd.created_at', 'f.name as vehicle_name')
                ->where(function ($query) use ($like) {
                    $query->where('d.title', 'LIKE', $like)
                        ->orWhere('d.description', 'LIKE', $like)
                        ->orWhere('f.name', 'LIKE', $like)
                        ->orWhere('f.identifier', 'LIKE', $like);
                })
                ->orderByDesc('d.created_at')
                ->limit($limit)
                ->get();
        } catch (PDOException) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $sub = (string) $row->vehicle_name . ' · ' . (self::STATUS[$row->status] ?? (string) $row->status);
            if ($row->created_at) {
                $sub .= ' · ' . date('d.m.Y', (int) strtotime((string) $row->created_at));
            }
            $items[] = [
                'label' => (string) $row->title,
                'sub'   => $sub,
                'href'  => $base . 'settings/vehicles/defects/index?vehicle=' . (int) $row->vehicle_id,
            ];
        }

        return $items;
    }
}
