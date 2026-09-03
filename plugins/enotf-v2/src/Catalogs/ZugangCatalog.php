<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Catalogs;

/**
 * Gefäßzugänge (Spalte intra_edivi.c_zugang, JSON-Array von {art, groesse, ort, seite};
 * Sonderwert '0' = "Kein Zugang").
 *
 * Quellen im Alt-System:
 * - plugins/enotf/templates/enotf/protokoll/massnahmen/zugang/1_1_1…1_1_9.php (PVK: Orte + Größen)
 * - plugins/enotf/templates/enotf/protokoll/massnahmen/zugang/1_2_1…1_2_5.php (intraossär: Orte + Größen)
 * - plugins/enotf/src/Controllers/Api/EnotfController.php Z. 1313 ff. (Server-Whitelists)
 *
 * 'zvk' ist in der Server-Whitelist erlaubt, wird aber von keinem Formularblatt erzeugt.
 */
final class ZugangCatalog
{
    /** Sonderwert der Spalte c_zugang für "Kein Zugang". */
    public const KEIN_ZUGANG = '0';

    /** art: Code => Anzeigename (print/index.php bzw. massnahmen/index.php). */
    public const ARTEN = [
        'pvk' => 'PVK',
        'zvk' => 'ZVK',
        'io' => 'intraossär',
    ];

    /** groesse: Code => Anzeigelabel. G-Größen für PVK, mm-Größen für intraossär. */
    public const GROESSEN = [
        '24G' => '24 G',
        '22G' => '22 G',
        '20G' => '20 G',
        '18G' => '18 G',
        '18G_kurz' => '18 G kurz',
        '17G' => '17 G',
        '16G' => '16 G',
        '14G' => '14 G',
        '15mm' => '15mm',
        '25mm' => '25mm',
        '45mm' => '45mm',
    ];

    /** Größen, die die PVK-Blätter anbieten (in Formular-Reihenfolge). */
    public const GROESSEN_PVK = ['24G', '22G', '20G', '18G', '18G_kurz', '17G', '16G', '14G'];

    /** Größen, die die intraossär-Blätter anbieten. */
    public const GROESSEN_IO = ['15mm', '25mm', '45mm'];

    /**
     * PVK-Lokationen: gespeicherter ort-Wert => ['sheet' => Blatt, 'seiten' => wählbare Seiten].
     * Die Blattnavigation zeigt Blatt 1_1_4 als "Fuss" an, gespeichert wird aber "Fuß".
     */
    public const ORTE_PVK = [
        'Handrücken' => ['sheet' => '1_1_1', 'seiten' => ['links', 'rechts']],
        'Unterarm' => ['sheet' => '1_1_2', 'seiten' => ['links', 'rechts']],
        'Ellbeuge' => ['sheet' => '1_1_3', 'seiten' => ['links', 'rechts']],
        'Fuß' => ['sheet' => '1_1_4', 'seiten' => ['links', 'rechts']],
        'Oberarm' => ['sheet' => '1_1_5', 'seiten' => ['links', 'rechts']],
        'Hals' => ['sheet' => '1_1_6', 'seiten' => ['links', 'rechts']],
        'Kopf' => ['sheet' => '1_1_7', 'seiten' => ['']],
        'Bein' => ['sheet' => '1_1_8', 'seiten' => ['links', 'rechts']],
        'Sonstige' => ['sheet' => '1_1_9', 'seiten' => ['']],
    ];

    /** intraossär-Lokationen: gespeicherter ort-Wert => ['sheet' => Blatt, 'seiten' => wählbare Seiten]. */
    public const ORTE_IO = [
        'Tibia proximal' => ['sheet' => '1_2_1', 'seiten' => ['links', 'rechts']],
        'Humerus proximal' => ['sheet' => '1_2_2', 'seiten' => ['links', 'rechts']],
        'Tibia distal' => ['sheet' => '1_2_3', 'seiten' => ['links', 'rechts']],
        'Sternum' => ['sheet' => '1_2_4', 'seiten' => ['']],
        'anderer Ort' => ['sheet' => '1_2_5', 'seiten' => ['']],
    ];

    /** Erlaubte seite-Werte ('' = keine Seitenangabe, z. B. Sternum). */
    public const SEITEN = ['links', 'rechts', ''];

    public static function artLabel(string $code): ?string
    {
        return self::ARTEN[$code] ?? null;
    }

    public static function groesseLabel(string $code): ?string
    {
        return self::GROESSEN[$code] ?? null;
    }
}
