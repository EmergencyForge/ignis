<?php

declare(strict_types=1);

namespace App\Search\Sources;

use App\Models\EditorTemplate;
use App\Policies\DocumentPolicy;
use App\Search\SearchSourceInterface;
use PDOException;

/**
 * Dokumentvorlagen nach Name oder Kategorie; das Ziel ist die
 * Vorlagenverwaltung des Editors.
 */
final class TemplateSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'templates';
    }

    public function label(): string
    {
        return 'Dokumentvorlagen';
    }

    public function allowed(): bool
    {
        return DocumentPolicy::resetTemplate();
    }

    /**
     * @return list<array{label: string, sub: string, href: string}>
     */
    public function search(string $q, int $limit): array
    {
        $like = '%' . ignis_like_prefix($q) . '%';
        $base = search_base_path();

        try {
            $rows = EditorTemplate::query()
                ->select(['id', 'name', 'category', 'is_active'])
                ->where(static function ($query) use ($like): void {
                    $query->where('name', 'LIKE', $like)
                        ->orWhere('category', 'LIKE', $like);
                })
                ->orderBy('name')
                ->limit($limit)
                ->get();
        } catch (PDOException) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $sub = (string) ($row->category ?? '');
            if (!$row->is_active) {
                $sub .= ($sub !== '' ? ' · ' : '') . 'deaktiviert';
            }

            $items[] = [
                'label' => (string) $row->name,
                'sub'   => $sub,
                'href'  => $base . 'settings/documents/editor-templates/' . $row->id,
            ];
        }

        return $items;
    }
}
