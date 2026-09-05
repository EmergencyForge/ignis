<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Security\CsrfProtection;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die QM-Liste der Einsatzprotokolle (fireTab, FiretabController::adminList)
 * sortiert, sucht und blättert auf dem Server statt über DataTables, und
 * angehakte Zeilen lassen sich über die Aktionsleiste des Arbeitsbereichs
 * löschen (archiviert mit Status ausgeblendet, wie das Löschen nach leeren
 * Feldern), hinter Recht und CSRF-Token.
 */
final class FiretabAdminListTest extends FeatureTestCase
{
    private const LIST = '/firetab/admin/list';

    private int $userId = 0;

    /**
     * @param list<string> $permissions
     */
    private function login(array $permissions): int
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username]);

        return $this->userId = $user->id;
    }

    private function incident(string $number, string $location, string $keyword, int $hoursAgo, int $archived = 0, int $status = 0): int
    {
        return (int) Capsule::table('intra_fire_incidents')->insertGetId([
            'incident_number' => $number,
            'location'        => $location,
            'keyword'         => $keyword,
            'started_at'      => date('Y-m-d H:i:s', strtotime("-$hoursAgo hours")),
            'created_at'      => date('Y-m-d H:i:s', strtotime("-$hoursAgo hours")),
            'status'          => $status,
            'finalized'       => 1,
            'archived'        => $archived,
            'archived_at'     => $archived ? date('Y-m-d H:i:s') : null,
            'created_by'      => $this->userId,
        ]);
    }

    private function pos(string $body, string $needle): int
    {
        $pos = strpos($body, $needle);
        $this->assertNotFalse($pos, "'$needle' fehlt in der Antwort.");

        return $pos;
    }

    #[Test]
    public function liste_sortiert_sucht_und_blaettert_auf_dem_server(): void
    {
        $this->login(['fire.incident.qm']);
        $stamp = uniqid();
        for ($i = 1; $i <= 22; $i++) {
            $this->incident("QM-$stamp-" . sprintf('%02d', $i), "Liststraße $i", $i === 3 ? "B3 Wohnhaus $stamp" : "TH1 Ölspur $stamp", $i);
        }
        $this->incident("QM-$stamp-ARCH", 'Archivweg 1', "Archiv $stamp", 1, 1);

        $page = $this->get(self::LIST, ['query' => ['q' => "QM-$stamp"]]);
        $this->assertOk($page);
        $this->assertBodyNotContains('DataTable(', $page);
        $this->assertBodyContains('data-ignis-workbench', $page);
        $this->assertBodyContains('1–20 von 22 Protokolle', $page);
        // Neueste zuerst: 01 liegt eine Stunde zurück, 02 zwei.
        $this->assertLessThan($this->pos($page->body, "QM-$stamp-02"), $this->pos($page->body, "QM-$stamp-01"));
        $this->assertBodyNotContains("QM-$stamp-21", $page);
        $this->assertBodyNotContains("QM-$stamp-ARCH", $page);
        $this->assertBodyContains('<a class="ignis-table__sort" href="/firetab/admin/list?q=QM-' . $stamp . '&amp;sort=keyword&amp;dir=asc">Stichwort', $page);

        $second = $this->get(self::LIST, ['query' => ['q' => "QM-$stamp", 'page' => '2']]);
        $this->assertBodyContains("QM-$stamp-21", $second);
        $this->assertBodyContains("QM-$stamp-22", $second);
        $this->assertBodyNotContains("QM-$stamp-01", $second);

        $byKeyword = $this->get(self::LIST, ['query' => ['q' => "QM-$stamp", 'sort' => 'keyword', 'dir' => 'asc']]);
        $this->assertBodyContains('aria-sort="ascending"><a class="ignis-table__sort is-asc"', $byKeyword);
        $this->assertLessThan($this->pos($byKeyword->body, "QM-$stamp-01"), $this->pos($byKeyword->body, "QM-$stamp-03"));

        $search = $this->get(self::LIST, ['query' => ['q' => "B3 Wohnhaus $stamp"]]);
        $this->assertBodyContains("QM-$stamp-03", $search);
        $this->assertBodyNotContains("QM-$stamp-01", $search);
        $this->assertBodyContains('1–1 von 1 Protokolle', $search);

        $archive = $this->get(self::LIST, ['query' => ['show_archived' => '1', 'q' => "QM-$stamp"]]);
        $this->assertBodyContains("QM-$stamp-ARCH", $archive);
        $this->assertBodyNotContains("QM-$stamp-01", $archive);
        $this->assertBodyNotContains('data-ignis-bulkbar', $archive);
        $this->assertBodyContains('unarchive_incident', $archive);
    }

    #[Test]
    public function zeilen_tragen_haken_und_die_leiste_loescht_die_auswahl(): void
    {
        $this->login(['fire.incident.qm']);
        $stamp = uniqid();
        $a = $this->incident("DEL-$stamp-A", 'Weg 1', 'TH1', 1);
        $b = $this->incident("DEL-$stamp-B", 'Weg 2', 'TH1', 2);
        $stays = $this->incident("DEL-$stamp-C", 'Weg 3', 'TH1', 3);

        $page = $this->get(self::LIST, ['query' => ['q' => "DEL-$stamp"]]);
        $this->assertBodyContains('action="/firetab/admin/list/delete" class="ignis-bulkbar" data-ignis-bulkbar hidden', $page);
        $this->assertBodyContains('data-ignis-select-all', $page);
        $this->assertBodyContains('data-ignis-select value="' . $a . '"', $page);
        $this->assertBodyContains('<tr data-ignis-row="' . $a . '" data-href="/firetab/view?id=' . $a . '" tabindex="0">', $page);

        $response = $this->post('/firetab/admin/list/delete', ['ids' => [(string) $a, (string) $b, '999999999'], 'csrf_token' => CsrfProtection::getToken()]);
        $this->assertRedirect($response, self::LIST);
        foreach ([$a, $b] as $id) {
            $row = (array) Capsule::table('intra_fire_incidents')->where('id', $id)->first();
            $this->assertSame(1, (int) $row['archived']);
            $this->assertSame(4, (int) $row['status']);
            $this->assertSame($this->userId, (int) $row['archived_by']);
            $this->assertSame(1, Capsule::table('intra_audit_log')->where('user', $this->userId)->where('module', 'Feuerwehr')->where('action', 'Einsatz gelöscht [ID: ' . $id . ']')->count());
        }
        $this->assertSame(0, (int) Capsule::table('intra_fire_incidents')->where('id', $stays)->value('archived'));
        $this->assertStringContainsString('2 Einsatzprotokolle gelöscht.', (string) ($_SESSION['flash']['text'] ?? ''));

        // Nur noch im Archiv.
        $this->assertBodyNotContains("DEL-$stamp-A", $this->get(self::LIST, ['query' => ['q' => "DEL-$stamp"]]));
        $this->assertBodyContains("DEL-$stamp-A", $this->get(self::LIST, ['query' => ['q' => "DEL-$stamp", 'show_archived' => '1']]));
    }

    #[Test]
    public function loeschen_braucht_recht_und_csrf(): void
    {
        $this->login(['fire.incident.qm']);
        $id = $this->incident('KEEP-' . uniqid(), 'Weg 1', 'TH1', 1);

        $noToken = $this->post('/firetab/admin/list/delete', ['ids' => [(string) $id], 'csrf_token' => 'falsch']);
        $this->assertSame(403, $noToken->status);
        $this->assertSame(0, (int) Capsule::table('intra_fire_incidents')->where('id', $id)->value('archived'));

        $this->login(['personnel.view']);
        $denied = $this->post('/firetab/admin/list/delete', ['ids' => [(string) $id], 'csrf_token' => CsrfProtection::getToken()]);
        $this->assertRedirect($denied);
        $this->assertSame(0, (int) Capsule::table('intra_fire_incidents')->where('id', $id)->value('archived'));
        $this->assertRedirect($this->get(self::LIST));
    }

    /**
     * Ein Einsatz im Verbund-Cache, wie ihn FederationSyncService ablegt.
     */
    private function federatedIncident(string $instance, string $name, bool $active, string $number, string $location, string $keyword, int $hoursAgo): void
    {
        if (Capsule::table('intra_federation_links')->where('instance_id', $instance)->doesntExist()) {
            Capsule::table('intra_federation_links')->insert([
                'instance_id'      => $instance,
                'instance_name'    => $name,
                'instance_url'     => 'https://' . $instance . '.example',
                'api_key_outgoing' => bin2hex(random_bytes(16)),
                'api_key_incoming' => bin2hex(random_bytes(16)),
                'consume_fire'     => 1,
                'is_active'        => $active ? 1 : 0,
            ]);
        }
        $created = date('Y-m-d H:i:s', strtotime("-$hoursAgo hours"));
        Capsule::table('intra_federation_cache_fire')->insert([
            'source_instance_id' => $instance,
            'remote_id'          => random_int(1000, 999999),
            'incident_number'    => $number,
            'cached_data'        => json_encode([
                'incident_number' => $number, 'location' => $location, 'keyword' => $keyword,
                'status' => 2, 'finalized' => 1, 'leader_id' => 7, 'leader_name' => 'Nachbar Leiter',
                'created_at' => $created, 'updated_at' => $created,
            ], JSON_UNESCAPED_UNICODE),
            'incident_date'      => $created,
            'cached_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Die QM-Liste liest den Verbund-Schalter live aus intra_config (die
     * Konstante FEDERATION_ENABLED steht einmal pro Prozess fest und ist
     * im Testlauf schon false). Der Schalter geht am Ende wieder zurück.
     */
    #[Test]
    public function verbund_einsaetze_stehen_in_suche_sortierung_und_seiten(): void
    {
        $before = Capsule::table('intra_config')->where('config_key', 'FEDERATION_ENABLED')->value('config_value');
        Capsule::table('intra_config')->updateOrInsert(['config_key' => 'FEDERATION_ENABLED'], ['config_value' => 'true', 'config_type' => 'boolean']);
        try {
            $this->verbundListe();
        } finally {
            Capsule::table('intra_config')->where('config_key', 'FEDERATION_ENABLED')->update(['config_value' => $before ?? 'false']);
        }
    }

    private function verbundListe(): void
    {
        $this->login(['fire.incident.qm']);
        $stamp = uniqid();
        $local = $this->incident("VB-$stamp-L", "Verbundplatz $stamp", 'TH1 Ölspur', 1);
        $this->federatedIncident('inst-' . $stamp, 'Nachbarwache', true, "VB-$stamp-F", "Verbundplatz $stamp", "B2 Scheune $stamp", 5);
        $this->federatedIncident('inst-off-' . $stamp, 'Stille Wache', false, "VB-$stamp-X", "Verbundplatz $stamp", 'B1 Papierkorb', 6);

        // Das Suchwort trifft den Verbund-Einsatz über den Ort …
        $page = $this->get(self::LIST, ['query' => ['q' => "Verbundplatz $stamp"]]);
        $this->assertOk($page);
        $this->assertBodyContains('1–2 von 2 Protokolle', $page);
        $this->assertBodyContains("VB-$stamp-F <span class=\"ignis-chip ignis-chip--secondary\">Nachbarwache</span>", $page);
        $this->assertBodyContains('<span class="ignis-list-meta">nur lesen</span>', $page);
        $this->assertBodyContains('Nachbar Leiter', $page);
        $this->assertBodyContains('data-ignis-select value="' . $local . '"', $page);
        $this->assertBodyNotContains("VB-$stamp-X", $page);
        // … und über das Stichwort, auch ohne eigenen Treffer.
        $byKeyword = $this->get(self::LIST, ['query' => ['q' => "B2 Scheune $stamp"]]);
        $this->assertBodyContains('1–1 von 1 Protokolle', $byKeyword);
        $this->assertBodyContains("VB-$stamp-F", $byKeyword);
        $this->assertBodyNotContains("VB-$stamp-L", $byKeyword);

        // Sortiert wie die eigenen: nach Nummer aufsteigend steht F vor L.
        $sorted = $this->get(self::LIST, ['query' => ['q' => "VB-$stamp", 'sort' => 'nr', 'dir' => 'asc']]);
        $this->assertLessThan($this->pos($sorted->body, "VB-$stamp-L"), $this->pos($sorted->body, "VB-$stamp-F"));

        // Im Archiv gibt es keinen Verbund.
        $this->assertBodyNotContains("VB-$stamp-F", $this->get(self::LIST, ['query' => ['q' => "VB-$stamp", 'show_archived' => '1']]));
    }
}
