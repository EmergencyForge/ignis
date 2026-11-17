<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Das Krankenhaus-Portal meldet sich per POST an seiner eigenen Adresse an.
 * Die Route nahm nur GET an, der Router antwortete mit 405.
 */
final class EnotfHospitalPortalTest extends FeatureTestCase
{
    private const PATH = '/enotf/monitor/availability';

    #[Test]
    public function anmeldung_mit_zugangscode_zeigt_die_klinik(): void
    {
        $name = 'Portaltest ' . uniqid();
        $poi  = (int) Capsule::table('intra_edivi_pois')->insertGetId(['name' => $name, 'ort' => 'Teststadt', 'active' => 1]);
        $code = 'Code' . bin2hex(random_bytes(4));
        Capsule::table('intra_edivi_hospital_access_codes')->insert(['poi_id' => $poi, 'code' => $code]);

        $response = $this->post(self::PATH, ['code' => $code]);

        $this->assertOk($response);
        $this->assertBodyContains(htmlspecialchars($name), $response);
    }
}
