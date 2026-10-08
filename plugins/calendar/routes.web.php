<?php

declare(strict_types=1);

/**
 * Kalender: Web-Routen.
 *
 * Termine, role-getaggte Dienste, Recurring-Series. Alle Routes brauchen
 * AuthMiddleware + PolicyMiddleware('calendar.view'), der Create-Endpoint
 * zusätzlich 'calendar.create'. Update/Delete-Rechte prüft der Controller
 * per Gate::authorize() je Termin (Ersteller darf immer, sonst
 * calendar.manage).
 *
 * @var \EmergencyForge\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\PolicyMiddleware;
use Plugin\Calendar\Controllers\CalendarController;

$calendarViewAuth   = [new AuthMiddleware(), new PolicyMiddleware('calendar.view')];
$calendarCreateAuth = [new AuthMiddleware(), new PolicyMiddleware('calendar.create')];

$router->get('/calendar',          [CalendarController::class, 'index'],         $calendarViewAuth);
$router->get('/calendar/',         [CalendarController::class, 'index'],         $calendarViewAuth);
$router->get('/calendar/view',     [CalendarController::class, 'show'],          $calendarViewAuth);
// Anlage-Formular: Seite oder Fragment im Drawer (drawer-form.js)
$router->get('/calendar/create',   [CalendarController::class, 'create'],        $calendarCreateAuth);
$router->post('/calendar/create',  [CalendarController::class, 'store'],         $calendarCreateAuth);
$router->post('/calendar/update',  [CalendarController::class, 'update'],        $calendarViewAuth);
$router->post('/calendar/delete',  [CalendarController::class, 'destroy'],       $calendarViewAuth);
$router->post('/calendar/respond', [CalendarController::class, 'respondInvite'], $calendarViewAuth);
