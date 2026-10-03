<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Helpers\Navigation;
use App\Http\Controllers\Controller;

/**
 * SettingsController: Übersicht /settings/index (Alias /settings).
 *
 * Bündelt die Verwaltungsbereiche, die vorher als ~20 einzelne Sidebar-
 * Zeilen unter „Einstellungen" standen, als Kacheln in Abschnitten. Jeder
 * Abschnitt ist eine Gruppe mit `placement => 'settings'` aus
 * config/navigation.php; welche Kacheln ein Betrachter sieht, entscheidet
 * dieselbe Rechteprüfung wie in der Sidebar (Navigation::groups()), damit
 * beide nie auseinanderlaufen.
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

        $this->renderView('settings/index', ['settingsSections' => $sections]);
    }
}
