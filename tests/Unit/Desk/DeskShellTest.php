<?php

declare(strict_types=1);

namespace Tests\Unit\Desk;

use App\Helpers\Layout;
use App\Support\DeskShell;
use PHPUnit\Framework\TestCase;

final class DeskShellTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_COOKIE[DeskShell::COOKIE], $_GET['ui'], $_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    public function testWithoutCookieTheClassicShellStays(): void
    {
        $this->assertSame('classic', DeskShell::mode());
    }

    public function testCookieChoosesTheShell(): void
    {
        $_COOKIE[DeskShell::COOKIE] = 'tablet';
        $this->assertSame('tablet', DeskShell::mode());

        $_COOKIE[DeskShell::COOKIE] = 'kaputt';
        $this->assertSame('classic', DeskShell::mode());
    }

    public function testQueryParameterFromEfBridgeIsRemembered(): void
    {
        $_GET['ui'] = 'tablet';
        $this->assertTrue(DeskShell::rememberFromQuery());
        $this->assertSame('tablet', DeskShell::mode());

        $_GET['ui'] = '<script>';
        $this->assertFalse(DeskShell::rememberFromQuery());
        $this->assertSame('tablet', DeskShell::mode());
    }

    public function testNavigationBecomesApps(): void
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $nav = DeskShell::nav([
            ['label' => null, 'items' => [
                ['label' => 'Dashboard', 'href' => $base . 'index', 'icon' => 'fa-solid fa-house'],
                ['label' => 'Kalender', 'href' => $base . 'calendar', 'icon' => 'fa-solid fa-calendar-days'],
            ]],
            ['label' => 'Protokolle', 'items' => [
                ['label' => 'eNOTF öffnen', 'href' => $base . 'enotf/', 'external' => true],
                ['label' => 'Fremd', 'href' => 'https://example.org/', 'external' => true],
            ]],
        ]);

        $this->assertCount(2, $nav);
        $this->assertSame(['Kalender'], array_column($nav[0]['items'], 'label'));
        $this->assertSame('pink', $nav[0]['items'][0]['app']['tone']);
        $this->assertSame(['eNOTF', 'Fremd'], array_column($nav[1]['items'], 'label'));
        $this->assertTrue($nav[1]['items'][0]['app']['external']);
        $this->assertSame(['external' => true], $nav[1]['items'][1]['app']);
    }

    public function testFragmentCarriesWhatAWindowNeeds(): void
    {
        $html = Layout::fragment('<p>x</p>', 'Akte & Co', ['bodyId' => 'personnel', 'head' => '<style>.a{}</style>', 'single' => true]);

        $this->assertStringStartsWith('<div class="ignis-fragment" data-title="Akte &amp; Co" data-body-id="personnel" data-window-single>', $html);
        $this->assertStringContainsString('<style>.a{}</style>', $html);
        $this->assertStringNotContainsString('data-window-page', $html);
        $this->assertStringEndsWith('<p>x</p></div>', $html);
    }

    public function testPlainFragmentStaysAsBefore(): void
    {
        $this->assertSame('<div class="ignis-fragment" data-title="T"><p>x</p></div>', Layout::fragment('<p>x</p>', 'T'));
    }
}
