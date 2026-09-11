<?php

declare(strict_types=1);

namespace Plugin\Enotf\Controllers\Api;

use App\Http\Request;
use Plugin\Enotf\Requests\KlinikCodeGenerateRequest;
use App\Http\Response;
use App\Logging\Logger;
use Illuminate\Database\Capsule\Manager as DB;
use PDOException;
use Plugin\Enotf\Models\Edivi;
use Plugin\Enotf\Models\EdiviKlinikcode;

/**
 * Generiert Klinik-Einmal-Codes für ein eNOTF-Protokoll.
 *
 * Klinik-Codes sind 6-stellige alphanumerische Codes, mit denen Klinik-
 * Personal einmalig auf das eNOTF-Protokoll zugreifen kann (2h-Window).
 * Jeder Code ist einzigartig und läuft nach einer Stunde ab.
 *
 * Workflow:
 *   1. Prüfe ob das Protokoll existiert
 *   2. Wenn noch ein gültiger Code existiert → gib den zurück
 *   3. Sonst: generiere neuen Code mit Kollisions-Check (bis zu 100 Versuche)
 *   4. Speichere in `intra_edivi_klinikcodes` mit 1h Ablauf
 */
final class KlinikCodeController
{
    /** Zeichen für die Code-Generierung — nur eindeutig lesbare Zeichen */
    private const CODE_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const CODE_LENGTH = 6;
    private const MAX_GENERATE_ATTEMPTS = 100;

    /**
     * POST /api/klinik/generate-code
     */
    public function generate(Request $request): Response
    {
        $data = KlinikCodeGenerateRequest::validate($request);
        $enr  = (string) $data['enr'];

        try {
            if (!$this->protokollExists($enr)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Protokoll nicht gefunden',
                ], 404);
            }

            $existing = $this->findValidExistingCode($enr);
            if ($existing !== null) {
                return Response::json([
                    'success'    => true,
                    'code'       => $existing['code'],
                    'expires_at' => $existing['expires_at'],
                ]);
            }

            $code = $this->generateUniqueCode();
            if ($code === null) {
                Logger::error('KlinikCode: Code-Generierung nach MaxAttempts fehlgeschlagen', ['enr' => $enr]);
                return Response::json([
                    'success' => false,
                    'message' => 'Code-Generierung fehlgeschlagen',
                ], 500);
            }

            $stored = $this->storeCode($enr, $code);
            return Response::json([
                'success'    => true,
                'code'       => $stored['code'],
                'expires_at' => $stored['expires_at'],
            ]);
        } catch (PDOException $e) {
            Logger::error('KlinikCode: DB-Fehler beim Code-Generieren', [
                'error' => $e->getMessage(),
                'enr'   => $enr,
            ]);
            return Response::json([
                'success' => false,
                'message' => 'Datenbankfehler',
            ], 500);
        }
    }

    private function protokollExists(string $enr): bool
    {
        return Edivi::where('enr', $enr)->exists();
    }

    /**
     * @return array{code: string, expires_at: string}|null
     */
    private function findValidExistingCode(string $enr): ?array
    {
        $row = EdiviKlinikcode::where('enr', $enr)
            ->where('expires_at', '>', DB::raw('NOW()'))
            ->orderByDesc('created_at')
            ->first(['code', 'expires_at']);

        return $row ? ['code' => $row->code, 'expires_at' => $row->expires_at] : null;
    }

    /**
     * Generiert einen Code und prüft gegen Kollisionen in der DB. Gibt
     * `null` zurück, wenn nach MaxAttempts kein freier Code gefunden
     * wurde — extrem unwahrscheinlich bei 6 Zeichen aus 36 möglichen
     * (≈ 2 Milliarden Kombinationen), aber wir handhaben's defensiv.
     */
    private function generateUniqueCode(): ?string
    {
        for ($attempt = 0; $attempt < self::MAX_GENERATE_ATTEMPTS; $attempt++) {
            $code = '';
            $charsLen = strlen(self::CODE_CHARS);
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_CHARS[random_int(0, $charsLen - 1)];
            }

            if (!EdiviKlinikcode::where('code', $code)->exists()) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Speichert den Code mit 1h Ablaufzeit und gibt die Datenbank-Werte
     * (code + formatted expires_at) zurück.
     *
     * @return array{code: string, expires_at: string}
     */
    private function storeCode(string $enr, string $code): array
    {
        DB::table('intra_edivi_klinikcodes')->insert([
            'enr'        => $enr,
            'code'       => $code,
            'expires_at' => DB::raw('DATE_ADD(NOW(), INTERVAL 1 HOUR)'),
        ]);

        $row = DB::table('intra_edivi_klinikcodes')
            ->where('enr', $enr)
            ->orderByDesc('id')
            ->select('code', DB::raw("DATE_FORMAT(expires_at, '%Y-%m-%d %H:%i:%s') AS expires_at"))
            ->first();

        return [
            'code'       => (string) $row->code,
            'expires_at' => (string) $row->expires_at,
        ];
    }
}
