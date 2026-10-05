<?php

declare(strict_types=1);

namespace Plugin\Enotf\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugin\Enotf\Helpers\ReanimationCatalog;
use Plugin\Enotf\Models\Edivi;
use Tests\TestCase;

class ReanimationCatalogTest extends TestCase
{
    // v1 freigabe.php reicht das Edivi-Model direkt in die check-Closures.
    #[Test]
    public function missing_details_accepts_edivi_model(): void
    {
        $protokoll = (new Edivi())->forceFill(['rea_status' => ReanimationCatalog::STATUS_DURCHGEFUEHRT]);

        $this->assertNotSame([], ReanimationCatalog::fehlendeDetails($protokoll));
    }

    #[Test]
    public function missing_details_empty_without_performed_resuscitation(): void
    {
        $this->assertSame([], ReanimationCatalog::fehlendeDetails(new Edivi()));
        $this->assertSame([], ReanimationCatalog::fehlendeDetails(['rea_status' => 1]));
    }
}
