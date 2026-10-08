<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Settings\FailedJobActionRequest;
use EmergencyForge\Http\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Der Post der Fehlerprotokoll-Seite für fehlgeschlagene Jobs
 * (assets/js/modules/logs-app.js, postAction()). Die Seite hat dafür keine
 * POST-Route, deshalb steht die Prüfung hier statt in einem Feature-Test.
 */
class FailedJobActionRequestTest extends TestCase
{
    #[Test]
    public function einzelne_aktion_mit_id(): void
    {
        $this->assertSame(
            ['action' => 'retry', 'id' => 7],
            FailedJobActionRequest::validate(['action' => 'retry', 'id' => '7', 'csrf_token' => 'x']),
        );
    }

    #[Test]
    public function aktion_fuer_alle_ohne_id(): void
    {
        $this->assertSame(['action' => 'delete_all', 'id' => 0], FailedJobActionRequest::validate(['action' => 'delete_all']));
    }

    #[Test]
    public function unbekannte_aktion(): void
    {
        $this->expectExceptionMessage('Unbekannte Aktion');
        FailedJobActionRequest::validate(['action' => 'drop_table']);
    }

    #[Test]
    public function id_als_text(): void
    {
        try {
            FailedJobActionRequest::validate(['action' => 'delete', 'id' => 'abc']);
            $this->fail('ValidationException erwartet');
        } catch (ValidationException $e) {
            $this->assertSame('Ungültige ID', $e->firstError());
        }
    }
}
