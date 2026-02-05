<?php

namespace App\Documents;

use PDO;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use App\Helpers\ProtocolDetection;

class DocumentRenderer
{
    private PDO $pdo;
    private Environment $twig;
    private string $templatePath;

    public function __construct(PDO $pdo, string $templatePath = __DIR__ . '/../../dokumente/templates')
    {
        $this->pdo = $pdo;
        $this->templatePath = $templatePath;

        // Initialisiere Twig Template Engine
        $loader = new FilesystemLoader($this->templatePath);
        $this->twig = new Environment($loader, [
            'cache' => false, // In Produktion auf Cache-Pfad setzen
            'autoescape' => 'html'
        ]);
    }

    /**
     * Rendert ein Dokument
     */
    public function renderDocument(int $docId): string
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, t.template_file, t.is_system
            FROM intra_mitarbeiter_dokumente d
            LEFT JOIN intra_dokument_templates t ON d.template_id = t.id
            WHERE d.id = :docid
        ");
        $stmt->execute(['docid' => $docId]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc || !$doc['template_id']) {
            throw new \Exception("Dokument oder Template nicht gefunden");
        }

        // Alle Dokumente durch Twig rendern
        return $this->renderCustomDocument($doc);
    }

    /**
     * Rendert Custom-Dokumente aus Templates
     */
    private function renderCustomDocument(array $doc): string
    {
        $customData = json_decode($doc['custom_data'] ?? '{}', true);
        $issuer = $this->getIssuerData($doc['ausstellerid']);

        // Lade Template-Felder und Config
        $stmt = $this->pdo->prepare("
            SELECT tf.*, t.config 
            FROM intra_dokument_template_fields tf
            JOIN intra_dokument_templates t ON tf.template_id = t.id
            WHERE tf.template_id = ?
            ORDER BY tf.sort_order
        ");
        $stmt->execute([$doc['template_id']]);
        $templateFields = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $templateConfig = json_decode($templateFields[0]['config'] ?? '{}', true);

        // Anrede-Logik
        $anrede = (int)($doc['anrede'] ?? 0);
        $anredeText = match ($anrede) {
            0 => 'Herr',
            1 => 'Frau',
            default => 'Divers'
        };

        $geehrte = match ($anrede) {
            0 => 'geehrter',
            1 => 'geehrte',
            default => 'geehrte/-r'
        };

        $zum = match ($anrede) {
            0 => 'zum',
            1 => 'zur',
            default => 'zum/zur'
        };

        $seine_ihre = match ($anrede) {
            0 => 'seine',
            1 => 'ihre',
            default => 'seine/ihre'
        };

        $ihm_ihr = match ($anrede) {
            0 => 'ihm',
            1 => 'ihr',
            default => 'ihm/ihr'
        };

        // Dynamische Verarbeitung aller Felder
        $processedData = [];

        foreach ($templateFields as $field) {
            $fieldName = $field['field_name'];
            $fieldValue = $customData[$fieldName] ?? null;

            // Wenn es ein geschlechtsspezifisches Feld ist und einen Wert hat
            if ($field['gender_specific'] && $fieldValue !== null && $fieldValue !== '') {
                $options = $this->getFieldOptions($field['field_type'], $field['field_options']);
                $processedData[$fieldName] = $this->resolveGenderSpecificValue($options, $fieldValue, $anrede);

                // Erstelle zusätzlich eine "_text" Variable für bessere Lesbarkeit
                $processedData[$fieldName . '_text'] = $processedData[$fieldName];
            } else {
                $processedData[$fieldName] = $fieldValue;
            }
        }

        // Legacy-Felder für Rückwärtskompatibilität
        // Diese können später entfernt werden, wenn alle Templates migriert sind
        $dienstgradText = '';
        if (isset($customData['erhalter_rang']) && $customData['erhalter_rang']) {
            $options = $this->getFieldOptions('db_dg', null);
            $dienstgradText = $this->resolveGenderSpecificValue($options, $customData['erhalter_rang'], $anrede);
        }

        $dienstgrad = '';
        if (isset($customData['erhalter_rang_rd']) && $customData['erhalter_rang_rd']) {
            $options = $this->getFieldOptions('db_rdq', null);
            $dienstgrad = $this->resolveGenderSpecificValue($options, $customData['erhalter_rang_rd'], $anrede);
        }

        $qualifikation = '';
        if (isset($customData['erhalter_quali']) && $customData['erhalter_quali'] !== null) {
            // Hole Optionen aus Template-Config
            $qualiConfig = $templateConfig['fields']['erhalter_quali'] ?? null;
            if ($qualiConfig && isset($qualiConfig['options'])) {
                $qualifikation = $this->resolveGenderSpecificValue(
                    $qualiConfig['options'],
                    $customData['erhalter_quali'],
                    $anrede
                );
            }
        }

        // Suspend-String
        $suspendstring = 'bis auf unbestimmt';
        if (isset($customData['suspendtime']) && $customData['suspendtime'] && $customData['suspendtime'] != '0000-00-00') {
            $suspendstring = 'bis zum ' . date('d.m.Y', strtotime($customData['suspendtime']));
        }

        // Typtext basierend auf Template
        $typtext = match ($doc['template_file']) {
            'ausbildung.html.twig' => 'Ausbildungszertifikat',
            'lehrgang.html.twig', 'fachlehrgang.html.twig' => 'Lehrgangszertifikat',
            default => ''
        };

        // Bereite Daten vor
        $data = array_merge($customData, $processedData, [
            'dokument' => $doc,
            'doc' => $doc,
            'issuer' => $issuer,
            'aussteller' => [
                'fullname' => $issuer['fullname'] ?? '',
                'lastname' => $issuer['lastname'] ?? '',
                'dienstgrad' => $issuer['dienstgrad_text'] ?? '',
                'badge' => $issuer['dienstgrad_badge'] ?? null,
                'zusatz' => $issuer['zusatz'] ?? null,
            ],
            'anrede' => $anredeText,
            'anrede_text' => $anredeText,
            'geehrte' => $geehrte,
            'zum' => $zum,
            'seine_ihre' => $seine_ihre,
            'ihm_ihr' => $ihm_ihr,
            'suspendstring' => $suspendstring,
            'dienstgrad_text' => $dienstgradText,
            'dienstgrad' => $dienstgrad,
            'qualifikation' => $qualifikation,
            'typtext' => $typtext,
            'erhalter' => $doc['erhalter'],
            'erhalter_gebdat_formatted' => $this->formatGermanDate($doc['erhalter_gebdat']),
            'formatted_date' => $this->formatGermanDate($doc['erhalter_gebdat']),
            'inhalt' => $customData['inhalt'] ?? '',
            'ausstellungsdatum' => date("d.m.Y", strtotime($doc['ausstellungsdatum'])),
            'ausstelldatum' => date("d.m.Y", strtotime($doc['ausstellungsdatum'])),
            'wappen_base64' => $this->getImageAsBase64(__DIR__ . '/../../assets/img/wappen_small.png'),
            'logo_base64' => $this->getImageAsBase64(__DIR__ . '/../../assets/img/schrift_fw_schwarz.png'),
            'BASE_PATH' => BASE_PATH,
            'SYSTEM_NAME' => SYSTEM_NAME,
            'SYSTEM_COLOR' => SYSTEM_COLOR ?? '#000000',
            'SERVER_CITY' => SERVER_CITY,
            'RP_ORGTYPE' => RP_ORGTYPE,
            'RP_STREET' => RP_STREET,
            'RP_ZIP' => RP_ZIP,
            'SERVER_NAME' => SERVER_NAME,
            'META_IMAGE_URL' => META_IMAGE_URL ?? '',
            'own_url' => ProtocolDetection::getCurrentUrl(),
        ]);

        $templateFile = $doc['template_file'] ?? 'default.html.twig';
        $data['BASE_PATH_ABSOLUTE'] = 'file://' . realpath(__DIR__ . '/../../') . '/';
        return $this->twig->render($templateFile, $data);
    }

    /**
     * Löst geschlechtsspezifische Werte auf
     */
    private function resolveGenderSpecificValue(array $options, $value, int $gender): string
    {
        foreach ($options as $option) {
            if ($option['value'] == $value) {
                if ($gender === 1 && isset($option['label_w'])) {
                    return $option['label_w'];
                } elseif ($gender === 0 && isset($option['label_m'])) {
                    return $option['label_m'];
                }
                return $option['label'] ?? '';
            }
        }
        return '';
    }

    private function getImageAsBase64(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }

        $imageData = file_get_contents($path);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];

        $mimeType = $mimeTypes[$extension] ?? 'image/png';
        $base64 = base64_encode($imageData);

        return "data:$mimeType;base64,$base64";
    }

    /**
     * Lädt Optionen für ein Feld basierend auf dessen Typ
     */
    private function getFieldOptions(string $fieldType, ?string $fieldOptions): array
    {
        switch ($fieldType) {
            case 'db_dg':
                $stmt = $this->pdo->query("
                    SELECT id as value, name as label, name_m as label_m, name_w as label_w 
                    FROM intra_mitarbeiter_dienstgrade 
                    WHERE archive = 0 
                    ORDER BY priority ASC
                ");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            case 'db_rdq':
                $stmt = $this->pdo->query("
                    SELECT id as value, name as label, name_m as label_m, name_w as label_w 
                    FROM intra_mitarbeiter_rdquali 
                    WHERE none = 0 
                    ORDER BY priority ASC
                ");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            case 'select':
                return $fieldOptions ? json_decode($fieldOptions, true) : [];

            default:
                return [];
        }
    }

    private function formatGermanDate(?string $date): string
    {
        if (!$date) return '';

        $dt = new \DateTime($date);
        $months = [
            1 => 'Januar',
            2 => 'Februar',
            3 => 'März',
            4 => 'April',
            5 => 'Mai',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'August',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Dezember'
        ];

        return $dt->format('d. ') . $months[(int)$dt->format('m')] . $dt->format(' Y');
    }

    /**
     * Lädt Aussteller-Daten
     */
    private function getIssuerData(int $discordId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.*, 
                COALESCE(m.fullname, u.fullname) as fullname,
                m.dienstgrad, 
                m.zusatz, 
                m.geschlecht
            FROM intra_users u
            LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag
            WHERE u.discord_id = :id
        ");
        $stmt->execute(['id' => $discordId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            // Lade Dienstgrad-Info
            if ($data['dienstgrad']) {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM intra_mitarbeiter_dienstgrade WHERE id = :id
                ");
                $stmt->execute(['id' => $data['dienstgrad']]);
                $dginfo = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($dginfo) {
                    if ($data['geschlecht'] == 0) {
                        $data['dienstgrad_text'] = $dginfo['name_m'];
                    } elseif ($data['geschlecht'] == 1) {
                        $data['dienstgrad_text'] = $dginfo['name_w'];
                    } else {
                        $data['dienstgrad_text'] = $dginfo['name'];
                    }
                    $data['dienstgrad_badge'] = $dginfo['badge'] ?? null;
                }
            }

            // Extrahiere Nachnamen
            if (!empty($data['fullname'])) {
                $splitname = explode(" ", $data['fullname']);
                $data['lastname'] = end($splitname);
            } else {
                $data['lastname'] = '';
            }
        }

        return $data ?? [];
    }
}
