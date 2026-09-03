<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Support;

use App\Events\EventDispatcher;
use App\Logging\Logger;
use Illuminate\Database\Capsule\Manager as DB;
use Plugin\Enotf\Events\EnotfProtocolReleased;
use Plugin\EnotfV2\Catalogs\TransportzielCatalog;
use Plugin\EnotfV2\Models\Edivi;
use Plugin\EnotfV2\Models\EdiviPoi;

/**
 * ProtokollService — die zentralen Schreib- und Statusregeln für
 * intra_edivi, gebündelt an EINER Stelle (v1 verteilt das auf
 * Api\EnotfController + 121 Templates).
 *
 * Verhaltensregeln:
 *   - Feld-Whitelist ALLOWED_FIELDS — identisch zu v1 PLUS
 *     `awsicherung_2` (fehlte in v1 und ließ den Autosave mit 400
 *     abblitzen).
 *   - Jeder Write setzt `last_edit = NOW()`.
 *   - Datumsfelder (edatum, patgebdat, symptombeginn_datum) akzeptieren
 *     DD.MM.YYYY und ISO, gespeichert wird immer Y-m-d.
 *   - Namensfelder (pat_vorname/pat_nachname) leiten `patname` als
 *     "Nachname, Vorname" ab und setzen `pat_synced = 0` zurück.
 *   - `freigegeben = 1` sperrt das Protokoll: weitere Writes werden mit
 *     STATUS_RELEASED abgewiesen (HTTP-403-Semantik im Controller).
 *   - Sonderfeld `freigeber`: schreibt `freigeber_name`, setzt
 *     `freigegeben = 1` und feuert das v1-Event EnotfProtocolReleased
 *     (Discord-Webhook-Listener hängen daran). Vor der Freigabe wird
 *     serverseitig geprüft, dass ein Protokollant (pfname) gesetzt ist
 *     und keine Pflichtangabe mehr offen ist (ConditionsService) —
 *     Panel-User mit admin/edivi.edit dürfen das Plausibilitäts-Gate
 *     für QM-Korrekturen übersteuern (Parameter in saveFields).
 *   - `c_zugang` wird strukturell validiert (JSON-Whitelist inkl.
 *     Duplikat-Check art/ort/seite), wie in v1.
 *
 * Kein Konfliktschutz: last-write-wins wie in v1 — eine
 * Versionsstempel-Lösung braucht erst ein UX-Konzept.
 */
final class ProtokollService
{
    public const STATUS_OK        = 'ok';
    public const STATUS_NOT_FOUND = 'not_found';
    public const STATUS_RELEASED  = 'released';

    /**
     * Felder, die über save-fields geschrieben werden dürfen.
     * Quelle: v1 Api\EnotfController::ALLOWED_FIELDS + awsicherung_2.
     */
    public const ALLOWED_FIELDS = [
        'pat_vorname', 'pat_nachname', 'patgebdat', 'patsex',
        'edatum', 'ezeit', 'eort', 'eart', 'ebesonderheiten', 'elokation',
        'awfrei_1', 'awsicherung_1', 'awsicherung_2', 'awsicherung_neu', 'hws_immo',
        'zyanose_1', 'o2gabe', 'b_symptome', 'b_auskult', 'b_beatmung',
        'spo2', 'atemfreq', 'etco2',
        'c_zugang', 'c_kreislauf', 'c_ekg', 'c_puls_rad', 'c_puls_reg', 'c_rekap', 'c_blutung',
        'rrsys', 'rrdias', 'herzfreq',
        'medis', 'entlastungspunktion',
        'd_bewusstsein', 'd_ex_1', 'd_pupillenw_1', 'd_pupillenw_2',
        'd_lichtreakt_1', 'd_lichtreakt_2', 'd_gcs_1', 'd_gcs_2', 'd_gcs_3',
        'v_muster_k', 'v_muster_k1', 'v_muster_t', 'v_muster_t1',
        'v_muster_a', 'v_muster_a1', 'v_muster_al', 'v_muster_al1',
        'v_muster_bl', 'v_muster_bl1', 'v_muster_w', 'v_muster_w1',
        'sz_nrs', 'sz_toleranz_1', 'bz', 'temp', 'psych',
        'anmerkungen', 'diagnose_haupt', 'diagnose_weitere', 'diagnose',
        'fzg_transp', 'fzg_transp_perso', 'fzg_transp_perso_2', 'fzg_transp_perso_3',
        'fzg_na', 'fzg_na_perso', 'fzg_na_perso_2', 'fzg_na_perso_3', 'fzg_sonst',
        'transportziel', 'pfname', 'prot_by',
        'uebergabe_ort', 'uebergabe_an',
        'na_nachf', 'rettungstechnik', 'lagerung',
        'waerme_passiv', 'waerme_aktiv',
        'e_reposition', 'e_verband', 'e_krintervention', 'e_kuehlung',
        'e_narkose', 'e_tourniquet', 'e_cpr',
        'salarm', 's1', 's2', 's3', 's4', 'spat', 's7', 's8', 'sende',
        'symptombeginn_datum', 'symptombeginn_zeit', 'symptombeginn_geschaetzt', 'symptombeginn_nf',
        'naca_initial', 'naca_uebergabe',
        'sonderrechte_anfahrt', 'sonderrechte_transport',
    ];

