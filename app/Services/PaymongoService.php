<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymongoService
{
    public function __construct(
        protected EnrollmentFinancialService $enrollmentFinancialService
    ) {}

    /**
     * Create a card checkout session from an existing enrollment.
     *
     * @param  string  $purpose  Payment::PURPOSE_INITIAL | Payment::PURPOSE_BALANCE
     *
     * @throws RuntimeException
     */
    public function createCheckoutSession(Enrollment $enrollment, string $purpose = Payment::PURPOSE_INITIAL): array
    {
        $purchasableName = (string) ($enrollment->purchasable_name_snapshot ?? 'Enrollment');
        $fee = EnrollmentPricingService::CONVENIENCE_FEE_PESOS;

        if ($purpose === Payment::PURPOSE_BALANCE) {
            if ($enrollment->payment_type !== 'downpayment') {
                throw new RuntimeException('Balance checkout is only available for downpayment enrollments.');
            }

            $this->enrollmentFinancialService->recalculateEnrollmentFinancials($enrollment);
            $enrollment->refresh();

            $tuitionPortion = EnrollmentPricingService::balanceTuitionDue($enrollment);
            if ($tuitionPortion <= 0) {
                throw new RuntimeException('No remaining tuition balance.');
            }

            $totalPesos = $tuitionPortion + $fee;
            $lineSuffix = 'Balance';
        } else {
            $tuitionPortion = (int) $enrollment->base_amount;
            $totalPesos = $tuitionPortion + $fee;
            $lineSuffix = $enrollment->payment_type === 'downpayment' ? 'Downpayment' : 'Full Payment';
        }

        $payment = Payment::updateOrCreate(
            [
                'enrollment_id' => $enrollment->id,
                'purpose' => $purpose,
            ],
            [
                'payment_method' => 'card',
                'amount' => (int) round($totalPesos * 100),
                'currency' => 'PHP',
                'status' => 'pending',
                'tuition_amount' => $tuitionPortion,
            ]
        );

        $payload = [
            'data' => [
                'attributes' => [
                    'billing' => $this->buildCheckoutBilling($enrollment),
                    'send_email_receipt' => true,
                    'show_description' => true,
                    'show_line_items' => true,
                    'cancel_url' => route('enroll.cancel', ['ref' => $enrollment->reference_number]),
                    'description' => $purchasableName,
                    'line_items' => [
                        [
                            'currency' => 'PHP',
                            'amount' => (int) round($totalPesos * 100),
                            'description' => sprintf(
                                '%s (%s)',
                                $purchasableName,
                                $lineSuffix
                            ),
                            'name' => $purchasableName,
                            'quantity' => 1,
                        ],
                    ],
                    'payment_method_types' => ['card'],
                    'reference_number' => $enrollment->reference_number,
                    'success_url' => route('enroll.success', ['ref' => $enrollment->reference_number]),
                ],
            ],
        ];
        $idempotencyKey = sprintf(
            'enroll-%s-payment-%s-purpose-%s',
            $enrollment->reference_number,
            (string) $payment->id,
            $purpose
        );

        try {
            $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
                ->withHeaders([
                    'Idempotency-Key' => $idempotencyKey,
                ])
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->retry(2, 300)
                ->post('https://api.paymongo.com/v1/checkout_sessions', $payload);

            if (! $response->successful()) {
                Log::error('PayMongo checkout session creation failed.', [
                    'reference_number' => $enrollment->reference_number,
                    'idempotency_key' => $idempotencyKey,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new RuntimeException('Unable to initialize checkout session.');
            }

            $responseData = $response->json('data');
            $attributes = $responseData['attributes'] ?? [];

            $payment->update([
                'paymongo_checkout_session_id' => $responseData['id'] ?? null,
                'status' => 'pending',
                'paymongo_payload' => $response->json(),
            ]);

            return [
                'payment' => $payment,
                'checkout_url' => $attributes['checkout_url'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('PayMongo checkout exception.', [
                'reference_number' => $enrollment->reference_number,
                'idempotency_key' => $idempotencyKey,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Unable to connect to payment gateway.', previous: $e);
        }
    }

    public function syncCheckoutSessionStatus(string $checkoutSessionId): ?Enrollment
    {
        $payment = Payment::with(['enrollment', 'enrollment.payments'])
            ->where('paymongo_checkout_session_id', $checkoutSessionId)
            ->first();

        if (! $payment) {
            return null;
        }

        try {
            $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
                ->acceptJson()
                ->timeout(20)
                ->retry(2, 300)
                ->get("https://api.paymongo.com/v1/checkout_sessions/{$checkoutSessionId}");

            if (! $response->successful()) {
                Log::warning('PayMongo checkout status fetch failed.', [
                    'checkout_session_id' => $checkoutSessionId,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return $payment->enrollment;
            }

            $attributes = $response->json('data.attributes', []);
            $paymentIntent = $attributes['payment_intent'] ?? [];
            $paymentIntentId = $paymentIntent['id'] ?? null;
            $paymentIntentAttributes = $paymentIntent['attributes'] ?? [];
            $paymentStatus = $paymentIntentAttributes['status'] ?? null;
            $latestPaymentId = $paymentIntentAttributes['payments'][0]['id'] ?? null;

            $normalizedStatus = strtolower((string) $paymentStatus);
            $localStatus = in_array($normalizedStatus, ['succeeded', 'paid'], true) ? 'paid' : 'pending';

            $payment->update([
                'paymongo_payment_intent_id' => $paymentIntentId,
                'paymongo_payment_id' => $latestPaymentId,
                'status' => $localStatus,
                'paid_at' => $localStatus === 'paid' ? now() : null,
                'paymongo_payload' => $response->json(),
            ]);

            if ($localStatus === 'paid') {
                $this->enrollmentFinancialService->recalculateEnrollmentFinancials($payment->enrollment->fresh());
            }

            return $payment->enrollment->refresh()->load(['payments']);
        } catch (\Throwable $e) {
            Log::error('PayMongo checkout status sync exception.', [
                'checkout_session_id' => $checkoutSessionId,
                'error' => $e->getMessage(),
            ]);

            return $payment->enrollment;
        }
    }

    /**
     * Poll PayMongo for any pending checkout sessions tied to this enrollment so the
     * local ledger can move to "paid" before the webhook arrives (success_url only passes ref).
     *
     * @author CKD
     *
     * @created 2026-04-21
     */
    public function syncPendingCheckoutSessionsForEnrollment(Enrollment $enrollment): void
    {
        $sessionIds = Payment::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->where('status', 'pending')
            ->whereNotNull('paymongo_checkout_session_id')
            ->pluck('paymongo_checkout_session_id')
            ->unique()
            ->filter()
            ->values();

        foreach ($sessionIds as $checkoutSessionId) {
            $this->syncCheckoutSessionStatus((string) $checkoutSessionId);
        }
    }

    public function handleWebhook(string $rawPayload, ?string $signatureHeader): array
    {
        $payload = json_decode($rawPayload, true);
        if (! is_array($payload)) {
            return [
                'status' => 202,
                'body' => ['message' => 'Invalid payload format.'],
            ];
        }

        $isLiveMode = (bool) data_get($payload, 'data.attributes.livemode', false);
        if (! $this->isValidWebhookSignature($rawPayload, $signatureHeader, $isLiveMode)) {
            Log::warning('PayMongo webhook rejected due to invalid signature.', [
                'livemode' => $isLiveMode,
            ]);

            return [
                'status' => 401,
                'body' => ['message' => 'Invalid webhook signature.'],
            ];
        }

        $eventType = (string) data_get($payload, 'data.attributes.type', '');
        if ($eventType !== 'checkout_session.payment.paid') {
            Log::info('PayMongo webhook ignored due to unsubscribed event type.', [
                'event_type' => $eventType,
            ]);

            return [
                'status' => 202,
                'body' => ['message' => 'Ignored: unsupported event type.'],
            ];
        }

        $resourceData = data_get($payload, 'data.attributes.data', []);
        $checkoutSessionId = (string) (
            data_get($resourceData, 'id')
            ?? data_get($resourceData, 'attributes.checkout_session_id')
            ?? data_get($resourceData, 'attributes.id')
            ?? ''
        );

        if ($checkoutSessionId === '') {
            Log::info('PayMongo webhook ignored due to missing checkout_session_id.', [
                'event_type' => $eventType,
            ]);

            return [
                'status' => 202,
                'body' => ['message' => 'Ignored: checkout session not found in payload.'],
            ];
        }

        $payment = Payment::with('enrollment')
            ->where('paymongo_checkout_session_id', $checkoutSessionId)
            ->first();

        if (! $payment) {
            Log::info('PayMongo webhook ignored due to unknown checkout session.', [
                'event_type' => $eventType,
                'checkout_session_id' => $checkoutSessionId,
            ]);

            return [
                'status' => 202,
                'body' => ['message' => 'Ignored: payment not found.'],
            ];
        }

        $intentData = data_get($resourceData, 'attributes.payment_intent', []);
        $paymentIntentId = (string) (data_get($intentData, 'id') ?? '');
        $intentStatus = (string) (data_get($intentData, 'attributes.status') ?? '');
        $paymongoPaymentId = (string) (
            data_get($resourceData, 'attributes.payments.0.id')
            ?? data_get($intentData, 'attributes.payments.0.id')
            ?? ''
        );
        $checkoutPaidAtTimestamp = data_get($resourceData, 'attributes.paid_at');
        $webhookPaidAt = is_numeric($checkoutPaidAtTimestamp)
            ? now()->setTimestamp((int) $checkoutPaidAtTimestamp)
            : now();

        $resolvedStatus = $this->resolveWebhookPaymentStatus($eventType, $intentStatus);

        if ($payment->status === 'paid' && $resolvedStatus === 'paid') {
            $payment->update([
                'paymongo_payload' => $payload,
            ]);

            return [
                'status' => 200,
                'body' => ['message' => 'Already processed.'],
            ];
        }

        $payment->update([
            'paymongo_payment_intent_id' => $paymentIntentId !== '' ? $paymentIntentId : $payment->paymongo_payment_intent_id,
            'paymongo_payment_id' => $paymongoPaymentId !== '' ? $paymongoPaymentId : $payment->paymongo_payment_id,
            'status' => $resolvedStatus,
            'paid_at' => $resolvedStatus === 'paid' ? ($payment->paid_at ?? $webhookPaidAt) : null,
            'paymongo_payload' => $payload,
        ]);

        if ($resolvedStatus === 'paid') {
            $this->enrollmentFinancialService->recalculateEnrollmentFinancials($payment->enrollment->fresh());
        }

        return [
            'status' => 200,
            'body' => ['message' => 'Webhook processed.'],
        ];
    }

    protected function isValidWebhookSignature(string $rawPayload, ?string $signatureHeader, bool $isLiveMode): bool
    {
        $webhookSecret = (string) config('services.paymongo.webhook_secret');
        if ($webhookSecret === '' || $signatureHeader === null || trim($signatureHeader) === '') {
            return false;
        }

        $signatureParts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) {
                $signatureParts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = $signatureParts['t'] ?? null;
        if ($timestamp === null || ! ctype_digit((string) $timestamp)) {
            return false;
        }

        // Optional replay protection: reject stale webhook signatures.
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $signedPayload = $timestamp ? "{$timestamp}.{$rawPayload}" : $rawPayload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

        $targetSignature = $isLiveMode
            ? ($signatureParts['li'] ?? null)
            : ($signatureParts['te'] ?? null);

        if ($targetSignature === null || $targetSignature === '') {
            return false;
        }

        return hash_equals($expectedSignature, (string) $targetSignature);
    }

    protected function resolveWebhookPaymentStatus(string $eventType, string $intentStatus): string
    {
        $event = strtolower($eventType);
        $intent = strtolower($intentStatus);

        if (str_contains($event, 'paid') || $intent === 'succeeded') {
            return 'paid';
        }

        if (str_contains($event, 'failed') || str_contains($event, 'expired') || str_contains($event, 'cancel')) {
            return 'failed';
        }

        return 'pending';
    }

    /**
     * Billing details for PayMongo Checkout (card), including address prefill from enrollment.
     *
     * @return array{name: string, email: string, phone: string, address: array{line1: string, city: string, state: string, postal_code: string, country: string}}
     */
    private function buildCheckoutBilling(Enrollment $enrollment): array
    {
        return [
            'name' => (string) $enrollment->full_name,
            'email' => (string) $enrollment->email,
            'phone' => (string) $enrollment->phone,
            'address' => [
                'line1' => (string) $enrollment->addr_street,
                'city' => (string) $enrollment->addr_city,
                'state' => (string) $enrollment->addr_province,
                'postal_code' => (string) $enrollment->addr_zip,
                'country' => 'PH',
            ],
        ];
    }
}
