<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormType;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

class AntragTypModelTest extends IntegrationTestCase
{
    private int $typId;
    private array $cleanupAntragIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $typ = new FormType();
        $typ->name        = 'TypTest_' . uniqid();
        $typ->beschreibung = 'Integration-Test';
        $typ->icon        = 'fa-solid fa-flask';
        $typ->aktiv       = true;
        $typ->sortierung  = 99;
        $typ->save();
        $this->typId = $typ->id;
    }

    protected function tearDown(): void
    {
        if (!empty($this->cleanupAntragIds)) {
            \App\Models\FormData::whereIn('antrag_id', $this->cleanupAntragIds)->delete();
            Form::whereIn('id', $this->cleanupAntragIds)->delete();
        }
        FormField::where('antragstyp_id', $this->typId)->delete();
        FormType::where('id', $this->typId)->delete();
        parent::tearDown();
    }

    #[Test]
    public function typ_can_be_persisted_and_retrieved(): void
    {
        $typ = FormType::find($this->typId);
        $this->assertNotNull($typ);
        $this->assertTrue($typ->aktiv);
        $this->assertSame(99, $typ->sortierung);
    }

    #[Test]
    public function active_scope_filters_inactive_typen(): void
    {
        $inactive = new FormType();
        $inactive->name       = 'Inactive_' . uniqid();
        $inactive->aktiv      = false;
        $inactive->sortierung = 99;
        $inactive->save();

        try {
            $found = FormType::active()->where('id', $this->typId)->first();
            $this->assertNotNull($found);

            $foundInactive = FormType::active()->where('id', $inactive->id)->first();
            $this->assertNull($foundInactive);
        } finally {
            FormType::where('id', $inactive->id)->delete();
        }
    }

    #[Test]
    public function felder_relationship_is_ordered_by_sortierung(): void
    {
        $secondField = new FormField();
        $secondField->antragstyp_id = $this->typId;
        $secondField->feldname      = 'feld_zwei';
        $secondField->label         = 'Zweites';
        $secondField->feldtyp       = 'text';
        $secondField->pflichtfeld   = false;
        $secondField->sortierung    = 20;
        $secondField->breite        = 'full';
        $secondField->readonly      = false;
        $secondField->save();

        $firstField = new FormField();
        $firstField->antragstyp_id = $this->typId;
        $firstField->feldname      = 'feld_eins';
        $firstField->label         = 'Erstes';
        $firstField->feldtyp       = 'text';
        $firstField->pflichtfeld   = true;
        $firstField->sortierung    = 10;
        $firstField->breite        = 'full';
        $firstField->readonly      = false;
        $firstField->save();

        $typ = FormType::with('felder')->find($this->typId);
        $this->assertCount(2, $typ->felder);
        $this->assertSame('feld_eins', $typ->felder->first()->feldname);
        $this->assertSame('feld_zwei', $typ->felder->last()->feldname);
    }

    #[Test]
    public function antrag_belongs_to_typ_relationship_works(): void
    {
        $antrag = new Form();
        $antrag->uniqueid       = (string) random_int(100000, 999999);
        $antrag->antragstyp_id  = $this->typId;
        $antrag->name_dn        = 'Max Mustermann (12-34)';
        $antrag->dienstgrad     = 'Brandmeister';
        $antrag->discordid      = 'mustermann#1234';
        $antrag->cirs_status    = Form::STATUS_IN_PROGRESS;
        $antrag->save();
        $this->cleanupAntragIds[] = $antrag->id;

        $loaded = Form::with('typ')->find($antrag->id);
        $this->assertSame($this->typId, $loaded->typ->id);
        $this->assertTrue($loaded->isOpen());
        $this->assertSame('In Bearbeitung', $loaded->statusLabel());
    }

    #[Test]
    public function status_constants_match_legacy_values(): void
    {
        $this->assertSame(0, Form::STATUS_IN_PROGRESS);
        $this->assertSame(1, Form::STATUS_REJECTED);
        $this->assertSame(2, Form::STATUS_DEFERRED);
        $this->assertSame(3, Form::STATUS_ACCEPTED);
    }

    #[Test]
    public function field_select_options_are_parsed_from_newline_string(): void
    {
        $field = new FormField();
        $field->antragstyp_id = $this->typId;
        $field->feldname      = 'auswahl';
        $field->label         = 'Auswahl';
        $field->feldtyp       = 'select';
        $field->optionen      = "Option A\nOption B\n\nOption C\n";
        $field->pflichtfeld   = false;
        $field->sortierung    = 1;
        $field->breite        = 'full';
        $field->readonly      = false;
        $field->save();

        $loaded = FormField::find($field->id);
        $this->assertSame(['Option A', 'Option B', 'Option C'], $loaded->selectOptions());
    }

    #[Test]
    public function non_select_field_returns_empty_options(): void
    {
        $field = new FormField();
        $field->antragstyp_id = $this->typId;
        $field->feldname      = 'text_feld';
        $field->label         = 'Text';
        $field->feldtyp       = 'text';
        $field->pflichtfeld   = false;
        $field->sortierung    = 1;
        $field->breite        = 'full';
        $field->readonly      = false;
        $field->save();

        $this->assertSame([], FormField::find($field->id)->selectOptions());
    }
}
