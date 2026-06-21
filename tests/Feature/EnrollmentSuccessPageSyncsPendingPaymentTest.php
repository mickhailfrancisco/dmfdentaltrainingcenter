<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Program;
use App\Services\EnrollmentPricingService;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ensures the enrollment success page polls PayMongo for pending checkouts so
 * tuition paid / remaining balance reflect the initial payment immediately.
 */
class EnrollmentSuccessPageSyncsPendingPaymentTest extends TestCase
{
    public function test_success_page_syncs_pending_checkout_before_showing_balance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = Program::create([
            'name' => 'Program F',
            'slug' => 'program-f',
            'category' => 'Individual Programs (Theoretical)',
            'tag' => null,
            'price_full' => 30_000,
            'price_early' => 24_000,
            'early_deadline' => '2026-07-15',
            'early_bird_label' => 'Early',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $enrollment = app(EnrollmentService::class)->createEnrollment([
            'program' => 'program-f',
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

        $checkoutSessionId = 'cs_test_sync_pending';

        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => (15_000 + EnrollmentPricingService::CONVENIENCE_FEE_PESOS) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 15_000,
            'status' => 'pending',
            'paymongo_checkout_session_id' => $checkoutSessionId,
        ]);

        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions/'.$checkoutSessionId => Http::response([
                'data' => [
                    'attributes' => [
                        'payment_intent' => [
                            'id' => 'pi_test_123',
                            'attributes' => [
                                'status' => 'succeeded',
                                'payments' => [
                                    ['id' => 'pay_test_456'],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->get(route('enroll.success', ['ref' => $enrollment->reference_number]));

        $response->assertOk();

        $enrollment->refresh();

        $this->assertSame('paid', Payment::query()->where('enrollment_id', $enrollment->id)->first()->status);
        $this->assertSame(15_000, $enrollment->amount_paid_tuition);
        $this->assertSame(9_000, $enrollment->computed_balance_tuition_due);

        Carbon::setTestNow();
    }

    public function test_success_page_treats_paid_status_as_settled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = Program::create([
            'name' => 'Program Paid Status',
            'slug' => 'program-paid-status',
            'category' => 'Individual Programs (Theoretical)',
            'tag' => null,
            'price_full' => 30_000,
            'price_early' => 24_000,
            'early_deadline' => '2026-07-15',
            'early_bird_label' => 'Early',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $enrollment = app(EnrollmentService::class)->createEnrollment([
            'program' => 'program-paid-status',
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

        $checkoutSessionId = 'cs_test_sync_paid_status';

        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => (15_000 + EnrollmentPricingService::CONVENIENCE_FEE_PESOS) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 15_000,
            'status' => 'pending',
            'paymongo_checkout_session_id' => $checkoutSessionId,
        ]);

        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions/'.$checkoutSessionId => Http::response([
                'data' => [
                    'attributes' => [
                        'payment_intent' => [
                            'id' => 'pi_test_paid_123',
                            'attributes' => [
                                'status' => 'paid',
                                'payments' => [
                                    ['id' => 'pay_test_paid_456'],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->get(route('enroll.success', ['ref' => $enrollment->reference_number]));

        $response->assertOk();

        $enrollment->refresh();

        $this->assertSame('paid', Payment::query()->where('enrollment_id', $enrollment->id)->first()->status);
        $this->assertSame(15_000, $enrollment->amount_paid_tuition);

        Carbon::setTestNow();
    }

    public function test_success_page_skips_paymongo_sync_for_bank_transfer_submissions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = Program::create([
            'name' => 'Program Bank Transfer',
            'slug' => 'program-bank-transfer',
            'category' => 'Individual Programs (Theoretical)',
            'tag' => null,
            'price_full' => 30_000,
            'price_early' => 24_000,
            'early_deadline' => '2026-07-15',
            'early_bird_label' => 'Early',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $enrollment = app(EnrollmentService::class)->createEnrollment([
            'program' => 'program-bank-transfer',
            'schedule_id' => null,
            'first_name' => 'Bank',
            'middle_name' => null,
            'surname' => 'Transfer',
            'birthday' => '2000-01-01',
            'sex' => 'Female',
            'phone' => '09171234567',
            'email' => 'bank@example.com',
            'facebook_messenger_name' => 'Bank Transfer',
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
            'amount' => (15_000 + EnrollmentPricingService::CONVENIENCE_FEE_PESOS) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 15_000,
            'status' => 'submitted',
        ]);

        Http::fake();

        $response = $this->get(route('enroll.success', ['ref' => $enrollment->reference_number]));

        $response->assertOk();
        Http::assertNothingSent();

        Carbon::setTestNow();
    }
}
