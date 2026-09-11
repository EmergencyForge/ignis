<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Zustand einer Listenansicht aus der Query: Suchwort `q`, Sortierung
 * `sort`/`dir`, Seite `page` und benannte Filter. Ersetzt DataTables in den
 * Listen (Redesign I6): Sortieren, Filtern und Blättern passieren auf dem
 * Server, die Kopfzellen sind Links, das Suchfeld ein GET-Formular.
 *
 * Der Controller nennt die sortierbaren Spalten als Whitelist (Schlüssel in
 * der URL => Spalte für orderBy). Ein unbekannter `sort` fällt auf den
 * Standard zurück, `dir` kennt nur asc und desc, `page` alles unter 1 wird 1.
 *
 *     $list = ListQuery::fromQuery($_GET, ['name' => 'intra_users.username', 'created' => 'intra_users.created_at'], 'name');
 *     if ($list->q !== '') {
 *         $query->where('username', 'LIKE', $list->like());
 *     }
 *     $users = $list->paginate($query);
 *
 * Im Template: `$list->th('name', 'Name', 'users/list')` rendert die
 * Kopfzelle mit Sortier-Link, templates/partials/pagination.php die
 * Seitennavigation.
 */
final class ListQuery
{
    public readonly string $q;
    public readonly string $sort;
    public readonly string $dir;
    public readonly int $page;

    private int $total = 0;

    /** @var array<string,string> */
    private array $filters = [];

    /**
     * @param array<string,mixed>  $query      $_GET
     * @param array<string,string> $sortable   URL-Schlüssel => Spalte oder Ausdruck für orderBy
     * @param list<string>         $filterKeys weitere Parameter, die die Liste durch alle URLs trägt
     */
    private function __construct(
        array $query,
        private readonly array $sortable,
        private readonly string $defaultSort,
        private readonly string $defaultDir,
        public readonly int $perPage,
        array $filterKeys,
    ) {
        $this->q = self::scalar($query['q'] ?? null);

        $sort = self::scalar($query['sort'] ?? null);
        $dir  = strtolower(self::scalar($query['dir'] ?? null));
        if ($sort === '' || !isset($sortable[$sort])) {
            $sort = $defaultSort;
            $dir  = $defaultDir;
        }
        $this->sort = $sort;
        $this->dir  = in_array($dir, ['asc', 'desc'], true) ? $dir : $defaultDir;

        $this->page = max(1, (int) self::scalar($query['page'] ?? null));

        foreach ($filterKeys as $key) {
            $value = self::scalar($query[$key] ?? null);
            if ($value !== '') {
                $this->filters[$key] = $value;
            }
        }
    }

