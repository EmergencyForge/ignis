<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\LogbookEntry;
use App\Policies\LogbookPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Coverage für die wichtigste Multi-Auth-Policy: LogbookPolicy darf
 * Anträge aus DREI parallelen Session-Kontexten (Admin / eNOTF /
 * FireTab) akzeptieren — und für Update zusätzlich die Eigentümer-
 * Identität pro Source-Typ prüfen. Die Update-Matrix (4 Wege) ist
 * historisch fragil und verdient explizite Regressions-Tests.
 */
class LogbookPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    private function loginAdmin(array $permissions = []): void
    {
        $_SESSION['userid']      = 42;
        $_SESSION['permissions'] = $permissions;
    }

    private function loginEnotf(string $fahrer = 'Müller', string $vehicle = 'RTW-1'): void
    {
        $_SESSION['fahrername'] = $fahrer;
        $_SESSION['protfzg']    = $vehicle;
    }

    private function loginFiretab(int $vehicleId = 7, string $operator = 'Schmidt'): void
    {
        $_SESSION['einsatz_vehicle_id']    = $vehicleId;
        $_SESSION['einsatz_operator_name'] = $operator;
    }

    private function makeFahrt(array $attrs): LogbookEntry
    {
        $f = new LogbookEntry();
        foreach ($attrs as $k => $v) {
            $f->$k = $v;
        }
        return $f;
    }

    // ─── viewList ────────────────────────────────────────────────────

    #[Test]
    public function view_list_requires_admin_or_fahrtenbuch_view(): void
    {
        $this->loginAdmin([]);
        $this->assertFalse(LogbookPolicy::viewList());

        $this->loginAdmin(['logbook.view']);
        $this->assertTrue(LogbookPolicy::viewList());

        $this->loginAdmin(['admin']);
        $this->assertTrue(LogbookPolicy::viewList());
    }

    // ─── create ──────────────────────────────────────────────────────

    #[Test]
    public function create_accepts_admin_session(): void
    {
        $this->loginAdmin();
        $this->assertTrue(LogbookPolicy::create());
    }

    #[Test]
    public function create_accepts_enotf_session(): void
    {
        $this->loginEnotf();
        $this->assertTrue(LogbookPolicy::create());
    }

    #[Test]
    public function create_accepts_firetab_session(): void
    {
        $this->loginFiretab();
        $this->assertTrue(LogbookPolicy::create());
    }

    #[Test]
    public function create_rejects_anonymous(): void
    {
        $this->assertFalse(LogbookPolicy::create());
    }

    #[Test]
    public function create_rejects_partial_enotf_session(): void
    {
        // Nur fahrername ohne protfzg → unvollständige eNOTF-Session
        $_SESSION['fahrername'] = 'Müller';
        $this->assertFalse(LogbookPolicy::create());
    }

    // ─── update ──────────────────────────────────────────────────────

    #[Test]
    public function update_admin_with_manage_permission_can_update_anything(): void
    {
        $this->loginAdmin(['logbook.manage']);
        $entry = $this->makeFahrt([
            'created_by'  => 999,
            'source'      => LogbookEntry::SOURCE_ENOTF,
            'fahrer_name' => 'Other',
        ]);
        $this->assertTrue(LogbookPolicy::update($entry));
    }

    #[Test]
    public function update_owner_can_update_own_entry(): void
    {
        $this->loginAdmin(['user']); // Kein admin/manage
        $entry = $this->makeFahrt([
            'created_by' => 42,       // matched session userid
            'source'     => LogbookEntry::SOURCE_ENOTF,
            'fahrer_name' => 'Other',
        ]);
        $this->assertTrue(LogbookPolicy::update($entry));
    }

    #[Test]
    public function update_enotf_session_can_update_own_enotf_entry(): void
    {
        $this->loginEnotf('Müller');
        $entry = $this->makeFahrt([
            'created_by'  => 999,
            'source'      => LogbookEntry::SOURCE_ENOTF,
            'fahrer_name' => 'Müller',
        ]);
        $this->assertTrue(LogbookPolicy::update($entry));
    }

    #[Test]
    public function update_enotf_session_cannot_update_others_enotf_entry(): void
    {
        $this->loginEnotf('Müller');
        $entry = $this->makeFahrt([
            'created_by'  => 999,
            'source'      => LogbookEntry::SOURCE_ENOTF,
            'fahrer_name' => 'Schmidt',
        ]);
        $this->assertFalse(LogbookPolicy::update($entry));
    }

    #[Test]
    public function update_enotf_session_cannot_update_firetab_entry(): void
    {
        $this->loginEnotf('Müller');
        $entry = $this->makeFahrt([
            'created_by'  => 999,
            'source'      => LogbookEntry::SOURCE_FIRETAB,
            'fahrer_name' => 'Müller', // Name passt, aber Source nicht
        ]);
        $this->assertFalse(LogbookPolicy::update($entry));
    }

    #[Test]
    public function update_firetab_session_can_update_own_firetab_entry(): void
    {
        $this->loginFiretab(7, 'Schmidt');
        $entry = $this->makeFahrt([
            'created_by'  => 999,
            'source'      => LogbookEntry::SOURCE_FIRETAB,
            'fahrer_name' => 'Schmidt',
        ]);
        $this->assertTrue(LogbookPolicy::update($entry));
    }

    #[Test]
    public function update_with_null_entry_falls_back_to_create(): void
    {
        $this->loginEnotf();
        $this->assertTrue(LogbookPolicy::update(null));

        $_SESSION = [];
        $this->assertFalse(LogbookPolicy::update(null));
    }

    // ─── delete ──────────────────────────────────────────────────────

    #[Test]
    public function delete_requires_admin_session_with_manage(): void
    {
        $this->loginAdmin(['logbook.manage']);
        $this->assertTrue(LogbookPolicy::delete());

        $this->loginAdmin(['logbook.view']);
        $this->assertFalse(LogbookPolicy::delete());
    }

    #[Test]
    public function delete_rejects_enotf_session_even_for_own_entry(): void
    {
        $this->loginEnotf('Müller');
        $entry = $this->makeFahrt([
            'created_by'  => 999,
            'source'      => LogbookEntry::SOURCE_ENOTF,
            'fahrer_name' => 'Müller',
        ]);
        $this->assertFalse(LogbookPolicy::delete($entry));
    }

    #[Test]
    public function delete_rejects_firetab_session(): void
    {
        $this->loginFiretab();
        $this->assertFalse(LogbookPolicy::delete());
    }
}
