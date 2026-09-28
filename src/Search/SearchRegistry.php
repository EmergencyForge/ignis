<?php

declare(strict_types=1);

namespace App\Search;

use App\Logging\Logger;
use App\Plugins\PluginLoader;
use App\Search\Sources\DefectSource;
use App\Search\Sources\DocumentSource;
use App\Search\Sources\EnotfProtocolSource;
use App\Search\Sources\PersonnelSource;
use App\Search\Sources\TemplateSource;
use App\Search\Sources\VehicleSource;
use EmergencyForge\FuzzySearch\Normalizer;
use EmergencyForge\FuzzySearch\Vocabulary;

/**
 * Sammelt die Quellen der globalen Suche: die des Kerns in fester
 * Reihenfolge, dahinter die der aktiven Plugins aus deren Manifest
 * (PluginLoader::searchSources()). run() fragt jede Quelle ab, die
 * allowed() bejaht, und lässt leere Gruppen weg; eine Quelle, die wirft,
 * fällt für diese Anfrage aus und wird protokolliert, die anderen
 * antworten trotzdem.
 *
 * Liefert eine Quelle, die zusätzlich FuzzySearchSource implementiert,
 * weniger als das Limit, wird nachgelegt: ihr Vokabular (gecacht über
 * VocabularyCache) erweitert die Suchworte auf bis zu drei korrigierte
 * Anfragen, die erneut über dieselbe search() laufen — Rechte und
 * Zeilenfilter bleiben also unverändert. Neue Treffer werden ohne
 * Duplikate (nach href) hinten angehängt und mit `approx: true`
 * markiert.
 */
final class SearchRegistry
{
    public const LIMIT = 5;

    /**
     * @param list<SearchSourceInterface>|null $core Kern-Quellen; null nimmt
     *        die feste Liste aus coreSources(). Tests reichen eigene herein.
     */
    public function __construct(
        private readonly PluginLoader $plugins,
        private readonly ?array $core = null,
        private readonly ?VocabularyCache $cache = null,
    ) {
    }

    /**
     * @return list<SearchSourceInterface>
     */
    public function sources(): array
    {
        return [...($this->core ?? $this->coreSources()), ...$this->plugins->searchSources()];
    }

    /**
     * @return list<SearchSourceInterface>
     */
    private function coreSources(): array
    {
        return [
            new PersonnelSource(),
            new EnotfProtocolSource($this->plugins),
            new DocumentSource(),
            new TemplateSource(),
            new VehicleSource(),
            new DefectSource(),
        ];
    }

    /**
     * @return list<array{key: string, label: string, items: list<array{label: string, sub: string, href: string}>}>
     */
    public function run(string $q, int $limit = self::LIMIT, string $scope = 'all'): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $groups = [];
        foreach ($this->sources() as $source) {
            if (!$source->allowed() || ($scope !== 'all' && $scope !== $source->key())) {
                continue;
            }
            try {
                $items = array_slice($source->search($q, $limit), 0, $limit);
                if ($source instanceof FuzzySearchSource && count($items) < $limit) {
                    $items = $this->withFuzzyMatches($source, $q, $limit, $items);
                }
            } catch (\Throwable $e) {
                Logger::error('Suche: Quelle ' . $source->key() . ' ausgefallen', ['error' => $e->getMessage()]);
                continue;
            }
            if ($items === []) {
                continue;
            }
            $groups[] = [
                'key'   => $source->key(),
                'label' => $source->label(),
                'items' => $items,
            ];
        }

        return $groups;
    }

    /** @return list<array{key: string, label: string}> */
    public function scopes(): array
    {
        $scopes = [];
        foreach ($this->sources() as $source) {
            if ($source->allowed()) {
                $scopes[] = ['key' => $source->key(), 'label' => $source->label()];
            }
        }
        return $scopes;
    }

    /**
     * Legt für $source bis zu drei über das Vokabular korrigierte
     * Suchworte nach, bis $items das Limit erreicht. Neue Treffer sind
     * nach href dedupliziert und tragen `approx: true`.
     *
     * @param list<array{label: string, sub: string, href: string}> $items
     * @return list<array{label: string, sub: string, href: string, approx?: bool}>
     */
    private function withFuzzyMatches(FuzzySearchSource $source, string $q, int $limit, array $items): array
    {
        $tokens = Normalizer::tokens($q);
        if ($tokens === []) {
            return $items;
        }

        $vocabulary = $this->vocabulary($source);
        $seen = array_fill_keys(array_column($items, 'href'), true);
        $remaining = $limit - count($items);

        foreach ($this->candidateQueries($tokens, $vocabulary) as $candidate) {
            if ($remaining <= 0) {
                break;
            }
            foreach ($source->search($candidate, $limit) as $hit) {
                if ($remaining <= 0) {
                    break;
                }
                if (isset($seen[$hit['href']])) {
                    continue;
                }
                $seen[$hit['href']] = true;
                $hit['approx'] = true;
                $items[] = $hit;
                $remaining--;
            }
        }

        return $items;
    }

    /**
     * Ersetzt jedes Token durch seine besten Vokabular-Treffer (Original-
     * Schreibweise) und kombiniert das zu höchstens drei Anfragen — ein
     * Token ohne Treffer bleibt unverändert.
     *
     * @param list<string> $tokens
     * @return list<string>
     */
    private function candidateQueries(array $tokens, Vocabulary $vocabulary, int $max = 3): array
    {
        $options = [];
        foreach ($tokens as $token) {
            $replacements = [];
            foreach ($vocabulary->expand($token, 3) as $hit) {
                foreach ($hit->originals as $original) {
                    $replacements[] = $original;
                }
            }
            $options[] = $replacements === [] ? [$token] : array_slice(array_values(array_unique($replacements)), 0, 2);
        }

        $queries = [''];
        foreach ($options as $choices) {
            $next = [];
            foreach ($queries as $prefix) {
                foreach ($choices as $choice) {
                    $next[] = trim($prefix . ' ' . $choice);
                    if (count($next) >= $max) {
                        break 2;
                    }
                }
            }
            $queries = $next;
        }

        return array_values(array_unique($queries));
    }

    private function vocabulary(FuzzySearchSource $source): Vocabulary
    {
        $cache = $this->cache ?? new VocabularyCache(dirname(__DIR__, 2) . '/storage/cache');

        return $cache->remember($source->key(), static function () use ($source): Vocabulary {
            $vocabulary = new Vocabulary();
            foreach ($source->vocabulary() as $phrase) {
                foreach (preg_split('/[-_\s]+/u', trim((string) $phrase)) ?: [] as $word) {
                    if ($word !== '') {
                        $vocabulary->add($word);
                    }
                }
            }
            return $vocabulary;
        });
    }
}
