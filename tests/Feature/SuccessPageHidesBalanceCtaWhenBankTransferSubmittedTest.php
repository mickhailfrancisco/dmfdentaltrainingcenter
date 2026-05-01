<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Program;
use App\Services\EnrollmentPricingService;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Tests\TestCase;

class SuccessPageHidesBalanceCtaWhenBankTransferSubmittedTest extends TestCase
{
    public function test_success_page_hides_balance_cta_when_balance_bank_transfer_is_submitted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = Program::create([
            'name' => 'Program Balance BT',
            'slug' => 'program-balance-bt',
            'category' => 'Individual Programs (Theoretical)',
            'tag' => null,
            'price_full' => 30_000,
            'price_dp' => 15_000,
            'price_early' => 24_000,
            'early_deadline' => '2026-07-15',
            'early_bird_label' => 'Early',
            'inclusions' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $enrollment = app(EnrollmentService::class)->createEnrollment([
            'program' => $program->slug,
            'schedule_id' => null,
            'first_name' => 'Test',
            'middle_name' => null,
            'surname' => 'User',
            'birthday' => '2000-01-01',
            'sex' => 'Female',
            'phone' => '09171234567',
            'email' => 'test@example.com',
            'facebook_messenger_name' => 'Test User',
            'facebook_messenger_url' => null,
            'addr_street' => '1 Main',
            'addr_city' => 'Manila',
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1000',
            'deliv_street' => null,
            'deliv_city' => null,
            'deliv_province' => null,
            'deliv_zip' => null,
            'school' => 'U',
            'year_level' => 'Graduate',
            'year_graduated' => '2024',
            'taker_status' => 'First taker',
            'payment_type' => 'downpayment',
        ]);

        // Initial payment is already paid.
        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => (15_000 + EnrollmentPricingService::CONVENIENCE_FEE_PESOS) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 15_000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Balance payment was submitted via bank transfer (pending verification).
        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_BALANCE,
            'payment_method' => 'bank_transfer',
            'amount' => (9_000 + EnrollmentPricingService::CONVENIENCE_FEE_PESOS) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 9_000,
            'status' => 'submitted',
        ]);

        $response = $this->get(route('enroll.success', ['ref' => $enrollment->reference_number]));

        $response->assertOk();
        $response->assertDontSee('Pay remaining tuition');
        $response->assertDontSee('Balance payment submitted');

        Carbon::setTestNow();
    }

    public function test_success_page_shows_downpayment_pending_verification_when_initial_bank_transfer_is_submitted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = Program::create([
            'name' => 'Program Initial BT',
            'slug' => 'program-initial-bt',
            'category' => 'Individual Programs (Theoretical)',
            'tag' => null,
            'price_full' => 30_000,
            'price_dp' => 12_500,
            'price_early' => 24_000,
            'early_deadline' => '2026-07-15',
            'early_bird_label' => 'Early',
            'inclusions' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $enrollment = app(EnrollmentService::class)->createEnrollment([
            'program' => $program->slug,
            'schedule_id' => null,
            'first_name' => 'Test',
            'middle_name' => null,
            'surname' => 'User',
            'birthday' => '2000-01-01',
            'sex' => 'Female',
            'phone' => '09171234567',
            'email' => 'test@example.com',
            'facebook_messenger_name' => 'Test User',
            'facebook_messenger_url' => null,
            'addr_street' => '1 Main',
            'addr_city' => 'Manila',
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1000',
            'deliv_street' => null,
            'deliv_city' => null,
            'deliv_province' => null,
            'deliv_zip' => null,
            'school' => 'U',
            'year_level' => 'Graduate',
            'year_graduated' => '2024',
            'taker_status' => 'First taker',
            'payment_type' => 'downpayment',
        ]);

        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'bank_transfer',
            'amount' => (12_500 + EnrollmentPricingService::CONVENIENCE_FEE_PESOS) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 12_500,
            'status' => 'submitted',
        ]);

        $response = $this->get(route('enroll.success', ['ref' => $enrollment->reference_number]));

        $response->assertOk();
        $response->assertSee('Downpayment (pending verification)');
        $response->assertSee('₱12,500');

        Carbon::setTestNow();
    }
}
