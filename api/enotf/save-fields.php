<?php
require_once __DIR__ . '/../../assets/config/config.php';
require __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Integrations\DiscordWebhook;

if (isset($_POST['enr']) && isset($_POST['field'])) {
    $enr = $_POST['enr'];
    $field = $_POST['field'];
    $value = array_key_exists('value', $_POST) ? $_POST['value'] : null;

    $allowedFields = [
        'pat_vorname', 'pat_nachname', 'patgebdat', 'patsex',
        'edatum', 'ezeit', 'eort', 'eart', 'ebesonderheiten', 'elokation',
        'awfrei_1', 'awsicherung_1', 'awsicherung_neu', 'hws_immo',
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

    if ($field === 'freigeber') {
        if (empty($value)) {
            http_response_code(400);
            echo "Freigeber darf nicht leer sein.";
            exit();
        }

        $query = "UPDATE intra_edivi SET freigeber_name = :value, freigegeben = 1, last_edit = NOW() WHERE enr = :enr";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['value' => $value, 'enr' => $enr]);

        // Discord Webhook Benachrichtigung bei Protokoll-Freigabe
        try {
            $stmt = $pdo->prepare("SELECT * FROM intra_edivi WHERE enr = :enr");
            $stmt->execute(['enr' => $enr]);
            $protokoll = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($protokoll) {
                $discordWebhook = new DiscordWebhook($pdo);
                $discordWebhook->notifyEnotfProtocolReleased($protokoll);
            }
        } catch (\Exception $e) {
            // Fehler beim Discord-Webhook loggen, aber Prozess nicht unterbrechen
            error_log("Discord Webhook Fehler (eNOTF Protokoll-Freigabe): " . $e->getMessage());
        }

        echo "Freigeber erfolgreich gespeichert und freigegeben.";
        exit();
    }

    if ($field === 'c_zugang') {
        if ($value === '0') {
        } else if ($value === null || $value === '') {
        } else {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo "Ungültiges JSON-Format";
                exit();
            }

            $zugaengeToValidate = [];
            if (isset($decoded['art'])) {
                $zugaengeToValidate = [$decoded];
            } else if (is_array($decoded)) {
                $zugaengeToValidate = $decoded;
            } else {
                http_response_code(400);
                echo "Ungültige Datenstruktur";
                exit();
            }

            $seenLocations = [];
            foreach ($zugaengeToValidate as $zugang) {
                $requiredFields = ['art', 'groesse', 'ort'];
                foreach ($requiredFields as $requiredField) {
                    if (!isset($zugang[$requiredField]) || $zugang[$requiredField] === '') {
                        http_response_code(400);
                        echo "Pflichtfeld fehlt: $requiredField";
                        exit();
                    }
                }

                if (!array_key_exists('seite', $zugang)) {
                    http_response_code(400);
                    echo "Pflichtfeld fehlt: seite";
                    exit();
                }

                $locationKey = $zugang['art'] . '-' . $zugang['ort'] . '-' . $zugang['seite'];
                if (in_array($locationKey, $seenLocations)) {
                    http_response_code(400);
                    echo "Doppelter Zugang an gleicher Position nicht erlaubt";
                    exit();
                }
                $seenLocations[] = $locationKey;

                $allowedArts = ['pvk', 'zvk', 'io'];
                $allowedGroessen = ['24G', '22G', '20G', '18G', '18G_kurz', '17G', '16G', '14G', '15mm', '25mm', '45mm'];
                $allowedSeiten = ['links', 'rechts', ''];

                if (!in_array($zugang['art'], $allowedArts)) {
                    http_response_code(400);
                    echo "Ungültige Zugangsart: " . $zugang['art'];
                    exit();
                }

                if (!in_array($zugang['groesse'], $allowedGroessen)) {
                    http_response_code(400);
                    echo "Ungültige Zugangsgröße: " . $zugang['groesse'];
                    exit();
                }

                if (!in_array($zugang['seite'], $allowedSeiten)) {
                    http_response_code(400);
                    echo "Ungültige Seite: " . $zugang['seite'];
                    exit();
                }
            }
        }
    }

    // Datumsfelder: in YYYY-MM-DD konvertieren für DB (DATE-Spalten)
    $dateFields = ['edatum', 'patgebdat', 'symptombeginn_datum'];
    if (in_array($field, $dateFields) && !empty($value)) {
        // Bereits ISO-Format?
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            // OK, nichts zu tun
        }
        // DD.MM.YYYY Format?
        elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m)) {
            $value = $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }
        // Unbekanntes Format
        else {
            http_response_code(400);
            echo "Ungültiges Datumsformat";
            exit();
        }
        // Validierung: Datum muss gültig sein
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if (!$dt || $dt->format('Y-m-d') !== $value) {
            http_response_code(400);
            echo "Ungültiges Datum";
            exit();
        }
    }

    if (in_array($field, $allowedFields)) {
        try {
            $checkStmt = $pdo->prepare("SELECT freigegeben FROM intra_edivi WHERE enr = :enr");
            $checkStmt->execute(['enr' => $enr]);
            $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                http_response_code(404);
                echo "Protokoll nicht gefunden.";
                exit();
            }

            if ((int)$row['freigegeben'] === 1) {
                http_response_code(403);
                echo "Protokoll ist freigegeben und kann nicht mehr bearbeitet werden.";
                exit();
            }

            $query = "UPDATE intra_edivi SET $field = :value, last_edit = NOW() WHERE enr = :enr";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['value' => $value, 'enr' => $enr]);

            // Bei Namensänderung: patname synchron halten + pat_synced zurücksetzen
            if ($field === 'pat_vorname' || $field === 'pat_nachname') {
                $currentStmt = $pdo->prepare("SELECT pat_vorname, pat_nachname FROM intra_edivi WHERE enr = ?");
                $currentStmt->execute([$enr]);
                $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
                $vn = trim($current['pat_vorname'] ?? '');
                $nn = trim($current['pat_nachname'] ?? '');
                $combined = $nn . (!empty($nn) && !empty($vn) ? ', ' : '') . $vn;
                $pdo->prepare("UPDATE intra_edivi SET patname = ?, pat_synced = 0 WHERE enr = ?")->execute([$combined, $enr]);
            }

            if ($field === 'c_zugang') {
                if ($value === '0') {
                    echo "Zugang auf 'Kein Zugang' gesetzt";
                } else if ($value === null || $value === '') {
                    echo "Zugang zurückgesetzt";
                } else {
                    echo "Zugang erfolgreich gespeichert";
                }
            } else {
                echo "Field updated";
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo "Datenbankfehler";
            error_log("Database error in save_fields.php: " . $e->getMessage());
        }
    } else {
        http_response_code(400);
        echo "Invalid field: $field";
    }
} else {
    http_response_code(400);
    echo "Missing data";
}
