<?php

declare(strict_types=1);

namespace App\Documents\Editor;

use App\Models\Personnel;
use App\Personnel\AccountLink;

/**
 * Welche Platzhalter eine Vorlage kennt und womit sie beim Ausstellen
 * gefüllt werden.
 *
 * Die Schlüssel stehen im Dokument als Chips (`{{mitarbeiter.name}}`); der
 * Editor bekommt Beschriftung und aktuellen Wert, der Renderer setzt den
 * Wert ein. Beim Ausstellen wandern die aufgelösten Werte nach
 * `frozen_values`. Ein Dokument von gestern zeigt den Dienstgrad von
 * gestern, auch wenn der Mitarbeiter inzwischen befördert wurde.
 *
 * Der Satz stammt aus den zehn Twig-Vorlagen des alten Systems: was die
 * dort benutzt haben, muss auch im Editor zur Verfügung stehen, sonst
 * lassen sie sich nicht nachbauen. Die geschlechtsabhängigen Formen sind
 * dabei keine Kosmetik: ohne sie steht in der Urkunde einer Frau „seine
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
            'mitarbeiter.name'             => 'Mitarbeiter: Name mit Titel',
            'mitarbeiter.titel'            => 'Mitarbeiter: Titel',
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
            'aussteller.name'              => 'Aussteller: Name mit Titel',
            'aussteller.titel'             => 'Aussteller: Titel',
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
     * Ergebnis. Im Editor steht der Platzhalter dann als „fehlt“, beim
     * Ausstellen fragt ignis nach, und im PDF bleibt die Stelle leer
     * (PdfGenerator, blankUnresolved).
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
            // Der Aussteller braucht nur Name, Titel, Dienstgrad und Zusatz; der
            // Rest wäre in einem Dokument über jemand anderen verwirrend.
            foreach (['aussteller.name', 'aussteller.titel', 'aussteller.dienstgrad', 'aussteller.zusatz'] as $key) {
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
     * Warum ein Platzhalter ohne Wert bleibt, je fehlendem Schlüssel ein
     * Satz für den Schreiber: was er (oder jemand anderes) eintragen muss,
     * damit der Wert kommt. Gleicher Kontext wie {@see resolve()};
     * Schlüssel mit Wert kommen nicht vor.
     *
     * @param  array{mitarbeiter?: Personnel|null, docid?: string|null}  $context
     * @return array<string,string>
     */
    public static function missingReasons(array $context = []): array
    {
        $resolved = self::resolve($context);
        $reasons  = [];

        $mitarbeiter = $context['mitarbeiter'] ?? null;
        $issuer      = self::issuer();

        foreach (array_keys(self::catalog()) as $name) {
            if (isset($resolved[$name])) {
                continue;
            }
            [$group, $field] = array_pad(explode('.', $name, 2), 2, '');

            $reasons[$name] = match ($group) {
                'mitarbeiter' => $mitarbeiter instanceof Personnel
                    ? self::personReason($field, 'Beim Mitarbeiter')
                    : 'Das Dokument hängt an keinem Mitarbeiter.',
                'aussteller' => $issuer instanceof Personnel
                    ? self::personReason($field, 'In deinem Mitarbeiterdatensatz')
                    : 'Dein Benutzerkonto ist mit keinem Mitarbeiter verknüpft. Das erledigt die Personalverwaltung.',
                'organisation' => 'In den Systemeinstellungen ist dazu nichts eingetragen.',
                'dokument'     => 'Das Dokument hat noch keine Kennung.',
                default        => 'Dafür ist kein Wert erfasst.',
            };
        }

        return $reasons;
    }

    private static function personReason(string $field, string $where): string
    {
        return $where . ' ' . match ($field) {
            'titel'            => 'ist kein Titel eingetragen.',
            'dienstgrad'       => 'ist kein Dienstgrad eingetragen.',
            'qualifikation_fw' => 'ist keine Qualifikation Feuerwehr eingetragen.',
            'qualifikation_rd' => 'ist keine Qualifikation Rettungsdienst eingetragen.',
            'dienstnummer'     => 'ist keine Dienstnummer eingetragen.',
            'zusatz'           => 'ist kein Zusatz eingetragen.',
            default            => 'fehlt dieser Wert.',
        };
    }

    /**
     * @return array<string,string>
     */
    private static function person(Personnel $p, string $prefix): array
    {
        // geschlecht: 0 männlich, 1 weiblich, dieselbe Kodierung, mit der
        // Rank::displayName() und die Quali-Modelle rechnen.
        $geschlecht = (int) $p->geschlecht;
        $weiblich   = $geschlecht === 1;

        $values = [
            "{$prefix}.name"        => $p->formalName(),
            "{$prefix}.anrede"      => $weiblich ? 'Frau' : 'Herr',
            "{$prefix}.briefanrede" => $weiblich ? 'Sehr geehrte Frau' : 'Sehr geehrter Herr',
            "{$prefix}.ihm_ihr"     => $weiblich ? 'ihr' : 'ihm',
            "{$prefix}.seine_ihre"  => $weiblich ? 'ihre' : 'seine',
            "{$prefix}.zum_zur"     => $weiblich ? 'zur' : 'zum',
        ];

        if (($p->zusatz ?? '') !== '') {
            $values["{$prefix}.zusatz"] = (string) $p->zusatz;
        }
        if ($p->titel !== null) {
            $values["{$prefix}.titel"] = (string) $p->titel->name;
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
     * Wer stellt aus: der mit dem angemeldeten Konto verknüpfte Mitarbeiter.
     * Ohne Mitarbeiterdatensatz bleiben die Aussteller-Felder leer. Der
     * Name aus dem Benutzerkonto allein wäre in einer Urkunde irreführend,
     * weil dort der Dienstgrad danebenstünde.
     */
    private static function issuer(): ?Personnel
    {
        return AccountLink::current();
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
