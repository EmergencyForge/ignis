<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugin\EnotfV2\Support\ProtokollAccessGuard;
use Tests\TestCase;

/**
 * Zugriffs-Matrix des ProtokollAccessGuard: Fahrzeug-Scoping (fzg_transp /
 * fzg_na), Panel-User via Permissions, Klinik-ENR-Match. Die Session wird
 * pro Test direkt über $_SESSION aufgebaut — der Guard liest nichts anderes.
 */
class EnotfV2ProtokollAccessGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    /** @return array<string,mixed> */
    private function protokoll(array $overrides = []): array
    {
        return array_merge([
            'enr'        => 'ENR-100',
            'fzg_transp' => 'RTW-1',
            'fzg_na'     => 'NEF-1',
        ], $overrides);
    }

    private function crewSession(string $vehicle): void
    {
        $_SESSION['fahrername'] = 'Max Mustermann';
        $_SESSION['protfzg']    = $vehicle;
    }

    // ── Keine Session ────────────────────────────────────────────────

    #[Test]
    public function ohne_session_wird_lesen_und_schreiben_verweigert(): void
    {
        $this->assertFalse(ProtokollAccessGuard::canRead($this->protokoll()));
        $this->assertFalse(ProtokollAccessGuard::canWrite($this->protokoll()));
    }

    // ── Crew-Session / Fahrzeug-Scoping ──────────────────────────────

    #[Test]
    public function eigenes_transportfahrzeug_darf_lesen_und_schreiben(): void
    {
        $this->crewSession('RTW-1');

        $this->assertTrue(ProtokollAccessGuard::canRead($this->protokoll()));
        $this->assertTrue(ProtokollAccessGuard::canWrite($this->protokoll()));
    }

    #[Test]
    public function eigenes_na_fahrzeug_darf_lesen_und_schreiben(): void
    {
        $this->crewSession('NEF-1');

        $this->assertTrue(ProtokollAccessGuard::canRead($this->protokoll()));
        $this->assertTrue(ProtokollAccessGuard::canWrite($this->protokoll()));
    }

    #[Test]
    public function fremdes_fahrzeug_wird_verweigert(): void
    {
        $this->crewSession('RTW-99');

        $this->assertFalse(ProtokollAccessGuard::canRead($this->protokoll()));
        $this->assertFalse(ProtokollAccessGuard::canWrite($this->protokoll()));
    }

    #[Test]
    public function leeres_fahrzeugfeld_im_protokoll_matcht_keine_leere_session(): void
    {
        // hasCrewSession verlangt non-empty protfzg — aber selbst wenn beide
        // Seiten leer wären, darf '' === '' keinen Zugriff geben.
        $_SESSION['fahrername'] = 'Max';
        $_SESSION['protfzg']    = '';

        $p = $this->protokoll(['fzg_transp' => '', 'fzg_na' => '']);
        $this->assertFalse(ProtokollAccessGuard::canRead($p));
        $this->assertFalse(ProtokollAccessGuard::canWrite($p));
    }

    #[Test]
    public function protokoll_ohne_fahrzeugfelder_matcht_nicht(): void
    {
        $this->crewSession('RTW-1');

        $p = ['enr' => 'ENR-100'];
        $this->assertFalse(ProtokollAccessGuard::canRead($p));
        $this->assertFalse(ProtokollAccessGuard::canWrite($p));
    }

    // ── Panel-User (Permissions) ─────────────────────────────────────

    #[Test]
    public function panel_user_mit_edivi_view_darf_lesen_aber_nicht_schreiben(): void
    {
        $_SESSION['permissions'] = ['edivi.view'];

        $this->assertTrue(ProtokollAccessGuard::canRead($this->protokoll()));
        $this->assertFalse(ProtokollAccessGuard::canWrite($this->protokoll()));
    }

    #[Test]
    public function panel_user_mit_enotf_view_darf_lesen_aber_nicht_schreiben(): void
    {
        $_SESSION['permissions'] = ['enotf.view'];

        $this->assertTrue(ProtokollAccessGuard::canRead($this->protokoll()));
        $this->assertFalse(ProtokollAccessGuard::canWrite($this->protokoll()));
    }

    #[Test]
    public function panel_user_mit_edivi_edit_darf_schreiben(): void
    {
        $_SESSION['permissions'] = ['edivi.edit'];

        $this->assertTrue(ProtokollAccessGuard::canWrite($this->protokoll()));
    }

    #[Test]
    public function admin_darf_lesen_und_schreiben(): void
    {
        $_SESSION['permissions'] = ['admin'];

        $this->assertTrue(ProtokollAccessGuard::canRead($this->protokoll()));
        $this->assertTrue(ProtokollAccessGuard::canWrite($this->protokoll()));
    }

    // ── Klinikzugriff (Einmalcode) ───────────────────────────────────

    #[Test]
    public function klinik_session_darf_genau_ihre_enr_lesen(): void
    {
        $_SESSION['klinik_access_enr']  = 'ENR-100';
        $_SESSION['klinik_access_time'] = time() - 60;

        $this->assertTrue(ProtokollAccessGuard::canRead($this->protokoll()));
        $this->assertFalse(ProtokollAccessGuard::canWrite($this->protokoll()));
    }

    #[Test]
    public function klinik_session_darf_fremde_enr_nicht_lesen(): void
    {
        $_SESSION['klinik_access_enr']  = 'ENR-999';
        $_SESSION['klinik_access_time'] = time() - 60;

        $this->assertFalse(ProtokollAccessGuard::canRead($this->protokoll()));
    }

    #[Test]
    public function abgelaufene_klinik_session_darf_nicht_mehr_lesen(): void
    {
        $_SESSION['klinik_access_enr']  = 'ENR-100';
        $_SESSION['klinik_access_time'] = time() - 7200 - 60; // TTL (2h) überschritten

        $this->assertFalse(ProtokollAccessGuard::canRead($this->protokoll()));
    }
}
