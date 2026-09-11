<?php

declare(strict_types=1);

namespace App\Search\Sources;

use App\Policies\PersonnelPolicy;
use App\Search\SearchSourceInterface;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Mitarbeiter nach Name oder Dienstnummer.
 */
final class PersonnelSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'personnel';
    }

    public function label(): string
    {
        return 'Mitarbeiter';
    }

    public function allowed(): bool
    {
        return PersonnelPolicy::viewList();
    }

    public function search(string $q, int $limit): array
    {
        $like = '%' . ignis_like_prefix($q) . '%';
        $base = search_base_path();

        $rows = Capsule::table('intra_mitarbeiter')
            ->select(['id', 'fullname', 'dienstnr'])
            ->where(function ($query) use ($like) {
                $query->where('fullname', 'LIKE', $like)
                    ->orWhere('dienstnr', 'LIKE', $like);
            })
            ->orderBy('fullname')
            ->limit($limit)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'label' => (string) $row->fullname,
                'sub'   => $row->dienstnr ? 'Dienstnr. ' . $row->dienstnr : '',
                'href'  => $base . 'personnel/profile?id=' . (int) $row->id,
            ];
        }

        return $items;
    }
}
