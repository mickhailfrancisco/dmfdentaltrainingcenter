<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;

/**
 * Prepares enrollment data for the public success page without blocking on
 * unnecessary PayMongo polling or duplicate ledger recalculations.
 */
final class EnrollmentSuccessService
{
    /** @var list<string> */
    private const ENROLLMENT_RELATIONS = [
        'purchasable',
        'payments.bankTransferSubmission',
        'items.program',
    ];

    public function __construct(
        protected PaymongoService $paymongoService,
        protected EnrollmentFinancialService $enrollmentFinancialService,
    ) {}

    /**
     * Resolve an enrollment for the success page and refresh payment state when needed.
     *
     * @author CKD
     *
     * @created 2026-06-19
     *
     * @modified 2026-06-19 CKD
     */
    public function resolveEnrollmentByReference(string $referenceNumber): ?Enrollment
    {
        return Enrollment::query()
            ->with(self::ENROLLMENT_RELATIONS)
            ->where('reference_number', $referenceNumber)
            ->first();
    }

    /**
     * Sync pending PayMongo checkouts only when needed, then return a fresh enrollment graph.
     *
     * @author CKD
     *
     * @created 2026-06-19
     *
     * @modified 2026-06-19 CKD
     */
    public function prepareEnrollmentForDisplay(Enrollment $enrollment): Enrollment
    {
        if ($this->paymongoService->hasPendingCheckoutSessions($enrollment)) {
            $this->paymongoService->syncPendingCheckoutSessionsForEnrollment($enrollment);
            $enrollment = $this->reloadEnrollment($enrollment);
        }

        return $enrollment;
    }

    /**
     * @author CKD
     *
     * @created 2026-06-19
     *
     * @modified 2026-06-19 CKD
     */
    public function reloadEnrollment(Enrollment $enrollment): Enrollment
    {
        return $enrollment->fresh(self::ENROLLMENT_RELATIONS) ?? $enrollment;
    }

    /**
     * Pre-compute tuition figures once for the success page view.
     *
     * @return array{balance_tuition_due: int, applicable_tuition_total: int}
     *
     * @author CKD
     *
     * @created 2026-06-19
     *
     * @modified 2026-06-19 CKD
     */
    public function pricingSnapshot(Enrollment $enrollment): array
    {
        return [
            'balance_tuition_due' => EnrollmentPricingService::balanceTuitionDue($enrollment),
            'applicable_tuition_total' => EnrollmentPricingService::applicableTuitionTotal($enrollment),
        ];
    }
}
