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

if (!function_exists('csrf_token')) {
    /**
     * Der CSRF-Token der Sitzung.
     */
    function csrf_token(): string
    {
        return \App\Security\CsrfProtection::getToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Das versteckte Feld fuer ein Formular:
     *
     *     <form method="POST" action="...">
     *         <?= csrf_field() ?>
     *
     * Jedes schreibende Formular braucht es, seit CsrfMiddleware global
     * am Router haengt — ohne kommt die 403-Seite.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES)
            . '">';
    }
}

if (!function_exists('csrf_head')) {
    /**
     * Der Kopfteil des CSRF-Schutzes: der Token als Meta-Tag und der
     * Aufsatz für schreibende fetch()- und XMLHttpRequest-Anfragen.
     *
     * Der Aufsatz steht bewusst inline und nicht in einem Modul: er muss
     * stehen, bevor irgendein Modul das erste Mal fetch() ruft, und die
     * Einstiegspunkte (Admin, Mitarbeiter, eNOTF) haben nicht denselben
     * Import-Baum. Wer den Header selbst setzt, behaelt ihn.
     *
     * Fremde Ziele bekommen den Token nicht — sonst reicht ein fetch()
     * auf eine andere Domain, um ihn dorthin zu tragen.
     */
    function csrf_head(): string
    {
        $token = htmlspecialchars(csrf_token(), ENT_QUOTES);

        return <<<HTML
            <meta name="csrf-token" content="{$token}">
            <script>
            (function () {
                var meta = document.querySelector('meta[name="csrf-token"]');
                if (!meta) { return; }

                var write = /^(POST|PUT|PATCH|DELETE)$/i;
                var original = window.fetch;

                function isSameOrigin(url) {
                    try {
                        return new URL(url, location.href).origin === location.origin;
                    } catch (e) {
                        return false;
                    }
                }

                if (typeof original === 'function') {
                    window.fetch = function (input, init) {
                        var opts = init || {};
                        var method = opts.method || (input && input.method) || 'GET';
                        var url = input && typeof input.url === 'string' ? input.url : String(input);

                        if (write.test(method) && isSameOrigin(url)) {
                            var headers = new Headers(opts.headers || (input && input.headers) || {});
                            if (!headers.has('X-CSRF-Token')) {
                                headers.set('X-CSRF-Token', meta.content);
                            }
                            opts = Object.assign({}, opts, { headers: headers });
                        }

                        return original.call(this, input, opts);
                    };
                }

                if (typeof window.XMLHttpRequest === 'function') {
                    var proto = window.XMLHttpRequest.prototype;
                    var open = proto.open;
                    var send = proto.send;
                    var setHeader = proto.setRequestHeader;
                    var requests = new WeakMap();

                    proto.open = function (method, url) {
                        requests.set(this, { needsToken: write.test(method) && isSameOrigin(url), hasToken: false });
                        return open.apply(this, arguments);
                    };
                    proto.setRequestHeader = function (name, value) {
                        var state = requests.get(this);
                        if (state && String(name).toLowerCase() === 'x-csrf-token') {
                            state.hasToken = true;
                        }
                        return setHeader.apply(this, arguments);
                    };
                    proto.send = function () {
                        var state = requests.get(this);
                        if (state && state.needsToken && !state.hasToken) {
                            setHeader.call(this, 'X-CSRF-Token', meta.content);
                            state.hasToken = true;
                        }
                        return send.apply(this, arguments);
                    };
                }
            })();
            </script>
            HTML;
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

if (!function_exists('search_base_path')) {
    /**
     * BASE_PATH mit genau einem Schrägstrich am Ende, für die Ziele der
     * Suchquellen (App\Search). Über defined(), damit PHPStan nicht den
     * Fallback aus config.php als festen Wert nimmt.
     */
    function search_base_path(): string
    {
        return rtrim(defined('BASE_PATH') ? (string) constant('BASE_PATH') : '/', '/') . '/';
    }
}

if (!function_exists('env_value')) {
    /**
     * Liest eine Umgebungsvariable aus $_ENV, $_SERVER, getenv().
     *
     * Alle drei, weil SetEnv und FPM-Pool-Einträge bei variables_order ohne E
     * nicht in $_ENV landen. null heißt nirgends gesetzt; ein leerer String
     * ist ein gültiger Wert (leeres DB_PASS).
     */
    function env_value(string $key): ?string
    {
        if (isset($_ENV[$key])) {
            return (string) $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        $value = getenv($key);

        return $value === false ? null : $value;
    }
}
