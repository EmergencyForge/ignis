<?php

declare(strict_types=1);

namespace App\Support;

use App\Auth\Gate;
use App\Models\Form;
use App\Plugins\PluginLoader;
use Illuminate\Database\Capsule\Manager as Capsule;

final class OpenTasks
{
    /** @return list<array{label:string,reason:string,href:string}>|null */
    public static function forCurrentUser(PluginLoader $plugins): ?array
    {
        $discord = (string) ($_SESSION['discordtag'] ?? '');
        if ($discord === '') {
            return null;
        }
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $tasks = [];
        $canDecideForms = Gate::allows('forms.decide');
        $canEditIncidents = $plugins->isActive('firetab') && \Plugin\Firetab\Policies\FireIncidentPolicy::viewList()
            && \Plugin\Firetab\Policies\FireIncidentPolicy::update();
        if (!$canDecideForms && !$canEditIncidents) {
            return null;
        }
        if ($canDecideForms) {
            $forms = Form::query()->with('typ')->where('discordid', $discord)
                ->where('cirs_status', Form::STATUS_IN_PROGRESS)->orderBy('time_added')->limit(20)->get();
            foreach ($forms as $form) {
                if (!Gate::allows('forms.view', $form)) {
                    continue;
                }
                $tasks[] = ['label' => ($form->typ?->name ?? 'Antrag') . ' · ' . $form->uniqueid,
                    'reason' => 'Eigener Antrag · Bearbeitung möglich',
                    'href' => $base . 'forms/admin/view?antrag=' . rawurlencode($form->uniqueid)];
            }
        }
        if ($canEditIncidents) {
            $incidents = Capsule::table('intra_fire_incidents as i')->join('intra_mitarbeiter as m', 'm.id', '=', 'i.leader_id')
                ->where('m.discordtag', $discord)->where('i.archived', 0)->where('i.finalized', 0)
                ->orderBy('i.created_at')->limit(20)->get(['i.id', 'i.incident_number', 'i.location']);
            foreach ($incidents as $incident) {
                $tasks[] = ['label' => (string) $incident->incident_number . ' · ' . (string) $incident->location,
                    'reason' => 'Eigene Einsatzleitung · Protokoll nicht abgeschlossen',
                    'href' => $base . 'firetab/view?id=' . (int) $incident->id];
            }
        }
        return $tasks;
    }
}
