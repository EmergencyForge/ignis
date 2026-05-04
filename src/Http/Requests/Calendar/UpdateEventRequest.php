<?php

declare(strict_types=1);

namespace App\Http\Requests\Calendar;

/**
 * Validation fuer POST /kalender/update?id=X.
 *
 * Aktuell identische Regeln wie Create (Vollupdate via Form-Submit). Falls
 * spaeter Patch-Style noetig wird, koennen einzelne Felder hier optional
 * gemacht werden.
 */
class UpdateEventRequest extends CreateEventRequest
{
}
