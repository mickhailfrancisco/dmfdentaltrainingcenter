<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class ResumeCheckoutService
{
    private const PAY_URL_TTL_MINUTES = 120;

    public function __construct(
        protected PaymongoService $paymongoService,
        protected BankTransferService $bankTransferService,
    ) {}

    /**
     * @return array{enrollment: Enrollment, pay_url: string}
     */
    public function getPageData(string $referenceNumber): array
    {
        $enrollment = Enrollment::query()
            ->where('reference_number', $referenceNumber)
            ->firstOrFail();

        $payUrl = URL::temporarySignedRoute(
            'enroll.checkout.pay',
            now()->addMinutes(self::PAY_URL_TTL_MINUTES),
            ['reference_number' => $referenceNumber],
        );

        return [
            'enrollment' => $enrollment,
            'pay_url' => $payUrl,
        ];
    }

    public function startInitialCheckout(string $referenceNumber, string $paymentMethod): string
    {
        $enrollment = Enrollment::query()
            ->where('reference_number', $referenceNumber)
            ->firstOrFail();

        if ($paymentMethod === 'bank_transfer') {
            return $this->bankTransferService->startInitialBankTransfer($enrollment)->getTargetUrl();
        }

        $checkout = $this->paymongoService->createCheckoutSession(
            $enrollment,
            Payment::PURPOSE_INITIAL,
        );

        $url = (string) ($checkout['checkout_url'] ?? '');
        if ($url === '') {
            throw new RuntimeException('Unable to initialize payment checkout. Please try again.');
        }

        return $url;
    }
}
