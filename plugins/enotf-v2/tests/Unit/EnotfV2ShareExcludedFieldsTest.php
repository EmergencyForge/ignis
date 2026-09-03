<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugin\Enotf\Controllers\Api\EnotfController;
use Plugin\EnotfV2\Controllers\Api\ShareApiController;
use Tests\TestCase;

/**
 * Die v2-Ausschlussliste beim Share-Merge/-New muss exakt der v1-Liste
 * entsprechen — sonst übernimmt einer der beiden Wege Identitäts-,
 * Freigabe- oder QM-Felder, die beim Ziel unangetastet bleiben müssen.
 * Beide Konstanten sind private, daher Reflection.
 */
class EnotfV2ShareExcludedFieldsTest extends TestCase
{
    /** @return list<string> */
    private function excludedFields(string $class): array
    {
        $value = (new \ReflectionClass($class))->getConstant('SHARE_EXCLUDED_FIELDS');

        $this->assertIsArray($value, "$class::SHARE_EXCLUDED_FIELDS fehlt");

        return $value;
    }

    #[Test]
    public function v2_ausschlussliste_ist_identisch_zur_v1_liste(): void
    {
        $this->assertSame(
            $this->excludedFields(EnotfController::class),
            $this->excludedFields(ShareApiController::class),
        );
    }

    #[Test]
    public function ausschlussliste_schuetzt_die_kritischen_zielfelder(): void
    {
        $fields = $this->excludedFields(ShareApiController::class);

        foreach ([
            'id', 'enr',
            'fzg_transp', 'fzg_na',
            'freigegeben', 'freigeber_name',
            'hidden', 'hidden_user',
            'bearbeiter', 'qmkommentar',
        ] as $feld) {
            $this->assertContains($feld, $fields);
        }
    }
}
