<?php

declare(strict_types=1);

namespace Plugin\Enotf\Controllers\Api;

use App\Http\Request;
use Plugin\Enotf\Requests\KlinikCodeGenerateRequest;
use App\Http\Response;
use App\Logging\Logger;
use PDO;
use PDOException;

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

    public function __construct(
        private readonly PDO $pdo,
    ) {}

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
        $stmt = $this->pdo->prepare("SELECT enr FROM intra_edivi WHERE enr = :enr LIMIT 1");
        $stmt->execute([':enr' => $enr]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * @return array{code: string, expires_at: string}|null
     */
    private function findValidExistingCode(string $enr): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT code, expires_at
            FROM intra_edivi_klinikcodes
            WHERE enr = :enr AND expires_at > NOW()
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':enr' => $enr]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Generiert einen Code und prüft gegen Kollisionen in der DB. Gibt
     * `null` zurück, wenn nach MaxAttempts kein freier Code gefunden
     * wurde — extrem unwahrscheinlich bei 6 Zeichen aus 36 möglichen
     * (≈ 2 Milliarden Kombinationen), aber wir handhaben's defensiv.
     */
    private function generateUniqueCode(): ?string
    {
        $checkStmt = $this->pdo->prepare("SELECT id FROM intra_edivi_klinikcodes WHERE code = :code");

        for ($attempt = 0; $attempt < self::MAX_GENERATE_ATTEMPTS; $attempt++) {
            $code = '';
            $charsLen = strlen(self::CODE_CHARS);
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_CHARS[random_int(0, $charsLen - 1)];
            }

            $checkStmt->execute([':code' => $code]);
            if (!$checkStmt->fetch()) {
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
        $this->pdo->prepare("
            INSERT INTO intra_edivi_klinikcodes (enr, code, expires_at)
            VALUES (:enr, :code, DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ")->execute([
            ':enr'  => $enr,
            ':code' => $code,
        ]);

        $stmt = $this->pdo->prepare("
            SELECT code, DATE_FORMAT(expires_at, '%Y-%m-%d %H:%i:%s') AS expires_at
            FROM intra_edivi_klinikcodes
            WHERE enr = :enr
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([':enr' => $enr]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'code'       => (string) $row['code'],
            'expires_at' => (string) $row['expires_at'],
        ];
    }
}
