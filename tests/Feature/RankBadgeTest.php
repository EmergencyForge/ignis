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
 * Dienstgradabzeichen: der Pfad aus intra_mitarbeiter_dienstgrade.badge
 * wird zur URL mit BASE_PATH (ein Pfad ohne führenden Schrägstrich zeigte
 * vorher relativ zur Seite ins Leere), steht in einer festen Box vor dem
 * Namen und als data-image an jeder Dienstgrad-Auswahl für das Dropdown.
 */
final class RankBadgeTest extends FeatureTestCase
{
    private const BADGE = 'assets/img/dienstgrade/bf/1.png';

    private Rank $rank;
    private Personnel $person;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user(['full_admin' => true]);
        $this->userId = (int) $user->id;
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username, 'discordtag' => (string) $user->discord_id]);

        $this->rank = new Rank();
        $this->rank->name     = 'Brandmeister <BM> ' . uniqid();
        $this->rank->name_m   = 'Brandmeister';
        $this->rank->name_w   = 'Brandmeisterin';
        $this->rank->badge    = self::BADGE;
        $this->rank->priority = 10;
        $this->rank->archive  = false;
        $this->rank->save();

        $rd = new AmbSkill();
        $rd->name = 'Keine <RD> ' . uniqid(); $rd->name_m = $rd->name; $rd->name_w = $rd->name; $rd->priority = 0; $rd->none = true;
        $rd->save();
        $fw = new FdSkill();
        $fw->name = 'Keine <FW> ' . uniqid(); $fw->shortname = 'KE'; $fw->name_m = $fw->name; $fw->name_w = $fw->name; $fw->priority = 0; $fw->none = true;
        $fw->save();

        $this->person = new Personnel();
        $this->person->fullname   = 'Berta Abzeichen';
        $this->person->dienstnr   = 'AB-' . random_int(100, 999);
        $this->person->gebdatum   = new \DateTime('1990-01-01');
        $this->person->geschlecht = 1;
        $this->person->einstdatum = new \DateTime('2024-01-01');
        $this->person->dienstgrad = $this->rank->id;
        $this->person->qualird    = $rd->id;
        $this->person->qualifw2   = $fw->id;
        $this->person->save();
    }

    private function badgeSrc(): string
    {
        return 'src="' . rtrim(BASE_PATH, '/') . '/' . self::BADGE . '?v=';
    }

    private function dataImage(): string
    {
        return 'data-image="' . rtrim(BASE_PATH, '/') . '/' . self::BADGE . '?v=';
    }

    #[Test]
    public function profilkopf_karte_und_auswahl_zeigen_das_abzeichen(): void
    {
        $page = $this->get('/personnel/profile', ['query' => ['id' => (string) $this->person->id]]);

        $this->assertOk($page);
        $this->assertSame(2, substr_count($page->body, $this->badgeSrc()), 'Abzeichen im Kopf und in der Profilkarte.');
        $this->assertMatchesRegularExpression('~ · <img ' . preg_quote($this->badgeSrc(), '~') . '\d+" alt="" loading="lazy" style="width:36px;height:16px;[^"]*">Brandmeisterin</p>~', $page->body);
        $this->assertBodyNotContains('alt="Dienstgrad"', $page);

        $this->assertBodyContains('<label class="twplus-form-section__label" for="dienstgrad">Dienstgrad</label>', $page);
        $this->assertBodyContains('<select class="ignis-input" data-custom-dropdown="true" name="dienstgrad" id="dienstgrad">', $page);
        $this->assertBodyContains('<option value="' . $this->rank->id . '" selected ' . $this->dataImage(), $page);
        $this->assertBodyContains('<select class="ignis-input" data-custom-dropdown="true" name="qualird" id="qualird">', $page);
        $this->assertBodyContains('<select class="ignis-input" data-custom-dropdown="true" name="qualifw2" id="qualifw2">', $page);

        // Die Namen kommen von Admins und standen in den Auswahlen roh im Markup.
        $this->assertBodyContains('>Brandmeister &lt;BM&gt; ', $page);
        $this->assertBodyNotContains('<BM>', $page);
        $this->assertBodyNotContains('<RD>', $page);
        $this->assertBodyNotContains('<FW>', $page);
    }

    #[Test]
    public function personalliste_und_filter_zeigen_das_abzeichen(): void
    {
        $page = $this->get('/personnel/list', ['query' => ['q' => $this->person->dienstnr]]);

        $this->assertOk($page);
        $this->assertBodyContains('<select class="ignis-input" data-custom-dropdown="true" name="dg" id="filterDienstgrad">', $page);
        $this->assertBodyContains('<option value="' . $this->rank->id . '" ' . $this->dataImage(), $page);
        $this->assertMatchesRegularExpression('~<img ' . preg_quote($this->badgeSrc(), '~') . '\d+" alt="" [^>]*>Brandmeisterin~', $page->body);
    }

    #[Test]
    public function anlegen_bietet_den_dienstgrad_mit_abzeichen_an(): void
    {
        $page = $this->get('/personnel/create');

        $this->assertOk($page);
        $this->assertBodyContains('<select class="ignis-input" data-custom-dropdown="true" name="dienstgrad" id="cm_dienstgrad"', $page);
        $this->assertBodyContains('<option value="' . $this->rank->id . '" ' . $this->dataImage(), $page);
    }

    #[Test]
    public function hover_karte_nennt_den_dienstgrad_mit_abzeichen(): void
    {
        $card = $this->get('/api/personnel/' . $this->person->id . '/card');

        $this->assertOk($card);
        $this->assertBodyContains('<dt>Dienstgrad</dt>', $card);
        $this->assertBodyNotContains('<dt>Rank</dt>', $card);
        $this->assertMatchesRegularExpression('~<dd><img ' . preg_quote($this->badgeSrc(), '~') . '\d+" alt="" [^>]*>Brandmeisterin</dd>~', $card->body);
    }

    #[Test]
    public function verwaltung_zeigt_relative_und_externe_abzeichen(): void
    {
        $extern = new Rank();
        $extern->name = 'Extern ' . uniqid(); $extern->name_m = $extern->name; $extern->name_w = $extern->name;
        $extern->badge = 'https://cdn.example.org/bm.png'; $extern->priority = 11; $extern->archive = false;
        $extern->save();

        $page = $this->get('/settings/personnel/ranks/index');

        $this->assertOk($page);
        $this->assertBodyContains('<td><img ' . $this->badgeSrc(), $page);
        $this->assertBodyContains('<td><img src="https://cdn.example.org/bm.png" alt=""', $page);
        // Der alte Platzhalter schlug einen Ordner vor, den es nicht gibt.
        $this->assertBodyContains('placeholder="assets/img/dienstgrade/bf/1.png"', $page);
        $this->assertBodyNotContains('assets/img/badges/', $page);
    }

    #[Test]
    public function meldungen_der_verwaltung_sind_deutsch(): void
    {
        unset($_SESSION['flash']);

        $this->post('/settings/personnel/ranks/create', ['name' => 'Neu', 'name_m' => 'Neu', 'name_w' => 'Neu', 'priority' => '5']);

        $this->assertSame('Der Dienstgrad wurde erfolgreich erstellt.', $_SESSION['flash']['text'] ?? null);
        $this->assertSame(1, Capsule::table('intra_audit_log')->where('user', $this->userId)->where('module', 'Dienstgrade')->where('action', 'Dienstgrad erstellt')->count());
    }
}
