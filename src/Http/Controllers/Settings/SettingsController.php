<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Navigation;
use App\Http\Controllers\Controller;
use App\Setup\SetupCheck;

/**
 * SettingsController: Übersicht /settings/index (Alias /settings).
 *
 * Bündelt die Verwaltungsbereiche, die vorher als ~20 einzelne Sidebar-
 * Zeilen unter „Einstellungen" standen, als Kacheln in Abschnitten. Jeder
 * Abschnitt ist eine Gruppe mit `placement => 'settings'` aus
 * config/navigation.php; welche Kacheln ein Betrachter sieht, entscheidet
 * dieselbe Rechteprüfung wie in der Sidebar (Navigation::groups()), damit
 * beide nie auseinanderlaufen.
 *
 * Wer die System-Konfiguration bearbeiten darf, sieht oben einen Hinweis,
 * solange dort Pflichtangaben fehlen (App\Setup\SetupCheck).
 */
class SettingsController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $sections = array_values(array_filter(
            Navigation::groups(),
            static fn (array $group): bool => ($group['placement'] ?? null) === 'settings' && $group['items'] !== [],
        ));

        $setupRequired = Gate::allows('system.admin') ? SetupCheck::fromConfig()->required() : [];

        $this->renderView('settings/index', [
            'settingsSections' => $sections,
            'setupRequired'    => $setupRequired,
        ]);
    }
}
