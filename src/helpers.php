<?php

/**
 * intraRP — Globale Helper-Funktionen
 *
 * Wird via composer "files"-autoload bei jedem Request automatisch geladen.
 */

declare(strict_types=1);

use Psr\Container\ContainerInterface;

if (!function_exists('app')) {
    /**
     * Service-Container-Accessor.
     *
     *     app()                   → Container-Instanz
     *     app(SomeClass::class)   → aufgelöste Instanz
     *
     * Wirft \RuntimeException, falls der Container vor Bootstrap aufgerufen
     * wird (passiert nur, wenn assets/config/config.php nicht durchlief).
     *
     * @template T of object
     * @param class-string<T>|null $abstract
     * @return ($abstract is null ? ContainerInterface : T)
     */
    function app(?string $abstract = null)
    {
        $container = $GLOBALS['app_container'] ?? null;
        if (!$container instanceof ContainerInterface) {
            throw new \RuntimeException(
                'Service container not initialized. '
                . 'Stelle sicher, dass assets/config/config.php geladen wurde.'
            );
        }
        if ($abstract === null) {
            return $container;
        }
        return $container->get($abstract);
    }
}

if (!function_exists('asset')) {
    /**
     * Baut eine Asset-URL mit automatischem Cache-Buster-Query.
     *
     * Hängt `?v=<mtime>` an, damit Browser nach einem Deploy die neue
     * Datei ziehen, ohne dass der User manuell einen Hard-Reload machen
     * muss. Existiert die Datei nicht, entfällt der Query-String.
     *
     *     asset('public/assets/dist/vendor.css')
     *     → /assets/dist/vendor.css?v=1713456789
     *
     * Das Docroot ist public/. Ein führendes `public/` im Pfad fällt
     * deshalb aus der URL heraus; die Datei wird für den Cache-Buster
     * zuerst unter public/ gesucht (dort landen auch die vom Build
     * gespiegelten assets/img, assets/js usw.) und sonst im Projekt-Root
     * (Plugin-Assets, die eine Route ausliefert).
     *
     * BASE_PATH wird vorangestellt, damit Subdirectory-Installs
     * (`/intrarp/abc/…`) automatisch korrekt verlinkt werden.
     */
    function asset(string $path): string
    {
        $relPath = ltrim($path, '/');
        if (str_starts_with($relPath, 'public/')) {
            $relPath = substr($relPath, strlen('public/'));
        }
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $root = dirname(__DIR__);

        $version = 0;
        foreach ([$root . '/public/' . $relPath, $root . '/' . $relPath] as $absolute) {
            if (is_file($absolute)) {
                $version = (int) filemtime($absolute);
                break;
            }
        }

        $url = rtrim($base, '/') . '/' . $relPath;
        return $version > 0 ? $url . '?v=' . $version : $url;
    }
}

if (!function_exists('confirm_attr')) {
    /**
     * Baut den Wert eines `onsubmit`/`onclick`-Attributs `return confirm(...)`
     * mit dynamischem Text.
     *
     * Zwei Kontexte, zwei Escapings: json_encode() mit HEX-Flags für den
     * JS-String (Apostroph, Anführungszeichen, spitze Klammern, Kaufmanns-Und),
     * htmlspecialchars() für das Attribut. Nur `htmlspecialchars($name)` in
     * `confirm('...')` reicht nicht: der Browser dekodiert das Attribut, bevor
     * der JS-Parser es sieht, ein Apostroph im Namen bricht den String auf.
     *
     *     <form onsubmit="<?= confirm_attr("Rolle \"$name\" wirklich löschen?") ?>">
     */
    function confirm_attr(string $message): string
    {
        $jsString = json_encode(
            $message,
            JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
        );
        if ($jsString === false) {
            $jsString = '""';
        }

        return htmlspecialchars('return confirm(' . $jsString . ');', ENT_QUOTES);
    }
}

if (!function_exists('ignis_like_prefix')) {
    /**
     * Escaped Nutzereingabe für ein LIKE-Muster: `%`, `_` und `\` werden
     * zu Literalen, damit ein Suchwort wie "%" nicht jede Zeile trifft.
     * Die Wildcards baut der Aufrufer selbst drumherum:
     *
     *     ->where('name', 'LIKE', '%' . ignis_like_prefix($q) . '%')
     *
     * MySQL/MariaDB nehmen `\` als Escape-Zeichen, ohne ESCAPE-Klausel.
     */
    function ignis_like_prefix(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}

if (!function_exists('old')) {
    /**
     * Die Eingabe aus dem letzten gescheiterten Formular-Post, damit das
     * Formular nach dem Redirect wieder gefüllt ist:
     *
     *     <input name="title" value="<?= htmlspecialchars((string) old('title')) ?>">
     *
     * Liest den Bag, den FormRequest::validate() bzw. rememberInput() in
     * die Session gelegt hat, einmalig pro Request (One-Shot). Ohne Bag
     * oder ohne das Feld kommt $default.
     */
    function old(string $field, mixed $default = ''): mixed
    {
        return \App\Http\Requests\FormRequest::pullOldInput($field, $default);
    }
}
