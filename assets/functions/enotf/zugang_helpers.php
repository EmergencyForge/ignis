<?php

/**
 * Shared helper functions for Zugang (IV access) management in eNOTF protocol
 * Used across multiple zugang protocol pages
 */

if (!function_exists('getCurrentZugaenge')) {
    /**
     * Parse JSON string to get current Zugaenge array
     *
     * @param string|null $zugangJson
     * @return array<int, array<string, mixed>>
     */
    function getCurrentZugaenge(?string $zugangJson): array
    {
        if (empty($zugangJson)) {
            return [];
        }

        $decoded = json_decode($zugangJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        if (isset($decoded['art'])) {
            return [$decoded];
        } elseif (is_array($decoded)) {
            return $decoded;
        }

        return [];
    }
}

if (!function_exists('hasZugangAtLocation')) {
    /**
     * Check if a Zugang exists at a specific location
     *
     * @param array<int, array<string, mixed>> $zugaenge
     */
    function hasZugangAtLocation(array $zugaenge, string $art, string $ort, string $seite): bool
    {
        foreach ($zugaenge as $zugang) {
            if (
                $zugang['art'] === $art &&
                $zugang['ort'] === $ort &&
                $zugang['seite'] === $seite
            ) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('addZugang')) {
    /**
     * Add or update a Zugang in the array
     *
     * @param array<int, array<string, mixed>> $zugaenge
     * @param array<string, mixed> $newZugang
     * @return array<int, array<string, mixed>>
     */
    function addZugang(array $zugaenge, array $newZugang): array
    {
        foreach ($zugaenge as $index => $zugang) {
            if (
                $zugang['art'] === $newZugang['art'] &&
                $zugang['ort'] === $newZugang['ort'] &&
                $zugang['seite'] === $newZugang['seite']
            ) {
                $zugaenge[$index] = $newZugang;
                return $zugaenge;
            }
        }

        $zugaenge[] = $newZugang;
        return $zugaenge;
    }
}

if (!function_exists('removeZugang')) {
    /**
     * Remove a Zugang from the array
     *
     * @param array<int, array<string, mixed>> $zugaenge
     * @return array<int, array<string, mixed>>
     */
    function removeZugang(array $zugaenge, string $art, string $ort, string $seite): array
    {
        return array_values(array_filter($zugaenge, function ($zugang) use ($art, $ort, $seite) {
            return !($zugang['art'] === $art &&
                $zugang['ort'] === $ort &&
                $zugang['seite'] === $seite);
        }));
    }
}

if (!function_exists('hasAnyZugang')) {
    /**
     * Check if any Zugang exists
     */
    function hasAnyZugang(?string $zugangJson): bool
    {
        $zugaenge = getCurrentZugaenge($zugangJson);
        return !empty($zugaenge);
    }
}

if (!function_exists('displayAllZugaenge')) {
    /**
     * Display all Zugaenge as HTML string
     */
    function displayAllZugaenge(?string $zugangJson): string
    {
        if ($zugangJson === null) {
            return '<em>Nicht gesetzt</em>';
        }

        if ($zugangJson === '0') {
            return 'Kein Zugang';
        }

        $zugaenge = getCurrentZugaenge($zugangJson);

        if (empty($zugaenge)) {
            return '<em>Keine gültigen Zugänge</em>';
        }

        $displays = [];
        foreach ($zugaenge as $zugang) {
            $artNames = ['pvk' => 'PVK', 'zvk' => 'ZVK', 'io' => 'intraossär'];
            $artName = $artNames[$zugang['art'] ?? ''] ?? ($zugang['art'] ?? '');
            $displays[] = sprintf(
                '%s %s - %s %s',
                $artName,
                $zugang['groesse'],
                $zugang['ort'],
                $zugang['seite']
            );
        }

        return implode(' | ', $displays);
    }
}
