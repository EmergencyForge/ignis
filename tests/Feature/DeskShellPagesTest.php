<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Notifications\NotificationManager;
use App\Support\DeskShell;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Desktop-Hülle (efOS): mit dem Cookie `ignis_ui` kommt statt der
 * klassischen Hülle templates/layouts/desk.php, die angefragte Seite steht
 * darin als erstes Fenster. Die Mitteilungen der Hülle kommen als JSON aus
 * dem Posteingang.
 */
final class DeskShellPagesTest extends FeatureTestCase
{
    protected function tearDown(): void
    {
        unset($_COOKIE[DeskShell::COOKIE]);
        parent::tearDown();
    }

    private function login(): int
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);

        return $user->id;
    }

    #[Test]
    public function mit_cookie_kommt_die_seite_als_erstes_fenster(): void
    {
        $this->login();
        $_COOKIE[DeskShell::COOKIE] = 'desktop';

        $page = $this->get('/settings/vehicles/vehicles/create');

        $this->assertOk($page);
        $this->assertBodyContains('class="ignis-desk-body"', $page);
        $this->assertBodyContains('data-ignis-desk-config', $page);
        $this->assertBodyContains('assets/js/ui/desk.js', $page);
        $this->assertMatchesRegularExpression('~<template data-ignis-desk-initial data-url="/settings/vehicles/vehicles/create"><div class="ignis-fragment" data-title="Fahrzeug anlegen"~', $page->body);
        $this->assertBodyNotContains('class="ignis-topbar"', $page);
    }

    #[Test]
    public function ohne_cookie_bleibt_die_klassische_huelle(): void
    {
        $this->login();

        $page = $this->get('/settings/vehicles/vehicles/create');

        $this->assertOk($page);
        $this->assertBodyContains('class="ignis-topbar"', $page);
        $this->assertBodyNotContains('data-ignis-desk-config', $page);
        $this->assertBodyContains('name="ui" value="desktop"', $page);
    }

    #[Test]
    public function mitteilungen_kommen_als_json_mit_fester_id(): void
    {
        $me = $this->login();
        $this->container->get(NotificationManager::class)->notify('antrag', [$me], [
            'title'   => 'Ihr Antrag #7 wurde bearbeitet',
            'message' => 'Status: Genehmigt.',
            'link'    => '/forms/view?antrag=7',
        ]);

        $response = $this->get('/inbox/notifications');

        $this->assertOk($response);
        $data = json_decode($response->body, true);
        $this->assertSame(1, $data['count']);
        $this->assertCount(1, $data['items']);
        $item = $data['items'][0];
        $this->assertMatchesRegularExpression('~^inbox:\d+$~', $item['id']);
        $this->assertSame('Ihr Antrag #7 wurde bearbeitet', $item['title']);
        $this->assertSame('Status: Genehmigt.', $item['body']);
        $this->assertSame('Anträge', $item['group']);
        $this->assertSame('forms', $item['app']);
        $this->assertFalse($item['read']);
        $this->assertMatchesRegularExpression('~^/inbox/\d+/open$~', $item['href']);
    }
}
