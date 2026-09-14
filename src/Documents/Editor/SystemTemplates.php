<?php

declare(strict_types=1);

namespace App\Documents\Editor;

/**
 * Die zehn Systemvorlagen, die ignis mitbringt — als ProseMirror-Dokumente
 * fuer den Editor.
 *
 * Der Wortlaut stammt woertlich aus den Twig-Vorlagen des alten
 * Canvas-Systems. Was der Editor nicht kann, faellt weg: der rote Rahmen,
 * das eingebettete Wappen und die am Seitenfuss festgenagelte Fussnote. Er
 * setzt fliessenden Text, keine absolut positionierten Kaesten.
 *
 * Was die alten Vorlagen ueber Zusatzfelder geloest haben, sind hier
 * ausfuellbare Felder im gesperrten Text: der neue Dienstgrad einer
 * Befoerderung stand nie im Mitarbeiterdatensatz, er wird beim Ausstellen
 * eingetragen. Bei den Schreiben ist der Grund ein freier Abschnitt — dort
 * schreibt der Aussteller Fliesstext, und nur dort.
 *
 * Die Migration `20260914000002_seed_editor_templates` legt sie an;
 * getestet werden sie ueber den Renderer, damit ein Tippfehler in der
 * Struktur nicht erst beim Ausstellen auffaellt.
 */
final class SystemTemplates
{
    /**
     * Name auf Kategorie und Abschnitte.
     *
     * @return array<string,array{category: string, sections: list<array<string,mixed>>}>
     */
    public static function all(): array
    {
        return (new self())->templates();
    }

    // ── Bausteine ─────────────────────────────────────────────────────

