<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentItem;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes enrollment tuition paid, balance due, and status from paid Payment rows.
 *
 * Idempotent: safe to call after every successful webhook or checkout sync.
 *
 * @author CKD
 *
 * @created 2026-03-26
 */
final class EnrollmentFinancialService
{
    public function recalculateEnrollmentFinancials(Enrollment $enrollment): void
    {
        DB::transaction(function () use ($enrollment): void {
            $locked = Enrollment::query()->whereKey($enrollment->getKey())->lockForUpdate()->firstOrFail();

            $sum = Payment::query()
                ->where('enrollment_id', $locked->getKey())
                ->where('status', 'paid')
                ->get()
                ->sum(function (Payment $payment): int {
                    $fromColumn = (int) $payment->tuition_amount;
                    if ($fromColumn > 0) {
                        return $fromColumn;
                    }

                    $chargedPesos = (int) round(((int) $payment->amount) / 100);

                    return max(0, $chargedPesos - EnrollmentPricingService::CONVENIENCE_FEE_PESOS);
                });

            $locked->amount_paid_tuition = $sum;
            $locked->balance_tuition_due = EnrollmentPricingService::balanceTuitionDue($locked);
            $locked->status = $this->resolveStatusFromLedger($locked);
            $locked->save();

            EnrollmentItem::query()
                ->where('enrollment_id', $locked->getKey())
                ->update([
                    'status' => (string) $locked->status->value,
                ]);
        });
    }

    private function resolveStatusFromLedger(Enrollment $enrollment): EnrollmentStatus
    {
        $paidExists = Payment::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->where('status', 'paid')
            ->exists();

        if (! $paidExists) {
            return $enrollment->status === EnrollmentStatus::CANCELLED
                ? EnrollmentStatus::CANCELLED
                : EnrollmentStatus::PENDING;
        }

        if ($enrollment->payment_type === 'full') {
            return EnrollmentStatus::CONFIRMED;
        }

        return $enrollment->balance_tuition_due > 0
            ? EnrollmentStatus::PARTIALLY_PAID
            : EnrollmentStatus::CONFIRMED;
    }
}
