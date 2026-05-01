<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentLinkService
{
    public function __construct(
        protected PaymongoService $paymongoService,
        protected EnrollmentFinancialService $financialService,
    ) {}

    /**
     * Start a card-only PayMongo checkout from a direct pay link.
     *
     * The HTTP route may still include a third `{payment_method}` segment for
     * backward compatibility with old shared URLs; it is ignored.
     */
    public function startPaymongoCheckoutFromLink(string $referenceNumber, string $purpose): string
    {
        if (! in_array($purpose, [Payment::PURPOSE_INITIAL, Payment::PURPOSE_BALANCE], true)) {
            throw new RuntimeException('Invalid payment purpose.');
        }

        $enrollment = Enrollment::query()
            ->where('reference_number', $referenceNumber)
            ->firstOrFail();

        try {
            // Keep ledger fresh for balance checkout logic and statuses.
            $this->financialService->recalculateEnrollmentFinancials($enrollment);

            $checkout = $this->paymongoService->createCheckoutSession(
                $enrollment,
                $purpose,
            );

            $checkoutUrl = (string) ($checkout['checkout_url'] ?? '');
            if ($checkoutUrl === '') {
                throw new RuntimeException('Checkout URL missing.');
            }

            return $checkoutUrl;
        } catch (\Throwable $e) {
            Log::error('Payment link checkout failed.', [
                'reference_number' => $referenceNumber,
                'purpose' => $purpose,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Unable to initialize payment checkout.', previous: $e);
        }
    }
}