    /**
     * @param  array<int,array<string,mixed>>  $content
     * @return array<string,mixed>
     */
    private function locked(array $content): array
    {
        return [
            'type'    => 'docSection',
            'attrs'   => ['mode' => 'locked', 'sectionId' => $this->uuid()],
            'content' => $content,
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $content
     * @return array<string,mixed>
     */
    private function free(array $content, string $title = ''): array
    {
        $attrs = ['mode' => 'free', 'sectionId' => $this->uuid()];
        if ($title !== '') {
            $attrs['title'] = $title;
        }

        return ['type' => 'docSection', 'attrs' => $attrs, 'content' => $content];
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

    /** @return array<string,mixed> */
    private function f(string $id, string $label, bool $required = true, int $maxLength = 120): array
    {
        return ['type' => 'docField', 'attrs' => [
            'fieldId'   => $id,
            'label'     => $label,
            'required'  => $required,
            'maxLength' => $maxLength,
            'value'     => '',
        ]];
    }

    private function uuid(): string
    {
        $d    = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        $h    = bin2hex($d);

        return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20, 12));
    }

    /**
     * Kopfzeile, die in allen zehn Vorlagen gleich aussieht.
     *
     * @return list<array<string,mixed>>
     */
    private function head(string $titel): array
    {
        return [
            $this->p([$this->v('organisation.art'), $this->t(' '), $this->v('organisation.stadt')], 'center'),
            $this->h(1, [$this->t($titel)]),
        ];
    }

    /**
     * Abbinder mit Ort, Datum, Zeichen und Aussteller.
     *
     * @return list<array<string,mixed>>
     */
    private function foot(bool $disclaimer): array
    {
        $nodes = [
            $this->p(),
            $this->p([$this->v('organisation.stadt'), $this->t(', den '), $this->v('datum')]),
            $this->p([$this->t('Ihr Zeichen: ', ['bold']), $this->v('dokument.kennung')]),
            $this->p(),
            $this->p([$this->v('aussteller.name', )], null),
            $this->p([$this->v('aussteller.dienstgrad')]),
            $this->p([$this->t('— Dieses Dokument wurde elektronisch erstellt und ist ohne Unterschrift gültig. —', ['italic'])]),
        ];

        if ($disclaimer) {
            $nodes[] = $this->p([$this->t(
                'Diese fiktive Urkunde ist lediglich für das Roleplay-Projekt „' ,
                ['italic'],
            ), $this->v('organisation.name'), $this->t(
                '" ausgelegt. Der Besitz dieser Urkunde befugt in keinster Weise zum Führen einer echten Qualifikation.',
                ['italic'],
            )]);
        }

        return $nodes;
    }

    /**
     * Anschrift und Betreff der vier Schreiben.
     *
     * @return list<array<string,mixed>>
     */
    private function letterHead(string $betreff): array
    {
        return [
            $this->p([$this->v('organisation.art'), $this->t(' '), $this->v('organisation.stadt')]),
            $this->p([$this->v('organisation.strasse')]),
            $this->p([$this->v('organisation.plz'), $this->t(' '), $this->v('organisation.stadt')]),
            $this->p(),
            $this->p([$this->v('mitarbeiter.anrede'), $this->t(' '), $this->v('mitarbeiter.name')]),
            $this->p([$this->v('organisation.plz'), $this->t(' '), $this->v('organisation.stadt')]),
            $this->p(),
            $this->h(2, [$this->t($betreff)], null),
            $this->p([
                $this->v('mitarbeiter.briefanrede'), $this->t(' '),
                $this->v('mitarbeiter.name'), $this->t(','),
            ]),
        ];
    }

    /**
     * @return array<string,array{category: string, sections: list<array<string,mixed>>}>
     */
    private function templates(): array
    {
        return [
            'Beförderungsurkunde' => ['category' => 'Urkunde', 'sections' => [
                $this->locked(array_merge(
                    $this->head('URKUNDE'),
                    [
                        $this->p([$this->t('Im Namen der Stadt '), $this->v('organisation.stadt')], 'center'),
                        $this->p([$this->t('wird '), $this->v('mitarbeiter.anrede')], 'center'),
                        $this->p([$this->v('mitarbeiter.name')], 'center'),
                        $this->p([$this->t('» geb. am '), $this->v('mitarbeiter.geburtsdatum'), $this->t(' «')], 'center'),
                        $this->p([
                            $this->t('mit sofortiger Wirkung befördert und '),
                            $this->v('mitarbeiter.zum_zur'),
                        ], 'center'),
                        $this->p([$this->f('dienstgrad', 'Neuer Dienstgrad')], 'center'),
                        $this->p([
                            $this->t('der '), $this->v('organisation.art'), $this->t(' '),
                            $this->v('organisation.stadt'), $this->t(' ernannt.'),
                        ], 'center'),
                    ],
                    $this->foot(true),
                )),
            ]],

            'Ernennungsurkunde' => ['category' => 'Urkunde', 'sections' => [
                $this->locked(array_merge(
                    $this->head('URKUNDE'),
                    [
                        $this->p([$this->t('Im Namen der Stadt '), $this->v('organisation.stadt')], 'center'),
                        $this->p([$this->t('wird '), $this->v('mitarbeiter.anrede')], 'center'),
                        $this->p([$this->v('mitarbeiter.name')], 'center'),
                        $this->p([$this->t('» geb. am '), $this->v('mitarbeiter.geburtsdatum'), $this->t(' «')], 'center'),
                        $this->p([$this->t('mit sofortiger Wirkung in das Beamtenverhältnis auf Widerruf berufen und im Dienstverhältnis als')], 'center'),
                        $this->p([$this->f('dienstgrad', 'Dienstgrad')], 'center'),
                        $this->p([
                            $this->t('zum Beamten der '), $this->v('organisation.art'), $this->t(' '),
                            $this->v('organisation.stadt'), $this->t(' ernannt.'),
                        ], 'center'),
                    ],
                    $this->foot(true),
                )),
            ]],

            'Entlassungsurkunde' => ['category' => 'Urkunde', 'sections' => [
                $this->locked(array_merge(
                    $this->head('URKUNDE'),
                    [
                        $this->p([$this->t('Im Namen der Stadt '), $this->v('organisation.stadt')], 'center'),
                        $this->p([$this->t('wird '), $this->v('mitarbeiter.anrede')], 'center'),
                        $this->p([$this->v('mitarbeiter.name')], 'center'),
                        $this->p([$this->t('auf eigenes Verlangen mit sofortiger Wirkung aus dem Beamtenverhältnis sowie aus dem Dienst entlassen.')], 'center'),
                        $this->p([
                            $this->t('Für '), $this->v('mitarbeiter.seine_ihre'),
                            $this->t(' geleisteten Dienste sprechen wir '), $this->v('mitarbeiter.ihm_ihr'),
                            $this->t(' Dank und Anerkennung aus.'),
                        ], 'center'),
                    ],
                    $this->foot(true),
                )),
            ]],

            'Ausbildungszertifikat' => ['category' => 'Zertifikat', 'sections' => [
                $this->locked(array_merge(
                    $this->head('ZERTIFIKAT'),
                    [
                        $this->p([$this->t('Hiermit wird bestätigt,')], 'center'),
                        $this->p([$this->t('dass '), $this->v('mitarbeiter.anrede')], 'center'),
                        $this->p([$this->v('mitarbeiter.name')], 'center'),
                        $this->p([$this->t('» geb. am '), $this->v('mitarbeiter.geburtsdatum'), $this->t(' «')], 'center'),
                        $this->p([$this->t('die Prüfung '), $this->v('mitarbeiter.zum_zur')], 'center'),
                        $this->p([$this->f('qualifikation', 'Berufsbezeichnung')], 'center'),
                        $this->p([
                            $this->t('am '), $this->f('pruefungsdatum', 'Prüfungsdatum', true, 20),
                            $this->t(' bestanden und somit die Genehmigung zum Führen der Qualifikation und oben genannter Berufsbezeichnung erworben hat.'),
                        ], 'center'),
                    ],
                    $this->foot(true),
                )),
            ]],

            'Lehrgangszertifikat' => ['category' => 'Zertifikat', 'sections' => [
                $this->locked(array_merge(
                    $this->head('ZERTIFIKAT'),
                    [
                        $this->p([$this->t('Hiermit wird bestätigt,')], 'center'),
                        $this->p([$this->t('dass '), $this->v('mitarbeiter.anrede')], 'center'),
                        $this->p([$this->v('mitarbeiter.name')], 'center'),
                        $this->p([$this->t('» geb. am '), $this->v('mitarbeiter.geburtsdatum'), $this->t(' «')], 'center'),
                        $this->p([$this->t('den Lehrgang '), $this->v('mitarbeiter.zum_zur')], 'center'),
                        $this->p([$this->f('qualifikation', 'Lehrgang')], 'center'),
                        $this->p([
                            $this->t('am '), $this->f('pruefungsdatum', 'Abschlussdatum', true, 20),
                            $this->t(' erfolgreich absolviert hat.'),
                        ], 'center'),
                    ],
                    $this->foot(true),
                )),
            ]],

            'Lehrgangszertifikat Fachdienste' => ['category' => 'Zertifikat', 'sections' => [
                $this->locked(array_merge(
                    $this->head('ZERTIFIKAT'),
                    [
                        $this->p([$this->t('Hiermit wird bestätigt,')], 'center'),
                        $this->p([$this->t('dass '), $this->v('mitarbeiter.anrede')], 'center'),
                        $this->p([$this->v('mitarbeiter.name')], 'center'),
                        $this->p([$this->t('» geb. am '), $this->v('mitarbeiter.geburtsdatum'), $this->t(' «')], 'center'),
                        $this->p([$this->t('die Prüfung '), $this->v('mitarbeiter.zum_zur')], 'center'),
                        $this->p([$this->f('qualifikation', 'Fachdienst-Qualifikation')], 'center'),
                        $this->p([
                            $this->t('am '), $this->f('pruefungsdatum', 'Prüfungsdatum', true, 20),
                            $this->t(' erfolgreich absolviert hat.'),
                        ], 'center'),
                    ],
                    $this->foot(true),
                )),
            ]],

            'Schriftliche Abmahnung' => ['category' => 'Schreiben', 'sections' => [
                $this->locked(array_merge($this->letterHead('Schriftliche Abmahnung'), [
                    $this->p([$this->t('hiermit werden Sie schriftlich bezüglich der unten genannten Vorfälle abgemahnt.')]),
                    $this->p([$this->t('Sollten Sie weiterhin dienstlich auffällig werden, müssen Sie mit weiteren dienstrechtlichen Konsequenzen bis hin zur Dienstentfernung rechnen.')]),
                    $this->p([$this->t('Der Grund der Abmahnung lautet:', ['bold'])]),
                ])),
                $this->free([$this->p()], 'Grund der Abmahnung'),
                $this->locked($this->foot(false)),
            ]],

            'Vorläufige Dienstenthebung' => ['category' => 'Schreiben', 'sections' => [
                $this->locked(array_merge($this->letterHead('Vorläufige Dienstenthebung'), [
                    $this->p([$this->t('mit diesem Schreiben informieren wir Sie über Ihre vorläufige Dienstenthebung.')]),
                    $this->p([
                        $this->t('Ab sofort sind Sie '),
                        $this->f('zeitraum', 'Zeitraum der Suspendierung', true, 80),
                        $this->t(' suspendiert. In diesem Zeitraum sind Sie von Ihren dienstlichen Pflichten entbunden.'),
                    ]),
                    $this->p([$this->t('Der Grund der Suspendierung lautet:', ['bold'])]),
                ])),
                $this->free([$this->p()], 'Grund der Suspendierung'),
                $this->locked($this->foot(false)),
            ]],

            'Dienstentfernung' => ['category' => 'Schreiben', 'sections' => [
                $this->locked(array_merge($this->letterHead('Dienstentfernung'), [
                    $this->p([$this->t('mit diesem Schreiben informieren wir Sie über Ihre Entfernung aus dem Beamtendienst.')]),
                    $this->p([$this->t('Mit sofortiger Wirkung ist das Arbeits-/Beamtenverhältnis beendigt. Eine Wiedereinstellung ist ausgeschlossen.')]),
                    $this->p([$this->t('Der Grund der Dienstentfernung lautet:', ['bold'])]),
                ])),
                $this->free([$this->p()], 'Grund der Dienstentfernung'),
                $this->locked($this->foot(false)),
            ]],

            'Außerordentliche Kündigung' => ['category' => 'Schreiben', 'sections' => [
                $this->locked(array_merge($this->letterHead('Außerordentliche Kündigung'), [
                    $this->p([$this->t('mit diesem Schreiben informieren wir Sie über Ihre außerordentliche Kündigung.')]),
                    $this->p([$this->t('Mit sofortiger Wirkung ist das Arbeitsverhältnis beendigt. Eine Wiedereinstellung ist ausgeschlossen.')]),
                    $this->p([$this->t('Der Grund für die Kündigung lautet:', ['bold'])]),
                ])),
                $this->free([$this->p()], 'Grund der Kündigung'),
                $this->locked($this->foot(false)),
            ]],
        ];
    }
}
