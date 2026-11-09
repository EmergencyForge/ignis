<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Documents\Editor\FieldChoices;
use App\Documents\Editor\PdfGenerator;
use App\Models\EditorDocument;
use App\Models\EditorTemplate;
use App\Security\CsrfProtection;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use EmergencyForge\Editor\Layout;
use EmergencyForge\Editor\PageShell;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Der Weg eines Dokuments: aus einer Vorlage anlegen, als Entwurf
 * speichern, ausstellen.
 *
 * Geprüft wird dabei vor allem, was der Editor nicht selbst garantieren
 * kann: dass fester Text beim Speichern aus der Vorlagenfassung
 * zurückkommt, auch wenn der Browser etwas anderes schickt, dass ein Feld
 * mit Recht nur mit diesem Recht ausgefüllt wird, dass ein Entwurf auf eine
 * neue Fassung umgestellt werden kann, und dass ein ausgestelltes Dokument
 * sich nicht mehr speichern lässt.
 */
final class EditorDocumentTest extends FeatureTestCase
{
    private int $mitarbeiterId = 0;

    /** @var list<string> */
    private array $pdfFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->pdfFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

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
     * Eine Vorlage aus festem Text und einem Textbereich.
     *
     * @return array<string,mixed>
     */
    private function templateContent(): array
    {
        return [
            'format' => 2,
            'layout' => Layout::defaults(),
            'body'   => ['type' => 'doc', 'content' => [
                [
                    'type'    => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Aus der Vorlage.']],
                ],
                [
                    'type'    => 'docInput',
                    'attrs'   => [
                        'inputId' => 'frei', 'kind' => 'text', 'label' => 'Text', 'format' => 'full',
                        'start' => 'empty', 'placeholder' => '', 'required' => false, 'minLength' => null, 'who' => null,
                    ],
                    'content' => [['type' => 'paragraph']],
                ],
            ]],
            'header' => ['first' => null, 'rest' => null],
            'footer' => ['first' => null, 'rest' => null],
        ];
    }

    /**
     * Vorlage mit genau diesem Inhalt im ersten Absatz.
     *
     * @param list<array<string,mixed>> $inline
     */
    private function templateWithInline(array $inline): EditorTemplate
    {
        $content = $this->templateContent();
        $content['body']['content'][0]['content'] = $inline;

        $template          = $this->template();
        $template->content = $content;
        $template->save();

        return $template;
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
        // assertEquals: MySQL sortiert die Schlüssel einer JSON-Spalte um, MariaDB nicht.
        $this->assertEquals($this->templateContent(), $document->content);
        $this->assertMatchesRegularExpression('/^\d{7}$/', $document->docid);
    }

    #[Test]
    public function anlegen_haengt_den_entwurf_an_die_juengste_fassung(): void
    {
        $template = $this->template();
        $document = $this->draft($template);

        $version = $template->latestVersion();
        $this->assertNotNull($version);
        $this->assertSame($version->id, $document->template_version_id);
    }

    #[Test]
    public function fester_text_kommt_beim_speichern_aus_der_vorlage_zurueck(): void
    {
        $document = $this->draft($this->template());

        // Der Browser schickt manipulierten festen Text mit.
        $tampered = $this->templateContent();
        $tampered['body']['content'][0]['content'][0]['text'] = 'Heimlich geändert.';
        $tampered['body']['content'][1]['content'][0] = [
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
            $saved->content['body']['content'][0]['content'][0]['text'],
            'Fester Text muss aus der Vorlage stammen, nicht aus dem Formular.',
        );
        $this->assertSame(
            'Frei geschrieben.',
            $saved->content['body']['content'][1]['content'][0]['content'][0]['text'],
            'Der Textbereich bleibt, wie der Verfasser ihn geschickt hat.',
        );
    }

