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

            $sum = $this->sumPaidTuitionForEnrollment((int) $locked->getKey());

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

    /**
     * Sum tuition credited from paid payments using a single aggregate query.
     */
    private function sumPaidTuitionForEnrollment(int $enrollmentId): int
    {
        $convenienceFee = EnrollmentPricingService::CONVENIENCE_FEE_PESOS;

        $driver = DB::connection()->getDriverName();

        $tuitionExpression = match ($driver) {
            'pgsql' => 'CASE WHEN tuition_amount > 0 THEN tuition_amount ELSE GREATEST(0, (ROUND(amount / 100.0)::integer - ?)) END',
            default => 'CASE WHEN tuition_amount > 0 THEN tuition_amount ELSE MAX(0, CAST(ROUND(amount / 100.0) AS INTEGER) - ?) END',
        };

        return (int) Payment::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('status', 'paid')
            ->selectRaw("COALESCE(SUM({$tuitionExpression}), 0) as tuition_total", [$convenienceFee])
            ->value('tuition_total');
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
