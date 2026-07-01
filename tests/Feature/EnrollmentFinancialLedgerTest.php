<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransferSubmission;
use App\Models\Payment;
use App\Models\Program;
use App\Services\EnrollmentFinancialService;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EnrollmentFinancialLedgerTest extends TestCase
{
    private function createProgram(array $overrides = []): Program
    {
        return Program::create(array_merge([
            'name' => 'Ledger Program',
            'slug' => 'ledger-program',
            'category' => 'Individual Programs (Theoretical)',
            'tag' => null,
            'price_full' => 10_000,
            'price_early' => 8_000,
            'early_deadline' => '2026-07-15',
            'early_bird_label' => 'Early',
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    private function baseEnrollmentPayload(Program $program, array $overrides = []): array
    {
        return array_merge([
            'program' => $program->slug,
            'schedule_id' => null,
            'first_name' => 'Ana',
            'middle_name' => null,
            'surname' => 'Santos',
            'birthday' => '2000-01-01',
            'sex' => 'Female',
            'phone' => '09171234567',
            'email' => 'ana@example.com',
            'facebook_messenger_name' => 'Ana Santos',
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
        ], $overrides);
    }

    public function test_downpayment_initial_payment_sets_partially_paid_and_balance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram();
        $enrollment = app(EnrollmentService::class)->createEnrollment($this->baseEnrollmentPayload($program));

        $this->assertSame(10_000, $enrollment->fresh()->tuition_list_amount);
        $this->assertSame(8_000, $enrollment->fresh()->tuition_price_early);

        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => (5_000) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 5_000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        app(EnrollmentFinancialService::class)->recalculateEnrollmentFinancials($enrollment->fresh());

        $enrollment->refresh();

        $this->assertSame('partially_paid', $enrollment->status->value);
        $this->assertSame(5_000, $enrollment->amount_paid_tuition);
        $this->assertSame(3_000, $enrollment->balance_tuition_due);
        $this->assertSame(3_000, $enrollment->computed_balance_tuition_due);
        $this->assertSame('partially_paid', (string) ($enrollment->items()->first()?->status));

        Carbon::setTestNow();
    }

    public function test_recalculate_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram();
        $enrollment = app(EnrollmentService::class)->createEnrollment($this->baseEnrollmentPayload($program));

        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => (5_000) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 5_000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $financial = app(EnrollmentFinancialService::class);
        $financial->recalculateEnrollmentFinancials($enrollment->fresh());
        $financial->recalculateEnrollmentFinancials($enrollment->fresh());

        $enrollment->refresh();

        $this->assertSame(5_000, $enrollment->amount_paid_tuition);
        $this->assertSame('partially_paid', $enrollment->status->value);

        Carbon::setTestNow();
    }

    public function test_full_payment_enrollment_confirmed_with_zero_balance(): void
    {
        $program = $this->createProgram([
            'slug' => 'full-ledger',
            'price_early' => null,
            'early_deadline' => null,
            'early_bird_label' => null,
        ]);

        $enrollment = app(EnrollmentService::class)->createEnrollment($this->baseEnrollmentPayload($program, [
            'payment_type' => 'full',
        ]));

        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => ($enrollment->base_amount) * 100,
            'currency' => 'PHP',
            'tuition_amount' => $enrollment->base_amount,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        app(EnrollmentFinancialService::class)->recalculateEnrollmentFinancials($enrollment->fresh());
        $enrollment->refresh();

        $this->assertSame('confirmed', $enrollment->status->value);
        $this->assertSame(0, $enrollment->balance_tuition_due);
    }

    public function test_signed_balance_route_returns_403_without_signature(): void
    {
        $program = $this->createProgram(['slug' => 'bal-route']);
        $enrollment = app(EnrollmentService::class)->createEnrollment($this->baseEnrollmentPayload($program, [
            'program' => 'bal-route',
        ]));

        $this->get(route('enroll.balance', ['reference_number' => $enrollment->reference_number]))
            ->assertForbidden();
    }

    public function test_unsigned_post_to_balance_pay_returns_403(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram(['slug' => 'bal-pay-unsigned']);
        $enrollment = app(EnrollmentService::class)->createEnrollment(
            $this->baseEnrollmentPayload($program, ['program' => 'bal-pay-unsigned'])
        );

        $this->post(
            route('enroll.balance.pay', ['reference_number' => $enrollment->reference_number]),
            ['payment_method' => 'card']
        )->assertForbidden();

        Carbon::setTestNow();
    }

    public function test_balance_pay_route_requires_valid_payment_method(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram(['slug' => 'bal-pay-method']);
        $enrollment = app(EnrollmentService::class)->createEnrollment(
            $this->baseEnrollmentPayload($program, ['program' => 'bal-pay-method'])
        );

        $signedUrl = URL::temporarySignedRoute(
            'enroll.balance.pay',
            now()->addMinutes(120),
            ['reference_number' => $enrollment->reference_number],
        );

        $this->post($signedUrl, ['payment_method' => 'invalid_method'])
            ->assertSessionHasErrors('payment_method');

        Carbon::setTestNow();
    }

    public function test_balance_becomes_zero_after_full_tuition_is_paid_via_two_payments(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram(['slug' => 'multi-pay']);
        $enrollment = app(EnrollmentService::class)->createEnrollment(
            $this->baseEnrollmentPayload($program, ['program' => 'multi-pay'])
        );

        // First payment: initial DP
        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => (5_000) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 5_000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Second payment: balance (3 000 = early-bird 8 000 - DP 5 000)
        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_BALANCE,
            'payment_method' => 'card',
            'amount' => (3_000) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 3_000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        app(EnrollmentFinancialService::class)->recalculateEnrollmentFinancials($enrollment->fresh());
        $enrollment->refresh();

        $this->assertSame(8_000, $enrollment->amount_paid_tuition);
        $this->assertSame(0, $enrollment->balance_tuition_due);
        $this->assertSame('confirmed', $enrollment->status->value);

        Carbon::setTestNow();
    }

    public function test_create_enrollment_snapshots_second_tier_early_pricing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-01', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram([
            'slug' => 'tier-two-program',
            'price_full' => 10_000,
            'price_early' => 8_000,
            'early_deadline' => '2026-07-15',
            'price_early_2' => 9_000,
            'early_deadline_2' => '2026-08-31',
            'early_bird_label' => 'Early',
        ]);

        $enrollment = app(EnrollmentService::class)->createEnrollment(
            $this->baseEnrollmentPayload($program, ['program' => 'tier-two-program'])
        );

        $enrollment->refresh();

        $this->assertSame(9_000, $enrollment->tuition_price_early_2);
        $this->assertSame('2026-08-31', $enrollment->tuition_early_deadline_2?->toDateString());
        $this->assertSame('Early bird (2nd)', $enrollment->tuition_discount_label);
        $this->assertSame(9_000, $enrollment->balance_tuition_due);

        Carbon::setTestNow();
    }

    public function test_recalculate_updates_balance_after_first_early_deadline_using_second_tier(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-01', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram([
            'slug' => 'tier-two-recalc',
            'price_full' => 10_000,
            'price_early' => 8_000,
            'early_deadline' => '2026-07-15',
            'price_early_2' => 9_000,
            'early_deadline_2' => '2026-08-31',
        ]);

        $enrollment = app(EnrollmentService::class)->createEnrollment(
            $this->baseEnrollmentPayload($program, ['program' => 'tier-two-recalc'])
        );

        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => (5_000) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 5_000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        app(EnrollmentFinancialService::class)->recalculateEnrollmentFinancials($enrollment->fresh());
        $enrollment->refresh();

        $this->assertSame(4_000, $enrollment->balance_tuition_due);
        $this->assertSame(4_000, $enrollment->computed_balance_tuition_due);

        Carbon::setTestNow();
    }

    public function test_full_early_bird_paid_before_deadline_stays_zero_after_deadline_recalc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-04', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram([
            'slug' => 'early-lock-full',
            'price_full' => 43_000,
            'price_early' => 41_000,
            'early_deadline' => '2026-05-30',
        ]);

        $enrollment = app(EnrollmentService::class)->createEnrollment(
            $this->baseEnrollmentPayload($program, ['program' => 'early-lock-full'])
        );

        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => (21_500) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 21_500,
            'status' => 'paid',
            'paid_at' => Carbon::parse('2026-05-15 10:00:00', 'Asia/Manila'),
        ]);

        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_BALANCE,
            'payment_method' => 'card',
            'amount' => (19_500) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 19_500,
            'status' => 'paid',
            'paid_at' => Carbon::parse('2026-05-24 10:00:00', 'Asia/Manila'),
        ]);

        app(EnrollmentFinancialService::class)->recalculateEnrollmentFinancials($enrollment->fresh());
        $enrollment->refresh();

        $this->assertSame(41_000, $enrollment->amount_paid_tuition);
        $this->assertSame(0, $enrollment->balance_tuition_due);
        $this->assertSame(0, $enrollment->computed_balance_tuition_due);
        $this->assertSame('confirmed', $enrollment->status->value);

        Carbon::setTestNow();
    }

    public function test_bank_transfer_submitted_before_deadline_locks_early_bird_even_if_verified_late(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-04', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram([
            'slug' => 'early-lock-bt',
            'price_full' => 10_000,
            'price_early' => 8_000,
            'early_deadline' => '2026-05-30',
        ]);

        $enrollment = app(EnrollmentService::class)->createEnrollment(
            $this->baseEnrollmentPayload($program, ['program' => 'early-lock-bt'])
        );

        $initialPayment = Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'bank_transfer',
            'amount' => (5_000) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 5_000,
            'status' => 'paid',
            'paid_at' => Carbon::parse('2026-05-10 10:00:00', 'Asia/Manila'),
        ]);

        BankTransferSubmission::query()->create([
            'payment_id' => $initialPayment->getKey(),
            'reference_number' => 'BT-INITIAL',
            'proof_path' => 'bank-transfers/test/initial.pdf',
            'submitted_at' => Carbon::parse('2026-05-10 09:00:00', 'Asia/Manila'),
        ]);

        $balancePayment = Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_BALANCE,
            'payment_method' => 'bank_transfer',
            'amount' => (3_000) * 100,
            'currency' => 'PHP',
            'tuition_amount' => 3_000,
            'status' => 'paid',
            'paid_at' => Carbon::parse('2026-06-02 10:00:00', 'Asia/Manila'),
        ]);

        BankTransferSubmission::query()->create([
            'payment_id' => $balancePayment->getKey(),
            'reference_number' => 'BT-BALANCE',
            'proof_path' => 'bank-transfers/test/balance.pdf',
            'submitted_at' => Carbon::parse('2026-05-28 09:00:00', 'Asia/Manila'),
        ]);

        app(EnrollmentFinancialService::class)->recalculateEnrollmentFinancials($enrollment->fresh());
        $enrollment->refresh();

        $this->assertSame(8_000, $enrollment->amount_paid_tuition);
        $this->assertSame(0, $enrollment->balance_tuition_due);
        $this->assertSame(0, $enrollment->computed_balance_tuition_due);
        $this->assertSame('confirmed', $enrollment->status->value);

        Carbon::setTestNow();
    }
}