    /**
     * Ein Feld, das `personnel.documents.approve` verlangt: ohne das Recht
     * bleibt der gespeicherte Stand, mit dem Recht kommt der Wert an.
     */
    #[Test]
    public function ein_feld_mit_recht_fuellt_nur_wer_das_recht_hat(): void
    {
        $field = [
            'type'  => 'docField',
            'attrs' => [
                'fieldId' => 'freigabe', 'label' => 'Freigabe', 'kind' => 'line', 'required' => false,
                'value' => '', 'who' => 'personnel.documents.approve',
            ],
        ];
        $document = $this->draft($this->templateWithInline([$field]));

        $content = $this->templateContent();
        $field['attrs']['value'] = 'Freigegeben';
        $content['body']['content'][0]['content'] = [$field];
        $_SESSION['permissions'] = ['personnel.documents.manage'];
        $this->assertSame('', $this->saveAndRead($document, $content)['body']['content'][0]['content'][0]['attrs']['value']);

        $_SESSION['permissions'] = ['personnel.documents.manage', 'personnel.documents.approve'];
        $this->assertSame('Freigegeben', $this->saveAndRead($document, $content)['body']['content'][0]['content'][0]['attrs']['value']);
    }

    /**
     * @param  array<string,mixed> $content
     * @return array<string,mixed>
     */
    private function saveAndRead(EditorDocument $document, array $content): array
    {
        $this->postWithToken('/documents/' . $document->id . '/save', [
            'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
            'title'   => 'Ein Dokument',
        ]);

        return EditorDocument::find($document->id)->content;
    }

    #[Test]
    public function ein_entwurf_wechselt_auf_die_neue_fassung_und_behaelt_seinen_text(): void
    {
        $template = $this->template();
        $document = $this->draft($template);

        $written = $this->templateContent();
        $written['body']['content'][1]['content'][0] = [
            'type'    => 'paragraph',
            'content' => [['type' => 'text', 'text' => 'Bleibt stehen.']],
        ];
        $this->postWithToken('/documents/' . $document->id . '/save', [
            'content' => json_encode($written, JSON_UNESCAPED_UNICODE),
            'title'   => 'Ein Dokument',
        ]);

        // Am Entwurf hängt Fassung 1, also legt Speichern Fassung 2 an.
        $neu = $this->templateContent();
        $neu['body']['content'][0]['content'][0]['text'] = 'Neuer fester Text.';
        $template->saveContent($neu, null);
        $this->assertSame(2, $template->latestVersion()?->version);

        $edit = $this->get('/documents/' . $document->id . '/edit')->body;
        $this->assertStringContainsString('id="document-upgrade-notice"', $edit);

        $response = $this->postWithToken('/documents/' . $document->id . '/upgrade', ['lost' => '']);
        $this->assertRedirect($response, '/documents/' . $document->id . '/edit');

        $saved = EditorDocument::find($document->id);
        $this->assertSame($template->latestVersion()->id, $saved->template_version_id);
        $this->assertSame('Neuer fester Text.', $saved->content['body']['content'][0]['content'][0]['text']);
        $this->assertSame('Bleibt stehen.', $saved->content['body']['content'][1]['content'][0]['content'][0]['text']);
    }

    #[Test]
    public function umgestellt_wird_nicht_wenn_die_liste_der_verluste_nicht_stimmt(): void
    {
        $template = $this->template();
        $document = $this->draft($template);
        $before   = $document->template_version_id;

        $neu = $this->templateContent();
        $neu['body']['content'][0]['content'][0]['text'] = 'Neuer fester Text.';
        $template->saveContent($neu, null);

        $this->postWithToken('/documents/' . $document->id . '/upgrade', ['lost' => 'erfunden']);

        $this->assertSame($before, EditorDocument::find($document->id)->template_version_id);
    }

