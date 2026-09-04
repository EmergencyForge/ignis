<?php

declare(strict_types=1);

namespace Plugin\Firetab\Search;

use App\Search\SearchSourceInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;
use Plugin\Firetab\Policies\FireIncidentPolicy;

/**
 * Brandeinsätze in der globalen Suche: Einsatznummer, Einsatzort oder
 * Stichwort. Eingetragen über das Manifest (`search`), abgefragt von
 * App\Search\SearchRegistry; sichtbar für die Einsatz-QM.
 */
final class IncidentSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'fire-incidents';
    }

    public function label(): string
    {
        return 'Brandeinsätze';
    }

    public function allowed(): bool
    {
        return FireIncidentPolicy::manageQm();
    }

    public function search(string $q, int $limit): array
    {
        $like = '%' . ignis_like_prefix($q) . '%';
        $base = search_base_path();

        try {
            $rows = Capsule::table('intra_fire_incidents')
                ->select(['id', 'incident_number', 'location', 'keyword', 'started_at'])
                ->where(function ($query) use ($like) {
                    $query->where('incident_number', 'LIKE', $like)
                        ->orWhere('location', 'LIKE', $like)
                        ->orWhere('keyword', 'LIKE', $like);
                })
                ->orderByDesc('started_at')
                ->limit($limit)
                ->get();
        } catch (PDOException) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $sub = (string) ($row->keyword ?: $row->location ?: '');
            if ($row->started_at) {
                $sub .= ($sub !== '' ? ' · ' : '') . date('d.m.Y', (int) strtotime((string) $row->started_at));
            }
            $items[] = [
                'label' => (string) $row->incident_number,
                'sub'   => $sub,
                'href'  => $base . 'firetab/view?id=' . (int) $row->id,
            ];
        }

        return $items;
    }
}
