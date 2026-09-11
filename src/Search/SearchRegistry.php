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

/**
 * Sammelt die Quellen der globalen Suche: die des Kerns in fester
 * Reihenfolge, dahinter die der aktiven Plugins aus deren Manifest
 * (PluginLoader::searchSources()). run() fragt jede Quelle ab, die
 * allowed() bejaht, und lässt leere Gruppen weg; eine Quelle, die wirft,
 * fällt für diese Anfrage aus und wird protokolliert, die anderen
 * antworten trotzdem.
 */
final class SearchRegistry
{
    public const LIMIT = 5;

    /**
     * @param list<SearchSourceInterface>|null $core Kern-Quellen; null nimmt
     *        die feste Liste aus coreSources(). Tests reichen eigene herein.
     */
    public function __construct(private readonly PluginLoader $plugins, private readonly ?array $core = null)
    {
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
    public function run(string $q, int $limit = self::LIMIT): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $groups = [];
        foreach ($this->sources() as $source) {
            if (!$source->allowed()) {
                continue;
            }
            try {
                $items = array_slice($source->search($q, $limit), 0, $limit);
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
}