    /**
     * Dokument aus einer Vorlage, deren Feld seine Liste aus den Dienstgraden
     * zieht.
     *
     * @return array{EditorDocument, array<string,mixed>}
     */
    private function draftWithRankField(): array
    {
        $inline = [[
            'type'  => 'docField',
            'attrs' => ['fieldId' => 'dienstgrad', 'label' => 'Dienstgrad', 'value' => '', 'optionsSource' => FieldChoices::RANKS, 'who' => null],
        ]];
        $content = $this->templateContent();
        $content['body']['content'][0]['content'] = $inline;

        return [$this->draft($this->templateWithInline($inline)), $content];
    }

    /** @param array<string,mixed> $content */
    private function saveRank(EditorDocument $document, array $content, string $value): string
    {
        $content['body']['content'][0]['content'][0]['attrs']['value'] = $value;

        $this->postWithToken('/documents/' . $document->id . '/save', [
            'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
            'title'   => 'Ein Dokument',
        ]);

        return EditorDocument::find($document->id)->content['body']['content'][0]['content'][0]['attrs']['value'];
    }

    #[Test]
    public function ein_feld_mit_quelle_nimmt_die_form_aus_den_stammdaten_an(): void
    {
        [$document, $content] = $this->draftWithRankField();

        $this->assertSame('Brandmeisterin', $this->saveRank($document, $content, 'Brandmeisterin'));
        $this->assertSame('', $this->saveRank($document, $content, 'Gefälscht'));
    }

    #[Test]
    public function ein_getippter_wert_im_entwurf_uebersteht_den_autosave(): void
    {
        [$document, $content] = $this->draftWithRankField();

        $stored = $content;
        $stored['body']['content'][0]['content'][0]['attrs']['value'] = 'Von Hand getippt';
        Capsule::table('intra_documents')->where('id', $document->id)->update([
            'content' => json_encode($stored, JSON_UNESCAPED_UNICODE),
        ]);

        $this->assertSame('Von Hand getippt', $this->saveRank($document, $content, 'Von Hand getippt'));
    }

    #[Test]
    public function der_editor_bekommt_die_aktuelle_liste(): void
    {
        [$document] = $this->draftWithRankField();

        $html = $this->get('/documents/' . $document->id . '/edit')->body;

        $this->assertStringContainsString('Brandmeisterin', html_entity_decode($html));
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
    public function autosave_antwortet_als_json_und_derselbe_token_traegt_den_naechsten(): void
    {
        $document = $this->draft($this->template());
        $inhalt   = json_encode($this->templateContent(), JSON_UNESCAPED_UNICODE);
        $json     = ['headers' => ['Accept' => 'application/json']];

        $erste = $this->postWithToken('/documents/' . $document->id . '/save', [
            'content'  => $inhalt,
            'autosave' => '1',
        ], $json);

        $this->assertTrue($this->assertJsonResponse($erste)['success']);

        // Der Token der Sitzung gilt weiter. Solange er bei jeder Pruefung
        // rotierte, musste die Antwort den neuen mitliefern und der Editor
        // ihn zurueckschreiben, sonst war schon die zweite Autosave tot.
        $zweite = $this->postWithToken('/documents/' . $document->id . '/save', [
            'content'  => $inhalt,
            'autosave' => '1',
        ], $json);

        $this->assertTrue($this->assertJsonResponse($zweite)['success']);
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
        $this->assertSame('storage/private/editor-documents/' . $document->docid . '.pdf', $issued->pdf_path);

        $pdf = dirname(__DIR__, 2) . '/' . $issued->pdf_path;
        $this->pdfFiles[] = $pdf;
        $this->assertFileExists($pdf);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($pdf));

        $response = $this->get('/documents/' . $document->id . '/pdf');
        $this->assertOk($response);
        $this->assertStringStartsWith('%PDF', $response->body);
        $this->assertNotFound($this->get('/' . $issued->pdf_path));

        $_SESSION['permissions'] = [];
        $this->assertStatus(403, $this->get('/documents/' . $document->id . '/pdf'));
        $_SESSION = [];
        $this->assertRedirect($this->get('/documents/' . $document->id . '/pdf'), '/login');
    }

