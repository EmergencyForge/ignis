<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\PersonnelTitle;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\SignatureTemplate;
use Tests\FeatureTestCase;

/** absender.name trägt den Titel, absender.titel steht einzeln zur Verfügung. */
final class SignatureTitleTest extends FeatureTestCase
{
    use MailFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
    }

    /** @return array<string,mixed> */
    private function vorlage(): array
    {
        $zeile = static fn (string $key): array => ['type' => 'paragraph', 'content' => [['type' => 'docVariable', 'attrs' => ['name' => $key]]]];

        return ['type' => 'doc', 'content' => [$zeile('absender.name'), $zeile('absender.titel')]];
    }

    #[Test]
    public function mit_titel(): void
    {
        $dr     = (int) PersonnelTitle::query()->where('name', 'Dr.')->value('id');
        $person = $this->mitarbeiter('Max Muster', ['titel_id' => $dr]);

        $doc = SignatureTemplate::resolve($this->vorlage(), $person, 'm.muster@ignis.ef');

        $this->assertSame('Dr. Max Muster', $doc['content'][0]['content'][0]['text']);
        $this->assertSame('Dr.', $doc['content'][1]['content'][0]['text']);
        $this->assertArrayHasKey('absender.titel', SignatureTemplate::catalog());
    }

    #[Test]
    public function ohne_titel_faellt_die_titelzeile_weg(): void
    {
        $doc = SignatureTemplate::resolve($this->vorlage(), $this->mitarbeiter('Erika Muster'), 'e.muster@ignis.ef');

        $this->assertCount(1, $doc['content']);
        $this->assertSame('Erika Muster', $doc['content'][0]['content'][0]['text']);
    }
}
