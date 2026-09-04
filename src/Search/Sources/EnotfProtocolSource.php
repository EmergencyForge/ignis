<?php

declare(strict_types=1);

namespace App\Search\Sources;

use App\Auth\Gate;
use App\Plugins\PluginLoader;
use App\Search\SearchSourceInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * eNOTF-Protokolle nach Einsatznummer, Patient oder Diagnose.
 *
 * Gehört eigentlich ins Plugin (plugins/enotf, Manifest-Feld `search`,
 * wie fireTab und die Wissensdatenbank es machen). eNOTF v1 und v2 sind
 * aber vom Redesign ausgenommen und werden nicht angefasst, bis v2 v1
 * ersetzt; bis dahin bleibt die Quelle hier im Kern und prüft selbst, ob
 * das Plugin aktiv ist. Beim Umzug: Klasse in das Plugin verschieben,
 * im Manifest eintragen, hier aus der SearchRegistry nehmen.
 */
final class EnotfProtocolSource implements SearchSourceInterface
{
    public function __construct(private readonly PluginLoader $plugins)
    {
    }

    public function key(): string
    {
        return 'enotf';
    }

    public function label(): string
    {
        return 'eNOTF-Protokolle';
    }

    public function allowed(): bool
    {
        return $this->plugins->isActive('enotf') && Gate::allows('enotf.viewAdminList');
    }

    public function search(string $q, int $limit): array
    {
        $like = '%' . ignis_like_prefix($q) . '%';
        $base = search_base_path();

        try {
            $rows = Capsule::table('intra_edivi')
                ->select(['id', 'enr', 'patname', 'diagnose', 'edatum'])
                ->where(function ($query) use ($like) {
                    $query->where('enr', 'LIKE', $like)
                        ->orWhere('patname', 'LIKE', $like)
                        ->orWhere('diagnose', 'LIKE', $like);
                })
                ->orderByDesc('edatum')
                ->limit($limit)
                ->get();
        } catch (PDOException) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $sub = (string) ($row->patname ?: '');
            if ($row->edatum) {
                $sub .= ($sub !== '' ? ' · ' : '') . date('d.m.Y', (int) strtotime((string) $row->edatum));
            }
            $items[] = [
                'label' => 'Protokoll ' . $row->enr,
                'sub'   => $sub,
                // Eine Einzelansicht im Admin gibt es nicht als Route; die
                // Liste ist das nächste Ziel.
                'href'  => $base . 'enotf/admin/list',
            ];
        }

        return $items;
    }
}