    /** Datumsfelder mit DD.MM.YYYY→Y-m-d-Konvertierung. */
    public const DATE_FIELDS = ['edatum', 'patgebdat', 'symptombeginn_datum'];

    // ── Status-Helper ─────────────────────────────────────────────────

    /**
     * Lädt die komplette Protokoll-Zeile per ENR (oder null).
     *
     * @return array<string,mixed>|null
     */
    public function findByEnr(string $enr): ?array
    {
        $protokoll = Edivi::query()->where('enr', $enr)->first();

        // getAttributes() = rohe DB-Spalten, identisch zum früheren
        // fetch(FETCH_ASSOC) — Aufrufer (Controller, Templates) erwarten Arrays
        return $protokoll?->getAttributes();
    }

    /**
     * Gesperrt = freigegeben=1 (echte Freigabe ODER User-Löschung).
     *
     * @param array<string,mixed> $protokoll
     */
    public function istGesperrt(array $protokoll): bool
    {
        return (int) ($protokoll['freigegeben'] ?? 0) === 1;
    }

    /**
     * Vom User „gelöscht"? Diskriminator zur echten Freigabe
     * (freigegeben=1 setzen beide Wege).
     *
     * @param array<string,mixed> $protokoll
     */
    public function istUserGeloescht(array $protokoll): bool
    {
        return (int) ($protokoll['hidden_user'] ?? 0) === 1;
    }

    /**
     * Anzeige-Text für transportziel-Werte mit POI-Semantik.
     *
     * Die Spalte intra_edivi.transportziel trägt historisch zwei
     * Bedeutungen: Versorgungsart-Code (TransportzielCatalog) oder
     * POI-Kennung (`poi_<id>` bzw. legacy_identifier — v1 löste das in
     * abschluss/freigabe.php auf). Für Katalog-Codes und leere Werte
     * kommt null zurück; alles andere wird als POI aufgelöst
     * (POI-Name), nicht auflösbare Kennungen kommen roh zurück — so
     * zeigen Altprotokolle nie einen falschen Katalog-Zustand an.
     */
    public function transportzielPoiAnzeige(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $wert = trim((string) $raw);
        if ($wert === '') {
            return null;
        }

        // Katalog-Semantik (reiner Zahlencode) → hier nichts anzuzeigen
        if (preg_match('/^\d+$/', $wert) && TransportzielCatalog::label((int) $wert) !== null) {
            return null;
        }

        $poiName = str_starts_with($wert, 'poi_')
            ? EdiviPoi::query()->where('id', substr($wert, 4))->value('name')
            : EdiviPoi::query()->where('legacy_identifier', $wert)->value('name');

        return is_string($poiName) && $poiName !== '' ? $poiName : $wert;
    }

    // ── Schreiben ─────────────────────────────────────────────────────

