<?php

declare(strict_types=1);

namespace App\Documents\Editor;

use Closure;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;

/**
 * Erzeugt die siebenstellige öffentliche `docid` eines Dokuments
 * (z. B. `0483920`), zufällig statt fortlaufend.
 *
 * Zufall statt Sequenz: die Kennung steht auf ausgestellten PDFs und wird
 * weitergereicht. Eine laufende Nummer würde nach außen verraten, wie
 * viele Dokumente eine Wache insgesamt oder in einem Zeitraum ausgestellt
 * hat, und trägt anders als ein Aktenzeichen keine Information, die das
 * rechtfertigt.
 *
 * Der Unique-Index auf `docid` ist die einzige verlässliche Sicherung
 * gegen einen Zufallstreffer — deshalb wird gewürfelt, eingefügt und bei
 * Kollision mit neuer Zahl wiederholt, statt vorher zu prüfen. Bei zehn
 * Millionen möglichen Werten ist der zweite Durchlauf die Ausnahme.
 */
final class DocumentId
{
    private const LENGTH = 7;
    private const MAX_ATTEMPTS = 3;

    /**
     * Legt ein Dokument mit einer freien Kennung an. `$create` bekommt die
     * Kennung und muss daraus den Datensatz erzeugen.
     *
     * @template T
     * @param  Closure(string): T  $create
     * @return T
     */
    public static function create(Closure $create): mixed
    {
        $last = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return Capsule::connection()->transaction(
                    static fn () => $create(self::candidate()),
                );
            } catch (QueryException $e) {
                if (!self::isUniqueViolation($e)) {
                    throw $e;
                }
                $last = $e;
            }
        }

        // Die Schleife verlässt die Methode sonst über return oder das
        // erneute throw; hier angekommen hat jeder Durchlauf $last gesetzt.
        throw $last;
    }

    private static function candidate(): string
    {
        $max = (10 ** self::LENGTH) - 1;

        return str_pad((string) random_int(0, $max), self::LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * MySQL und MariaDB melden eine verletzte Eindeutigkeit als 1062, der
     * SQLSTATE dazu ist 23000. Beides prüfen, weil der Treiber je nach
     * Konfiguration nur eines von beiden sauber durchreicht.
     */
    private static function isUniqueViolation(QueryException $e): bool
    {
        $code = $e->errorInfo[1] ?? null;
        if ($code === 1062) {
            return true;
        }

        return ($e->errorInfo[0] ?? null) === '23000';
    }
}
