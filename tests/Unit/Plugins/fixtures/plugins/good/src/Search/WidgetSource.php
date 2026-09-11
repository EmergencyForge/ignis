<?php

declare(strict_types=1);

namespace GoodPluginFixture\Search;

use App\Search\SearchSourceInterface;

/**
 * Fixture-Suchquelle für das Manifest-Feld `search`: antwortet ohne
 * Datenbank mit einem Treffer, wenn das Suchwort „widget" enthält.
 */
final class WidgetSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'widgets';
    }

    public function label(): string
    {
        return 'Widgets';
    }

    public function allowed(): bool
    {
        return true;
    }

    public function search(string $q, int $limit): array
    {
        if (!str_contains(strtolower($q), 'widget')) {
            return [];
        }

        return [
            ['label' => 'Widget Alpha', 'sub' => 'aus dem Fixture-Plugin', 'href' => '/good/widgets/1'],
        ];
    }
}
