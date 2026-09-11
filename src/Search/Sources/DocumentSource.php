<?php

declare(strict_types=1);

namespace App\Search\Sources;

use App\Policies\PersonnelPolicy;
use App\Search\SearchSourceInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * Ausgestellte Dokumente nach Empfänger, Dokument-ID, Vorlage oder
 * Aussteller. Archivierte bleiben weg, sofern die Spalte schon da ist.
 */
final class DocumentSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'documents';
    }

    public function label(): string
    {
        return 'Dokumente';
    }

    public function allowed(): bool
    {
        return PersonnelPolicy::viewList();
    }

    public function search(string $q, int $limit): array
    {
        $like = '%' . ignis_like_prefix($q) . '%';
        $base = search_base_path();

        $build = static fn (bool $withArchiveFilter) => Capsule::table('intra_mitarbeiter_dokumente as d')
            ->leftJoin('intra_dokument_templates as t', 'd.template_id', '=', 't.id')
            ->select('d.docid', 'd.erhalter', 'd.ausstellungsdatum', 't.name as template_name')
            ->where(function ($query) use ($like) {
                $query->where('d.erhalter', 'LIKE', $like)
                    ->orWhere('d.docid', 'LIKE', $like)
                    ->orWhere('t.name', 'LIKE', $like)
                    ->orWhere('d.aussteller_name', 'LIKE', $like);
            })
            ->when($withArchiveFilter, static fn ($query) => $query->whereRaw('IFNULL(d.is_archived, 0) = 0'))
            ->orderByDesc('d.timestamp')
            ->limit($limit);

        try {
            $rows = $build(true)->get();
        } catch (PDOException) {
            $rows = $build(false)->get();
        }

        $items = [];
        foreach ($rows as $row) {
            $sub = (string) ($row->template_name ?: '');
            if ($row->ausstellungsdatum) {
                $sub .= ($sub !== '' ? ' · ' : '') . date('d.m.Y', (int) strtotime((string) $row->ausstellungsdatum));
            }
            $items[] = [
                'label' => (string) ($row->erhalter ?: 'Dokument #' . $row->docid),
                'sub'   => $sub,
                'href'  => $base . 'personnel/document-view?docid=' . rawurlencode((string) $row->docid),
            ];
        }

        return $items;
    }
}
