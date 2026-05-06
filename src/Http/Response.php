<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Schlankes Response-Objekt für die Middleware-Pipeline.
 *
 * Wird vom Router/Controller zurückgegeben. Die Pipeline emittiert es
 * am Ende via `send()` — das schreibt Header und Body in die echte PHP-
 * Response. Controller dürfen alternativ auch weiterhin direkt
 * `header()` + `echo` nutzen (Legacy-Kompat) und `Response::empty()`
 * zurückgeben; dann emittiert die Pipeline nichts mehr.
 */
final class Response
{
    /** @param array<string,string> $headers */
    public function __construct(
        public readonly int $status = 200,
        public readonly string $body = '',
        public readonly array $headers = [],
        /** Flag: true = Controller hat schon direkt ausgegeben, Pipeline überspringt send() */
        public readonly bool $emitted = false,
    ) {}

    public static function empty(): self
    {
        return new self(emitted: true);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            $status,
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, '', ['Location' => $location]);
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($status, $text, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;
        return new self($this->status, $this->body, $headers, $this->emitted);
    }

    public function withoutHeader(string $name): self
    {
        $headers = $this->headers;
        unset($headers[$name]);
        return new self($this->status, $this->body, $headers, $this->emitted);
    }

    /**
     * Schreibt Status, Header und Body in die PHP-Response. Idempotent:
     * wenn `emitted=true` gesetzt ist, passiert nichts (Controller hat
     * schon selbst ausgegeben).
     */
    public function send(): void
    {
        if ($this->emitted) {
            return;
        }

        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        if ($this->body !== '') {
            echo $this->body;
        }
    }
}
