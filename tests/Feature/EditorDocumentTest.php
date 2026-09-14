<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EditorDocument;
use App\Models\EditorTemplate;
use App\Security\CsrfProtection;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Der Weg eines Dokuments: aus einer Vorlage anlegen, als Entwurf
 * speichern, ausstellen.
 *
 * Geprüft wird dabei vor allem, was der Editor nicht selbst garantieren
 * kann: dass ein gesperrter Abschnitt beim Speichern aus der Vorlage
 * zurückkommt, auch wenn der Browser etwas anderes schickt, und dass ein
 * ausgestelltes Dokument sich nicht mehr speichern lässt.
 */
final class EditorDocumentTest extends FeatureTestCase
{
    private const SECTION_LOCKED = '11111111-1111-4111-8111-111111111111';
    private const SECTION_FREE   = '22222222-2222-4222-8222-222222222222';

    private int $mitarbeiterId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->actingAs($user->id, [
            'permissions'    => ['admin'],
            'cirs_username'  => $user->username,
        ]);

        // Dienstgrad und Qualifikationen haengen als Fremdschluessel am
        // Mitarbeiter; ohne echte Zeilen kommt kein Datensatz durch.
        $rang = (int) Capsule::table('intra_mitarbeiter_dienstgrade')->insertGetId([
            'priority' => 1,
            'name'     => 'Brandmeister',
            'name_m'   => 'Brandmeister',
            'name_w'   => 'Brandmeisterin',
            'archive'  => 0,
        ]);
        $fw = (int) Capsule::table('intra_mitarbeiter_fwquali')->insertGetId([
            'priority'  => 1,
            'shortname' => 'TM',
            'name'      => 'Truppmann',
            'name_m'    => 'Truppmann',
            'name_w'    => 'Truppfrau',
            'none'      => 0,
        ]);
        $rd = (int) Capsule::table('intra_mitarbeiter_rdquali')->insertGetId([
            'priority'  => 1,
            'name'      => 'Rettungssanitaeter',
            'name_m'    => 'Rettungssanitaeter',
            'name_w'    => 'Rettungssanitaeterin',
            'none'      => 0,
            'trainable' => 0,
        ]);

