<?php

declare(strict_types=1);

namespace App\Documents\Editor;

use App\Models\Personnel;
use App\Session\SessionManager;

/**
 * Welche Platzhalter eine Vorlage kennt und womit sie beim Ausstellen
 * gefüllt werden.
 *
 * Die Schlüssel stehen im Dokument als Chips (`{{mitarbeiter.name}}`); der
 * Editor bekommt Beschriftung und aktuellen Wert, der Renderer setzt den
 * Wert ein. Beim Ausstellen wandern die aufgelösten Werte nach
 * `frozen_values` — ein Dokument von gestern zeigt den Dienstgrad von
 * gestern, auch wenn der Mitarbeiter inzwischen befördert wurde.
 *
 * Der Satz stammt aus den zehn Twig-Vorlagen des alten Systems: was die
 * dort benutzt haben, muss auch im Editor zur Verfügung stehen, sonst
 * lassen sie sich nicht nachbauen. Die geschlechtsabhängigen Formen sind
 * dabei keine Kosmetik — ohne sie steht in der Urkunde einer Frau „seine
 * Ernennung".
 */
final class VariableCatalog
{
    /**
     * Schlüssel auf Beschriftung, in der Reihenfolge, in der der Editor
     * sie anbietet.
     *
     * @return array<string,string>
     */
    public static function catalog(): array
    {
        return [
            'mitarbeiter.name'             => 'Mitarbeiter: Name',
            'mitarbeiter.dienstgrad'       => 'Mitarbeiter: Dienstgrad',
            'mitarbeiter.qualifikation_fw' => 'Mitarbeiter: Qualifikation Feuerwehr',
            'mitarbeiter.qualifikation_rd' => 'Mitarbeiter: Qualifikation Rettungsdienst',
            'mitarbeiter.dienstnummer'     => 'Mitarbeiter: Dienstnummer',
            'mitarbeiter.geburtsdatum'     => 'Mitarbeiter: Geburtsdatum',
            'mitarbeiter.eintritt'         => 'Mitarbeiter: Eintrittsdatum',
            'mitarbeiter.anrede'           => 'Anrede (Herr/Frau)',
            'mitarbeiter.briefanrede'      => 'Briefanrede (Sehr geehrter Herr …)',
            'mitarbeiter.ihm_ihr'          => 'ihm / ihr',
            'mitarbeiter.seine_ihre'       => 'seine / ihre',
            'mitarbeiter.zum_zur'          => 'zum / zur',
            'aussteller.name'              => 'Aussteller: Name',
            'aussteller.dienstgrad'        => 'Aussteller: Dienstgrad',
            'aussteller.zusatz'            => 'Aussteller: Zusatz',
            'organisation.name'            => 'Organisation: Name',
            'organisation.art'             => 'Organisation: Art',
            'organisation.stadt'           => 'Organisation: Stadt',
            'organisation.strasse'         => 'Organisation: Straße',
            'organisation.plz'             => 'Organisation: PLZ',
            'dokument.kennung'             => 'Dokument: Kennung',
            'datum'                        => 'Datum (heute)',
        ];
    }

    /**
     * Die Werte zu den Schlüsseln. Was sich nicht auflösen lässt, fehlt im
     * Ergebnis; der Renderer lässt den Chip dann leer, statt einen
     * Platzhalter ins PDF zu schreiben.
     *
     * @param  array{mitarbeiter?: Personnel|null, docid?: string|null}  $context
     * @return array<string,string>
     */
    public static function resolve(array $context = []): array
    {
        $values = [];

        $mitarbeiter = $context['mitarbeiter'] ?? null;
        if ($mitarbeiter instanceof Personnel) {
            $values += self::person($mitarbeiter, 'mitarbeiter');
        }

        $issuer = self::issuer();
        if ($issuer instanceof Personnel) {
            $issuerValues = self::person($issuer, 'aussteller');
            // Der Aussteller braucht nur Name, Dienstgrad und Zusatz; der
            // Rest waere in einem Dokument ueber jemand anderen verwirrend.
            foreach (['aussteller.name', 'aussteller.dienstgrad', 'aussteller.zusatz'] as $key) {
                if (isset($issuerValues[$key])) {
                    $values[$key] = $issuerValues[$key];
                }
            }
        }

        $values += self::organisation();

        $docid = $context['docid'] ?? null;
        if (is_string($docid) && $docid !== '') {
            $values['dokument.kennung'] = $docid;
        }

        $values['datum'] = date('d.m.Y');

        return $values;
    }

