<?php

declare(strict_types=1);

namespace App\Documents\Editor;

use EmergencyForge\Editor\Layout;

/**
 * Die zehn Systemvorlagen, die ignis mitbringt, als Dokumentpakete.
 *
 * Der Wortlaut stammt aus den Twig-Vorlagen des alten Canvas-Systems.
 * Urkunden und Zertifikate liegen quer, mit breitem Rand und ohne
 * Textbereich: ausgefüllt werden nur die Felder im festen Text (der neue
 * Dienstgrad einer Beförderung stand nie im Mitarbeiterdatensatz). Der
 * Hinweis, der früher am Seitenfuß klebte, steht in der Fußzeile. Die
 * Schreiben bleiben hochkant, der Grund ist ein Textbereich.
 *
 * Nur frische Installationen bekommen sie
 * (`20260914000002_seed_editor_templates`); bestehende Vorlagen stellt
 * `20261109100001_editor_template_versions_letterheads` um, ohne sie zu
 * überschreiben.
 */
final class SystemTemplates
{
    /**
     * Auswahl der Lehrgangszertifikate, wie sie das alte System mitbrachte.
     * `male` und `female` setzt der Editor nach dem Geschlecht ein.
     */
    public const LEHRGAENGE = [
        ['label' => 'Brandmeister/-in', 'male' => 'Brandmeister', 'female' => 'Brandmeisterin'],
        ['label' => 'Gruppenführer/-in', 'male' => 'Gruppenführer', 'female' => 'Gruppenführerin'],
        ['label' => 'Zugführer/-in', 'male' => 'Zugführer', 'female' => 'Zugführerin'],
        ['label' => 'Sonderfahrzeug-Maschinist/-in', 'male' => 'Sonderfahrzeug-Maschinist', 'female' => 'Sonderfahrzeug-Maschinistin'],
    ];

    public const FACHDIENSTE = [
        ['label' => 'Leitstellen-Disponent/-in', 'male' => 'Leitstellen-Disponent', 'female' => 'Leitstellen-Disponentin'],
        ['label' => 'Helfergrundmodul (SEG)'],
        ['label' => 'SEG-Sanitäter/-in', 'male' => 'SEG-Sanitäter', 'female' => 'SEG-Sanitäterin'],
        ['label' => 'Gruppenführer/-in-BevS', 'male' => 'Gruppenführer-BevS', 'female' => 'Gruppenführerin-BevS'],
        ['label' => 'HEMS-TC'],
        ['label' => 'Luftrettungspilot/-in', 'male' => 'Luftrettungspilot', 'female' => 'Luftrettungspilotin'],
    ];

    public const TRAINING_QUALIFICATION = 'Ausbildungszertifikat (Lehrgang)';
    public const TRAINING_COURSE        = 'Lehrgangszertifikat (Lehrgang)';

    private const CERTIFICATE_LAYOUT = [
        'orientation'    => 'landscape',
        'margins'        => ['top' => 25, 'right' => 35, 'bottom' => 30, 'left' => 35],
        'footerDistance' => 10,
    ];

    /**
     * Name auf Kategorie und Dokumentpaket.
     *
     * @return array<string,array{category: string, content: array<string,mixed>}>
     */
    public static function all(): array
    {
        return (new self())->templates();
    }

    // ── Bausteine ─────────────────────────────────────────────────────

