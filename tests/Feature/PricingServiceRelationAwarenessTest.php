<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\EnrollmentPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PricingServiceRelationAwarenessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build enrollment attributes that force the early-bird settlement check,
     * which is the code path that calls paidPaymentsForSettlement().
     *
     * @return array<string, mixed>
     */
    private function enrollmentWithEarlyBird(): array
    {
        return [
            'payment_type' => 'dp',
            'tuition_list_amount' => 10000,
            'tuition_price_early' => 9000,
            'tuition_early_deadline' => now()->addDays(30)->toDateString(),
            'tuition_price_early_2' => null,
            'tuition_early_deadline_2' => null,
            'amount_paid_tuition' => 3000,
        ];
    }

    public function test_balance_tuition_due_does_not_query_db_when_payments_relation_is_loaded(): void
    {
        $enrollment = Enrollment::factory()->create($this->enrollmentWithEarlyBird());

        Payment::factory()->sequence(
            ['purpose' => Payment::PURPOSE_INITIAL],
            ['purpose' => Payment::PURPOSE_BALANCE],
        )->count(2)->create([
            'enrollment_id' => $enrollment->getKey(),
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Reload with relation pre-loaded — mimics what EnrollmentSuccessService does
        $enrollment = $enrollment->fresh(['payments.bankTransferSubmission']);

        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            if (stripos($query->sql, 'payments') !== false) {
                $queryCount++;
            }
        });

        EnrollmentPricingService::balanceTuitionDue($enrollment);

        $this->assertSame(0, $queryCount, "Expected 0 payment queries when relation is pre-loaded, got {$queryCount}");
    }

    public function test_balance_tuition_due_queries_db_when_payments_relation_is_not_loaded(): void
    {
        $enrollment = Enrollment::factory()->create($this->enrollmentWithEarlyBird());

        Payment::factory()->sequence(
            ['purpose' => Payment::PURPOSE_INITIAL],
            ['purpose' => Payment::PURPOSE_BALANCE],
        )->count(2)->create([
            'enrollment_id' => $enrollment->getKey(),
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Do NOT load the relation — fresh enrollment from factory
        $this->assertFalse($enrollment->relationLoaded('payments'));

        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            if (stripos($query->sql, 'payments') !== false) {
                $queryCount++;
            }
        });

        EnrollmentPricingService::balanceTuitionDue($enrollment);

        $this->assertGreaterThan(0, $queryCount, 'Expected at least 1 payment query when relation is not pre-loaded');
    }

    public function test_balance_calculation_is_correct_with_pre_loaded_relation(): void
    {
        $enrollment = Enrollment::factory()->create([
            'payment_type' => 'dp',
            'tuition_list_amount' => 10000,
            'tuition_price_early' => null,
            'tuition_early_deadline' => null,
            'tuition_price_early_2' => null,
            'tuition_early_deadline_2' => null,
            'amount_paid_tuition' => 3000,
        ]);

        Payment::factory()->create([
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => Payment::PURPOSE_INITIAL,
            'status' => 'paid',
            'paid_at' => now(),
            'amount' => 310000, // 3100 pesos in centavos
        ]);

        $enrollment = $enrollment->fresh(['payments.bankTransferSubmission']);

        $balance = EnrollmentPricingService::balanceTuitionDue($enrollment);

        $this->assertSame(7000, $balance); // 10000 list - 3000 amount_paid_tuition
    }
}
