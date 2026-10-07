<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\UrlMap;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Jeder interne Pfad im Code muss auf eine registrierte Route zeigen, und
 * zwar unter seinem englischen Namen ohne .php.
 *
 * Bei der Umstellung auf englische URLs blieb templates/roles/index.php
 * auf users/rollen/* stehen. UrlMap übersetzt nur das erste Segment, also
 * lief jedes Speichern einer Rolle in eine 404. Der Test liest die
 * Route-Dateien mit einem Router, der nur mitschreibt, und gleicht
 * BASE_PATH-Links, redirect()- und redirectTo-Ziele dagegen ab.
 */
final class InternalLinksTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** Pfade, die keine Route sind, sondern Dateien unter public/. */
    private const STATIC_PREFIXES = ['assets/', 'public/assets/', 'storage/', 'vendor/', 'uploads/', 'favicon', 'cron'];

    #[Test]
    public function jeder_interne_pfad_trifft_eine_route(): void
    {
        $routes = $this->routePatterns();
        $broken = [];

        foreach ($this->references() as [$where, $path]) {
            if (!$this->matchesRoute($path, $routes)) {
                $broken[] = "$where  $path";
            }
        }

        $this->assertSame([], $broken, "Pfade ohne Route:\n" . implode("\n", $broken));
    }

    #[Test]
    public function kein_interner_pfad_nutzt_alte_namen_oder_php(): void
    {
        $old = [];

        foreach ($this->references() as [$where, $path]) {
            $translated = UrlMap::translatePath('/' . $path);
            if ($translated !== null) {
                $old[] = "$where  $path -> " . ltrim($translated, '/');
            } elseif (preg_match('~\.php$~', $path)) {
                $old[] = "$where  $path -> " . preg_replace('~(/index)?\.php$~', '', $path);
            }
        }

        $this->assertSame([], $old, "Alte Pfade:\n" . implode("\n", $old));
    }

    /**
     * @return array<string, string> Rohpfad => Regex
     */
    private function routePatterns(): array
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', '/');
        }

        $router = new class {
            /** @var list<string> */
            public array $paths = [];
            private string $prefix = '';

            public function __call(string $name, array $args): void
            {
                if (in_array($name, ['get', 'post', 'put', 'delete', 'patch'], true)) {
                    $this->paths[] = $this->prefix . '/' . ltrim((string) $args[0], '/');
                } elseif ($name === 'match') {
                    $this->paths[] = $this->prefix . '/' . ltrim((string) $args[1], '/');
                } elseif ($name === 'group') {
                    $outer = $this->prefix;
                    $this->prefix .= rtrim((string) $args[0], '/');
                    try {
                        ($args[2])($this);
                    } finally {
                        $this->prefix = $outer;
                    }
                }
            }
        };

        $files = [
            self::ROOT . '/routes/web.php',
            self::ROOT . '/routes/api.php',
            self::ROOT . '/routes/api.session.php',
            ...glob(self::ROOT . '/plugins/*/routes.web.php') ?: [],
            ...glob(self::ROOT . '/plugins/*/routes.api.php') ?: [],
        ];
        foreach ($files as $file) {
            (static function (string $file, object $router): void {
                require $file;
            })($file, $router);
        }

        $this->assertGreaterThan(300, count($router->paths), 'Route-Dateien wurden nicht gelesen');

        // {name} ist ein Segment, {name:regex} bringt seinen Ausdruck mit.
        $paths = array_values(array_unique(array_map(
            static fn (string $path): string => '/' . trim($path, '/'),
            $router->paths,
        )));

        return array_combine($paths, array_map(static function (string $path): string {
            $parts = preg_split('~(\{[^}]*\})~', $path, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
            $regex = '';
            foreach ($parts as $part) {
                if (preg_match('~^\{[^:}]+:(.+)\}$~', $part, $m)) {
                    $regex .= '(?:' . $m[1] . ')';
                } elseif (preg_match('~^\{[^}]+\}$~', $part)) {
                    $regex .= '[^/]+';
                } else {
                    $regex .= preg_quote($part, '~');
                }
            }

            return '~^' . $regex . '/?$~';
        }, $paths));
    }

    /**
     * @param array<string, string> $routes
     */
    private function matchesRoute(string $path, array $routes): bool
    {
        $path = '/' . $path;
        // /api/v1/x läuft im Front-Controller als /api/x.
        $path = preg_replace('~^/api/v1(/|$)~', '/api$1', $path);

        foreach ($routes as $raw => $route) {
            if (preg_match($route, $path)) {
                return true;
            }
            // Endet der Pfad auf /, setzt der Code dort einen Wert ein
            // (personnel/<id>/documents): die Route muss bis hierher
            // gleich sein und mit einem Parameter weitergehen.
            if (str_ends_with($path, '/') && str_starts_with($raw, $path . '{')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{string, string}> [Datei:Zeile, Pfad ohne Query]
     */
    private function references(): array
    {
        $patterns = [
            '~BASE_PATH\s*\?>\s*([a-z][a-z0-9_\-/.]*)~i',
            '~BASE_PATH\s*\.\s*[\'"]([a-z][a-z0-9_\-/.]*)~i',
            '~->redirect\(\s*[\'"]([a-z][a-z0-9_\-/.]*)~i',
            '~redirectTo:\s*[\'"]([a-z][a-z0-9_\-/.]*)~i',
            '~ensureAdmin\(\s*[\'"]([a-z][a-z0-9_\-/.]*)~i',
        ];

        $root = strtr((string) realpath(self::ROOT), "\\", "/");
        $dirs = ['templates', 'src', 'assets/components', 'assets/functions', 'plugins', 'auth'];
        $refs = [];

        foreach ($dirs as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                $name = strtr($file->getPathname(), '\\', '/');
                if (!str_ends_with($name, '.php') || str_contains($name, '/migrations/') || str_contains($name, '/tests/')) {
                    continue;
                }
                $source = (string) file_get_contents($name);
                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE);
                    foreach ($matches[1] as [$path, $offset]) {
                        $path = rtrim($path, '.');
                        if ($path === '' || $this->isStatic($path)) {
                            continue;
                        }
                        $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                        $refs[] = [substr($name, strlen($root) + 1) . ':' . $line, $path];
                    }
                }
            }
        }

        $this->assertNotEmpty($refs);

        return $refs;
    }

    private function isStatic(string $path): bool
    {
        foreach (self::STATIC_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
