<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Search;

use App\Search\SearchSourceInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\KnowledgeBase\KBHelper;
use Plugin\KnowledgeBase\Policies\KnowledgebasePolicy;

/**
 * Wissensdatenbank in der globalen Suche: Volltext über Titel, Untertitel
 * und Inhalt, Treffer im Titel zuerst. Eingetragen über das Manifest
 * (`search`), abgefragt von App\Search\SearchRegistry.
 */
final class LexiconSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'lexicon';
    }

    public function label(): string
    {
        return 'Wissensdatenbank';
    }

    public function allowed(): bool
    {
        return KnowledgebasePolicy::view();
    }

    public function search(string $q, int $limit): array
    {
        $like = '%' . ignis_like_prefix($q) . '%';
        $base = search_base_path();

        // Volltext-Ausdruck: jedes Wort ab zwei Zeichen als Pflicht-Präfix,
        // die Operatoren des Boolean Mode aus der Eingabe entfernt.
        $terms = [];
        foreach (preg_split('/\s+/', $q) ?: [] as $word) {
            $word = preg_replace('/[+\-><()~*"@]+/', '', trim($word)) ?? '';
            if (mb_strlen($word) >= 2) {
                $terms[] = '+' . $word . '*';
            }
        }
        $fulltext = implode(' ', $terms);

        $query = Capsule::table('intra_kb_entries as kb')
            ->select('kb.id', 'kb.title', 'kb.subtitle', 'kb.content')
            ->where('kb.is_archived', 0);
        if ($fulltext !== '') {
            $query
                ->where(function ($inner) use ($fulltext, $like) {
                    $inner->whereRaw('MATCH(kb.title, kb.subtitle, kb.content) AGAINST(? IN BOOLEAN MODE)', [$fulltext])
                        ->orWhere('kb.title', 'LIKE', $like);
                })
                ->orderByRaw('MATCH(kb.title, kb.subtitle, kb.content) AGAINST(? IN BOOLEAN MODE) DESC, kb.title ASC', [$fulltext]);
        } else {
            $query->where('kb.title', 'LIKE', $like)->orderBy('kb.title');
        }

        $items = [];
        foreach ($query->limit($limit)->get() as $row) {
            $snippet = KBHelper::createSearchSnippet((string) $row->content, $q, 100);
            $items[] = [
                'label' => (string) $row->title,
                'sub'   => (string) ($snippet ?? ($row->subtitle ?: '')),
                'href'  => $base . 'lexicon/view?id=' . (int) $row->id,
            ];
        }

        return $items;
    }
}
