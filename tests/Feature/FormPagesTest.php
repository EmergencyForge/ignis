<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AmbSkill;
use App\Models\FdSkill;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormType;
use App\Models\Personnel;
use App\Models\Rank;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Antragsseiten auf den ignis-Bausteinen: Antrag stellen als
 * Formularkarte, die Detailansichten nach dem Detailmuster mit Status-Chip
 * im Titel, die Antragstypen als ignis-Tabelle mit Zeilenaktionen, Anlegen
 * und Bearbeiten eines Typs als Formularkarten.
 */
final class FormPagesTest extends FeatureTestCase
{
    private FormType $typ;
    private string $discordId = '';

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->discordId = (string) $user->discord_id;
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username, 'discordtag' => $this->discordId]);

        $this->typ = new FormType();
        $this->typ->name         = 'Urlaubsantrag';
        $this->typ->beschreibung = 'Freie Tage beantragen';
        $this->typ->aktiv        = true;
        $this->typ->save();

        foreach ([['von', 'Urlaub von', 'date', 'half', 1], ['grund', 'Grund', 'textarea', 'full', 0], ['vertretung', 'Vertretung geklärt', 'checkbox', 'half', 0]] as $i => [$name, $label, $type, $breite, $required]) {
            $feld = new FormField();
            $feld->antragstyp_id = $this->typ->id;
            $feld->feldname      = $name;
            $feld->label         = $label;
            $feld->feldtyp       = $type;
            $feld->breite        = $breite;
            $feld->pflichtfeld   = (bool) $required;
            $feld->sortierung    = $i;
            $feld->save();
        }
    }

    private function antrag(int $status): Form
    {
        $antrag = new Form();
        $antrag->uniqueid      = 'F' . random_int(1000000, 9999999);
        $antrag->antragstyp_id = $this->typ->id;
        $antrag->discordid     = $this->discordId;
        $antrag->name_dn       = 'Frida Formular (F-1)';
        $antrag->dienstgrad    = 'Brandmeisterin';
        $antrag->cirs_status   = $status;
        $antrag->cirs_text     = "Bitte Vertretung\nnachreichen.";
        $antrag->time_added    = new \DateTime('2026-03-01 10:00:00');
        $antrag->save();

        return $antrag;
    }

    private function mitarbeiter(): void
    {
        $rank = new Rank();
        $rank->name = 'Rang_' . uniqid(); $rank->name_m = $rank->name; $rank->name_w = $rank->name; $rank->priority = 10; $rank->archive = false;
        $rank->save();
        $rd = new AmbSkill();
        $rd->name = 'Keine_' . uniqid(); $rd->name_m = $rd->name; $rd->name_w = $rd->name; $rd->priority = 0; $rd->none = true;
        $rd->save();
        $fw = new FdSkill();
        $fw->name = 'Keine_' . uniqid(); $fw->shortname = 'KE'; $fw->name_m = $fw->name; $fw->name_w = $fw->name; $fw->priority = 0; $fw->none = true;
        $fw->save();

        $m = new Personnel();
        $m->fullname = 'Frida Formular'; $m->dienstnr = 'F-1'; $m->gebdatum = new \DateTime('1990-01-01'); $m->geschlecht = 1;
        $m->einstdatum = new \DateTime('2024-01-01'); $m->dienstgrad = $rank->id; $m->qualird = $rd->id; $m->qualifw2 = $fw->id;
        $m->discordtag = $this->discordId;
        $m->save();
    }

    #[Test]
    public function antrag_stellen_ist_eine_formularkarte(): void
    {
        $this->mitarbeiter();

        $page = $this->get('/forms/create', ['query' => ['typ' => (string) $this->typ->id]]);

        $this->assertOk($page);
        $this->assertBodyContains('<title>Urlaubsantrag stellen', $page);
        $this->assertBodyContains('class="ignis-card ignis-form-card" data-ignis-form="antrag-create"', $page);
        $this->assertMatchesRegularExpression('~<input\s+type="date"\s+class="ignis-input"\s+id="von"\s+name="von"~', $page->body);
        $this->assertBodyContains('<label class="ignis-checkbox" for="vertretung">', $page);
        $this->assertBodyContains('<span class="ignis-field__required">*</span>', $page);
        $this->assertBodyContains('name="submit_antrag" class="ignis-btn ignis-btn--primary"', $page);
        $this->assertBodyNotContains('form-select', $page);
    }

    #[Test]
    public function detailansicht_und_bearbeitung_nach_dem_detailmuster(): void
    {
        $antrag = $this->antrag(Form::STATUS_DEFERRED);

        $view = $this->get('/forms/view', ['query' => ['antrag' => $antrag->uniqueid]]);
        $this->assertOk($view);
        $this->assertBodyContains('<h1 class="ignis-detail__title">Urlaubsantrag <span class="ignis-chip ignis-chip--dot ignis-chip--warn">Aufgeschoben</span></h1>', $view);
        $this->assertBodyContains('<div class="ignis-detail">', $view);
        $this->assertBodyContains('<dl class="ignis-detail__dl">', $view);
        $this->assertBodyContains('<dd class="whitespace-pre-line">Bitte Vertretung', $view);
        $this->assertBodyContains('href="/forms/admin/view?antrag=' . $antrag->uniqueid . '" class="ignis-btn ignis-btn--primary"', $view);
        $this->assertBodyNotContains('form-control-plaintext', $view);
        $this->assertBodyNotContains('.field-value', $view);

        $admin = $this->get('/forms/admin/view', ['query' => ['antrag' => $antrag->uniqueid]]);
        $this->assertOk($admin);
        $this->assertBodyContains('<form method="post" class="ignis-detail">', $admin);
        $this->assertBodyContains('<select class="ignis-input" id="cirs_status" name="cirs_status" required>', $admin);
        $this->assertBodyContains('name="save" class="ignis-btn ignis-btn--primary"', $admin);
        $this->assertBodyContains('<span class="ignis-mono">#' . $antrag->uniqueid . '</span>', $admin);
        $this->assertBodyNotContains('form-select', $admin);
    }

    #[Test]
    public function antragstypen_liste_anlegen_und_bearbeiten(): void
    {
        $list = $this->get('/settings/forms/list');
        $this->assertOk($list);
        $this->assertBodyContains('<table class="ignis-table" id="table-antragstypen">', $list);
        $this->assertBodyContains('name="sortierung[' . $this->typ->id . ']"', $list);
        $this->assertBodyContains('<span class="ignis-chip ignis-chip--dot ignis-chip--ok">Aktiv</span>', $list);
        $this->assertBodyContains('href="?delete=' . $this->typ->id . '" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon"', $list);
        $this->assertBodyContains('name="update_sortierung" class="ignis-btn ignis-btn--sm ignis-btn--secondary"', $list);
        $this->assertBodyNotContains('btn-group', $list);

        $create = $this->get('/settings/forms/create');
        $this->assertOk($create);
        $this->assertBodyContains('data-ignis-form="antragstyp-create"', $create);
        $this->assertBodyContains('<i id="icon-preview" class="fa-solid fa-file-lines"></i>', $create);
        $this->assertBodyNotContains('input-group', $create);

        $edit = $this->get('/settings/forms/edit', ['query' => ['id' => (string) $this->typ->id]]);
        $this->assertOk($edit);
        $this->assertBodyContains('<title>Antragstyp bearbeiten', $edit);
        $this->assertBodyContains('<table class="ignis-table" id="table-antragsfelder">', $edit);
        $this->assertBodyContains('<code class="ignis-mono">von</code>', $edit);
        $this->assertBodyContains('<span class="ignis-chip ignis-chip--warn">', $edit);
        $this->assertBodyContains('<select class="ignis-input" id="feldtyp" name="feldtyp" required>', $edit);
        $this->assertBodyNotContains('form-select', $edit);
        $this->assertBodyNotContains('table-hover', $edit);
    }
}
