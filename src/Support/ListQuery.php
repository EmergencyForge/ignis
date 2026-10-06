<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Listenzustand aus emergencyforge/list-query mit dem, was ignis anbaut:
 * Basispfad, Klassenpräfix und die Abfragen gegen Eloquent. Das Paket
 * bleibt so frei von illuminate/database.
 *
 *     $list = ListQuery::fromQuery($_GET, ['name' => 'intra_users.username', 'created' => 'intra_users.created_at'], 'name');
 *     if ($list->q !== '') {
 *         $query->where('username', 'LIKE', $list->like());
 *     }
 *     $users = $list->paginate($query);
 *
 * Im Template: `$list->th('name', 'Name', 'users/list')` für die
 * Kopfzelle, templates/partials/pagination.php für die Fußzeile,
 * `$list->hiddenFields([...])` im Filterformular.
 */
final class ListQuery extends \EmergencyForge\ListQuery\ListQuery
{
    protected function basePath(): string
    {
        return defined('BASE_PATH') ? (string) BASE_PATH : '/';
    }

    protected function prefix(): string
    {
        return 'ignis';
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
        // Gleiche Werte brauchen eine feste Reihenfolge, sonst rutschen Zeilen
        // beim Blättern auf die nächste Seite oder kommen doppelt.
        foreach ($this->tiebreak() as $tiebreakColumn) {
            $builder->orderBy($tiebreakColumn, $this->dir);
        }

        return $builder;
    }

    /**
     * Zählt, sortiert und schneidet die Seite aus. Danach kennen total(),
     * lastPage() und footer() den Stand.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param EloquentBuilder<TModel>|QueryBuilder $builder
     * @return Collection<int, mixed>
     */
    public function paginate(EloquentBuilder|QueryBuilder $builder): Collection
    {
        $this->setTotal((clone $builder)->count());

        return $this->order($builder)
            ->offset($this->offset())
            ->limit($this->perPage)
            ->get();
    }

    /**
     * Zeilen je Wert einer Spalte, für die Zähler im Segmentfilter. Die
     * Abfrage trägt Suche und übrige Filter, aber nicht den Filter, dessen
     * Segmente gezählt werden. Schlüssel ist der Wert, aus "0" und "1"
     * macht PHP 0 und 1.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param EloquentBuilder<TModel>|QueryBuilder $builder
     * @return array<int|string,int>
     */
    public static function countBy(EloquentBuilder|QueryBuilder $builder, string $column): array
    {
        $query = clone ($builder instanceof EloquentBuilder ? $builder->toBase() : $builder);
        $rows  = $query->reorder()
            ->select($query->raw($column . ' AS segment'))
            ->selectRaw('COUNT(*) AS n')
            ->groupBy($column)
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row->segment] = (int) $row->n;
        }

        return $counts;
    }
}
