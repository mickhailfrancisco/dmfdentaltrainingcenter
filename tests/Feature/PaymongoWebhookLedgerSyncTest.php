<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use App\Services\PaymongoService;
use Tests\TestCase;

class PaymongoWebhookLedgerSyncTest extends TestCase
{
    private const WEBHOOK_SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paymongo.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    public function test_webhook_marks_payment_paid_and_recalculates_enrollment(): void
    {
        $program = Program::factory()->create(['price_full' => 10_000]);
        $enrollment = Enrollment::factory()->create([
            'purchasable_type' => Program::class,
            'purchasable_id' => $program->getKey(),
            'payment_type' => 'full',
            'base_amount' => 10_000,
            'tuition_list_amount' => 10_000,
            'amount_paid_tuition' => 0,
        ]);

        $payment = Payment::factory()->create([
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => 10_326 * 100,
            'currency' => 'PHP',
            'tuition_amount' => 10_000,
            'status' => 'pending',
            'paymongo_checkout_session_id' => 'cs_test_abc123',
        ]);

        [$rawPayload, $signatureHeader] = $this->buildWebhookPayload('cs_test_abc123', 'succeeded');

        app(PaymongoService::class)->handleWebhook($rawPayload, $signatureHeader);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(10_000, (int) $enrollment->fresh()->amount_paid_tuition);
        $this->assertSame('confirmed', $enrollment->fresh()->status->value);
    }

    public function test_already_processed_webhook_still_recalculates_stale_enrollment(): void
    {
        $program = Program::factory()->create(['price_full' => 10_000]);

        // Payment is already 'paid' but enrollment ledger was never updated (the bug scenario).
        $enrollment = Enrollment::factory()->create([
            'purchasable_type' => Program::class,
            'purchasable_id' => $program->getKey(),
            'payment_type' => 'full',
            'base_amount' => 10_000,
            'tuition_list_amount' => 10_000,
            'amount_paid_tuition' => 0,     // stale — should be 10_000
            'balance_tuition_due' => 10_000, // stale — should be 0
        ]);

        Payment::factory()->create([
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => 10_326 * 100,
            'currency' => 'PHP',
            'tuition_amount' => 10_000,
            'status' => 'paid', // already paid
            'paymongo_checkout_session_id' => 'cs_test_def456',
            'paid_at' => now(),
        ]);

        [$rawPayload, $signatureHeader] = $this->buildWebhookPayload('cs_test_def456', 'succeeded');

        app(PaymongoService::class)->handleWebhook($rawPayload, $signatureHeader);

        // Ledger must be corrected even though payment was already paid.
        $enrollment->refresh();
        $this->assertSame(10_000, (int) $enrollment->amount_paid_tuition);
        $this->assertSame(0, (int) $enrollment->balance_tuition_due);
        $this->assertSame('confirmed', $enrollment->status->value);
    }

    /**
     * Build a signed webhook payload for the given checkout session ID and intent status.
     *
     * @return array{0: string, 1: string} [rawPayload, signatureHeader]
     */
    private function buildWebhookPayload(string $checkoutSessionId, string $intentStatus): array
    {
        $timestamp = (string) time();

        $rawPayload = json_encode([
            'data' => [
                'attributes' => [
                    'livemode' => false,
                    'type' => 'checkout_session.payment.paid',
                    'data' => [
                        'id' => $checkoutSessionId,
                        'attributes' => [
                            'paid_at' => (int) $timestamp,
                            'payment_intent' => [
                                'id' => 'pi_test_123',
                                'attributes' => [
                                    'status' => $intentStatus,
                                    'payments' => [
                                        ['id' => 'pay_test_123'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $signature = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", self::WEBHOOK_SECRET);
        $signatureHeader = "t={$timestamp},te={$signature}";

        return [$rawPayload, $signatureHeader];
    }
}
