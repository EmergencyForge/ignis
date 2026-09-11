# eNOTF v2 — Code-Kataloge

Zentrale Datenklassen für alle Code-Listen, die im alten eNOTF über 121 Templates verstreut
waren. Namespace `Plugin\EnotfV2\Catalogs`, reine Daten (`public const`
plus statische Getter), Labels byteweise wie im Alt-System — inklusive Tippfehlern wie
"exacerbierte COPD".

## Katalog → Alt-Quelle(n)

| Katalog | Inhalt | Alt-Quelle(n) |
|---|---|---|
| `DiagnoseCatalog` | Diagnose-Codes + Kategoriebaum, 113 Codes | `protokoll/diagnose/1_1…1_10_11.php`, `2_1…2_10_11.php`; `print/index.php` Z. 917; `schnittstelle/voranmeldung.php` Z. 293 (und Z. 148, veraltet) |
| `TransportzielCatalog` | Versorgungsart (transportziel), 9 Codes | `protokoll/rettdaten/index.php` Z. 215; `print/index.php` Z. 295 |
| `UebergabeCatalog` | uebergabe_ort (13), uebergabe_an (8) | `protokoll/abschluss/3_1.php`, `3_2.php`; `print/index.php` Z. 2250 |
| `EinsatzCatalog` | eart (6), elokation (13), ebesonderheiten (15) | `protokoll/rettdaten/index.php` Z. 230; `protokoll/anamnese/3.php`; `protokoll/abschluss/1.php` |
| `BefundCatalog` | Erstbefund + Maßnahmen: 24 Optionslisten + 13 Checkbox-Flags | `protokoll/erstbefund/**` und `protokoll/massnahmen/{atemwege,atmung,weitere}/**`; Abgleich `print/index.php` |
| `ZugangCatalog` | Arten (3), Größen (11), PVK-Orte (9), IO-Orte (5), Seiten | `protokoll/massnahmen/zugang/1_1_1…1_1_9.php`, `1_2_1…1_2_5.php`; Server-Whitelist `src/Controllers/Api/EnotfController.php` Z. 1313 |
| `MedikationCatalog` | Applikationswege (6), Einheiten (5) | `protokoll/massnahmen/medikamente/1.php` |
| `VitalparameterCatalog` | 9 Parameter-Codes ↔ Legacy-parameter_name + Einheit | `protokoll/verlauf/add.php` Z. 57 und Z. 90 |
| `NacaCatalog` | NACA 0–7, volle Labels + römische Kurzform | `protokoll/anamnese/2_2.php` Z. 41; `print/index.php` Z. 345 |

Alle Template-Pfade relativ zu `plugins/enotf/templates/enotf/`.

## Konflikte und Abweichungen zwischen den Quellen