    /**
     * Speichert einen Feld-Batch für ein Protokoll (Mehrfeld-Autosave).
     *
     * Bewusst NICHT atomar: valide Felder werden geschrieben, invalide
     * landen einzeln in errors — der Client zeigt Feld-Fehler an, ohne
     * dass der Rest des Batches verloren geht.
     *
     * Das Sonderfeld `freigeber` wird zuletzt verarbeitet, damit andere
     * Felder desselben Batches noch vor der Sperre landen — die
     * Freigabe-Prüfung sieht damit denselben Stand wie der Client-Flow
     * (erst Batch-Felder schreiben, dann Gate, dann freigeben).
     *
     * $ueberspringePlausibilitaet erlaubt Panel-Usern mit admin/edivi.edit
     * die Freigabe trotz offener Pflichtangaben (QM-Korrekturfälle);
     * die pfname-Pflicht gilt auch dann.
     *
     * @param array<string,mixed> $fields Spaltenname => Wert
     * @return array{status:string, updated:list<string>, errors:array<string,string>}
     */
    public function saveFields(string $enr, array $fields, bool $ueberspringePlausibilitaet = false): array
    {
        $protokoll = $this->findByEnr($enr);
        if ($protokoll === null) {
            return ['status' => self::STATUS_NOT_FOUND, 'updated' => [], 'errors' => []];
        }
        if ($this->istGesperrt($protokoll)) {
            return ['status' => self::STATUS_RELEASED, 'updated' => [], 'errors' => []];
        }

        $updated = [];
        $errors  = [];

        // freigeber ans Ende sortieren (siehe Docblock)
        $freigeberValue = null;
        $hasFreigeber   = array_key_exists('freigeber', $fields);
        if ($hasFreigeber) {
            $freigeberValue = $fields['freigeber'];
            unset($fields['freigeber']);
        }

        foreach ($fields as $field => $value) {
            $error = $this->writeField($enr, (string) $field, $value);
            if ($error === null) {
                $updated[] = (string) $field;
            } else {
                $errors[(string) $field] = $error;
            }
        }

        if ($hasFreigeber) {
            // Freigabe-Gate auf dem AKTUELLEN DB-Stand — die Batch-Felder
            // oben sind zu diesem Zeitpunkt bereits geschrieben
            $error = $this->pruefeFreigabe($enr, $ueberspringePlausibilitaet);
            if ($error === null) {
                $error = $this->release($enr, is_string($freigeberValue) ? $freigeberValue : '');
            }
            if ($error === null) {
                $updated[] = 'freigeber';
            } else {
                $errors['freigeber'] = $error;
            }
        }

        return ['status' => self::STATUS_OK, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Validiert + schreibt EIN Feld (Whitelist, Datums-Konvertierung,
     * c_zugang-Struktur, last_edit, patname-Ableitung).
     *
     * Sperr-/Existenz-Check macht der Aufrufer (saveFields) — wer diese
     * Methode direkt nutzt, muss vorher selbst prüfen.
     *
     * @return string|null null bei Erfolg, sonst Fehlermeldung fürs Frontend
     */
    public function writeField(string $enr, string $field, mixed $value): ?string
    {
        if (!in_array($field, self::ALLOWED_FIELDS, true)) {
            return 'Unbekanntes Feld';
        }

        if ($field === 'c_zugang') {
            $err = $this->validateCZugang($value);
            if ($err !== null) {
                return $err;
            }
        }

        if (in_array($field, self::DATE_FIELDS, true) && $value !== null && $value !== '') {
            $converted = $this->convertDateValue((string) $value);
            if ($converted === false) {
                return 'Ungültiges Datumsformat';
            }
            $value = $converted;
        }

        // Feldname ist durch die Whitelist abgesichert → kein Injection-Risiko
        Edivi::query()->where('enr', $enr)->update([
            $field      => $value,
            'last_edit' => DB::raw('NOW()'),
        ]);

        // Namensänderung → patname "Nachname, Vorname" ableiten + Sync-Reset
        if ($field === 'pat_vorname' || $field === 'pat_nachname') {
            $this->syncPatname($enr);
        }

        return null;
    }

    /**
     * Serverseitiges Freigabe-Gate: prüft auf dem aktuellen DB-Stand,
     * ob das Protokoll freigegeben werden darf.
     *
     *   - pfname (Protokollant) muss gesetzt sein — immer, auch bei
     *     Plausibilitäts-Override.
     *   - Alle Pflichtangaben müssen erfüllt sein
     *     (ConditionsService::isReleasable) — es sei denn, der Aufrufer
     *     darf das Gate übersteuern (Panel-User mit admin/edivi.edit).
     *
     * @return string|null null wenn freigebbar, sonst Fehlermeldung fürs Frontend
     */
    public function pruefeFreigabe(string $enr, bool $ueberspringePlausibilitaet = false): ?string
    {
        $protokoll = $this->findByEnr($enr);
        if ($protokoll === null) {
            return 'Protokoll nicht gefunden';
        }

        if (trim((string) ($protokoll['pfname'] ?? '')) === '') {
            return 'Freigabe nicht möglich — es ist kein Protokollant eingetragen';
        }

        if (!$ueberspringePlausibilitaet && !app(ConditionsService::class)->isReleasable($protokoll)) {
            return 'Freigabe nicht möglich — es sind noch offene Pflichtangaben vorhanden';
        }

        return null;
    }

    /**
     * Freigabe (Sonderfeld `freigeber`): freigeber_name + freigegeben=1 +
     * last_edit, dann EnotfProtocolReleased-Event (v1-Event — die
     * Discord-Webhook-Listener von v1 greifen damit auch für v2).
     *
     * @return string|null null bei Erfolg, sonst Fehlermeldung
     */
    public function release(string $enr, string $freigeberName): ?string
    {
        if (trim($freigeberName) === '') {
            return 'Freigeber darf nicht leer sein';
        }

        Edivi::query()->where('enr', $enr)->update([
            'freigeber_name' => $freigeberName,
            'freigegeben'    => 1,
            'last_edit'      => DB::raw('NOW()'),
        ]);

        try {
            $protokoll = $this->findByEnr($enr);
            if ($protokoll !== null) {
                app(EventDispatcher::class)->fire(new EnotfProtocolReleased($protokoll));
            }
        } catch (\Throwable $e) {
            // Freigabe ist gespeichert — ein Webhook-Fehler darf sie nicht kippen
            Logger::error('EnotfV2: EnotfProtocolReleased-Event fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        return null;
    }

    // ── Interna ───────────────────────────────────────────────────────

    /**
     * Kombinationsregel für patname: "Nachname, Vorname"; fehlt einer der
     * beiden Teile, entfällt das Komma. Whitespace wird getrimmt.
     */
    public static function combinePatname(string $vorname, string $nachname): string
    {
        $vorname  = trim($vorname);
        $nachname = trim($nachname);

        return $nachname . ($nachname !== '' && $vorname !== '' ? ', ' : '') . $vorname;
    }

    /**
     * patname = "Nachname, Vorname" aus den Quellfeldern neu ableiten und
     * pat_synced auf 0 zurücksetzen (v1-Semantik).
     */
    private function syncPatname(string $enr): void
    {
        $row = Edivi::query()->where('enr', $enr)->first(['pat_vorname', 'pat_nachname']);

        $combined = self::combinePatname(
            (string) ($row->pat_vorname ?? ''),
            (string) ($row->pat_nachname ?? ''),
        );

        Edivi::query()->where('enr', $enr)->update([
            'patname'    => $combined,
            'pat_synced' => 0,
        ]);
    }

    /**
     * Validiert die c_zugang-JSON-Struktur. Sonderwerte:
     * '0' = „Kein Zugang", ''/null = zurückgesetzt.
     *
     * @return string|null null bei OK, sonst Fehlertext
     */
    private function validateCZugang(mixed $value): ?string
    {
        if ($value === '0' || $value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            return 'Ungültiges JSON-Format';
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return 'Ungültiges JSON-Format';
        }

        $zugaenge = isset($decoded['art']) ? [$decoded] : (is_array($decoded) ? $decoded : null);
        if ($zugaenge === null) {
            return 'Ungültige Datenstruktur';
        }

        $allowedArts     = ['pvk', 'zvk', 'io'];
        $allowedGroessen = ['24G', '22G', '20G', '18G', '18G_kurz', '17G', '16G', '14G', '15mm', '25mm', '45mm'];
        $allowedSeiten   = ['links', 'rechts', ''];
        $seenLocations   = [];

        foreach ($zugaenge as $zugang) {
            if (!is_array($zugang)) {
                return 'Ungültige Datenstruktur';
            }
            foreach (['art', 'groesse', 'ort'] as $required) {
                if (!isset($zugang[$required]) || $zugang[$required] === '') {
                    return "Pflichtfeld fehlt: {$required}";
                }
            }
            if (!array_key_exists('seite', $zugang)) {
                return 'Pflichtfeld fehlt: seite';
            }

            $locKey = $zugang['art'] . '-' . $zugang['ort'] . '-' . $zugang['seite'];
            if (in_array($locKey, $seenLocations, true)) {
                return 'Doppelter Zugang an gleicher Position nicht erlaubt';
            }
            $seenLocations[] = $locKey;

            if (!in_array($zugang['art'], $allowedArts, true)) {
                return 'Ungültige Zugangsart: ' . $zugang['art'];
            }
            if (!in_array($zugang['groesse'], $allowedGroessen, true)) {
                return 'Ungültige Zugangsgröße: ' . $zugang['groesse'];
            }
            if (!in_array($zugang['seite'], $allowedSeiten, true)) {
                return 'Ungültige Seite: ' . $zugang['seite'];
            }
        }

        return null;
    }

    /**
     * DD.MM.YYYY → Y-m-d (ISO wird durchgereicht), mit Plausibilitäts-
     * Check über DateTime. false bei ungültigem Input.
     */
    private function convertDateValue(string $value): string|false
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            // bereits ISO
        } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m)) {
            $value = $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        } else {
            return false;
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : false;
    }
}
