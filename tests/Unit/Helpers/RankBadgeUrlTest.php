<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Models\Rank;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `rank_badge_url()` und `Rank::badgeUrl()`: das Abzeichen eines Dienstgrads
 * als URL. Ein Pfad ohne führenden Schrägstrich (so stand es im Platzhalter
 * der Verwaltung) zeigte vorher relativ zur Seite ins Leere, ein Pfad mit
 * Schrägstrich unter einem Unterpfad wie /intra/ ebenso.
 */
final class RankBadgeUrlTest extends TestCase
{
    private function base(): string
    {
        return rtrim(defined('BASE_PATH') ? (string) BASE_PATH : '/', '/');
    }

    #[Test]
    public function ohne_abzeichen_keine_url(): void
    {
        $this->assertNull(rank_badge_url(null));
        $this->assertNull(rank_badge_url(''));
        $this->assertNull(rank_badge_url('   '));
        $this->assertNull((new Rank())->badgeUrl());
    }

    #[Test]
    public function pfade_bekommen_base_path_mit_und_ohne_schraegstrich(): void
    {
        $url = $this->base() . '/assets/img/dienstgrade/bf/1.png?v=';

        $this->assertStringStartsWith($url, (string) rank_badge_url('assets/img/dienstgrade/bf/1.png'));
        $this->assertStringStartsWith($url, (string) rank_badge_url('/assets/img/dienstgrade/bf/1.png'));
        $this->assertSame($this->base() . '/uploads/ranks/x.png', rank_badge_url(' uploads/ranks/x.png '));
    }

    #[Test]
    public function externe_urls_bleiben_wie_sie_sind(): void
    {
        $this->assertSame('https://cdn.example.org/bm.png', rank_badge_url('https://cdn.example.org/bm.png'));
        $this->assertSame('//cdn.example.org/bm.png', rank_badge_url('//cdn.example.org/bm.png'));
    }

    #[Test]
    public function das_model_nimmt_seine_spalte(): void
    {
        $rank = new Rank(['badge' => 'https://cdn.example.org/bm.png']);

        $this->assertSame('https://cdn.example.org/bm.png', $rank->badgeUrl());
    }
}
