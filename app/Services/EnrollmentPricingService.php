<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;

/**
 * Enrollment tuition pricing and balance rules (snapshot + early-bird cutoff).
 *
 * Policy (locked for this implementation):
 * - Convenience fee: **₱50 per PayMongo checkout** (initial full/DP and balance checkout).
 * - Downpayment amount: **50% of list price** (`tuition_list_amount * 0.5`) at enrollment time.
 * - Full payment at enrollment: **active price** (`price_early` while early bird is active, else `price_full`).
 * - Remaining balance after DP: `applicable_tuition_total - amount_paid_tuition`, where
 *   `applicable_tuition_total` is `tuition_price_early` if still within `tuition_early_deadline`,
 *   else `tuition_price_early_2` if still within `tuition_early_deadline_2` (Asia/Manila),
 *   otherwise `tuition_list_amount` (`price_full` snapshot). This matches “early bird only if balance is
 *   settled before the early-bird deadline; after deadline use full list price.”
 *
 * @author CKD
 *
 * @created 2026-03-26
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

        if ($early !== null && $deadline !== null && self::isEarlyBirdWindowOpen($deadline)) {
            return (int) $early;
        }

        $early2 = $enrollment->tuition_price_early_2;
        $deadline2 = $enrollment->tuition_early_deadline_2;

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
}
