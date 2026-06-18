<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Enrollment tuition pricing and balance rules (snapshot + early-bird cutoff).
 *
 * Policy (locked for this implementation):
 * - Convenience fee: **₱50 per PayMongo checkout** (initial full/DP and balance checkout).
 * - Downpayment amount: **50% of list price** (`tuition_list_amount * 0.5`) at enrollment time.
 * - Full payment at enrollment: **active price** (`price_early` while early bird is active, else `price_full`).
 * - Remaining balance after DP: `applicable_tuition_total - amount_paid_tuition`.
 * - While an early-bird window is open, `applicable_tuition_total` follows that tier's price.
 * - After a deadline passes, students who **fully settled** that tier before the deadline keep
 *   that tier price locked (balance stays zero). Partial payers after deadline owe list price minus paid.
 * - Settlement before deadline uses `bank_transfer_submissions.submitted_at` when present, else `paid_at`.
 *
 * @author CKD
 *
 * @created 2026-03-26
 *
 * @modified 2026-06-04 CKD
 */
final class EnrollmentPricingService
{
    public const CONVENIENCE_FEE_PESOS = 50;

    /**
     * Full course tuition that applies **right now** for balance settlement (DP enrollments only).
     */
    public static function applicableTuitionTotal(Enrollment $enrollment): int
    {
        if ($enrollment->payment_type === 'full') {
            return (int) $enrollment->amount_paid_tuition;
        }

        $early = $enrollment->tuition_price_early;
        $deadline = $enrollment->tuition_early_deadline;

        if ($early !== null && $deadline !== null && self::tuitionSettledAtTierBeforeDeadline($enrollment, (int) $early, $deadline)) {
            return (int) $early;
        }

        $early2 = $enrollment->tuition_price_early_2;
        $deadline2 = $enrollment->tuition_early_deadline_2;

        if ($early2 !== null && $deadline2 !== null && self::tuitionSettledAtTierBeforeDeadline($enrollment, (int) $early2, $deadline2)) {
            return (int) $early2;
        }

        if ($early !== null && $deadline !== null && self::isEarlyBirdWindowOpen($deadline)) {
            return (int) $early;
        }

        if ($early2 !== null && $deadline2 !== null && self::isEarlyBirdWindowOpen($deadline2)) {
            return (int) $early2;
        }

        return (int) ($enrollment->tuition_list_amount ?? 0);
    }

    /**
     * Tuition still owed (excluding convenience fees).
     */
    public static function balanceTuitionDue(Enrollment $enrollment): int
    {
        if ($enrollment->payment_type === 'full') {
            return 0;
        }

        $target = self::applicableTuitionTotal($enrollment);
        $paid = (int) $enrollment->amount_paid_tuition;

        return max(0, $target - $paid);
    }

    public static function isEarlyBirdWindowOpen(?\DateTimeInterface $deadline): bool
    {
        if ($deadline === null) {
            return false;
        }

        return now()->timezone('Asia/Manila')->startOfDay()->lte($deadline);
    }

    /**
     * Whether cumulative paid tuition reached the tier target on or before the deadline.
     */
    public static function tuitionSettledAtTierBeforeDeadline(
        Enrollment $enrollment,
        int $targetPrice,
        \DateTimeInterface $deadline,
    ): bool {
        if (! $enrollment->exists) {
            return false;
        }

        $deadlineEnd = Carbon::parse($deadline)->timezone('Asia/Manila')->endOfDay();
        $settledBeforeDeadline = 0;

        foreach (self::paidPaymentsForSettlement($enrollment) as $payment) {
            $effectiveAt = self::effectiveSettlementAt($payment);

            if ($effectiveAt === null || $effectiveAt->gt($deadlineEnd)) {
                continue;
            }

            $settledBeforeDeadline += self::tuitionCreditedFromPayment($payment);

            if ($settledBeforeDeadline >= $targetPrice) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, Payment>
     */
    private static function paidPaymentsForSettlement(Enrollment $enrollment): Collection
    {
        return Payment::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->where('status', 'paid')
            ->with('bankTransferSubmission')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();
    }

    private static function effectiveSettlementAt(Payment $payment): ?Carbon
    {
        $submittedAt = $payment->bankTransferSubmission?->submitted_at;

        if ($submittedAt !== null) {
            return Carbon::parse($submittedAt)->timezone('Asia/Manila');
        }

        if ($payment->paid_at === null) {
            return null;
        }

        return Carbon::parse($payment->paid_at)->timezone('Asia/Manila');
    }

    private static function tuitionCreditedFromPayment(Payment $payment): int
    {
        $tuitionAmount = (int) $payment->tuition_amount;

        if ($tuitionAmount > 0) {
            return $tuitionAmount;
        }

        return max(0, (int) round(((int) $payment->amount) / 100) - self::CONVENIENCE_FEE_PESOS);
    }
}
