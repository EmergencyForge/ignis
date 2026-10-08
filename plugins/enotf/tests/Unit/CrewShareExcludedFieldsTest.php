<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugin\Enotf\Crew\Controllers\Api\ShareApiController;
use Tests\TestCase;

/**
 * Beim Teilen (Merge in ein eigenes Protokoll oder neues Protokoll) dürfen
 * Identitäts-, Freigabe- und QM-Felder des Ziels nicht überschrieben werden.
 * Die Konstante ist private, daher Reflection.
 */
class CrewShareExcludedFieldsTest extends TestCase
{
    #[Test]
    public function ausschlussliste_schuetzt_die_kritischen_zielfelder(): void
    {
        $fields = (new \ReflectionClass(ShareApiController::class))->getConstant('SHARE_EXCLUDED_FIELDS');
        $this->assertIsArray($fields, 'SHARE_EXCLUDED_FIELDS fehlt');

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
