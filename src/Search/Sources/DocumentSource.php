<?php

declare(strict_types=1);

namespace App\Search\Sources;

use App\Models\EditorDocument;
use App\Models\PersonnelDocument;
use App\Policies\DocumentPolicy;
use App\Search\SearchSourceInterface;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Aktuelle und ältere Dokumente innerhalb der Dokumentenrechte.
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
        return DocumentPolicy::view();
    }

    public function search(string $q, int $limit): array
    {
        if (!$this->allowed() || $limit < 1) {
            return [];
        }
        $like = '%' . ignis_like_prefix($q) . '%';
        $base = search_base_path();
        $items = [];
        $current = Capsule::table('intra_documents as d')
            ->leftJoin('intra_mitarbeiter as m', 'd.mitarbeiter_id', '=', 'm.id')
            ->select('d.id', 'd.docid', 'd.title', 'd.status', 'm.fullname')
            ->where(function ($query) use ($like) {
                $query->where('d.title', 'LIKE', $like)
                    ->orWhere('d.docid', 'LIKE', $like)
                    ->orWhere('m.fullname', 'LIKE', $like);
            })
            ->when(!DocumentPolicy::manage(), static fn ($query) => $query->where('d.status', EditorDocument::STATUS_ISSUED))
            ->orderByDesc('d.created_at')->limit($limit)->get();
        foreach ($current as $row) {
            $items[] = [
                'label' => (string) $row->title,
                'sub' => implode(' · ', array_filter([(string) $row->fullname, '#' . $row->docid, $row->status === EditorDocument::STATUS_DRAFT ? 'Entwurf' : 'Ausgestellt'])),
                'href' => $base . 'documents/' . (int) $row->id,
            ];
        }
        $remaining = $limit - count($items);
        if ($remaining === 0) {
            return $items;
        }
        $rows = Capsule::table('intra_mitarbeiter_dokumente as d')
            ->select('d.docid', 'd.erhalter', 'd.ausstellungsdatum', 'd.type')
            ->where(function ($query) use ($like) {
                $query->where('d.erhalter', 'LIKE', $like)
                    ->orWhere('d.docid', 'LIKE', $like)
                    ->orWhere('d.aussteller_name', 'LIKE', $like);
            })
            ->whereRaw('IFNULL(d.is_archived, 0) = 0')
            ->orderByDesc('d.timestamp')->limit($remaining)->get();
        foreach ($rows as $row) {
            $sub = PersonnelDocument::typeLabel((int) $row->type);
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
