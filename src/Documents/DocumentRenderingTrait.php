<?php

namespace App\Documents;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Gemeinsame Rendering-Logik für DocumentRenderer und VisualTemplateRenderer.
 */
trait DocumentRenderingTrait
{
    protected function resolveGenderSpecificValue(array $options, $value, int $gender): string
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

    protected function getFieldOptions(string $fieldType, ?string $fieldOptions): array
    {
        switch ($fieldType) {
            case 'db_dg':
                return Capsule::table('intra_mitarbeiter_dienstgrade')
                    ->where('archive', 0)
                    ->orderBy('priority')
                    ->get([
                        'id as value',
                        'name as label',
                        'name_m as label_m',
                        'name_w as label_w',
                    ])
                    ->map(fn ($row) => (array) $row)
                    ->all();

            case 'db_rdq':
                return Capsule::table('intra_mitarbeiter_rdquali')
                    ->where('none', 0)
                    ->orderBy('priority')
                    ->get([
                        'id as value',
                        'name as label',
                        'name_m as label_m',
                        'name_w as label_w',
                    ])
                    ->map(fn ($row) => (array) $row)
                    ->all();

            case 'select':
                return $fieldOptions ? json_decode($fieldOptions, true) : [];

            default:
                return [];
        }
    }

    protected function getIssuerData(int $discordId): array
    {
        $row = Capsule::table('intra_users as u')
            ->leftJoin('intra_mitarbeiter as m', 'u.discord_id', '=', 'm.discordtag')
            ->where('u.discord_id', $discordId)
            ->select(
                'u.*',
                Capsule::raw('COALESCE(m.fullname, u.fullname) as fullname'),
                'm.dienstgrad',
                'm.zusatz',
                'm.geschlecht'
            )
            ->first();

        $data = $row !== null ? (array) $row : null;

        if ($data) {
            if ($data['dienstgrad']) {
                $dginfoRow = Capsule::table('intra_mitarbeiter_dienstgrade')
                    ->where('id', $data['dienstgrad'])
                    ->first();
                $dginfo = $dginfoRow !== null ? (array) $dginfoRow : null;

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

            if (!empty($data['fullname'])) {
                $splitname = explode(" ", $data['fullname']);
                $data['lastname'] = end($splitname);
            } else {
                $data['lastname'] = '';
            }
        }

        return $data ?? [];
    }

    protected function formatGermanDate(?string $date): string
    {
        if (!$date) return '';

        $dt = new \DateTime($date);
        $months = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];

        return $dt->format('d. ') . $months[(int) $dt->format('m')] . $dt->format(' Y');
    }

    protected function getImageAsBase64(string $path): ?string
    {
        if (!file_exists($path)) return null;

        $imageData = file_get_contents($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeTypes = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'svg' => 'image/svg+xml',
        ];
        $mimeType = $mimeTypes[$extension] ?? 'image/png';

        return "data:$mimeType;base64," . base64_encode($imageData);
    }
}
