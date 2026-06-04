<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Package;
use Carbon\Carbon;
use Tests\TestCase;

class PackagePricingTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_full_price_when_no_early_pricing_set(): void
    {
        $package = new Package(['price_full' => 40_000]);

        $this->assertSame(40_000, $package->active_price);
        $this->assertFalse($package->isEarlyBirdActive());
    }

    public function test_first_early_price_when_within_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $package = new Package([
            'price_full' => 40_000,
            'price_early' => 36_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
        ]);

        $this->assertSame(36_000, $package->active_price);
        $this->assertTrue($package->isFirstEarlyBirdActive());
        $this->assertFalse($package->isSecondEarlyBirdActive());
    }

    public function test_second_early_price_when_past_first_deadline_but_within_second(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10', 'Asia/Manila')->startOfDay());

        $package = new Package([
            'price_full' => 40_000,
            'price_early' => 36_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
            'price_early_2' => 38_000,
            'early_deadline_2' => Carbon::parse('2026-08-01', 'Asia/Manila'),
        ]);

        $this->assertSame(38_000, $package->active_price);
        $this->assertTrue($package->isSecondEarlyBirdActive());
        $this->assertFalse($package->isFirstEarlyBirdActive());
    }

    public function test_full_price_when_both_deadlines_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01', 'Asia/Manila')->startOfDay());

        $package = new Package([
            'price_full' => 40_000,
            'price_early' => 36_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
            'price_early_2' => 38_000,
            'early_deadline_2' => Carbon::parse('2026-08-01', 'Asia/Manila'),
        ]);

        $this->assertSame(40_000, $package->active_price);
        $this->assertFalse($package->isEarlyBirdActive());
    }

    public function test_downpayment_is_always_50_percent_of_full_price(): void
    {
        $package = new Package(['price_full' => 40_000]);

        $this->assertSame(20_000, $package->downpayment_amount);
    }
}
