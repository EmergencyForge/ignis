<?php

declare(strict_types=1);

namespace Tests\Feature;

use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * eNOTF-Abrechnung: ignisTab ruft die freigegebenen Protokolle vom
 * FiveM-Server ab, mit API-Key und ohne Browser-Session. Früher hing die
 * Route hinter der Session-Anmeldung, der Spielserver bekam immer 401.
 */
final class EnotfBillingApiTest extends FeatureTestCase
{
    private function protocol(string $patname): int
    {
        return (int) Capsule::table('intra_edivi')->insertGetId([
            'enr'              => 'T-' . uniqid(),
            'patname'          => $patname,
            'patgebdat'        => '1990-01-15',
            'protokoll_status' => 0,
            'hidden'           => 0,
            'freigegeben'      => 1,
            'billing_sent'     => 0,
            'created_at'       => date('Y-m-d H:i:s', time() - 3600),
        ]);
    }

    /** @param array<string,mixed> $body */
    private function billing(array $body, ?string $headerKey = null): Response
    {
        $server = ['REMOTE_ADDR' => '192.0.2.10'];
        if ($headerKey !== null) {
            $server['HTTP_X_API_KEY'] = $headerKey;
        }

        return $this->router->dispatch(new Request(
            'POST',
            '/api/enotf/billing',
            server: $server,
            rawBody: json_encode($body, JSON_THROW_ON_ERROR),
        ));
    }

    private function billed(int $id): int
    {
        return (int) Capsule::table('intra_edivi')->where('id', $id)->value('billing_sent');
    }

    /** @return list<string> */
    private function names(Response $response): array
    {
        return array_column($this->assertJsonResponse($response)['protocols'], 'name');
    }

    #[Test]
    public function der_spielserver_bekommt_die_protokolle_mit_dem_schluessel_im_body(): void
    {
        $id = $this->protocol('Max Abrechnung');

        // So schickt ignisTab die Anfrage: Schlüssel im JSON-Body, keine Session.
        $response = $this->billing(['intraRP_API_Key' => (string) constant('API_KEY'), 'timestamp' => time()]);

        $this->assertStatus(200, $response);
        $this->assertContains('Max Abrechnung', $this->names($response));
        $this->assertSame(1, $this->billed($id));
        $this->assertArrayNotHasKey('userid', $_SESSION);
    }

    #[Test]
    public function der_schluessel_im_header_reicht_ebenso(): void
    {
        $this->protocol('Erika Abrechnung');

        $response = $this->billing(['timestamp' => time()], (string) constant('API_KEY'));

        $this->assertStatus(200, $response);
        $this->assertContains('Erika Abrechnung', $this->names($response));
    }

    #[Test]
    public function ohne_oder_mit_falschem_schluessel_gibt_es_nichts(): void
    {
        $id = $this->protocol('Ohne Schluessel');

        $this->assertStatus(403, $this->billing(['timestamp' => time()]));
        $this->assertStatus(403, $this->billing(['intraRP_API_Key' => 'falscher-schluessel', 'timestamp' => time()]));
        $this->assertStatus(403, $this->billing(['timestamp' => time()], 'falscher-schluessel'));
        $this->assertSame(0, $this->billed($id));
    }
}
