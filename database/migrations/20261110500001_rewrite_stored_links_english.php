<?php

declare(strict_types=1);

use App\Http\UrlMap;
use Phinx\Migration\AbstractMigration;

/**
 * Gespeicherte Links in Benachrichtigungen und Dashboard-Kacheln auf die
 * englischen Pfade und Parameternamen umschreiben, etwa
 * /forms/view?antrag=7 auf /forms/view?form=7. Alte Pfade leiten weiter und
 * alte Parameter werden weiter gelesen, die Migration spart nur den Umweg.
 *
 * Links mit Host bleiben, sie können auf eine fremde Seite zeigen.
 */
final class RewriteStoredLinksEnglish extends AbstractMigration
{
    /**
     * Umbenannte Query-Parameter je Seite. Ein Schlüssel mit / am Ende gilt
     * für alle Seiten darunter. Die Zuordnung hängt an der Seite, weil
     * Namen wie typ anderswo Listenfilter mit anderer Bedeutung sind.
     */
    private const PARAMS = [
        'forms/view'         => ['antrag' => 'form'],
        'forms/admin/view'   => ['antrag' => 'form'],
        'forms/create'       => ['typ' => 'type'],
        'mail/'              => ['postfach' => 'mailbox'],
        'personnel/list'     => ['archiv' => 'archived'],
        'mci/patient-create' => ['lage_id' => 'situation_id'],
        'mci/resources'      => ['lage_id' => 'situation_id'],
        'enotf/loggedout'    => ['grund' => 'reason'],
    ];

    public function up(): void
    {
        $base = defined('BASE_PATH') ? BASE_PATH : '/';
        $this->rewrite('intra_notifications', 'link', $base);
        $this->rewrite('intra_dashboard_tiles', 'url', $base);
    }

    public function down(): void
    {
        // Nichts zurück: die neuen Links gelten auch ohne diese Migration.
    }

    private function rewrite(string $table, string $column, string $base): void
    {
        if (!$this->hasTable($table)) {
            return;
        }
        $connection = $this->getAdapter()->getConnection();
        foreach ($this->fetchAll("SELECT id, {$column} AS link FROM {$table} WHERE {$column} LIKE '%/%'") as $row) {
            $link = self::translate((string) $row['link'], $base);
            if ($link !== null) {
                $this->execute(sprintf(
                    'UPDATE %s SET %s = %s WHERE id = %d',
                    $table,
                    $column,
                    $connection->quote($link),
                    (int) $row['id'],
                ));
            }
        }
    }

    public static function translate(string $link, string $base = '/'): ?string
    {
        if (!str_starts_with($link, $base) || preg_match('~^[a-z][a-z0-9+.-]*:|^//~i', $link)) {
            return null;
        }

        $rest     = substr($link, strlen($base));
        $hashPos  = strpos($rest, '#');
        $fragment = $hashPos === false ? '' : substr($rest, $hashPos);
        $rest     = $hashPos === false ? $rest : substr($rest, 0, $hashPos);
        [$path, $query] = array_pad(explode('?', $rest, 2), 2, null);

        $path = UrlMap::translateRelative($path) ?? $path;

        if ($query !== null) {
            foreach (self::PARAMS as $page => $names) {
                if ($path === $page || (str_ends_with($page, '/') && str_starts_with($path, $page))) {
                    foreach ($names as $old => $new) {
                        $query = (string) preg_replace('~(^|&)' . $old . '=~', '$1' . $new . '=', $query);
                    }
                }
            }
        }

        $translated = $base . $path . ($query !== null ? '?' . $query : '') . $fragment;

        return $translated === $link ? null : $translated;
    }
}
