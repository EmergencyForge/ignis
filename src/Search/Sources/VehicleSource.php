<?php

declare(strict_types=1);

namespace App\Search\Sources;

use App\Policies\VehiclePolicy;
use App\Search\SearchSourceInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * Fahrzeuge nach Kennung, Funkrufname oder Kennzeichen; das Ziel ist die
 * Fahrzeugliste, auf die Kennung gefiltert.
 */
final class VehicleSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'vehicles';
    }

    public function label(): string
    {
        return 'Fahrzeuge';
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
            $rows = Capsule::table('intra_fahrzeuge')
                ->select(['id', 'identifier', 'name', 'kennzeichen'])
                ->where(function ($query) use ($like) {
                    $query->where('identifier', 'LIKE', $like)
                        ->orWhere('name', 'LIKE', $like)
                        ->orWhere('kennzeichen', 'LIKE', $like);
                })
                ->orderBy('identifier')
                ->limit($limit)
                ->get();
        } catch (PDOException) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'label' => (string) $row->name,
                'sub'   => implode(' · ', array_filter([(string) $row->identifier, (string) ($row->kennzeichen ?? '')])),
                'href'  => $base . 'settings/vehicles/vehicles/' . (int) $row->id,
            ];
        }

        return $items;
    }
}