| # | Feld | Abweichung | Entscheidung |
|---|---|---|---|
| 1 | Diagnose | `voranmeldung.php` enthält ZWEI Label-Arrays. Das Array bei Z. 148 (nur für den Discord-Webhook-Text) weicht ab Code 75 massiv ab: 75 "Hypothermie" statt "bek. Dialysepflicht", 81–89 Verbrennung/Umwelt statt Anaphylaxie/Sonstige, 91–99 SHT/Polytrauma statt urologisch/unklar, 101–119 Gynäkologie/Pädiatrie statt Trauma; zusätzlich Codes 76, 105, 109, 115, 116, 119, 999, die es im Live-Katalog nicht gibt. | `LABELS` = Live-Katalog (Formularblätter = Print = Voranmeldung Z. 293, alle drei identisch). Das veraltete Array ist vollständig als `LEGACY_WEBHOOK_LABELS` dokumentiert. Die Formularblätter haben diese Codes nie geschrieben. |
| 2 | Diagnose (Trauma) | Die Trauma-Blätter 1_10_1…1_10_10 zeigen als Radio-Label nur "leicht/mittel/schwer/tödlich" (Region steckt in der Blattnavigation); Print/Voranmeldung führen die vollen Labels ("Trauma Schädel-Hirn leicht"). | Volle Labels im Katalog; Kategoriebaum in `CATEGORIES` bildet die Blattstruktur ab. |
| 3 | `c_kreislauf` | Formular (`erstbefund/kreislauf/1.php`) schreibt 99 = "Nicht beurteilbar"; `print/index.php` Z. 670 mappt stattdessen 3 => "Nicht beurteilbar" (99 wird im Druck gar nicht aufgelöst). | Katalog folgt dem Formular (1, 2, 99); der Print-Wert steht als `C_KREISLAUF_LEGACY` (3) für eventuelle Altdaten. |
| 4 | `uebergabe_ort` 10 | Formular: "Herzkatheterlabor"; Print: "HKL". | Formular-Label im Katalog, Print-Kurzform in `ORT_KURZLABELS`. |
| 5 | `d_pupillenw_*`, `d_lichtreakt_*` 99 | Formular: "Nicht untersucht"; Print: "nicht untersucht" (Kleinschreibung). | Formular-Schreibweise übernommen. |
| 6 | Bodymap `v_muster_*` | Formular-Buttons: "Keine/Leicht/Mittel/Schwer" bzw. "Offen/Geschlossen"; Print: kleingeschrieben und mit Zusatzwert 99 = "Nicht untersucht", den das Formular nicht anbietet. | Formular-Labels im Katalog; 99 als `V_MUSTER_SCHWERE_LEGACY`. |
| 7 | `c_blutung` | Formular: 1 = "nein", 2 = "ja"; Print rendert 2 als Radio "starke Blutung". | Formular-Labels übernommen, Print-Wording nur im Docblock erwähnt. |
| 8 | NACA | Formular: volle Labels ("NACA IV - Lebensgefahr nicht auszuschließen"); Print: römische Ziffern. | Beides aufgenommen (`LABELS` / `ROEMISCH`). |
| 9 | Zugang `zvk` | Server-Whitelist und Anzeigenamen kennen `zvk`, aber kein Formularblatt erzeugt ZVK-Einträge. | In `ARTEN` enthalten (Altdaten/Server erlauben es), keine Orte-Liste dafür. |
| 10 | Zugang "Fuß" | Blattnavigation zeigt "Fuss", gespeichert wird `ort` = "Fuß". | Gespeicherter Wert ist der Katalog-Schlüssel. |
| 11 | Einheit `mcg` | Gespeichert `mcg`, gerendert als `&micro;g` (µg); JS akzeptiert µg/μg/ug als Alias. | Label "µg" (dekodiert) im Katalog, Alias-Hinweis im Docblock. |
| 12 | IO-Größen | Formular-Labels sind die Rohwerte "15mm/25mm/45mm"; Print formatiert "15 mm". | Formular-Schreibweise übernommen. |
| 13 | `lagerung`, `awsicherung_neu`, `b_symptome`, `c_ekg` | Keine Label-Konflikte, aber die Formular-Reihenfolge ist nicht code-sortiert (z. B. lagerung: 99 vor 6). | Arrays behalten die Formular-Reihenfolge. |
| 14 | `rettungstechnik` | Anders als bei `psych` ist Wert 1 kein Exklusivwert "keine", sondern "Spineboard" — eine "keine"-Kachel mit eigenem Wert existiert nicht (Leeren läuft über die Quickfill-/JSON-Logik). | Formular-Werte übernommen. |

## Unsicherheiten

- `awsicherung_2` ("Absaugen") wird gerendert, fehlt aber in `ALLOWED_FIELDS` des
  Autosave-Endpoints — das Flag ist in `MASSNAHMEN_FLAGS` enthalten,
  in Altdaten dürfte die Spalte praktisch immer leer sein.
- Ob Produktivdaten `c_kreislauf` = 3 oder Bodymap-Schweregrad 99 enthalten (aus noch
  älteren Formularversionen), ließ sich aus dem Code nicht klären — deshalb die
  `*_LEGACY`-Konstanten.
- `sz_toleranz_2`, `awfrei_2/3`, `zyanose_2`, `c_zugang_art/gr/ort_1..3`, `naname` sind tote
  Spalten (kein Alt-Formular schreibt sie) und haben bewusst keinen Katalog.
