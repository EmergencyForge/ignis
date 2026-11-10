<?php

declare(strict_types=1);

use App\Http\UrlMap;
use Phinx\Migration\AbstractMigration;

/**
 * Gespeicherte Quicklinks auf die englischen Pfade umschreiben, etwa
 * ../enotf/fahrzeuginfo auf ../enotf/vehicle-info. Die alten Pfade leiten
 * weiter, aber ein Link soll nicht jedes Mal einen Umweg nehmen.
 *
 * Die Übersicht gibt die URL unverändert aus, aufgelöst gegen
 * /enotf/overview: "x" meint /enotf/x, "../x" und "/x" meinen /x.
 * Links mit Host bleiben, sie können auf eine fremde Seite zeigen.
 */
class UpdateIntraEnotfQuicklinks10112026EnglishPaths extends AbstractMigration
{
    public function up(): void
    {
        $connection = $this->getAdapter()->getConnection();
        foreach ($this->fetchAll('SELECT id, url FROM intra_enotf_quicklinks') as $row) {
            $url = self::translate((string) $row['url']);
            if ($url !== null) {
                $this->execute(sprintf(
                    'UPDATE intra_enotf_quicklinks SET url = %s WHERE id = %d',
                    $connection->quote($url),
                    (int) $row['id'],
                ));
            }
        }
    }

    public function down(): void
    {
        // Nichts zurück: die neuen Pfade gelten auch ohne diese Migration.
    }

    public static function translate(string $url): ?string
    {
        if ($url === '' || preg_match('~^[a-z][a-z0-9+.-]*:|^//~i', $url)) {
            return null;
        }

        [$prefix, $path] = match (true) {
            str_starts_with($url, '../') => ['../', substr($url, 3)],
            str_starts_with($url, '/')   => ['/', substr($url, 1)],
            default                      => ['', 'enotf/' . $url],
        };

        // Wie der Front-Controller: .php fällt weg, außer bei index.php
        $path = (string) preg_replace('~(?<!/index)(?<!^index)\.php(?=$|[?#])~', '', $path);

        $translated = UrlMap::translateRelative($path);
        if ($translated === null) {
            return null;
        }
        if ($prefix === '') {
            return str_starts_with($translated, 'enotf/') ? substr($translated, 6) : '../' . $translated;
        }

        return $prefix . $translated;
    }
}