    /**
     * @param array<string,mixed>  $query
     * @param array<string,string> $sortable
     * @param list<string>         $filterKeys
     */
    public static function fromQuery(
        array $query,
        array $sortable,
        string $defaultSort,
        string $defaultDir = 'asc',
        int $perPage = 25,
        array $filterKeys = [],
    ): self {
        if (!isset($sortable[$defaultSort])) {
            throw new \InvalidArgumentException("ListQuery: Standard-Sortierung '$defaultSort' steht nicht in der Whitelist.");
        }

        return new self($query, $sortable, $defaultSort, $defaultDir === 'desc' ? 'desc' : 'asc', max(1, $perPage), $filterKeys);
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    // ── Abfrage ───────────────────────────────────────────────────────

    /** Spalte oder Ausdruck der aktuellen Sortierung. */
    public function column(): string
    {
        return $this->sortable[$this->sort];
    }

    /** Suchmuster für LIKE: das Suchwort als Literal zwischen zwei Prozentzeichen. */
    public function like(): string
    {
        return '%' . ignis_like_prefix($this->q) . '%';
    }

    /** Wert eines benannten Filters, leer wenn nicht gesetzt. */
    public function filter(string $key): string
    {
        return $this->filters[$key] ?? '';
    }

    /**
     * Hängt die Sortierung an. Ein Ausdruck mit Klammern läuft über
     * orderByRaw, weil orderBy ihn sonst als Spaltennamen quoten würde; die
     * Richtung ist auf asc/desc beschränkt.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param EloquentBuilder<TModel>|QueryBuilder $builder
     * @return EloquentBuilder<TModel>|QueryBuilder
     */
    public function order(EloquentBuilder|QueryBuilder $builder): EloquentBuilder|QueryBuilder
    {
        $column = $this->column();
        if (str_contains($column, '(')) {
            $builder->orderByRaw($column . ' ' . $this->dir);
        } else {
            $builder->orderBy($column, $this->dir);
        }

        return $builder;
    }

    /**
     * Zählt, sortiert und schneidet die Seite aus. Danach kennen total(),
     * lastPage() und die Pagination-Partial den Stand.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param EloquentBuilder<TModel>|QueryBuilder $builder
     * @return Collection<int, mixed>
     */
    public function paginate(EloquentBuilder|QueryBuilder $builder): Collection
    {
        $this->total = (clone $builder)->count();

        return $this->order($builder)
            ->offset($this->offset())
            ->limit($this->perPage)
            ->get();
    }

    // ── Seitenzustand ─────────────────────────────────────────────────

    public function total(): int
    {
        return $this->total;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPrev(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->offset() + $this->perPage < $this->total;
    }

    /** Erste Zeilennummer der Seite (1-basiert), 0 ohne Treffer. */
    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    /** Letzte Zeilennummer der Seite. */
    public function to(): int
    {
        return min($this->total, $this->offset() + $this->perPage);
    }

    // ── URLs ──────────────────────────────────────────────────────────

    /**
     * Query-Parameter der Liste ohne Standardwerte: q, sort/dir (nur wenn
     * abweichend), Filter, page (nur ab 2). `$overrides` mit null entfernt
     * einen Schlüssel.
     *
     * @param array<string,scalar|null> $overrides
     * @return array<string,string|int>
     */
    public function params(array $overrides = []): array
    {
        $params = ['q' => $this->q, 'sort' => $this->sort, 'dir' => $this->dir]
            + $this->filters
            + ['page' => $this->page];

        foreach ($overrides as $key => $value) {
            if ($value === null || $value === '') {
                unset($params[$key]);
            } else {
                $params[$key] = is_int($value) ? $value : (string) $value;
            }
        }

        // Standardwerte bleiben weg, damit die Standard-URL der Liste
        // wirklich ohne Parameter auskommt.
        if (($params['q'] ?? '') === '') {
            unset($params['q']);
        }
        if (($params['sort'] ?? $this->defaultSort) === $this->defaultSort && ($params['dir'] ?? $this->defaultDir) === $this->defaultDir) {
            unset($params['sort'], $params['dir']);
        }
        if ((int) ($params['page'] ?? 1) <= 1) {
            unset($params['page']);
        }

        return $params;
    }

    /**
     * @param array<string,scalar|null> $overrides
     */
    public function url(string $path, array $overrides = []): string
    {
        $base   = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $params = $this->params($overrides);

        return $base . ltrim($path, '/') . ($params === [] ? '' : '?' . http_build_query($params));
    }

    public function pageUrl(string $path, int $page): string
    {
        return $this->url($path, ['page' => $page > 1 ? $page : null]);
    }

    /** Sortier-Link einer Spalte: dieselbe Spalte kehrt die Richtung um, eine andere beginnt aufsteigend. Seite springt auf 1. */
    public function sortUrl(string $key, string $path): string
    {
        $dir = $this->sort === $key && $this->dir === 'asc' ? 'desc' : 'asc';

        return $this->url($path, ['sort' => $key, 'dir' => $dir, 'page' => null]);
    }

    /** 'asc', 'desc' oder null, wenn die Spalte nicht sortiert. */
    public function sortState(string $key): ?string
    {
        return $this->sort === $key ? $this->dir : null;
    }

    /**
     * Kopfzelle mit Sortier-Link. Spalten außerhalb der Whitelist werden
     * als schlichte Kopfzelle ausgegeben.
     */
    public function th(string $key, string $label, string $path, string $class = ''): string
    {
        $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"' : '';
        $label     = htmlspecialchars($label, ENT_QUOTES);

        if (!isset($this->sortable[$key])) {
            return '<th scope="col"' . $classAttr . '>' . $label . '</th>';
        }

        $state = $this->sortState($key);
        $aria  = $state === null ? 'none' : ($state === 'asc' ? 'ascending' : 'descending');
        $icon  = $state === null ? 'fa-sort' : ($state === 'asc' ? 'fa-arrow-up-short-wide' : 'fa-arrow-down-wide-short');
        $link  = '<a class="ignis-table__sort' . ($state !== null ? ' is-' . $state : '') . '" href="' . htmlspecialchars($this->sortUrl($key, $path), ENT_QUOTES) . '">'
            . $label . '<i class="fa-solid ' . $icon . '" aria-hidden="true"></i></a>';

        return '<th scope="col" aria-sort="' . $aria . '"' . $classAttr . '>' . $link . '</th>';
    }
}
