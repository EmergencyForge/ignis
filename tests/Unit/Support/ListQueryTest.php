<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ListQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ListQuery liest den Listenzustand aus der Query und baut die URLs der
 * Kopfzellen und Seiten. Whitelist und Standardwerte halten Sortierung und
 * Richtung im Rahmen; unbekannte Werte fallen still auf den Standard.
 */
final class ListQueryTest extends TestCase
{
    private const SORTABLE = ['name' => 'u.username', 'created' => 'u.created_at', 'defects' => 'COUNT(d.id)'];

    #[Test]
    public function standardwerte_ohne_query(): void
    {
        $list = ListQuery::fromQuery([], self::SORTABLE, 'name');

        $this->assertSame('', $list->q);
        $this->assertSame('name', $list->sort);
        $this->assertSame('asc', $list->dir);
        $this->assertSame(1, $list->page);
        $this->assertSame('u.username', $list->column());
        $this->assertSame([], $list->params());
    }

    #[Test]
    public function unbekannte_spalte_und_richtung_fallen_auf_den_standard(): void
    {
        $list = ListQuery::fromQuery(['sort' => 'password', 'dir' => 'desc', 'page' => '-3'], self::SORTABLE, 'created', 'desc');

        $this->assertSame('created', $list->sort);
        $this->assertSame('desc', $list->dir);
        $this->assertSame(1, $list->page);

        $sideways = ListQuery::fromQuery(['sort' => 'name', 'dir' => 'sideways'], self::SORTABLE, 'created', 'desc');
        $this->assertSame('name', $sideways->sort);
        $this->assertSame('desc', $sideways->dir, 'Ungültige Richtung wird zur Standardrichtung.');
    }

    #[Test]
    public function arrays_in_der_query_gelten_als_nicht_gesetzt(): void
    {
        $list = ListQuery::fromQuery(['q' => ['x'], 'page' => ['2'], 'sort' => ['name']], self::SORTABLE, 'name');

        $this->assertSame('', $list->q);
        $this->assertSame(1, $list->page);
        $this->assertSame('name', $list->sort);
    }

    #[Test]
    public function suchwort_wird_fuer_like_escaped(): void
    {
        $list = ListQuery::fromQuery(['q' => ' 50%_x '], self::SORTABLE, 'name');

        $this->assertSame('50%_x', $list->q);
        $this->assertSame('%50\\%\\_x%', $list->like());
    }

    #[Test]
    public function urls_tragen_nur_abweichungen_vom_standard(): void
    {
        $list = ListQuery::fromQuery(['q' => 'max', 'sort' => 'created', 'dir' => 'desc', 'page' => '3', 'status' => 'active', 'noise' => 'x'], self::SORTABLE, 'name', 'asc', 25, ['status']);

        $this->assertSame(['q' => 'max', 'sort' => 'created', 'dir' => 'desc', 'status' => 'active', 'page' => 3], $list->params());
        $this->assertSame('/users/list?q=max&sort=created&dir=desc&status=active&page=2', $list->pageUrl('users/list', 2));
        $this->assertSame('/users/list?q=max&sort=created&dir=desc&status=active', $list->pageUrl('users/list', 1));
        $this->assertSame('/users/list?q=max&status=active', $list->url('users/list', ['sort' => 'name', 'dir' => 'asc', 'page' => null]));
    }

    #[Test]
    public function sortier_link_kehrt_die_richtung_um_und_springt_auf_seite_1(): void
    {
        $list = ListQuery::fromQuery(['sort' => 'created', 'dir' => 'asc', 'page' => '4'], self::SORTABLE, 'name');

        $this->assertSame('/users/list?sort=created&dir=desc', $list->sortUrl('created', 'users/list'));
        $this->assertSame('/users/list', $list->sortUrl('name', 'users/list'), 'Standardspalte aufsteigend braucht keine Parameter.');
        $this->assertSame('asc', $list->sortState('created'));
        $this->assertNull($list->sortState('name'));
    }

    #[Test]
    public function kopfzelle_traegt_link_und_aria_sort(): void
    {
        $list = ListQuery::fromQuery(['sort' => 'created', 'dir' => 'desc'], self::SORTABLE, 'name');

        $this->assertSame(
            '<th scope="col" aria-sort="descending"><a class="ignis-table__sort is-desc" href="/users/list?sort=created&amp;dir=asc">Angelegt<i class="fa-solid fa-arrow-down-wide-short" aria-hidden="true"></i></a></th>',
            $list->th('created', 'Angelegt', 'users/list'),
        );
        $this->assertSame(
            '<th scope="col" aria-sort="none" class="ignis-table__num"><a class="ignis-table__sort" href="/users/list">Name<i class="fa-solid fa-sort" aria-hidden="true"></i></a></th>',
            $list->th('name', 'Name', 'users/list', 'ignis-table__num'),
        );
        $this->assertSame('<th scope="col">Aktionen &amp; Co</th>', $list->th('actions', 'Aktionen & Co', 'users/list'));
    }

    #[Test]
    public function seitenzustand_vor_paginate_ist_leer(): void
    {
        $list = ListQuery::fromQuery(['page' => '2'], self::SORTABLE, 'name', 'asc', 10);

        $this->assertSame(10, $list->offset());
        $this->assertSame(0, $list->total());
        $this->assertSame(1, $list->lastPage());
        $this->assertTrue($list->hasPrev());
        $this->assertFalse($list->hasNext());
        $this->assertSame(0, $list->from());
    }

    #[Test]
    public function standard_sortierung_muss_in_der_whitelist_stehen(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ListQuery::fromQuery([], self::SORTABLE, 'missing');
    }
}
