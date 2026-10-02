<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die eNOTF-Prüfliste filtert nach Segmenten. „Nicht freigegeben“ zählt wie
 * die Alarmkachel des Dashboards, damit deren Zahl hinter dem Link steht.
 */
final class EnotfAdminListTest extends FeatureTestCase
{
    private function protocol(string $enr, int $released, int $hiddenUser = 0): void
    {
        Capsule::table('intra_edivi')->insert([
            'enr' => $enr, 'patname' => 'P', 'protokoll_status' => 2, 'hidden' => 0, 'hidden_user' => $hiddenUser,
            'freigegeben' => $released, 'sendezeit' => date('Y-m-d H:i:s'),
        ]);
    }

    #[Test]
    public function nicht_freigegeben_zeigt_nur_die_offenen_protokolle(): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
        Capsule::table('intra_edivi')->update(['hidden' => 1]);
        $this->protocol('LIST-OFFEN', 0);
        $this->protocol('LIST-FREI', 1);
        $this->protocol('LIST-GELOESCHT', 0, 1);

        $all = $this->get('/enotf/admin/list');
        $this->assertOk($all);
        $this->assertBodyContains('Nicht freigegeben <span class="ignis-segmented__count">1</span>', $all);
        $this->assertBodyContains('LIST-FREI', $all);

        $open = $this->get('/enotf/admin/list', ['query' => ['view' => '2']]);
        $this->assertBodyContains('<a href="?view=2" class="is-active" aria-current="true">', $open);
        $this->assertBodyContains('LIST-OFFEN', $open);
        $this->assertBodyNotContains('LIST-FREI', $open);
        $this->assertBodyNotContains('LIST-GELOESCHT', $open);
    }
}
