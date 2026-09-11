<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AmbSkill;
use App\Models\FdSkill;
use App\Models\Personnel;
use App\Models\Rank;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Personalakte (PersonnelController::show) rendert die Dokumente als
 * ignis-Tabelle: archivierte Zeilen versteckt mit Kästchen zum Einblenden,
 * Zeilenaktionen nur für personnel.documents.manage; der Fachdienst-Dialog
 * bringt seine Tabelle als Vorlage mit.
 */
final class PersonnelProfileTest extends FeatureTestCase
{
    private Personnel $person;

    protected function setUp(): void
    {
        parent::setUp();

        $rank = new Rank();
        $rank->name     = 'Rang_' . uniqid();
        $rank->name_m   = $rank->name;
        $rank->name_w   = $rank->name;
        $rank->priority = 10;
        $rank->archive  = false;
        $rank->save();

        $rd = new AmbSkill();
        $rd->name     = 'Keine_' . uniqid();
        $rd->name_m   = $rd->name;
        $rd->name_w   = $rd->name;
        $rd->priority = 0;
        $rd->none     = true;
        $rd->save();

        $fw = new FdSkill();
        $fw->name      = 'Keine_' . uniqid();
        $fw->shortname = 'KE';
        $fw->name_m    = $fw->name;
        $fw->name_w    = $fw->name;
        $fw->priority  = 0;
        $fw->none      = true;
        $fw->save();

        $this->person = new Personnel();
        $this->person->fullname   = 'Paula Profil';
        $this->person->dienstnr   = 'P-100';
        $this->person->gebdatum   = new \DateTime('1990-01-01');
        $this->person->geschlecht = 1;
        $this->person->einstdatum = new \DateTime('2024-01-01');
        $this->person->dienstgrad = $rank->id;
        $this->person->qualird    = $rd->id;
        $this->person->qualifw2   = $fw->id;
        $this->person->save();
    }

    /**
     * @param list<string> $permissions
     */
    private function login(array $permissions): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username, 'discordtag' => (string) $user->discord_id]);
    }

    private function dokument(int $docid, bool $archived): void
    {
        Capsule::table('intra_mitarbeiter_dokumente')->insert([
            'docid'             => $docid,
            'profileid'         => $this->person->id,
            'ausstellerid'      => '1',
            'aussteller_name'   => 'Ausstellerin',
            'ausstellungsdatum' => '2026-01-15',
            'type'              => 1,
            'is_archived'       => $archived ? 1 : 0,
        ]);
    }

    #[Test]
    public function dokumente_als_tabelle_mit_archiv_und_zeilenaktionen(): void
    {
        $this->login(['full_admin']);
        $this->dokument(900001, false);
        $this->dokument(900002, true);

        $page = $this->get('/personnel/profile', ['query' => ['id' => (string) $this->person->id]]);

        $this->assertOk($page);
        $this->assertBodyContains('Paula Profil', $page);
        $this->assertBodyContains('<table class="ignis-table" id="documentTable">', $page);
        $this->assertBodyContains('id="chk-show-archived"', $page);
        $this->assertBodyContains('<tr class="doc-archived is-muted" hidden>', $page);
        $this->assertBodyContains('<span class="ignis-mono">900002</span>', $page);
        $this->assertBodyContains('onclick="openDocumentViewer(\'900001\')"', $page);
        $this->assertBodyContains('onclick="confirmArchiveDoc(\'900001\', true)"', $page);
        $this->assertBodyContains('onclick="confirmArchiveDoc(\'900002\', false)"', $page);
        $this->assertBodyContains('<template id="fdqualiFormTemplate">', $page);
        $this->assertBodyNotContains('table-striped', $page);
    }

    #[Test]
    public function ohne_dokumentrecht_nur_ansehen(): void
    {
        $this->login(['personnel.view']);
        $this->dokument(900003, false);

        $page = $this->get('/personnel/profile', ['query' => ['id' => (string) $this->person->id]]);

        $this->assertOk($page);
        $this->assertBodyContains('onclick="openDocumentViewer(\'900003\')"', $page);
        $this->assertBodyNotContains('id="chk-show-archived"', $page);
        $this->assertBodyNotContains('onclick="confirmArchiveDoc(', $page);
    }
}