    /**
     * @param  list<array<string,mixed>>       $body
     * @param  array<string,mixed>             $layout
     * @param  list<array<string,mixed>>|null  $header
     * @param  list<array<string,mixed>>|null  $footer
     * @return array<string,mixed>
     */
    private function package(array $body, array $layout, ?array $header = null, ?array $footer = null): array
    {
        $doc = static fn (?array $content): ?array => $content === null ? null : ['type' => 'doc', 'content' => $content];

        return [
            'format' => 2,
            'layout' => Layout::normalize($layout),
            'body'   => $doc($body),
            'header' => ['first' => null, 'rest' => $doc($header)],
            'footer' => ['first' => null, 'rest' => $doc($footer)],
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $content
     * @return array<string,mixed>
     */
    private function p(array $content = [], ?string $align = null): array
    {
        $node = ['type' => 'paragraph'];
        if ($align !== null) {
            $node['attrs'] = ['textAlign' => $align];
        }
        if ($content !== []) {
            $node['content'] = $content;
        }

        return $node;
    }

    /**
     * @param  array<int,array<string,mixed>>  $content
     * @return array<string,mixed>
     */
    private function h(int $level, array $content, ?string $align = 'center'): array
    {
        $attrs = ['level' => $level];
        if ($align !== null) {
            $attrs['textAlign'] = $align;
        }

        return ['type' => 'heading', 'attrs' => $attrs, 'content' => $content];
    }

    /**
     * @param  list<string>  $marks
     * @return array<string,mixed>
     */
    private function t(string $text, array $marks = []): array
    {
        $node = ['type' => 'text', 'text' => $text];
        if ($marks !== []) {
            $node['marks'] = array_map(static fn (string $m): array => ['type' => $m], $marks);
        }

        return $node;
    }

    /** @return array<string,mixed> */
    private function v(string $name): array
    {
        return ['type' => 'docVariable', 'attrs' => ['name' => $name]];
    }

    /**
     * @param  list<array<string,string>>  $options  Auswahl statt Freitext
     * @param  string|null                $source   Auswahl aus den Stammdaten, siehe {@see FieldChoices}
     * @return array<string,mixed>
     */
    private function f(string $id, string $label, string $kind = 'line', array $options = [], ?string $source = null): array
    {
        $attrs = [
            'fieldId'   => $id,
            'label'     => $label,
            'kind'      => $kind,
            'required'  => true,
            'maxLength' => 120,
            'value'     => '',
            'who'       => null,
        ];
        if ($options !== []) {
            $attrs['options'] = $options;
        }
        if ($source !== null) {
            $attrs['optionsSource'] = $source;
        }

        return ['type' => 'docField', 'attrs' => $attrs];
    }

    /**
     * Pflicht-Textbereich für den Grund eines Schreibens.
     *
     * @return array<string,mixed>
     */
    private function reason(string $id, string $label): array
    {
        return [
            'type'    => 'docInput',
            'attrs'   => [
                'inputId'     => $id,
                'kind'        => 'text',
                'label'       => $label,
                'format'      => 'basic',
                'start'       => 'empty',
                'placeholder' => '',
                'required'    => true,
                'minLength'   => null,
                'who'         => null,
            ],
            'content' => [$this->p()],
        ];
    }

    /**
     * Urkunde oder Zertifikat: Kopf, Text in der Mitte, Abbinder.
     *
     * @param  list<array<string,mixed>>  $middle
     * @return array<string,mixed>
     */
    private function certificate(string $titel, array $middle): array
    {
        $body = array_merge(
            [
                $this->p([$this->v('organisation.art'), $this->t(' '), $this->v('organisation.stadt')], 'center'),
                $this->h(1, [$this->t($titel)]),
            ],
            $middle,
            [
                $this->p(),
                $this->p([
                    $this->v('organisation.stadt'), $this->t(', den '), $this->v('datum'),
                    $this->t(' · Zeichen: '), $this->v('dokument.kennung'),
                ], 'center'),
                $this->p([$this->v('aussteller.name'), $this->t(', '), $this->v('aussteller.dienstgrad')], 'center'),
            ],
        );

        $footer = [
            $this->p([$this->t('Dieses Dokument wurde elektronisch erstellt und ist ohne Unterschrift gültig.', ['italic'])], 'center'),
            $this->p([
                $this->t('Diese fiktive Urkunde ist lediglich für das Roleplay-Projekt „', ['italic']),
                $this->v('organisation.name'),
                $this->t('" ausgelegt. Der Besitz dieser Urkunde befugt in keinster Weise zum Führen einer echten Qualifikation.', ['italic']),
            ], 'center'),
        ];

        return $this->package($body, self::CERTIFICATE_LAYOUT, null, $footer);
    }

    /**
     * Schreiben: Absender im Kopf, Anschrift, Betreff, fester Text, Grund
     * als Textbereich, Abbinder.
     *
     * @param  list<array<string,mixed>>  $text
     * @return array<string,mixed>
     */
    private function letter(string $betreff, array $text, string $reasonLabel): array
    {
        $header = [
            $this->p([$this->v('organisation.art'), $this->t(' '), $this->v('organisation.stadt')], 'right'),
            $this->p([
                $this->v('organisation.strasse'), $this->t(', '),
                $this->v('organisation.plz'), $this->t(' '), $this->v('organisation.stadt'),
            ], 'right'),
        ];

        $body = array_merge(
            [
                $this->p([$this->v('mitarbeiter.anrede'), $this->t(' '), $this->v('mitarbeiter.name')]),
                $this->p([$this->v('organisation.plz'), $this->t(' '), $this->v('organisation.stadt')]),
                $this->p(),
                $this->p([$this->v('organisation.stadt'), $this->t(', den '), $this->v('datum')], 'right'),
                $this->p([$this->t('Ihr Zeichen: ', ['bold']), $this->v('dokument.kennung')], 'right'),
                $this->h(2, [$this->t($betreff)], null),
                $this->p([
                    $this->v('mitarbeiter.briefanrede'), $this->t(' '),
                    $this->v('mitarbeiter.name'), $this->t(','),
                ]),
            ],
            $text,
            [
                $this->reason('grund', $reasonLabel),
                $this->p(),
                $this->p([$this->v('aussteller.name')]),
                $this->p([$this->v('aussteller.dienstgrad')]),
            ],
        );

        $footer = [
            $this->p([$this->t('Dieses Dokument wurde elektronisch erstellt und ist ohne Unterschrift gültig.', ['italic'])], 'center'),
        ];

        return $this->package($body, [], $header, $footer);
    }

    /**
     * Name der Person, darunter ihr Geburtsdatum.
     *
     * @return list<array<string,mixed>>
     */
    private function person(bool $geburtsdatum = true): array
    {
        $nodes = [$this->p([$this->v('mitarbeiter.name')], 'center')];
        if ($geburtsdatum) {
            $nodes[] = $this->p([$this->t('» geb. am '), $this->v('mitarbeiter.geburtsdatum'), $this->t(' «')], 'center');
        }

        return $nodes;
    }

    /**
     * @return array<string,array{category: string, content: array<string,mixed>}>
     */
    private function templates(): array
    {
        $stadt = [$this->t('Im Namen der Stadt '), $this->v('organisation.stadt')];

        return [
            'Beförderungsurkunde' => ['category' => 'Urkunde', 'content' => $this->certificate('URKUNDE', [
                $this->p($stadt, 'center'),
                $this->p([$this->t('wird '), $this->v('mitarbeiter.anrede')], 'center'),
                ...$this->person(),
                $this->p([$this->t('mit sofortiger Wirkung befördert und '), $this->v('mitarbeiter.zum_zur')], 'center'),
                $this->p([$this->f('dienstgrad', 'Neuer Dienstgrad', 'choice', source: FieldChoices::RANKS)], 'center'),
                $this->p([
                    $this->t('der '), $this->v('organisation.art'), $this->t(' '),
                    $this->v('organisation.stadt'), $this->t(' ernannt.'),
                ], 'center'),
            ])],

            'Ernennungsurkunde' => ['category' => 'Urkunde', 'content' => $this->certificate('URKUNDE', [
                $this->p($stadt, 'center'),
                $this->p([$this->t('wird '), $this->v('mitarbeiter.anrede')], 'center'),
                ...$this->person(),
                $this->p([$this->t('mit sofortiger Wirkung in das Beamtenverhältnis auf Widerruf berufen und im Dienstverhältnis als')], 'center'),
                $this->p([$this->f('dienstgrad', 'Dienstgrad', 'choice', source: FieldChoices::RANKS)], 'center'),
                $this->p([
                    $this->t('zum Beamten der '), $this->v('organisation.art'), $this->t(' '),
                    $this->v('organisation.stadt'), $this->t(' ernannt.'),
                ], 'center'),
            ])],

            'Entlassungsurkunde' => ['category' => 'Urkunde', 'content' => $this->certificate('URKUNDE', [
                $this->p($stadt, 'center'),
                $this->p([$this->t('wird '), $this->v('mitarbeiter.anrede')], 'center'),
                ...$this->person(false),
                $this->p([$this->t('auf eigenes Verlangen mit sofortiger Wirkung aus dem Beamtenverhältnis sowie aus dem Dienst entlassen.')], 'center'),
                $this->p([
                    $this->t('Für '), $this->v('mitarbeiter.seine_ihre'),
                    $this->t(' geleisteten Dienste sprechen wir '), $this->v('mitarbeiter.ihm_ihr'),
                    $this->t(' Dank und Anerkennung aus.'),
                ], 'center'),
            ])],

            'Ausbildungszertifikat' => ['category' => 'Zertifikat', 'content' => $this->certificate('ZERTIFIKAT', [
                $this->p([$this->t('Hiermit wird bestätigt,')], 'center'),
                $this->p([$this->t('dass '), $this->v('mitarbeiter.anrede')], 'center'),
                ...$this->person(),
                $this->p([$this->t('die Prüfung '), $this->v('mitarbeiter.zum_zur')], 'center'),
                $this->p([$this->f('qualifikation', 'Berufsbezeichnung', 'choice', source: FieldChoices::AMB_SKILLS)], 'center'),
                $this->p([
                    $this->t('am '), $this->f('pruefungsdatum', 'Prüfungsdatum', 'date'),
                    $this->t(' bestanden und somit die Genehmigung zum Führen der Qualifikation und oben genannter Berufsbezeichnung erworben hat.'),
                ], 'center'),
            ])],

            'Lehrgangszertifikat' => ['category' => 'Zertifikat', 'content' => $this->certificate('ZERTIFIKAT', [
                $this->p([$this->t('Hiermit wird bestätigt,')], 'center'),
                $this->p([$this->t('dass '), $this->v('mitarbeiter.anrede')], 'center'),
                ...$this->person(),
                $this->p([$this->t('den Lehrgang '), $this->v('mitarbeiter.zum_zur')], 'center'),
                $this->p([$this->f('qualifikation', 'Lehrgang', 'choice', self::LEHRGAENGE)], 'center'),
                $this->p([
                    $this->t('am '), $this->f('pruefungsdatum', 'Abschlussdatum', 'date'),
                    $this->t(' erfolgreich absolviert hat.'),
                ], 'center'),
            ])],

            'Lehrgangszertifikat Fachdienste' => ['category' => 'Zertifikat', 'content' => $this->certificate('ZERTIFIKAT', [
                $this->p([$this->t('Hiermit wird bestätigt,')], 'center'),
                $this->p([$this->t('dass '), $this->v('mitarbeiter.anrede')], 'center'),
                ...$this->person(),
                $this->p([$this->t('die Prüfung '), $this->v('mitarbeiter.zum_zur')], 'center'),
                $this->p([$this->f('qualifikation', 'Fachdienst-Qualifikation', 'choice', self::FACHDIENSTE)], 'center'),
                $this->p([
                    $this->t('am '), $this->f('pruefungsdatum', 'Prüfungsdatum', 'date'),
                    $this->t(' erfolgreich absolviert hat.'),
                ], 'center'),
            ])],

            // Fassungen für den Lehrgangsabschluss: dieselben Sätze, aber aus
            // den Lehrgangs-Variablen statt aus Auswahlfeldern, damit das
            // System sie ohne Rückfrage ausstellen kann.
            self::TRAINING_QUALIFICATION => ['category' => 'Zertifikat', 'content' => $this->certificate('ZERTIFIKAT', [
                $this->p([$this->t('Hiermit wird bestätigt,')], 'center'),
                $this->p([$this->t('dass '), $this->v('mitarbeiter.anrede')], 'center'),
                ...$this->person(),
                $this->p([$this->t('die Prüfung '), $this->v('mitarbeiter.zum_zur')], 'center'),
                $this->p([$this->v('qualifikation.name')], 'center'),
                $this->p([
                    $this->t('am '), $this->v('pruefung.datum'), $this->t(' im '), $this->v('lehrgang.titel'),
                    $this->t(' bestanden und somit die Genehmigung zum Führen der Qualifikation und oben genannter Berufsbezeichnung erworben hat.'),
                ], 'center'),
            ])],

            self::TRAINING_COURSE => ['category' => 'Zertifikat', 'content' => $this->certificate('ZERTIFIKAT', [
                $this->p([$this->t('Hiermit wird bestätigt,')], 'center'),
                $this->p([$this->t('dass '), $this->v('mitarbeiter.anrede')], 'center'),
                ...$this->person(),
                $this->p([$this->t('den Lehrgang')], 'center'),
                $this->p([$this->v('lehrgang.titel'), $this->t(' ('), $this->v('lehrgang.art'), $this->t(')')], 'center'),
                $this->p([
                    $this->t('vom '), $this->v('lehrgang.beginn'), $this->t(' bis '), $this->v('lehrgang.ende'),
                    $this->t(' besucht und die Prüfung am '), $this->v('pruefung.datum'),
                    $this->t(' mit '), $this->v('pruefung.ergebnis'), $this->t(' bestanden hat.'),
                ], 'center'),
            ])],

            'Schriftliche Abmahnung' => ['category' => 'Schreiben', 'content' => $this->letter('Schriftliche Abmahnung', [
                $this->p([$this->t('hiermit werden Sie schriftlich bezüglich der unten genannten Vorfälle abgemahnt.')]),
                $this->p([$this->t('Sollten Sie weiterhin dienstlich auffällig werden, müssen Sie mit weiteren dienstrechtlichen Konsequenzen bis hin zur Dienstentfernung rechnen.')]),
                $this->p([$this->t('Der Grund der Abmahnung lautet:', ['bold'])]),
            ], 'Grund der Abmahnung')],

            'Vorläufige Dienstenthebung' => ['category' => 'Schreiben', 'content' => $this->letter('Vorläufige Dienstenthebung', [
                $this->p([$this->t('mit diesem Schreiben informieren wir Sie über Ihre vorläufige Dienstenthebung.')]),
                $this->p([
                    $this->t('Ab sofort sind Sie '),
                    $this->f('zeitraum', 'Zeitraum der Suspendierung'),
                    $this->t(' suspendiert. In diesem Zeitraum sind Sie von Ihren dienstlichen Pflichten entbunden.'),
                ]),
                $this->p([$this->t('Der Grund der Suspendierung lautet:', ['bold'])]),
            ], 'Grund der Suspendierung')],

            'Dienstentfernung' => ['category' => 'Schreiben', 'content' => $this->letter('Dienstentfernung', [
                $this->p([$this->t('mit diesem Schreiben informieren wir Sie über Ihre Entfernung aus dem Beamtendienst.')]),
                $this->p([$this->t('Mit sofortiger Wirkung ist das Arbeits-/Beamtenverhältnis beendigt. Eine Wiedereinstellung ist ausgeschlossen.')]),
                $this->p([$this->t('Der Grund der Dienstentfernung lautet:', ['bold'])]),
            ], 'Grund der Dienstentfernung')],

            'Außerordentliche Kündigung' => ['category' => 'Schreiben', 'content' => $this->letter('Außerordentliche Kündigung', [
                $this->p([$this->t('mit diesem Schreiben informieren wir Sie über Ihre außerordentliche Kündigung.')]),
                $this->p([$this->t('Mit sofortiger Wirkung ist das Arbeitsverhältnis beendigt. Eine Wiedereinstellung ist ausgeschlossen.')]),
                $this->p([$this->t('Der Grund für die Kündigung lautet:', ['bold'])]),
            ], 'Grund der Kündigung')],
        ];
    }
}
