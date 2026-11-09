<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EditorDocument;
use App\Models\EditorTemplate;
use App\Models\Letterhead;
use App\Security\CsrfProtection;
use EmergencyForge\Editor\Layout;
use EmergencyForge\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Briefbögen unter `/settings/documents/letterheads`: Anlegen, Ändern samt
 * neuer Fassung für jede Vorlage, die ihn nutzt, und Löschen nur ohne
 * Nutzer.
 */
final class LetterheadTest extends FeatureTestCase
{
    /** @return array<string,mixed> */
    private static function para(string $text): array
    {
        return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
    }

    /**
     * @param array<string,mixed>|null $header
     * @return array<string,mixed>
     */
    private static function package(string $body, ?array $header = null): array
    {
        return [
            'format' => 2,
            'layout' => Layout::defaults(),
            'body'   => ['type' => 'doc', 'content' => [self::para($body)]],
            'header' => ['first' => null, 'rest' => $header],
            'footer' => ['first' => null, 'rest' => null],
        ];
    }

    /** @return array<string,mixed> */
    private static function letterheadPackage(string $header): array
    {
        return self::package('Probetext', ['type' => 'doc', 'content' => [self::para($header)]]);
    }

    /** @param array<string,mixed> $package */
    private static function headerText(array $package): ?string
    {
        return $package['header']['rest']['content'][0]['content'][0]['text'] ?? null;
    }

    /** @param array<string,mixed> $package */
    private static function bodyText(array $package): ?string
    {
        return $package['body']['content'][0]['content'][0]['text'] ?? null;
    }

    /** @param list<string> $permissions */
    private function login(array $permissions = ['personnel.documents.manage']): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username]);
    }

    /** @param array<string,mixed> $post */
    private function postWithToken(string $path, array $post = []): Response
    {
        return $this->post($path, $post + ['csrf_token' => CsrfProtection::getToken()]);
    }

    private function makeLetterhead(string $header = 'Feuerwehr Musterstadt'): Letterhead
    {
        $letterhead          = new Letterhead();
        $letterhead->name    = 'Standard';
        $letterhead->content = self::letterheadPackage($header);
        $letterhead->save();

        return $letterhead;
    }

    /** @param array<string,mixed> $content */
    private function makeTemplate(array $content): EditorTemplate
    {
        $template            = new EditorTemplate();
        $template->name      = 'Brief ' . bin2hex(random_bytes(3));
        $template->is_active = true;
        $template->saveContent($content, null);

        return $template;
    }

    #[Test]
    public function die_liste_braucht_das_recht_auf_dokumente(): void
    {
        $this->login([]);
        $this->assertContains($this->get('/settings/documents/letterheads')->status, [302, 403]);

        $this->login();
        $this->assertOk($this->get('/settings/documents/letterheads'));
    }

    #[Test]
    public function anlegen_speichert_nur_layout_und_baender(): void
    {
        $this->login();

        $response = $this->postWithToken('/settings/documents/letterheads/create', [
            'name'    => 'Wache 1',
            'content' => json_encode(self::letterheadPackage('Feuerwache 1')),
        ]);

        $this->assertRedirect($response, '/settings/documents/letterheads');
        $letterhead = Letterhead::query()->where('name', 'Wache 1')->firstOrFail();
        $this->assertSame('Feuerwache 1', self::headerText($letterhead->content));
        $this->assertNull(self::bodyText($letterhead->content), 'Der Probetext gehört nicht zum Briefbogen.');
    }

    #[Test]
    public function kein_paket_wird_abgelehnt(): void
    {
        $this->login();

        $this->postWithToken('/settings/documents/letterheads/create', ['name' => 'Kaputt', 'content' => '{"type":"doc"}']);

        $this->assertNull(Letterhead::query()->where('name', 'Kaputt')->first());
        $this->assertSame('Der Briefbogen ist kein gültiges Dokumentpaket.', $_SESSION['flash']['text'] ?? null);
    }

    /**
     * Jede Vorlage mit diesem Briefbogen bekommt die neuen Bänder. Hängt an
     * ihrer Fassung schon ein Entwurf, entsteht eine neue, der Entwurf bleibt.
     */
    #[Test]
    public function aendern_gilt_fuer_jede_vorlage_die_ihn_nutzt(): void
    {
        $letterhead = $this->makeLetterhead();
        $template   = $this->makeTemplate($letterhead->applyTo(self::package('Fester Text')));
        $other      = $this->makeTemplate(self::package('Ohne Briefbogen'));
        $draft      = EditorDocument::create($template->newDocumentAttributes() + [
            'docid'          => (string) random_int(1000000, 9999999),
            'mitarbeiter_id' => FixtureFactory::personnel()->id,
            'title'          => 'Entwurf',
            'status'         => EditorDocument::STATUS_DRAFT,
        ]);
        $this->login();

        $response = $this->postWithToken('/settings/documents/letterheads/' . $letterhead->id, [
            'name'    => 'Standard',
            'content' => json_encode(self::letterheadPackage('Neue Anschrift')),
        ]);

        $this->assertRedirect($response, '/settings/documents/letterheads');
        $fresh = $template->fresh();
        $this->assertSame('Neue Anschrift', self::headerText($fresh->content));
        $this->assertSame('Fester Text', self::bodyText($fresh->content));
        $this->assertSame(2, $template->latestVersion()?->version);
        $this->assertSame('Feuerwehr Musterstadt', self::headerText($draft->fresh()->content));
        $this->assertNull($other->fresh()->content['header']['rest']);
    }

    #[Test]
    public function loeschen_geht_erst_wenn_keine_vorlage_ihn_nutzt(): void
    {
        $letterhead = $this->makeLetterhead();
        $template   = $this->makeTemplate($letterhead->applyTo(self::package('Fester Text')));
        $this->login();

        $this->postWithToken('/settings/documents/letterheads/' . $letterhead->id . '/delete');

        $this->assertNotNull(Letterhead::find($letterhead->id));
        $this->assertStringContainsString('noch von 1 Vorlage(n) genutzt', (string) ($_SESSION['flash']['text'] ?? ''));

        $template->delete();
        $this->postWithToken('/settings/documents/letterheads/' . $letterhead->id . '/delete');

        $this->assertNull(Letterhead::find($letterhead->id));
    }
}
