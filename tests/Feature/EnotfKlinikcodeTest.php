<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Codeeingabe unter /enotf/monitor/code. Den Ablauf schreibt die
 * Datenbankuhr (KlinikCodeController::storeCode, NOW() + 1 Stunde); ob ein
 * Code noch gilt, muss dieselbe Uhr entscheiden, auch wenn PHP und
 * Datenbank in verschiedenen Zeitzonen laufen.
 */
final class EnotfKlinikcodeTest extends FeatureTestCase
{
    private const PATH = '/enotf/monitor/code';

    private string $phpTimezone;

    private string $dbTimezone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->phpTimezone = date_default_timezone_get();
        $this->dbTimezone  = (string) Capsule::connection()->selectOne('SELECT @@session.time_zone AS tz')->tz;
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->phpTimezone);
        Capsule::connection()->statement('SET time_zone = ?', [$this->dbTimezone]);
        parent::tearDown();
    }

    private function code(string $expiresAt): string
    {
        $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        Capsule::table('intra_edivi_klinikcodes')->insert([
            'enr'        => '78' . random_int(1000000000, 9999999999),
            'code'       => $code,
            'expires_at' => Capsule::connection()->raw($expiresAt),
        ]);

        return $code;
    }

    #[Test]
    public function neuer_code_gilt_wenn_datenbank_in_utc_und_php_in_ortszeit_laeuft(): void
    {
        date_default_timezone_set('Europe/Berlin');
        Capsule::connection()->statement("SET time_zone = '+00:00'");
        // Vor fünf Minuten erzeugt, gilt also noch 55 Minuten.
        $code = $this->code('DATE_ADD(NOW(), INTERVAL 55 MINUTE)');

        $response = $this->post(self::PATH, ['code' => $code]);

        $this->assertBodyNotContains('abgelaufen', $response);
        $this->assertRedirect($response, '/enotf/print/index');
    }

    #[Test]
    public function abgelaufener_code_wird_abgewiesen(): void
    {
        date_default_timezone_set('Europe/Berlin');
        Capsule::connection()->statement("SET time_zone = '+00:00'");
        $code = $this->code('DATE_SUB(NOW(), INTERVAL 1 MINUTE)');

        $response = $this->post(self::PATH, ['code' => $code]);

        $this->assertOk($response);
        $this->assertBodyContains('Dieser Code ist abgelaufen.', $response);
    }

    #[Test]
    public function unbekannter_code_ist_ungueltig(): void
    {
        $response = $this->post(self::PATH, ['code' => 'ZZZZZZ']);

        $this->assertOk($response);
        $this->assertBodyContains('Ungültiger Code.', $response);
    }
}