    /**
     * @return array<string,string>
     */
    private static function person(Personnel $p, string $prefix): array
    {
        // geschlecht: 0 männlich, 1 weiblich — dieselbe Kodierung, mit der
        // Rank::displayName() und die Quali-Modelle rechnen.
        $geschlecht = (int) $p->geschlecht;
        $weiblich   = $geschlecht === 1;

        $values = [
            "{$prefix}.name"        => (string) $p->fullname,
            "{$prefix}.anrede"      => $weiblich ? 'Frau' : 'Herr',
            "{$prefix}.briefanrede" => $weiblich ? 'Sehr geehrte Frau' : 'Sehr geehrter Herr',
            "{$prefix}.ihm_ihr"     => $weiblich ? 'ihr' : 'ihm',
            "{$prefix}.seine_ihre"  => $weiblich ? 'ihre' : 'seine',
            "{$prefix}.zum_zur"     => $weiblich ? 'zur' : 'zum',
        ];

        if (($p->zusatz ?? '') !== '') {
            $values["{$prefix}.zusatz"] = (string) $p->zusatz;
        }
        if (($p->dienstnr ?? '') !== '') {
            $values["{$prefix}.dienstnummer"] = (string) $p->dienstnr;
        }
        // Geburts- und Eintrittsdatum sind Pflichtspalten, deshalb ohne
        // Null-Pruefung.
        $values["{$prefix}.geburtsdatum"] = self::date($p->gebdatum);
        $values["{$prefix}.eintritt"]     = self::date($p->einstdatum);
        if ($p->dienstgradModel !== null) {
            $values["{$prefix}.dienstgrad"] = $p->dienstgradModel->displayName($geschlecht);
        }
        if ($p->fwQualiModel !== null) {
            $values["{$prefix}.qualifikation_fw"] = $p->fwQualiModel->displayName($geschlecht);
        }
        if ($p->rdQualiModel !== null) {
            $values["{$prefix}.qualifikation_rd"] = $p->rdQualiModel->displayName($geschlecht);
        }

        return $values;
    }

    /**
     * Wer stellt aus: der angemeldete Benutzer über seine Discord-Kennung.
     * Ohne Mitarbeiterdatensatz bleiben die Aussteller-Felder leer — der
     * Name aus dem Benutzerkonto allein wäre in einer Urkunde irreführend,
     * weil dort der Dienstgrad danebenstünde.
     */
    private static function issuer(): ?Personnel
    {
        $discordTag = SessionManager::get('discordtag');
        if (!is_string($discordTag) || $discordTag === '') {
            return null;
        }

        return Personnel::query()->where('discordtag', $discordTag)->first();
    }

    /**
     * @return array<string,string>
     */
    private static function organisation(): array
    {
        $map = [
            'organisation.name'    => 'SERVER_NAME',
            'organisation.art'     => 'RP_ORGTYPE',
            'organisation.stadt'   => 'SERVER_CITY',
            'organisation.strasse' => 'RP_STREET',
            'organisation.plz'     => 'RP_ZIP',
        ];

        $values = [];
        foreach ($map as $key => $constant) {
            if (defined($constant) && (string) constant($constant) !== '') {
                $values[$key] = (string) constant($constant);
            }
        }

        return $values;
    }

    private static function date(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d.m.Y');
        }

        $time = strtotime((string) $value);

        return $time === false ? (string) $value : date('d.m.Y', $time);
    }
}
