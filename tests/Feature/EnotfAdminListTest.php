<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die eNOTF-Prüfliste (EnotfAdminController::listAction) sucht, sortiert
 * und blättert auf dem Server statt über DataTables. Die Segmente stehen in
 * `?view=`, „Nicht freigegeben“ zählt wie die Alarmkachel des Dashboards,
 * damit deren Zahl hinter dem Link steht. Verbund-Protokolle stehen nur
 * lesend dabei.
 */
final class EnotfAdminListTest extends FeatureTestCase
{
    private const LIST = '/enotf/admin/list';

    /**
     * @param list<string> $permissions
     */
    private function login(array $permissions = ['full_admin']): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username]);
    }

    /**
     * @param array<string,mixed> $fields
     */
    private function protocol(string $enr, array $fields = []): int
    {
        return (int) Capsule::table('intra_edivi')->insertGetId($fields + [
            'enr' => $enr, 'patname' => 'P', 'pfname' => 'Protokollant', 'protokoll_status' => 2,
            'hidden' => 0, 'hidden_user' => 0, 'freigegeben' => 1, 'sendezeit' => date('Y-m-d H:i:s'),
        ]);
    }

    private function pos(string $body, string $needle): int
    {
        $pos = strpos($body, $needle);
        $this->assertNotFalse($pos, "'$needle' fehlt in der Antwort.");

        return $pos;
    }

    #[Test]
    public function segmente_zaehlen_mit_der_suche_und_filtern(): void
    {
        $this->login();
        $s = 'SEG-' . uniqid();
        $this->protocol("$s-OFFEN", ['protokoll_status' => 0, 'freigegeben' => 0]);
        $this->protocol("$s-PRUEF", ['protokoll_status' => 1]);
        $this->protocol("$s-FREI");
        $this->protocol("$s-GELOESCHT", ['freigegeben' => 0, 'hidden_user' => 1]);
        $this->protocol("$s-HIDDEN", ['protokoll_status' => 0, 'freigegeben' => 0, 'hidden' => 1]);

        $all = $this->get(self::LIST, ['query' => ['q' => $s]]);
        $this->assertOk($all);
        $this->assertBodyNotContains('DataTable(', $all);
        $this->assertBodyContains('Alle <span class="ignis-segmented__count">4</span>', $all);
        $this->assertBodyContains('Unbearbeitet <span class="ignis-segmented__count">2</span>', $all);
        $this->assertBodyContains('Nicht freigegeben <span class="ignis-segmented__count">1</span>', $all);
        $this->assertBodyContains("$s-FREI", $all);
        $this->assertBodyNotContains("$s-HIDDEN", $all);

        $open = $this->get(self::LIST, ['query' => ['q' => $s, 'view' => '2']]);
        $this->assertBodyContains('<a href="/enotf/admin/list?q=' . $s . '&amp;view=2" class="is-active" aria-current="true">', $open);
        $this->assertBodyContains("$s-OFFEN", $open);
        $this->assertBodyNotContains("$s-FREI", $open);
        $this->assertBodyNotContains("$s-GELOESCHT", $open);
        // Suche und Sortierung behalten das Segment.
        $this->assertBodyContains('<input type="hidden" name="view" value="2">', $open);
        $this->assertBodyContains('href="/enotf/admin/list?q=' . $s . '&amp;sort=patient&amp;dir=asc&amp;view=2"', $open);

        $unprocessed = $this->get(self::LIST, ['query' => ['q' => $s, 'view' => '1']]);
        $this->assertBodyContains("$s-OFFEN", $unprocessed);
        $this->assertBodyContains("$s-PRUEF", $unprocessed);
        $this->assertBodyNotContains("$s-FREI", $unprocessed);

        // Ohne Suche dieselbe Zahl wie die Kachel des Dashboards (App\Support\Overview::openProtocols).
        $dashboard = \App\Support\Overview::openProtocols(app(\App\Plugins\PluginLoader::class), new \DateTimeImmutable())['open'] ?? null;
        $this->assertNotNull($dashboard, 'Die Dashboard-Kachel ist für diesen Benutzer nicht sichtbar.');
        $this->assertBodyContains('Nicht freigegeben <span class="ignis-segmented__count">' . $dashboard . '</span>', $this->get(self::LIST, ['query' => ['view' => '2']]));
    }

    #[Test]
    public function liste_sucht_sortiert_und_blaettert_auf_dem_server(): void
    {
        $this->login();
        $s = 'PL-' . uniqid();
        for ($i = 1; $i <= 22; $i++) {
            $this->protocol("$s-" . sprintf('%02d', $i), [
                'patname'   => $i === 3 ? "Erna $s" : 'Max Muster',
                'pfname'    => $i === 5 ? "Schreiber $s" : 'Protokollant',
                'sendezeit' => date('Y-m-d H:i:s', strtotime("-$i hours")),
            ]);
        }

        $page = $this->get(self::LIST, ['query' => ['q' => $s]]);
        $this->assertOk($page);
        $this->assertBodyContains('1 bis 20 von 22 Protokolle', $page);
        // Neueste zuerst: 01 liegt eine Stunde zurück, 02 zwei.
        $this->assertLessThan($this->pos($page->body, "$s-02"), $this->pos($page->body, "$s-01"));
        $this->assertBodyNotContains("$s-21", $page);
        $this->assertBodyContains('<a class="ignis-table__sort" href="/enotf/admin/list?q=' . $s . '&amp;sort=patient&amp;dir=asc">Patient', $page);

        $second = $this->get(self::LIST, ['query' => ['q' => $s, 'page' => '2']]);
        $this->assertBodyContains('21 bis 22 von 22 Protokolle', $second);
        $this->assertBodyContains("$s-22", $second);
        $this->assertBodyNotContains("$s-01", $second);

        $byNumber = $this->get(self::LIST, ['query' => ['q' => $s, 'sort' => 'nr', 'dir' => 'desc']]);
        $this->assertBodyContains('aria-sort="descending"><a class="ignis-table__sort is-desc"', $byNumber);
        $this->assertLessThan($this->pos($byNumber->body, "$s-21"), $this->pos($byNumber->body, "$s-22"));
        $this->assertBodyNotContains("$s-01", $byNumber);
        // Die Suche behält die Sortierung.
        $this->assertBodyContains('<input type="hidden" name="sort" value="nr">', $byNumber);

        $patient = $this->get(self::LIST, ['query' => ['q' => "Erna $s"]]);
        $this->assertBodyContains('1 bis 1 von 1 Protokolle', $patient);
        $this->assertBodyContains("$s-03", $patient);

        $author = $this->get(self::LIST, ['query' => ['q' => "Schreiber $s"]]);
        $this->assertBodyContains('1 bis 1 von 1 Protokolle', $author);
        $this->assertBodyContains("$s-05", $author);

        $none = $this->get(self::LIST, ['query' => ['q' => "nichts $s"]]);
        $this->assertBodyContains('Keine Protokolle gefunden', $none);
    }

    #[Test]
    public function zeilen_escapen_und_aktionen_brauchen_das_bearbeitungsrecht(): void
    {
        $this->login();
        $s = 'XSS-' . uniqid();
        $evil = '<img src=x onerror=alert(1)>"';
        $id = $this->protocol($s, ['patname' => $evil, 'pfname' => $evil, 'bearbeiter' => $evil, 'freigeber_name' => $evil]);

        $page = $this->get(self::LIST, ['query' => ['q' => $s]]);
        $this->assertOk($page);
        $this->assertBodyNotContains('<img src=x onerror=alert(1)>', $page);
        $this->assertBodyContains('<td>&lt;img src=x onerror=alert(1)&gt;&quot;</td>', $page);
        $this->assertBodyContains('data-patname="&lt;img src=x onerror=alert(1)&gt;&quot;"', $page);
        $this->assertBodyContains('data-ignis-tooltip="Prüfer: &lt;img src=x onerror=alert(1)&gt;&quot;"', $page);
        $this->assertBodyContains('data-ignis-tooltip="Freigeber: &lt;img src=x onerror=alert(1)&gt;&quot;"', $page);
        $this->assertBodyContains('<form method="post" action="/enotf/admin/delete"', $page);
        $this->assertBodyContains('<input type="hidden" name="id" value="' . $id . '">', $page);
        $this->assertBodyContains('data-enotf-qm="actions" data-id="' . $id . '"', $page);
        $this->assertBodyContains('onclick="showBulkDeleteModal()"', $page);

        // Nur lesen: ansehen ja, QM-Aktionen, Löschen und Sammellöschen nicht.
        $this->login(['edivi.view']);
        $readOnly = $this->get(self::LIST, ['query' => ['q' => $s]]);
        $this->assertOk($readOnly);
        $this->assertBodyContains('aria-label="Protokoll ansehen"', $readOnly);
        $this->assertBodyNotContains('data-enotf-qm', $readOnly);
        $this->assertBodyNotContains('enotf/admin/delete', $readOnly);
        $this->assertBodyNotContains('showBulkDeleteModal()"', $readOnly);
    }

    /**
     * Die Liste liest den Verbund-Schalter live aus intra_config (die
     * Konstante FEDERATION_ENABLED steht einmal pro Prozess fest).
     */
    #[Test]
    public function verbund_protokolle_stehen_nur_lesend_dabei(): void
    {
        Capsule::table('intra_config')->updateOrInsert(['config_key' => 'FEDERATION_ENABLED'], ['config_value' => 'true', 'config_type' => 'boolean']);
        $this->login();
        $s = 'VB-' . uniqid();
        $this->protocol("$s-L", ['sendezeit' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);
        $this->federatedProtocol("inst-$s", 'Nachbarwache', true, "$s-F", 5);
        $this->federatedProtocol("inst-off-$s", 'Stille Wache', false, "$s-X", 6);

        $page = $this->get(self::LIST, ['query' => ['q' => $s]]);
        $this->assertOk($page);
        $this->assertBodyContains('1 bis 2 von 2 Protokolle', $page);
        $this->assertBodyContains('<span class="ignis-chip ignis-chip--secondary">Nachbarwache</span>', $page);
        $this->assertBodyContains('<span class="ignis-list-meta">nur lesen</span>', $page);
        $this->assertBodyContains('<td>Unbekannt</td>', $page);
        $this->assertBodyNotContains("$s-X", $page);
        // Fehlende Felder gelten als geprüft und freigegeben.
        $this->assertBodyContains('Alle <span class="ignis-segmented__count">2</span>', $page);
        $this->assertBodyContains('Nicht freigegeben <span class="ignis-segmented__count">0</span>', $page);
        // Neueste zuerst, nach Nummer absteigend steht L vor F.
        $this->assertLessThan($this->pos($page->body, "$s-F"), $this->pos($page->body, "$s-L"));
        $byNumber = $this->get(self::LIST, ['query' => ['q' => $s, 'sort' => 'nr', 'dir' => 'asc']]);
        $this->assertLessThan($this->pos($byNumber->body, "$s-L"), $this->pos($byNumber->body, "$s-F"));

        // Die QM-Knöpfe hat nur das eigene Protokoll.
        $this->assertSame(1, substr_count($page->body, 'data-enotf-qm="actions"'));
    }

    /**
     * Ein Protokoll im Verbund-Cache, wie es FederationSyncService ablegt.
     */
    private function federatedProtocol(string $instance, string $name, bool $active, string $enr, int $hoursAgo): void
    {
        Capsule::table('intra_federation_links')->insert([
            'instance_id'      => $instance,
            'instance_name'    => $name,
            'instance_url'     => 'https://' . $instance . '.example',
            'api_key_outgoing' => bin2hex(random_bytes(16)),
            'api_key_incoming' => bin2hex(random_bytes(16)),
            'consume_enotf'    => 1,
            'is_active'        => $active ? 1 : 0,
        ]);
        $sent = date('Y-m-d H:i:s', strtotime("-$hoursAgo hours"));
        Capsule::table('intra_federation_cache_enotf')->insert([
            'source_instance_id' => $instance,
            'remote_id'          => random_int(1000, 999999),
            'cached_data'        => json_encode(['id' => 7, 'enr' => $enr, 'patname' => null, 'pfname' => 'Nachbar Protokollant', 'sendezeit' => $sent]),
            'protocol_date'      => $sent,
            'cached_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Der QM-Dialog speichert per POST an seine eigene Adresse. Die Route
     * nahm nur GET an, der Router antwortete vor dem Controller mit 405.
     * Speichern darf nur, wer Protokolle bearbeitet; ansehen, wer die Liste sieht.
     */
    #[Test]
    public function qm_dialog_nimmt_post_an_und_speichert_nur_mit_bearbeitungsrecht(): void
    {
        $id = $this->protocol('QM-' . uniqid(), ['protokoll_status' => 0]);

        $this->login(['edivi.view']);
        $form = $this->get('/enotf/admin/qm-actions-modal', ['query' => ['id' => (string) $id]]);
        $this->assertOk($form);
        $this->assertBodyContains('id="qmActionsForm"', $form);

        $denied = $this->post('/enotf/admin/qm-actions-modal', ['bearbeiter' => 'Leser', 'protokoll_status' => '2', 'qmkommentar' => ''], ['query' => ['id' => (string) $id]]);
        $this->assertStatus(403, $denied);
        $this->assertSame(false, $this->assertJsonResponse($denied)['success'] ?? null);
        $this->assertSame(0, (int) Capsule::table('intra_edivi')->where('id', $id)->value('protokoll_status'));
    }
}
