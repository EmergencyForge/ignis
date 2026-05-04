<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Schlankes Request-Wrapper-Objekt für die Middleware-Pipeline.
 *
 * Kein PSR-7 — intraRP hält die HTTP-Abstraktion bewusst minimal, um
 * webspace-kompatibel und framework-frei zu bleiben. PSR-7 wird erst
 * eingeführt, falls externe PSR-15-Middleware benötigt wird.
 *
 * Das Objekt ist lesbar mutierbar (`withAttribute`) via Copy-on-Write,
 * damit Middlewares Context anreichern können (z.B. `user_id`) ohne die
 * globalen Superglobals zu verändern.
 */
final class Request
{
    /**
     * @param array<string,mixed>  $query    $_GET
     * @param array<string,mixed>  $post     $_POST
     * @param array<string,string> $server   $_SERVER (Subset)
     * @param array<string,string> $cookies  $_COOKIE
     * @param array<string,mixed>  $files    $_FILES
     * @param array<string,mixed>  $attrs    freie Middleware-Attribute
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $server = [],
        public readonly array $cookies = [],
        public readonly array $files = [],
        private readonly ?string $rawBody = null,
        private readonly array $attrs = [],
    ) {}

    /**
     * Baut ein Request-Objekt aus den Superglobals. Wird vom Front-Controller
     * (public/index.php) genau einmal pro Request gerufen.
     */
    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        $path   = parse_url($uri, PHP_URL_PATH) ?: '/';

        // BASE_PATH aus dem Pfad entfernen, damit Routen-Matching relativ
        // zur Installations-Root funktioniert. BASE_PATH kann in einem
        // Sub-Ordner-Setup "/intrarp/" sein.
        if (defined('BASE_PATH') && BASE_PATH !== '/' && BASE_PATH !== '') {
            $base = rtrim((string) BASE_PATH, '/');
            if ($base !== '' && str_starts_with($path, $base)) {
                $path = substr($path, strlen($base)) ?: '/';
            }
        }

        // Trailing-Slash-Normalisierung: /manv/ und /manv sollen dieselbe Route
        // matchen. Root "/" bleibt unverändert. Wirkt vor dem FastRoute-Lookup,
        // also funktioniert das automatisch fuer alle Routen — keine Sonderfaelle
        // pro Route mehr noetig.
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/') ?: '/';
        }

        return new self(
            method:  $method,
            path:    $path,
            query:   $_GET,
            post:    $_POST,
            server:  array_map('strval', array_filter($_SERVER, 'is_scalar')),
            cookies: $_COOKIE,
            files:   $_FILES,
            rawBody: null,
        );
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$key]) ? (string) $this->server[$key] : null;
    }

    public function isMethod(string $method): bool
    {
        return strcasecmp($this->method, $method) === 0;
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function isFiveM(): bool
    {
        return str_contains($this->userAgent(), 'CitizenFX');
    }

    /**
     * Liest den Raw-Body (z.B. JSON). Gecached, weil `php://input` nur
     * einmal lesbar ist.
     */
    public function rawBody(): string
    {
        if ($this->rawBody !== null) {
            return $this->rawBody;
        }
        return (string) file_get_contents('php://input');
    }

    /**
     * @return array<string,mixed>|null
     */
    public function json(): ?array
    {
        $decoded = json_decode($this->rawBody(), true);
        return is_array($decoded) ? $decoded : null;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attrs[$key] ?? $default;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $attrs = $this->attrs;
        $attrs[$key] = $value;

        return new self(
            $this->method,
            $this->path,
            $this->query,
            $this->post,
            $this->server,
            $this->cookies,
            $this->files,
            $this->rawBody,
            $attrs,
        );
    }
}