        $this->mitarbeiterId = (int) Capsule::table('intra_mitarbeiter')->insertGetId([
            'fullname'    => 'Testperson',
            'gebdatum'    => '1990-01-01',
            'charakterid' => 'T1',
            'geschlecht'  => 0,
            'dienstnr'    => 'T-1',
            'einstdatum'  => '2020-01-01',
            'dienstgrad'  => $rang,
            'qualifw2'    => $fw,
            'qualird'     => $rd,
        ]);
    }

    /**
     * Eine Vorlage aus einem gesperrten und einem freien Abschnitt.
     *
     * @return array<string,mixed>
     */
    private function templateContent(): array
    {
        return [
            'type'    => 'doc',
            'content' => [
                [
                    'type'    => 'docSection',
                    'attrs'   => ['mode' => 'locked', 'sectionId' => self::SECTION_LOCKED],
                    'content' => [[
                        'type'    => 'paragraph',
                        'content' => [['type' => 'text', 'text' => 'Aus der Vorlage.']],
                    ]],
                ],
                [
                    'type'    => 'docSection',
                    'attrs'   => ['mode' => 'free', 'sectionId' => self::SECTION_FREE],
                    'content' => [['type' => 'paragraph']],
                ],
            ],
        ];
    }

    private function template(): EditorTemplate
    {
        $template            = new EditorTemplate();
        $template->name      = 'Prüfvorlage';
        $template->category  = 'Urkunde';
        $template->content   = $this->templateContent();
        $template->is_active = true;
        $template->save();

        return $template;
    }

    /**
     * @param array<string,mixed> $post
     * @param array<string,mixed> $opts
     */
    private function postWithToken(string $path, array $post, array $opts = []): Response
    {
        return $this->post($path, $post + ['csrf_token' => CsrfProtection::getToken()], $opts);
    }

    private function draft(EditorTemplate $template): EditorDocument
    {
        $response = $this->postWithToken('/personnel/' . $this->mitarbeiterId . '/documents', [
            'template_id' => (string) $template->id,
            'title'       => 'Ein Dokument',
        ]);
        // Ueber die Weiterleitung und nicht ueber "der neueste Datensatz":
        // letzteres greift den Entwurf eines vorherigen Tests ab, wenn das
        // Anlegen hier scheitert, und die Ursache bleibt verborgen.
        $location = $response->headers['Location'] ?? '';
        $this->assertMatchesRegularExpression(
            '~/documents/\d+/edit$~',
            $location,
            'Nach dem Anlegen muss der Editor des neuen Dokuments folgen.',
        );

        preg_match('~/documents/(\d+)/edit$~', $location, $m);
        $document = EditorDocument::find((int) $m[1]);
        $this->assertNotNull($document);

        return $document;
    }

    #[Test]
    public function anlegen_uebernimmt_die_vorlage_als_entwurf(): void
    {
        $document = $this->draft($this->template());

        $this->assertSame(EditorDocument::STATUS_DRAFT, $document->status);
        $this->assertSame('Ein Dokument', $document->title);
        $this->assertSame($this->templateContent(), $document->content);
        $this->assertMatchesRegularExpression('/^\d{7}$/', $document->docid);
    }

    #[Test]
    public function gesperrter_abschnitt_kommt_beim_speichern_aus_der_vorlage_zurueck(): void
    {
        $document = $this->draft($this->template());

        // Der Browser schickt einen manipulierten gesperrten Abschnitt mit.
        $tampered = $this->templateContent();
        $tampered['content'][0]['content'][0]['content'][0]['text'] = 'Heimlich geändert.';
        $tampered['content'][1]['content'][0] = [
            'type'    => 'paragraph',
            'content' => [['type' => 'text', 'text' => 'Frei geschrieben.']],
        ];

        $response = $this->postWithToken('/documents/' . $document->id . '/save', [
            'content' => json_encode($tampered, JSON_UNESCAPED_UNICODE),
            'title'   => 'Ein Dokument',
        ]);
        $this->assertRedirect($response);

        $saved = EditorDocument::find($document->id);
        $this->assertSame(
            'Aus der Vorlage.',
            $saved->content['content'][0]['content'][0]['content'][0]['text'],
            'Der gesperrte Abschnitt muss aus der Vorlage stammen, nicht aus dem Formular.',
        );
        $this->assertSame(
            'Frei geschrieben.',
            $saved->content['content'][1]['content'][0]['content'][0]['text'],
            'Der freie Abschnitt bleibt, wie der Verfasser ihn geschickt hat.',
        );
    }

    #[Test]
    public function ein_ausgestelltes_dokument_laesst_sich_nicht_mehr_speichern(): void
    {
        $document = $this->draft($this->template());

        Capsule::table('intra_documents')
            ->where('id', $document->id)
            ->update(['status' => EditorDocument::STATUS_ISSUED]);

        $response = $this->postWithToken('/documents/' . $document->id . '/save', [
            'content' => json_encode($this->templateContent(), JSON_UNESCAPED_UNICODE),
        ]);

        $this->assertRedirect($response);
        $this->assertSame(
            EditorDocument::STATUS_ISSUED,
            EditorDocument::find($document->id)->status,
        );
    }

    #[Test]
    public function autosave_antwortet_als_json_und_reicht_den_token_nach(): void
    {
        $document = $this->draft($this->template());

        $response = $this->postWithToken('/documents/' . $document->id . '/save', [
            'content'  => json_encode($this->templateContent(), JSON_UNESCAPED_UNICODE),
            'autosave' => '1',
        ], ['headers' => ['Accept' => 'application/json']]);

        $payload = $this->assertJsonResponse($response);
        $this->assertTrue($payload['success']);
        $this->assertNotEmpty($payload['csrf_token'], 'Ohne frischen Token scheitert die naechste Autosave.');
    }

    #[Test]
    public function ausstellen_friert_die_variablen_ein_und_legt_das_pdf_an(): void
    {
        $document = $this->draft($this->template());

        $response = $this->postWithToken('/documents/' . $document->id . '/issue', []);
        $this->assertRedirect($response);

        $issued = EditorDocument::find($document->id);
        $this->assertSame(EditorDocument::STATUS_ISSUED, $issued->status);
        $this->assertNotNull($issued->issued_at);
        $this->assertSame('Testperson', $issued->frozen_values['mitarbeiter.name'] ?? null);
        $this->assertSame($document->docid, $issued->frozen_values['dokument.kennung'] ?? null);
        $this->assertSame('storage/documents/' . $document->docid . '.pdf', $issued->pdf_path);

        $pdf = dirname(__DIR__, 2) . '/' . $issued->pdf_path;
        $this->assertFileExists($pdf);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($pdf));
        @unlink($pdf);
    }
}
