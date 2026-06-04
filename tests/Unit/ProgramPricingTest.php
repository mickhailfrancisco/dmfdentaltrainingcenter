<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Program;
use Carbon\Carbon;
use Tests\TestCase;

class ProgramPricingTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_full_price_when_no_early_pricing_set(): void
    {
        $program = new Program(['price_full' => 18_000]);

        $this->assertSame(18_000, $program->active_price);
        $this->assertFalse($program->isEarlyBirdActive());
    }

    public function test_first_early_price_when_within_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = new Program([
            'price_full' => 18_000,
            'price_early' => 15_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
        ]);

        $this->assertSame(15_000, $program->active_price);
        $this->assertTrue($program->isEarlyBirdActive());
        $this->assertTrue($program->isFirstEarlyBirdActive());
        $this->assertFalse($program->isSecondEarlyBirdActive());
    }

    public function test_second_early_price_when_past_first_deadline_but_within_second(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10', 'Asia/Manila')->startOfDay());

        $program = new Program([
            'price_full' => 18_000,
            'price_early' => 15_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
            'price_early_2' => 16_500,
            'early_deadline_2' => Carbon::parse('2026-08-01', 'Asia/Manila'),
        ]);

        $this->assertSame(16_500, $program->active_price);
        $this->assertTrue($program->isEarlyBirdActive());
        $this->assertFalse($program->isFirstEarlyBirdActive());
        $this->assertTrue($program->isSecondEarlyBirdActive());
    }

    public function test_full_price_when_both_deadlines_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01', 'Asia/Manila')->startOfDay());

        $program = new Program([
            'price_full' => 18_000,
            'price_early' => 15_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
            'price_early_2' => 16_500,
            'early_deadline_2' => Carbon::parse('2026-08-01', 'Asia/Manila'),
        ]);

        $this->assertSame(18_000, $program->active_price);
        $this->assertFalse($program->isEarlyBirdActive());
    }

    public function test_full_price_when_only_second_tier_set_and_deadline_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01', 'Asia/Manila')->startOfDay());

        $program = new Program([
            'price_full' => 18_000,
            'price_early_2' => 16_500,
            'early_deadline_2' => Carbon::parse('2026-08-01', 'Asia/Manila'),
        ]);

        $this->assertSame(18_000, $program->active_price);
        $this->assertFalse($program->isEarlyBirdActive());
    }

    public function test_second_early_price_when_only_second_tier_set_and_within_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15', 'Asia/Manila')->startOfDay());

        $program = new Program([
            'price_full' => 18_000,
            'price_early_2' => 16_500,
            'early_deadline_2' => Carbon::parse('2026-08-01', 'Asia/Manila'),
        ]);

        $this->assertSame(16_500, $program->active_price);
        $this->assertTrue($program->isEarlyBirdActive());
        $this->assertTrue($program->isSecondEarlyBirdActive());
    }

    public function test_downpayment_is_always_50_percent_of_full_price(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = new Program([
            'price_full' => 18_000,
            'price_early' => 15_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
        ]);

        $this->assertSame(9_000, $program->downpayment_amount);
    }
}
