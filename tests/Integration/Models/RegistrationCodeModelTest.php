<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\RegistrationCode;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

class RegistrationCodeModelTest extends IntegrationTestCase
{
    private array $cleanupIds = [];

    protected function tearDown(): void
    {
        if (!empty($this->cleanupIds)) {
            RegistrationCode::whereIn('id', $this->cleanupIds)->delete();
        }
        parent::tearDown();
    }

    private function makeCode(array $overrides = []): RegistrationCode
    {
        $code = new RegistrationCode();
        $code->code    = 'TEST_' . uniqid();
        $code->is_used = false;
        foreach ($overrides as $k => $v) {
            $code->$k = $v;
        }
        $code->save();
        $this->cleanupIds[] = $code->id;
        return $code;
    }

    #[Test]
    public function unused_code_is_redeemable(): void
    {
        $code = $this->makeCode();
        $this->assertTrue($code->isRedeemable());
    }

    #[Test]
    public function used_code_is_not_redeemable(): void
    {
        $code = $this->makeCode(['is_used' => true]);
        $this->assertFalse($code->isRedeemable());
    }

    #[Test]
    public function expired_code_is_not_redeemable(): void
    {
        $code = $this->makeCode(['expires_at' => Carbon::now()->subDay()]);
        $this->assertFalse($code->isRedeemable());
    }

    #[Test]
    public function future_expiry_code_is_redeemable(): void
    {
        $code = $this->makeCode(['expires_at' => Carbon::now()->addWeek()]);
        $this->assertTrue($code->isRedeemable());
    }

    #[Test]
    public function unused_scope_filters_correctly(): void
    {
        $unused = $this->makeCode();
        $used   = $this->makeCode(['is_used' => true]);

        $found = RegistrationCode::unused()->where('id', $unused->id)->first();
        $this->assertNotNull($found);

        $foundUsed = RegistrationCode::unused()->where('id', $used->id)->first();
        $this->assertNull($foundUsed);
    }

    #[Test]
    public function casts_apply_to_booleans_and_dates(): void
    {
        $code = $this->makeCode();
        $reloaded = RegistrationCode::find($code->id);

        $this->assertIsBool($reloaded->is_used);
        $this->assertInstanceOf(\DateTimeInterface::class, $reloaded->created_at);
    }
}
