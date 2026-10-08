<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RegistrationCode;
use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Formulare der Benutzer- und Rollenverwaltung, die noch keinen Test
 * hatten: Rolle eines Kontos ändern, Einladung löschen, Rolle löschen,
 * und was bei kaputten Posts passiert.
 */
final class UserFormsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    private function code(): RegistrationCode
    {
        $code = new RegistrationCode();
        $code->code       = bin2hex(random_bytes(8));
        $code->label      = 'Test';
        $code->created_by = (int) $_SESSION['userid'];
        $code->is_used    = false;
        $code->save();

        return $code;
    }

    #[Test]
    public function rolle_eines_kontos_aendern(): void
    {
        $target = FixtureFactory::user();
        $role   = FixtureFactory::role();

        $response = $this->post('/users/edit', [
            'new'      => '1',
            'id'       => (string) $target->id,
            'username' => $target->username,
            'role'     => (string) $role->id,
            'submit'   => '',
        ], ['query' => ['id' => (string) $target->id]]);

        $this->assertRedirect($response, '/users/list');
        $this->assertSame((int) $role->id, (int) User::query()->findOrFail($target->id)->role);
    }

    #[Test]
    public function rolle_als_text_aendert_nichts(): void
    {
        $target = FixtureFactory::user();
        $before = (int) $target->role;

        $this->post('/users/edit', ['new' => '1', 'id' => (string) $target->id, 'role' => 'admin'], ['query' => ['id' => (string) $target->id]]);

        $this->assertSame('Ungültige Rolle.', $_SESSION['flash']['text'] ?? null);
        $this->assertSame($before, (int) User::query()->findOrFail($target->id)->role);
    }

    #[Test]
    public function einladung_loeschen(): void
    {
        $code = $this->code();

        $this->assertRedirect($this->post('/users/registration-codes', ['action' => 'delete', 'code_id' => (string) $code->id]), '/users/registration-codes');

        $this->assertNull(RegistrationCode::query()->find($code->id));
    }

    #[Test]
    public function einladung_mit_kaputter_id_bleibt(): void
    {
        $code = $this->code();

        $this->post('/users/registration-codes', ['action' => 'delete', 'code_id' => 'abc']);

        $this->assertSame('Einladung konnte nicht gelöscht werden (bereits verwendet oder nicht gefunden).', $_SESSION['flash']['text'] ?? null);
        $this->assertNotNull(RegistrationCode::query()->find($code->id));
    }

    #[Test]
    public function unbekannte_aktion_zeigt_die_seite(): void
    {
        $this->assertOk($this->post('/users/registration-codes', ['action' => 'gibt-es-nicht']));
    }

    #[Test]
    public function aktivieren_mit_unbekannter_aktion_aendert_nichts(): void
    {
        $target = FixtureFactory::user();

        $this->assertRedirect($this->post('/users/toggle-active', ['id' => (string) $target->id, 'action' => 'loeschen']), '/users/list');

        $this->assertSame('invalid-request', $_SESSION['flash']['text'] ?? null);
        $this->assertTrue((bool) User::query()->findOrFail($target->id)->is_active);
    }

    #[Test]
    public function konto_loeschen_ohne_id(): void
    {
        $this->assertRedirect($this->post('/users/delete', ['id' => '']), '/users/list');

        $this->assertSame('invalid-request', $_SESSION['flash']['text'] ?? null);
    }

    #[Test]
    public function rolle_loeschen(): void
    {
        $role = FixtureFactory::role();

        $this->assertRedirect($this->post('/users/roles/delete', ['id' => (string) $role->id]), '/users/roles/index');

        $this->assertNull(Role::query()->find($role->id));
        $this->assertSame('Die Rolle wurde erfolgreich gelöscht.', $_SESSION['flash']['text'] ?? null);
    }

    #[Test]
    public function rolle_loeschen_ohne_gueltige_id(): void
    {
        $role = FixtureFactory::role();

        $this->post('/users/roles/delete', ['id' => 'x' . $role->id]);

        $this->assertSame('Ungültige Rollen-ID.', $_SESSION['flash']['text'] ?? null);
        $this->assertNotNull(Role::query()->find($role->id));
    }
}
