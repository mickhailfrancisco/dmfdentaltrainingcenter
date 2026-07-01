<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
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

    public function test_balance_stays_zero_when_early_bird_fully_paid_before_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-04', 'Asia/Manila')->startOfDay());

        $program = Program::query()->create([
            'name' => 'Screenshot Program',
            'slug' => 'screenshot-program-'.uniqid(),
            'category' => 'Individual Programs (Theoretical)',
            'price_full' => 43_000,
            'price_early' => 41_000,
            'early_deadline' => '2026-05-30',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $enrollment = Enrollment::query()->create([
            'reference_number' => 'DMF-SCREEN-'.strtoupper(substr(uniqid(), -6)),
            'status' => 'confirmed',
            'purchasable_type' => Program::class,
            'purchasable_id' => $program->getKey(),
            'first_name' => 'Test',
            'surname' => 'Student',
            'birthday' => '2000-01-01',
            'sex' => 'Female',
            'phone' => '09170000000',
            'email' => 'screen-'.uniqid().'@test.com',
            'facebook_messenger_name' => 'Test',
            'addr_street' => '1 Main',
            'addr_city' => 'Manila',
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1000',
            'school' => 'U',
            'year_level' => '2nd Year',
            'taker_status' => 'First taker',
            'payment_type' => 'downpayment',
            'base_amount' => 21_500,
            'convenience_fee' => 0,
            'total_amount' => 21_500,
            'purchasable_name_snapshot' => $program->name,
            'purchasable_slug_snapshot' => $program->slug,
            'tuition_list_amount' => 43_000,
            'tuition_price_early' => 41_000,
            'tuition_early_deadline' => '2026-05-30',
            'amount_paid_tuition' => 41_000,
            'balance_tuition_due' => 0,
        ]);

        Payment::query()->create([
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => (21_500) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 21_500,
            'status' => 'paid',
            'paid_at' => Carbon::parse('2026-05-15 10:00:00', 'Asia/Manila'),
        ]);

        Payment::query()->create([
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => Payment::PURPOSE_BALANCE,
            'payment_method' => 'card',
            'amount' => (19_500) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 19_500,
            'status' => 'paid',
            'paid_at' => Carbon::parse('2026-05-24 10:00:00', 'Asia/Manila'),
        ]);

        $enrollment->refresh();

        $this->assertSame(0, EnrollmentPricingService::balanceTuitionDue($enrollment));
        $this->assertSame(41_000, EnrollmentPricingService::applicableTuitionTotal($enrollment));

        Carbon::setTestNow();
    }

    public function test_card_fee_is_3125_percent_plus_13(): void
    {
        // 10_000 * 0.03125 = 312.5 → ceil = 313, + 13 = 326
        $this->assertSame(326, EnrollmentPricingService::convenienceFeeForPaymentMethod('card', 10_000));
    }

    public function test_card_fee_rounds_up_fractional_cents(): void
    {
        // 1_000 * 0.03125 = 31.25 → ceil = 32, + 13 = 45
        $this->assertSame(45, EnrollmentPricingService::convenienceFeeForPaymentMethod('card', 1_000));
    }

    public function test_card_fee_on_exact_amount(): void
    {
        // 16_000 * 0.03125 = 500.0 → ceil = 500, + 13 = 513
        $this->assertSame(513, EnrollmentPricingService::convenienceFeeForPaymentMethod('card', 16_000));
    }

    public function test_bank_transfer_fee_is_zero(): void
    {
        $this->assertSame(0, EnrollmentPricingService::convenienceFeeForPaymentMethod('bank_transfer', 10_000));
    }

    public function test_unknown_payment_method_fee_is_zero(): void
    {
        $this->assertSame(0, EnrollmentPricingService::convenienceFeeForPaymentMethod('gcash', 10_000));
    }
}
