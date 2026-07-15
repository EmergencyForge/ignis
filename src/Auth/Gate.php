<?php

declare(strict_types=1);

namespace App\Auth;

use App\Exceptions\AuthorizationException;

/**
 * Gate — kleiner statischer Facade für Authorization-Entscheidungen.
 *
 * Nutzt Policy-Klassen unter App\Policies\* als Single Source of Truth für
 * "wer darf was". Mappt Dot-Notation-Abilities auf Policy-Methoden:
 *
 *   Gate::allows('user.update', $targetUser)
 *     → \App\Policies\UserPolicy::update($targetUser)
 *
 *   Gate::allows('role.create')
 *     → \App\Policies\RolePolicy::create(null)
 *
 * Policy-Methoden lesen den aktuellen User aus $_SESSION (siehe Policy-Klassen).
 *
 *
 * Vorteile gegenüber direktem Permissions::check():
 *   - Eine Stelle pro Resource für ALLE Berechtigungsregeln
 *   - Semantische Aufrufe ('user.update' statt ['admin', 'users.edit'] + Priority-Check)
 *   - Kontextsensitiv: kann das Ziel-Objekt mit einbeziehen (z.B. Priority-Vergleich)
 */
class Gate
{
    /**
     * Prüft, ob die Ability erlaubt ist. Gibt false zurück bei unbekannten
     * Abilities (statt zu werfen) — Templates sollen sicher prüfen können.
     */
    public static function allows(string $ability, mixed $resource = null): bool
    {
        [$policyClass, $method] = self::resolve($ability);

        if ($policyClass === null) {
            return false;
        }

        try {
            return (bool) $policyClass::$method($resource);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function denies(string $ability, mixed $resource = null): bool
    {
        return !self::allows($ability, $resource);
    }

    /**
     * Wirft AuthorizationException wenn die Ability nicht erlaubt ist.
     * Wird von Controllern als "Hard-Stop" genutzt.
     *
     * @throws AuthorizationException
     */
    public static function authorize(string $ability, mixed $resource = null): void
    {
        if (!self::allows($ability, $resource)) {
            throw new AuthorizationException($ability);
        }
    }

    /**
     * Explizit registrierte Policies (Ressource => Policy-Klasse).
     * Ergänzt die Namespace-Konvention — Plugins registrieren ihre
     * Policies hierüber, weil sie außerhalb von App\Policies leben.
     *
     * @var array<string, class-string>
     */
    private static array $registered = [];

    /**
     * @param class-string $policyClass
     */
    public static function registerPolicy(string $resource, string $policyClass): void
    {
        self::$registered[strtolower($resource)] = $policyClass;
    }

    /**
     * @return array{0: class-string|null, 1: string}
     */
    private static function resolve(string $ability): array
    {
        if (!str_contains($ability, '.')) {
            return [null, ''];
        }
        [$resource, $method] = explode('.', $ability, 2);

        $policyClass = self::$registered[strtolower($resource)]
            ?? '\\App\\Policies\\' . ucfirst($resource) . 'Policy';

        if (!class_exists($policyClass)) {
            return [null, $method];
        }
        if (!method_exists($policyClass, $method)) {
            return [null, $method];
        }
        return [$policyClass, $method];
    }
}