    #[Test]
    public function bereits_im_alten_ordner_ausgestellte_editor_pdfs_sind_geschuetzt(): void
    {
        $document = $this->draft($this->template());
        $path = 'storage/documents/' . $document->docid . '.pdf';
        $this->pdfFixture($path);
        $document->update(['status' => EditorDocument::STATUS_ISSUED, 'pdf_path' => $path]);

        $this->assertSame('%PDF-test', $this->get('/documents/' . $document->id . '/pdf')->body);
        $this->assertNotFound($this->get('/' . $path));

        $_SESSION['permissions'] = [];
        $this->assertStatus(403, $this->get('/documents/' . $document->id . '/pdf'));
        $this->assertNotFound($this->get('/' . $path));
        $_SESSION = [];
        $this->assertRedirect($this->get('/documents/' . $document->id . '/pdf'), '/login');
        $this->assertNotFound($this->get('/' . $path));
    }

    #[Test]
    public function alte_personaldokumente_bleiben_ueber_ihren_bisherigen_link_erreichbar(): void
    {
        $name = implode('-', str_split(strtoupper(bin2hex(random_bytes(6))), 4)) . '.pdf';
        $path = 'storage/documents/' . $name;
        $this->pdfFixture($path);
        $_SESSION = [];

        $response = $this->get('/' . $path);
        $this->assertOk($response);
        $this->assertSame('%PDF-test', $response->body);
    }

    #[Test]
    public function ein_entwurf_und_ein_fremder_pdf_pfad_werden_nicht_ausgeliefert(): void
    {
        $document = $this->draft($this->template());
        $path = 'storage/private/editor-documents/' . $document->docid . '.pdf';
        $this->pdfFixture($path);
        $document->update(['pdf_path' => $path]);

        $this->assertNull((new PdfGenerator())->read($document));
        $this->assertRedirect($this->get('/documents/' . $document->id . '/pdf'));

        $document->status = EditorDocument::STATUS_ISSUED;
        $document->pdf_path = 'storage/documents/../private/editor-documents/' . $document->docid . '.pdf';
        $this->assertNull((new PdfGenerator())->read($document));
    }

    #[Test]
    public function ausstellen_prueft_den_nach_einem_autosave_aktuellen_inhalt(): void
    {
        $document = $this->draft($this->template());
        $changed = $this->templateContent();
        $changed['body']['content'][0]['content'] = [[
            'type' => 'docField',
            'attrs' => ['fieldId' => 'required', 'label' => 'Unterschrift', 'required' => true, 'value' => '', 'who' => null],
        ]];

        $connection = Capsule::connection();
        $previousEvents = $connection->getEventDispatcher();
        $events = new Dispatcher();
        $connection->setEventDispatcher($events);
        $autosaved = false;
        $events->listen(TransactionBeginning::class, function () use ($document, $changed, &$autosaved): void {
            if ($autosaved) {
                return;
            }
            $autosaved = true;
            // Autosave zwischen dem ersten Lesen und der Ausstellung.
            Capsule::table('intra_documents')->where('id', $document->id)->update([
                'content' => json_encode($changed, JSON_UNESCAPED_UNICODE),
            ]);
        });

        $path = 'storage/private/editor-documents/' . $document->docid . '.pdf';
        $this->pdfFiles[] = dirname(__DIR__, 2) . '/' . $path;
        $this->pdfFiles[] = dirname(__DIR__, 2) . '/storage/documents/' . $document->docid . '.pdf';
        try {
            $response = $this->postWithToken('/documents/' . $document->id . '/issue', []);
        } finally {
            $connection->setEventDispatcher($previousEvents);
        }

        $this->assertTrue($autosaved);
        $this->assertRedirect($response, '/documents/' . $document->id . '/edit');
        $saved = $document->fresh();
        $this->assertSame(EditorDocument::STATUS_DRAFT, $saved->status);
        $this->assertNull($saved->pdf_path);
        $this->assertFileDoesNotExist(dirname(__DIR__, 2) . '/' . $path);
    }

