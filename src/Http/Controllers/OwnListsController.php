<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Plugins\PluginLoader;
use App\Support\ListQuery;
use App\Support\OwnRecords;

/**
 * Die ganzen Listen hinter „Alle anzeigen“ der Dashboard-Karten: eigene
 * Dokumente, Anträge und Protokolle. Die Abfragen und Tabellen teilen sie
 * mit dem Dashboard (App\Support\OwnRecords, assets/components/index/).
 * Kein Menüeintrag, der Weg führt über das Dashboard.
 */
final class OwnListsController extends Controller
{
    public function __construct(private readonly PluginLoader $plugins)
    {
    }

    public function documents(): void
    {
        $this->requireAuth();

        $list = ListQuery::fromQuery($_GET, [
            'typ'         => 'pd.type',
            'nr'          => 'pd.docid',
            'ersteller'   => 'ersteller_name',
            'ausgestellt' => 'pd.ausstellungsdatum',
        ], 'ausgestellt', 'desc', 25, [], ['pd.docid']);

        $this->renderView('me/documents', ['list' => $list]);
    }

    public function applications(): void
    {
        $this->requireAuth();
        if (!$this->plugins->isActive('forms')) {
            $this->redirect('index');
        }

        $list = ListQuery::fromQuery($_GET, [
            'typ'         => 'at.name',
            'status'      => 'a.cirs_status',
            'nr'          => 'a.uniqueid',
            'bearbeiter'  => 'a.cirs_manager',
            'eingereicht' => 'a.time_added',
        ], 'eingereicht', 'desc', 25, ['status'], ['a.id']);

        $this->renderView('me/applications', [
            'list'   => $list,
            'counts' => ListQuery::countBy(OwnRecords::applications(), 'a.cirs_status'),
        ]);
    }

    public function protocols(): void
    {
        $this->requireAuth();

        $sources = array_values(array_filter(['enotf', 'firetab'], fn (string $id) => $this->plugins->isActive($id)));
        if ($sources === []) {
            $this->redirect('index');
        }
        $source = in_array($_GET['source'] ?? '', $sources, true) ? (string) $_GET['source'] : $sources[0];

        $list = $source === 'enotf'
            ? ListQuery::fromQuery($_GET, [
                'status'     => 'e.protokoll_status',
                'nr'         => 'e.enr',
                'bearbeiter' => 'e.bearbeiter',
                'gesendet'   => 'e.sendezeit',
            ], 'gesendet', 'desc', 25, ['source'], ['e.id'])
            : ListQuery::fromQuery($_GET, [
                'status' => 'i.status',
                'nr'     => 'i.incident_number',
                'ort'    => 'i.location',
                'beginn' => 'i.started_at',
            ], 'beginn', 'desc', 25, ['source'], ['i.id']);

        $counts = [];
        foreach ($sources as $id) {
            $counts[$id] = ($id === 'enotf' ? OwnRecords::enotfProtocols() : OwnRecords::firetabProtocols())->count();
        }

        $this->renderView('me/protocols', [
            'list'    => $list,
            'source'  => $source,
            'counts'  => $counts,
        ]);
    }
}
