<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Der Medikamentenstamm (eNOTF, MedikamenteController) sucht, sortiert,
 * filtert und blättert auf dem Server und steht in der Hülle der übrigen
 * Einstellungen. Die Formulare laufen über MedikamentSaveRequest.
 */
final class EnotfMedicationSettingsTest extends FeatureTestCase
{
    private const LIST = '/settings/medications/index';

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
        $this->prefix = 'MedTest ' . uniqid() . ' ';
    }

    /** @param array<string,mixed> $fields */
    private function medikament(string $wirkstoff, array $fields = []): int
    {
        return (int) Capsule::table('intra_edivi_medikamente')->insertGetId($fields + [
            'wirkstoff' => $this->prefix . $wirkstoff, 'priority' => 0, 'active' => 1,
        ]);
    }

    private function pos(string $body, string $needle): int
    {
        $pos = strpos($body, $needle);
        $this->assertNotFalse($pos, "'$needle' fehlt in der Antwort.");

        return $pos;
    }

    /** @return array<string,mixed>|null */
    private function row(string $wirkstoff): ?array
    {
        $row = Capsule::table('intra_edivi_medikamente')->where('wirkstoff', $this->prefix . $wirkstoff)->first();

        return $row === null ? null : (array) $row;
    }

    #[Test]
    public function suche_sortierung_und_aktiv_filter(): void
    {
        $this->medikament('Zulu', ['priority' => 1, 'herstellername' => 'Herstellerfirma']);
        $this->medikament('Alpha', ['priority' => 9, 'active' => 0, 'dosierungen' => '5 mg,10 mg']);

        $default = $this->get(self::LIST, ['query' => ['q' => $this->prefix]]);
        $this->assertOk($default);
        $this->assertBodyNotContains('DataTable(', $default);
        $this->assertBodyContains('class="ignis-app"', $default);
        $this->assertBodyContains('<p class="twplus-page-header__eyebrow">eNOTF</p>', $default);
        $this->assertBodyContains('aria-sort="ascending"><a class="ignis-table__sort is-asc"', $default);
        $this->assertBodyContains('5 mg, 10 mg', $default);
        $this->assertLessThan($this->pos($default->body, $this->prefix . 'Zulu'), $this->pos($default->body, $this->prefix . 'Alpha'));
        $this->assertBodyContains('1 bis 2 von 2 Medikamenten', $default);

        $byPriority = $this->get(self::LIST, ['query' => ['q' => $this->prefix, 'sort' => 'priority']]);
        $this->assertLessThan($this->pos($byPriority->body, $this->prefix . 'Alpha'), $this->pos($byPriority->body, $this->prefix . 'Zulu'));

        $inactive = $this->get(self::LIST, ['query' => ['q' => $this->prefix, 'active' => '0']]);
        $this->assertBodyContains($this->prefix . 'Alpha', $inactive);
        $this->assertBodyNotContains($this->prefix . 'Zulu', $inactive);
        // Die Segmente zählen mit der Suche, aber ohne den eigenen Filter.
        $this->assertMatchesRegularExpression('~is-active" aria-current="true">Inaktiv <span class="ignis-segmented__count">1</span>~', $inactive->body);
        $this->assertBodyContains('Alle <span class="ignis-segmented__count">2</span>', $inactive);

        $byMaker = $this->get(self::LIST, ['query' => ['q' => 'Herstellerfirma']]);
        $this->assertBodyContains($this->prefix . 'Zulu', $byMaker);
        $this->assertBodyNotContains($this->prefix . 'Alpha', $byMaker);

        $none = $this->get(self::LIST, ['query' => ['q' => $this->prefix . 'nichts']]);
        $this->assertBodyContains('Keine Medikamente gefunden', $none);
    }

    #[Test]
    public function seite_zwei(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->medikament(sprintf('Seite %02d', $i));
        }

        $first = $this->get(self::LIST, ['query' => ['q' => $this->prefix]]);
        $this->assertBodyContains($this->prefix . 'Seite 25', $first);
        $this->assertBodyNotContains($this->prefix . 'Seite 26', $first);
        $this->assertBodyContains('1 bis 25 von 26 Medikamenten', $first);

        $second = $this->get(self::LIST, ['query' => ['q' => $this->prefix, 'page' => '2']]);
        $this->assertBodyContains($this->prefix . 'Seite 26', $second);
        $this->assertBodyNotContains($this->prefix . 'Seite 25', $second);
    }

    #[Test]
    public function anlegen_aendern_loeschen(): void
    {
        $created = $this->post('/settings/medications/create', [
            'wirkstoff'      => '  ' . $this->prefix . 'Neu  ',
            'herstellername' => '',
            'dosierungen'    => ' 1 mg,2 mg ',
            'priority'       => '3',
            'active'         => 'on',
        ]);
        $this->assertRedirect($created, self::LIST);
        $row = $this->row('Neu');
        $this->assertNotNull($row);
        $this->assertNull($row['herstellername']);
        $this->assertSame('1 mg,2 mg', $row['dosierungen']);
        $this->assertSame(3, (int) $row['priority']);
        $this->assertSame(1, (int) $row['active']);

        $updated = $this->post('/settings/medications/update', [
            'id'             => (string) $row['id'],
            'wirkstoff'      => $this->prefix . 'Neu',
            'herstellername' => 'Hersteller',
            'dosierungen'    => '',
            'priority'       => '-2',
        ]);
        $this->assertRedirect($updated, self::LIST);
        $row = $this->row('Neu');
        $this->assertNotNull($row);
        $this->assertSame('Hersteller', $row['herstellername']);
        $this->assertNull($row['dosierungen']);
        $this->assertSame(-2, (int) $row['priority']);
        $this->assertSame(0, (int) $row['active'], 'Ohne Kästchen im Post ist das Medikament inaktiv.');

        $deleted = $this->post('/settings/medications/delete', ['id' => (string) $row['id']]);
        $this->assertRedirect($deleted, self::LIST);
        $this->assertNull($this->row('Neu'));
    }

    #[Test]
    public function ungueltige_eingaben_werden_abgewiesen(): void
    {
        $this->assertRedirect($this->post('/settings/medications/create', ['wirkstoff' => '   ']), self::LIST);
        $this->assertSame('Der Wirkstoff ist Pflicht und darf höchstens 255 Zeichen lang sein.', $_SESSION['flash']['text'] ?? null);

        $this->post('/settings/medications/create', ['wirkstoff' => $this->prefix . 'Prio', 'priority' => 'abc']);
        $this->assertSame('Die Priorität muss eine Zahl sein.', $_SESSION['flash']['text'] ?? null);
        $this->assertNull($this->row('Prio'));

        $this->post('/settings/medications/create', ['wirkstoff' => $this->prefix . 'Fremd', 'fremd' => '1']);
        $this->assertSame('Das Formular enthält unbekannte Felder.', $_SESSION['flash']['text'] ?? null);
        $this->assertNull($this->row('Fremd'));

        $this->post('/settings/medications/create', ['wirkstoff' => $this->prefix . str_repeat('x', 300)]);
        $this->assertSame('Der Wirkstoff ist Pflicht und darf höchstens 255 Zeichen lang sein.', $_SESSION['flash']['text'] ?? null);

        $this->post('/settings/medications/update', ['id' => '0', 'wirkstoff' => $this->prefix . 'Anders']);
        $this->assertNull($this->row('Anders'));

        $this->medikament('Bleibt');
        $this->post('/settings/medications/delete', ['id' => 'abc']);
        $this->assertSame('Ungültige ID.', $_SESSION['flash']['text'] ?? null);
        $this->assertNotNull($this->row('Bleibt'));
    }
}