    /**
     * Wie template(), nur steht im festen Text ein Satz mit diesen
     * Platzhaltern.
     */
    private function templateWithVariables(string ...$names): EditorTemplate
    {
        $inline = [['type' => 'text', 'text' => 'Gezeichnet: ']];
        foreach ($names as $name) {
            $inline[] = ['type' => 'docVariable', 'attrs' => ['name' => $name]];
        }

        return $this->templateWithInline($inline);
    }

    /**
     * Die Testperson hat keinen Titel, `mitarbeiter.titel` bleibt also
     * leer. Ohne ausdrückliche Bestätigung wird nicht ausgestellt, und die
     * Meldung nennt den Platzhalter mit seiner Beschriftung.
     */
    #[Test]
    public function ein_platzhalter_ohne_wert_braucht_eine_bestaetigung(): void
    {
        $document = $this->draft($this->templateWithVariables('mitarbeiter.name', 'mitarbeiter.titel'));

        $response = $this->postWithToken('/documents/' . $document->id . '/issue', []);

        $this->assertRedirect($response, '/documents/' . $document->id . '/edit');
        $this->assertSame(EditorDocument::STATUS_DRAFT, $document->fresh()->status);
        $flash = (string) ($_SESSION['flash']['text'] ?? '');
        $this->assertStringContainsString('Diese Platzhalter haben keinen Wert und blieben leer: Mitarbeiter: Titel.', $flash);
        $this->assertStringNotContainsString('Name mit Titel', $flash);
    }

    #[Test]
    public function bestaetigt_wird_ausgestellt_und_die_stelle_bleibt_leer(): void
    {
        $document = $this->draft($this->templateWithVariables('mitarbeiter.name', 'mitarbeiter.titel'));
        $this->pdfFiles[] = dirname(__DIR__, 2) . '/storage/private/editor-documents/' . $document->docid . '.pdf';

        $response = $this->postWithToken('/documents/' . $document->id . '/issue', ['accept_missing' => '1']);

        $this->assertRedirect($response, '/documents/' . $document->id);
        $issued = $document->fresh();
        $this->assertSame(EditorDocument::STATUS_ISSUED, $issued->status);

        $html = PageShell::html($issued->content, $issued->frozen_values)->html;
        $this->assertStringContainsString('Testperson', $html);
        $this->assertStringNotContainsString('{{', $html);

        $audit = Capsule::table('intra_audit_log')->where('action', 'Dokument ausgestellt')->orderBy('id', 'desc')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('leer gelassen: Mitarbeiter: Titel', (string) $audit->details);
    }

    #[Test]
    public function mit_allen_werten_braucht_es_keine_bestaetigung(): void
    {
        $document = $this->draft($this->templateWithVariables('mitarbeiter.name', 'dokument.kennung'));
        $this->pdfFiles[] = dirname(__DIR__, 2) . '/storage/private/editor-documents/' . $document->docid . '.pdf';

        $this->postWithToken('/documents/' . $document->id . '/issue', []);

        $this->assertSame(EditorDocument::STATUS_ISSUED, $document->fresh()->status);
    }

    #[Test]
    public function der_editor_sagt_warum_ein_platzhalter_leer_bleibt(): void
    {
        $document = $this->draft($this->templateWithVariables('mitarbeiter.titel'));

        $response = $this->get('/documents/' . $document->id . '/edit');

        $this->assertOk($response);
        $this->assertStringContainsString('data-efe-missing-reasons=', $response->body);
        $this->assertStringContainsString('Beim Mitarbeiter ist kein Titel eingetragen.', $response->body);
        $this->assertStringContainsString('name="accept_missing"', $response->body);
    }

    private function pdfFixture(string $relativePath): void
    {
        $file = dirname(__DIR__, 2) . '/' . $relativePath;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }
        $this->pdfFiles[] = $file;
        file_put_contents($file, '%PDF-test');
    }
}
