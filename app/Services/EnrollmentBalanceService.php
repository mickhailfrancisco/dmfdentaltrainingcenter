<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BalanceAlreadySettledException;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Orchestrates the student-facing balance payment flow for downpayment enrollments.
 *
 * Responsible for: loading enrollment data, recalculating the ledger,
 * generating a time-limited signed checkout URL, and starting the PayMongo
 * checkout session for the remaining tuition balance.
 *
 * @author CKD
 *
 * @created 2026-04-20
 */
final class EnrollmentBalanceService
{
    /**
     * Signed URL lifetime for the balance checkout POST action (minutes).
     * Starts when the student opens the balance page.
     */
    private const PAY_URL_TTL_MINUTES = 120;

    public function __construct(
        protected EnrollmentFinancialService $financialService,
        protected PaymongoService $paymongoService,
        protected BankTransferService $bankTransferService,
    ) {}

    /**
     * Resolve all data needed to render the balance payment page.
     *
     * Recalculates the ledger from paid payments before returning data,
     * so the page always reflects the latest settled amount.
     *
     * @param  string  $referenceNumber  Enrollment reference number from the signed route.
     * @return array{
     *     enrollment: Enrollment,
     *     purchasable_name: string,
     *     balance_tuition: int,
     *     convenience_fee: int,
     *     total_due: int,
     *     pay_url: string,
     * }
     *
     * @throws BalanceAlreadySettledException When the remaining tuition is zero or less.
     * @throws RuntimeException When the enrollment is not a downpayment type.
     *
     * @author CKD
     *
     * @created 2026-04-20
     */
    public function getBalancePageData(string $referenceNumber): array
    {
        $enrollment = Enrollment::query()
            ->where('reference_number', $referenceNumber)
            ->firstOrFail();

        $this->paymongoService->syncPendingCheckoutSessionsForEnrollment($enrollment);
        $enrollment->refresh();

        $this->financialService->recalculateEnrollmentFinancials($enrollment);
        $enrollment->refresh();

        if ($enrollment->payment_type !== 'downpayment') {
            throw new RuntimeException('This payment link is only valid for downpayment enrollments.');
        }

        if ((int) $enrollment->amount_paid_tuition <= 0) {
            throw new RuntimeException('No downpayment has been received yet. Please pay the initial checkout first.');
        }

        $balance = EnrollmentPricingService::balanceTuitionDue($enrollment);
        if ($balance <= 0) {
            throw new BalanceAlreadySettledException($enrollment->reference_number);
        }

        $fee = EnrollmentPricingService::CONVENIENCE_FEE_PESOS;

        $payUrl = URL::temporarySignedRoute(
            'enroll.balance.pay',
            now()->addMinutes(self::PAY_URL_TTL_MINUTES),
            ['reference_number' => $referenceNumber],
        );

        return [
            'enrollment' => $enrollment,
            'purchasable_name' => (string) ($enrollment->purchasable_name_snapshot ?? '—'),
            'balance_tuition' => $balance,
            'convenience_fee' => $fee,
            'total_due' => $balance + $fee,
            'pay_url' => $payUrl,
        ];
    }

    /**
     * Create a PayMongo checkout session for the remaining tuition balance.
     *
     * @param  string  $referenceNumber  Enrollment reference number from the signed route.
     * @param  string  $paymentMethod  Validated payment method slug (card | bank_transfer).
     * @return array{checkout_url: string, reference_number: string, payment_id: int}
     *
     * @throws RuntimeException When checkout session creation fails or returns no URL.
     *
     * @author CKD
     *
     * @created 2026-04-20
     */
    public function startBalanceCheckout(string $referenceNumber, string $paymentMethod): RedirectResponse
    {
        if ($paymentMethod === 'bank_transfer') {
            return $this->bankTransferService->startBalanceBankTransfer($referenceNumber);
        }

        $enrollment = Enrollment::where('reference_number', $referenceNumber)->firstOrFail();

        $this->paymongoService->syncPendingCheckoutSessionsForEnrollment($enrollment);
        $enrollment->refresh();

        $checkout = $this->paymongoService->createCheckoutSession(
            $enrollment,
            Payment::PURPOSE_BALANCE,
        );

        if (empty($checkout['checkout_url'])) {
            throw new RuntimeException('Unable to initialize payment checkout. Please try again.');
        }

        return redirect()->away($checkout['checkout_url']);
    }
}
