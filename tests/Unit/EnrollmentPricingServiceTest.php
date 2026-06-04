<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Enrollment;
use App\Services\EnrollmentPricingService;
use Carbon\Carbon;
use Tests\TestCase;

class EnrollmentPricingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_balance_uses_early_price_while_deadline_not_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $enrollment = new Enrollment([
            'payment_type' => 'downpayment',
            'tuition_list_amount' => 10_000,
            'tuition_price_early' => 8_000,
            'amount_paid_tuition' => 5_000,
        ]);
        $enrollment->tuition_early_deadline = Carbon::parse('2026-07-15', 'Asia/Manila');

        $this->assertSame(3_000, EnrollmentPricingService::balanceTuitionDue($enrollment));
    }

    public function test_balance_uses_list_price_after_early_bird_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-01', 'Asia/Manila')->startOfDay());

        $enrollment = new Enrollment([
            'payment_type' => 'downpayment',
            'tuition_list_amount' => 10_000,
            'tuition_price_early' => 8_000,
            'amount_paid_tuition' => 5_000,
        ]);
        $enrollment->tuition_early_deadline = Carbon::parse('2026-07-15', 'Asia/Manila');

        $this->assertSame(5_000, EnrollmentPricingService::balanceTuitionDue($enrollment));
    }

    public function test_full_payment_enrollment_has_zero_balance(): void
    {
        $enrollment = new Enrollment([
            'payment_type' => 'full',
            'amount_paid_tuition' => 10_000,
        ]);

        $this->assertSame(0, EnrollmentPricingService::balanceTuitionDue($enrollment));
    }

    public function test_balance_uses_second_early_price_when_past_first_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-01', 'Asia/Manila')->startOfDay());

        $enrollment = new Enrollment([
            'payment_type' => 'downpayment',
            'tuition_list_amount' => 10_000,
            'tuition_price_early' => 8_000,
            'tuition_price_early_2' => 9_000,
            'amount_paid_tuition' => 5_000,
        ]);
        $enrollment->tuition_early_deadline = Carbon::parse('2026-07-15', 'Asia/Manila');
        $enrollment->tuition_early_deadline_2 = Carbon::parse('2026-08-15', 'Asia/Manila');

        $this->assertSame(4_000, EnrollmentPricingService::balanceTuitionDue($enrollment));
    }

    public function test_balance_uses_list_price_when_both_deadlines_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01', 'Asia/Manila')->startOfDay());

        $enrollment = new Enrollment([
            'payment_type' => 'downpayment',
            'tuition_list_amount' => 10_000,
            'tuition_price_early' => 8_000,
            'tuition_price_early_2' => 9_000,
            'amount_paid_tuition' => 5_000,
        ]);
        $enrollment->tuition_early_deadline = Carbon::parse('2026-07-15', 'Asia/Manila');
        $enrollment->tuition_early_deadline_2 = Carbon::parse('2026-08-15', 'Asia/Manila');

        $this->assertSame(5_000, EnrollmentPricingService::balanceTuitionDue($enrollment));
    }

    public function test_balance_uses_second_early_price_when_only_second_tier_snapshot_present(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01', 'Asia/Manila')->startOfDay());

        $enrollment = new Enrollment([
            'payment_type' => 'downpayment',
            'tuition_list_amount' => 10_000,
            'tuition_price_early_2' => 9_000,
            'amount_paid_tuition' => 5_000,
        ]);
        $enrollment->tuition_early_deadline_2 = Carbon::parse('2026-08-15', 'Asia/Manila');

        $this->assertSame(4_000, EnrollmentPricingService::balanceTuitionDue($enrollment));
    }
}
