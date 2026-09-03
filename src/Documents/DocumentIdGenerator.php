<?php

namespace App\Documents;

use Illuminate\Database\Capsule\Manager as Capsule;

class DocumentIdGenerator
{
    /**
     * Generiert eine eindeutige 12-stellige alphanumerische Dokument-ID
     * Format: XXXX-XXXX-XXXX (z.B. A7B2-K9M4-P3X8)
     *
     * @return string 12-stellige Dokument-ID mit Bindestrichen
     */
    public static function generate(): string
    {
        $maxAttempts = 100;
        $attempts = 0;

        do {
            $docId = self::generateRandomId();
            $attempts++;

            if ($attempts >= $maxAttempts) {
                throw new \Exception('Konnte keine eindeutige Dokument-ID generieren');
            }

            // Prüfe ob ID bereits existiert
            $exists = Capsule::table('intra_mitarbeiter_dokumente')
                ->where('docid', $docId)
                ->count() > 0;
        } while ($exists);

        return $docId;
    }

    /**
     * Generiert eine zufällige 12-stellige alphanumerische ID
     * 
     * @return string ID im Format XXXX-XXXX-XXXX
     */
    private static function generateRandomId(): string
    {
        // Zeichen-Pool (ohne ähnliche Zeichen wie 0/O, 1/I/l)
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $segments = [];

        for ($i = 0; $i < 3; $i++) {
            $segment = '';
            for ($j = 0; $j < 4; $j++) {
                $segment .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $segments[] = $segment;
        }

        return implode('-', $segments);
    }

    /**
     * Formatiert eine Dokument-ID mit Bindestrichen
     * 
     * @param string $docId Dokument-ID (mit oder ohne Bindestriche)
     * @return string Formatierte ID mit Bindestrichen
     */
    public static function format(string $docId): string
    {
        // Entferne alle Bindestriche
        $clean = str_replace('-', '', $docId);

        // Füge Bindestriche nach jeweils 4 Zeichen ein
        if (strlen($clean) === 12) {
            return substr($clean, 0, 4) . '-' . substr($clean, 4, 4) . '-' . substr($clean, 8, 4);
        }

        return $docId;
    }

    /**
     * Validiert eine Dokument-ID
     * 
     * @param string $docId Zu validierende ID
     * @return bool True wenn ID gültig ist
     */
    public static function validate(string $docId): bool
    {
        // Entferne Bindestriche für Validierung
        $clean = str_replace('-', '', $docId);

        // Prüfe Länge und erlaubte Zeichen
        return strlen($clean) === 12 && preg_match('/^[A-Z0-9]{12}$/', $clean);
    }
}
