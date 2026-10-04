<?php

declare(strict_types=1);

namespace Tests\Unit\Setup;

use App\Setup\SetupCheck;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Jede Regel der Grundeinrichtung einzeln: Pflicht (SYSTEM_URL,
 * SERVER_NAME), Empfehlungen (alter Name, Beispieladresse, Standard-PIN)
 * und gesperrte Felder, die als erledigt gelten.
 */
final class SetupCheckTest extends TestCase
{
    /** Ein frisch installiertes System, wie es die Erstbefüllung anlegt. */
    private const FRESH = [
        'SYSTEM_URL'    => 'CHANGE_ME',
        'SERVER_NAME'   => 'CHANGE_ME',
        'SYSTEM_NAME'   => 'intraRP',
        'RP_STREET'     => 'Musterweg 0815',
        'RP_ZIP'        => '1337',
        'SERVER_CITY'   => 'Musterstadt',
        'ENOTF_USE_PIN' => 'true',
        'ENOTF_PIN'     => '1234',
    ];

    /**
     * @param array<string, string> $values
     * @param list<string> $locked
     */
    private function check(array $values, array $locked = []): SetupCheck
    {
        $rows = [];
        foreach ($values as $key => $value) {
            $rows[] = ['config_key' => $key, 'config_value' => $value, 'is_editable' => in_array($key, $locked, true) ? 0 : 1];
        }

        return new SetupCheck($rows);
    }

    /**
     * @param list<array{key: string, label: string, level: string, text: string}> $items
     * @return list<string>
     */
    private function keys(array $items): array
    {
        return array_column($items, 'key');
    }

    #[Test]
    public function frisch_ist_alles_offen_und_pflicht_kommt_zuerst(): void
    {
        $check = $this->check(self::FRESH);

        $this->assertSame(['SYSTEM_URL', 'SERVER_NAME', 'SYSTEM_NAME', 'RP_STREET', 'RP_ZIP', 'SERVER_CITY', 'ENOTF_PIN'], $this->keys($check->open()));
        $this->assertSame(['SYSTEM_URL', 'SERVER_NAME'], $this->keys($check->required()));
        $this->assertSame(['System-URL', 'Servername'], array_column($check->required(), 'label'));
        $this->assertFalse($check->isComplete());
    }

    #[Test]
    public function system_url_leer_oder_change_me_ist_offen(): void
    {
        $this->assertSame(['SYSTEM_URL'], $this->keys($this->check(['SYSTEM_URL' => '  '])->required()));
        $this->assertSame(['SYSTEM_URL'], $this->keys($this->check(['SYSTEM_URL' => 'CHANGE_ME'])->required()));
        $this->assertSame([], $this->check(['SYSTEM_URL' => 'intra.example.de'])->required());
    }

    #[Test]
    public function servername_leer_oder_change_me_ist_offen(): void
    {
        $this->assertSame(['SERVER_NAME'], $this->keys($this->check(['SERVER_NAME' => ''])->required()));
        $this->assertSame([], $this->check(['SERVER_NAME' => 'Rheinstadt RP'])->required());
    }

    #[Test]
    public function gesperrte_felder_gelten_als_erledigt(): void
    {
        $check = $this->check(self::FRESH, ['SYSTEM_URL', 'ENOTF_PIN']);

        $this->assertSame(['SERVER_NAME'], $this->keys($check->required()));
        $this->assertNotContains('ENOTF_PIN', $this->keys($check->open()));
    }

    #[Test]
    public function fehlende_schluessel_gelten_als_erledigt(): void
    {
        $this->assertSame([], $this->check([])->open());
        $this->assertTrue($this->check([])->isComplete());
    }

    #[Test]
    public function mit_url_und_servername_ist_die_einrichtung_vollstaendig(): void
    {
        $check = $this->check(['SYSTEM_URL' => 'intra.example.de', 'SERVER_NAME' => 'Rheinstadt RP'] + self::FRESH);

        $this->assertTrue($check->isComplete());
        $this->assertSame(['SYSTEM_NAME', 'RP_STREET', 'RP_ZIP', 'SERVER_CITY', 'ENOTF_PIN'], $this->keys($check->recommended()));
    }

    #[Test]
    public function alter_produktname_ist_eine_empfehlung(): void
    {
        $this->assertSame(['SYSTEM_NAME'], $this->keys($this->check(['SYSTEM_NAME' => 'intraRP'])->recommended()));
        $this->assertSame([], $this->check(['SYSTEM_NAME' => 'BF Rheinstadt'])->recommended());
    }

    #[Test]
    public function beispieladresse_ist_eine_empfehlung(): void
    {
        $this->assertSame(['RP_STREET'], $this->keys($this->check(['RP_STREET' => 'Musterweg 0815', 'RP_ZIP' => '50667', 'SERVER_CITY' => 'Köln'])->recommended()));
        $this->assertSame(['RP_ZIP'], $this->keys($this->check(['RP_STREET' => 'Ring 1', 'RP_ZIP' => '1337'])->recommended()));
        $this->assertSame(['SERVER_CITY'], $this->keys($this->check(['SERVER_CITY' => 'Musterstadt'])->recommended()));
    }

    #[Test]
    public function standard_pin_nur_bei_aktiver_pin_abfrage(): void
    {
        $item = $this->check(['ENOTF_USE_PIN' => 'true', 'ENOTF_PIN' => '1234'])->recommended()[0];
        $this->assertSame('ENOTF_PIN', $item['key']);
        $this->assertStringContainsString('Sicherheitsrisiko', $item['text']);

        $this->assertSame([], $this->check(['ENOTF_USE_PIN' => 'false', 'ENOTF_PIN' => '1234'])->open());
        $this->assertSame([], $this->check(['ENOTF_USE_PIN' => 'true', 'ENOTF_PIN' => '4711'])->open());
    }
}
