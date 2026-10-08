<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Einstellungen › System › Cronjobs: Anlegen, Umschalten und Löschen.
 *
 * „Jetzt ausführen" (/cron/run) beendet den Prozess mit exit und lässt
 * sich deshalb hier nicht aufrufen.
 */
final class CronSettingsTest extends FeatureTestCase
{
    private const SEITE = '/settings/system/cron';

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    /** @return array<string,string> */
    private function felder(string $identifier): array
    {
        return [
            'identifier'   => $identifier,
            'name'         => '  Wochenstatistik  ',
            'description'  => '',
            'handler_type' => 'webhook',
            'schedule'     => '*/5 * * * *',
            'handler'      => 'https://example.test/hook',
            'config'       => '',
        ];
    }

    private function job(string $identifier, int $builtin = 0): int
    {
        return (int) Capsule::table('intra_cron_jobs')->insertGetId([
            'identifier'   => $identifier,
            'name'         => 'Test',
            'description'  => '',
            'handler_type' => 'webhook',
            'handler'      => 'https://example.test/hook',
            'schedule'     => '0 * * * *',
            'config'       => '{}',
            'active'       => 1,
            'is_builtin'   => $builtin,
        ]);
    }

    #[Test]
    public function anlegen_mit_gueltigen_feldern(): void
    {
        $identifier = 'test.' . uniqid();

        $this->assertRedirect($this->post(self::SEITE . '/create', $this->felder($identifier)), self::SEITE);

        $row = Capsule::table('intra_cron_jobs')->where('identifier', $identifier)->first();
        $this->assertNotNull($row);
        $this->assertSame('Wochenstatistik', $row->name);
        $this->assertSame('{}', $row->config);
        $this->assertSame(0, (int) $row->is_builtin);
    }

    #[Test]
    public function anlegen_ohne_namen_oder_mit_liste_legt_nichts_an(): void
    {
        $identifier = 'test.' . uniqid();

        $this->post(self::SEITE . '/create', ['name' => ''] + $this->felder($identifier));
        $this->assertSame('fields-missing', $_SESSION['flash']['text'] ?? null);

        $this->post(self::SEITE . '/create', ['name' => ['x']] + $this->felder($identifier));
        $this->assertSame('Der Anzeigename muss Text sein.', $_SESSION['flash']['text'] ?? null);

        $this->post(self::SEITE . '/create', $this->felder($identifier) + ['extra' => '1']);
        $this->assertSame('Das Formular enthält unbekannte Felder.', $_SESSION['flash']['text'] ?? null);

        $this->assertFalse(Capsule::table('intra_cron_jobs')->where('identifier', $identifier)->exists());
    }

    #[Test]
    public function umschalten_und_loeschen(): void
    {
        $id = $this->job('test.' . uniqid());

        $this->assertRedirect($this->post(self::SEITE . '/toggle', ['id' => (string) $id]), self::SEITE);
        $this->assertSame(0, (int) Capsule::table('intra_cron_jobs')->where('id', $id)->value('active'));

        $this->assertRedirect($this->post(self::SEITE . '/delete', ['id' => (string) $id]), self::SEITE);
        $this->assertFalse(Capsule::table('intra_cron_jobs')->where('id', $id)->exists());
    }

    #[Test]
    public function ohne_gueltige_id_aendert_sich_nichts(): void
    {
        $id = $this->job('test.' . uniqid());

        foreach (['/toggle' => 'abc', '/delete' => '0'] as $pfad => $kaputt) {
            $this->post(self::SEITE . $pfad, ['id' => $kaputt]);
            $this->assertSame('no-job', $_SESSION['flash']['text'] ?? null, $pfad);
        }

        $this->assertSame(1, (int) Capsule::table('intra_cron_jobs')->where('id', $id)->value('active'));
    }

    #[Test]
    public function eingebaute_jobs_bleiben(): void
    {
        $id = $this->job('test.' . uniqid(), 1);

        $this->post(self::SEITE . '/delete', ['id' => (string) $id]);

        $this->assertTrue(Capsule::table('intra_cron_jobs')->where('id', $id)->exists());
    }
}
