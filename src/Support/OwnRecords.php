<?php

declare(strict_types=1);

namespace App\Support;

use App\Personnel\AccountLink;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;

/**
 * Was zum angemeldeten Konto gehört: Dokumente der eigenen Akte, eigene
 * Anträge, eNOTF-Protokolle mit dem eigenen Namen in der Besatzung und
 * fireTab-Einsätze, die der eigene Mitarbeiter geleitet hat.
 *
 * Das Dashboard zeigt daraus die neuesten PREVIEW Einträge, die Seiten
 * unter /me/ die ganze Liste. Beide lesen diese Abfragen, damit der Link
 * „Alle anzeigen“ genau die Einträge der Karte fortsetzt. Anträge und
 * Protokolle nur bei aktivem Plugin aufrufen, sonst fehlen die Tabellen.
 */
final class OwnRecords
{
    public const PREVIEW = 5;

    public static function documents(): Builder
    {
        return Capsule::table('intra_mitarbeiter_dokumente as pd')
            ->leftJoin('intra_users as u', 'pd.aussteller_user_id', '=', 'u.id')
            ->leftJoin('intra_mitarbeiter as m', 'u.aktenid', '=', 'm.id')
            // Ohne verknüpften Mitarbeiter bleibt die Liste leer: die 0 trifft keine ID.
            ->where('pd.profileid', AccountLink::currentId() ?? 0)
            ->select([
                'pd.docid',
                'pd.ausstellungsdatum',
                'pd.type',
                Capsule::connection()->raw("COALESCE(pd.aussteller_name, m.fullname, u.fullname, 'Unbekannt') as ersteller_name"),
            ]);
    }

    public static function applications(): Builder
    {
        $query = Capsule::table('intra_antraege as a')
            ->join('intra_antrag_typen as at', 'a.antragstyp_id', '=', 'at.id')
            ->select([
                'a.uniqueid',
                'at.name as typ_name',
                'at.icon as typ_icon',
                'a.cirs_status',
                'a.cirs_manager',
                'a.time_added',
            ]);
        \Plugin\Forms\Models\Form::whereOwn($query, 'a.');

        return $query;
    }

    public static function enotfProtocols(): Builder
    {
        return Capsule::table('intra_edivi as e')
            ->join('intra_mitarbeiter as m', function ($join) {
                $join->where('m.id', '=', AccountLink::currentId() ?? 0);
            })
            ->where(function ($q) {
                foreach (['e.pfname', 'e.fzg_transp_perso', 'e.fzg_transp_perso_2', 'e.fzg_transp_perso_3', 'e.fzg_na_perso', 'e.fzg_na_perso_2', 'e.fzg_na_perso_3'] as $column) {
                    $q->orWhereRaw($column . " LIKE CONCAT('%', m.fullname, '%')");
                }
            })
            ->where('e.hidden', '<>', 1)
            ->where('e.hidden_user', '<>', 1)
            ->select([
                'e.enr',
                'e.sendezeit',
                'e.protokoll_status',
                'e.bearbeiter',
                'e.freigegeben',
                'e.freigeber_name',
                'e.hidden_user',
            ]);
    }

    public static function firetabProtocols(): Builder
    {
        return Capsule::table('intra_fire_incidents as i')
            ->leftJoin('intra_mitarbeiter as m', 'i.leader_id', '=', 'm.id')
            ->where('i.leader_id', '=', AccountLink::currentId() ?? 0)
            ->where('i.archived', 0)
            ->select([
                'i.id',
                'i.incident_number',
                'i.location',
                'i.started_at',
                'i.status',
                'i.finalized',
                'm.fullname AS leader_name',
            ]);
    }
}
