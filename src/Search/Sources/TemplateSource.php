<?php

declare(strict_types=1);

namespace App\Search\Sources;

use App\Policies\DocumentPolicy;
use App\Search\SearchSourceInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * Dokumentvorlagen nach Name oder Beschreibung; das Ziel ist die
 * Vorlagenverwaltung.
 */
final class TemplateSource implements SearchSourceInterface
{
    private const CATEGORIES = [
        'urkunde'    => 'Urkunde',
        'zertifikat' => 'Zertifikat',
        'schreiben'  => 'Schreiben',
        'sonstiges'  => 'Sonstiges',
    ];

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

    public function search(string $q, int $limit): array
    {
        $like = '%' . ignis_like_prefix($q) . '%';
        $base = search_base_path();

        try {
            $rows = Capsule::table('intra_dokument_templates')
                ->select(['id', 'name', 'category', 'description'])
                ->where(function ($query) use ($like) {
                    $query->where('name', 'LIKE', $like)
                        ->orWhere('description', 'LIKE', $like);
                })
                ->orderBy('name')
                ->limit($limit)
                ->get();
        } catch (PDOException) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $sub = self::CATEGORIES[$row->category] ?? (string) ($row->category ?? '');
            if ($row->description) {
                $sub .= ($sub !== '' ? ' · ' : '') . mb_substr((string) $row->description, 0, 60);
            }
            $items[] = [
                'label' => (string) $row->name,
                'sub'   => $sub,
                'href'  => $base . 'settings/documents/templates',
            ];
        }

        return $items;
    }
}
