<?php

declare(strict_types=1);

namespace Plugin\Forms\Policies;

use App\Auth\Permissions;
use Plugin\Forms\Models\Form;

/**
 * FormsPolicy: Single Source of Truth für "wer darf was mit Anträgen".
 *
 *   viewAny(): Admin-Übersicht aller Anträge ansehen
 *   view():    Einzelantrag ansehen (eigene IMMER, fremde nur mit application.view)
 *   create():  Neuen Antrag stellen (jeder eingeloggte User)
 *   decide():  Status setzen / Antrag bearbeiten (application.edit)
 *
 * Aufruf bevorzugt über den Gate:
 *
 *     Gate::allows('forms.view', $antrag)
 *     Gate::allows('forms.decide')
 */
class FormsPolicy
{
    /**
     * Darf der Aktor die Admin-Übersicht aller Anträge sehen?
     */
    public static function viewAny(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'application.edit']);
    }

    /**
     * Darf der Aktor einen einzelnen Antrag ansehen?
     *
     * Logik:
     *   - Mit `application.view` Permission: jeden Antrag
     *   - Sonst: nur eigene Anträge (über den verknüpften Mitarbeiter,
     *     alte Anträge ohne mitarbeiter_id über die Discord-ID)
     *
     * Wenn `$target` null ist (z.B. Permission-Check vor Load), wird auf
     * die globale Permission geprüft.
     */
    public static function view(?Form $target = null): bool
    {
        if (Permissions::check(['admin', 'application.view'])) {
            return true;
        }
        if ($target === null) {
            return false;
        }
        if ($target->mitarbeiter_id !== null) {
            return $target->mitarbeiter_id === \App\Personnel\AccountLink::currentId();
        }
        $own = $_SESSION['discordtag'] ?? null;
        return $own !== null && $own !== '' && $target->discordid === $own;
    }

    /**
     * Darf der Aktor einen neuen Antrag stellen?
     * Aktuell jeder eingeloggte User. Der Login-Check erfolgt im Controller.
     */
    public static function create(mixed $context = null): bool
    {
        return isset($_SESSION['userid']);
    }

    /**
     * Darf der Aktor einen Antrag bearbeiten / Status setzen?
     */
    public static function decide(?Form $target = null): bool
    {
        return Permissions::check(['admin', 'application.edit']);
    }
}
