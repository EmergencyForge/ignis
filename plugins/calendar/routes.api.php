<?php

declare(strict_types=1);

/**
 * Kalender: API-Routen.
 *
 * Der FullCalendar-Feed liefert EventInput-Objekte für den Range
 * [from, to]; RecurrenceExpander löst Serien serverseitig auf. Der
 * iCal-Feed hat bewusst keine AuthMiddleware: der Token in der URL ist die
 * Anmeldung, externe Kalender-Apps schicken kein Cookie.
 *
 * @var \EmergencyForge\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use Plugin\Calendar\Controllers\CalendarController;

$router->get('/api/calendar/events', [CalendarController::class, 'eventsJson'], [new AuthMiddleware()]);

// Detail eines einzelnen Termins für das Bearbeiten-Formular
$router->get('/api/calendar/event', [CalendarController::class, 'eventJson'], [new AuthMiddleware()]);

// iCal-Abo: erzeugt den Token bei Bedarf und gibt die absolute URL zurück
$router->get('/api/calendar/subscribe-info', [CalendarController::class, 'subscribeInfo'], [new AuthMiddleware()]);
$router->post('/api/calendar/subscribe-regenerate', [CalendarController::class, 'subscribeRegenerate'], [new AuthMiddleware()]);

$router->get('/api/calendar/ical/{token:[a-f0-9]{20,64}}', [CalendarController::class, 'icalFeed']);
