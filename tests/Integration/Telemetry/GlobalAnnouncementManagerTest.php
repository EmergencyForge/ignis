<?php

declare(strict_types=1);

namespace Tests\Integration\Telemetry;

use App\Telemetry\GlobalAnnouncementManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use Tests\IntegrationTestCase;

/** Ein fehlgeschlagener oder leerer Abruf zählt als Abruf, sonst fragt jede Seite den Hub erneut. */
final class GlobalAnnouncementManagerTest extends IntegrationTestCase
{
    public function testAFailedFetchKeepsTheCacheFreshForAnHour(): void
    {
        Capsule::table('intra_config')->where('config_key', 'ANNOUNCEMENTS_LAST_FETCH')->delete();
        Capsule::table('intra_config')->updateOrInsert(
            ['config_key' => 'HUB_URL'],
            ['config_value' => 'http://127.0.0.1:9', 'config_type' => 'string'],
        );

        $manager = new GlobalAnnouncementManager();
        self::assertTrue($manager->isCacheStale());

        self::assertFalse($manager->refreshCache()['success']);
        self::assertSame(0, Capsule::table('intra_global_announcements_cache')->count());
        self::assertFalse($manager->isCacheStale());
    }
}
