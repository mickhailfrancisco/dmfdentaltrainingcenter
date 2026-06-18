<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RecalculateStaleEnrollmentFinancialsCommandTest extends TestCase
{
    public function test_command_recalculates_enrollments_past_early_deadline(): void
    {
        $program = Program::create([
            'name' => 'Command Program',
            'slug' => 'command-program',
            'category' => 'Individual Programs (Theoretical)',
            'price_full' => 10_000,
            'price_early' => 8_000,
            'early_deadline' => '2026-01-01',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $enrollment = Enrollment::create([
            'reference_number' => 'DMF-CMD-RECALC',
            'status' => EnrollmentStatus::PARTIALLY_PAID->value,
            'first_name' => 'Cmd',
            'surname' => 'Test',
            'email' => 'cmd@test.com',
            'phone' => '09000000000',
            'birthday' => '2000-01-01',
            'sex' => 'Male',
            'addr_street' => '123 Test St',
            'addr_city' => 'Quezon City',
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1100',
            'school' => 'Test University',
            'year_level' => 'Graduate',
            'taker_status' => 'First Taker',
            'payment_type' => 'downpayment',
            'base_amount' => 5_000,
            'convenience_fee' => 500,
            'total_amount' => 5_500,
            'purchasable_name_snapshot' => $program->name,
            'purchasable_slug_snapshot' => $program->slug,
            'purchasable_type' => Program::class,
            'purchasable_id' => $program->getKey(),
            'tuition_list_amount' => 10_000,
            'tuition_price_early' => 8_000,
            'tuition_early_deadline' => '2026-01-01',
            'amount_paid_tuition' => 5_000,
            'balance_tuition_due' => 3_000,
        ]);

        Payment::create([
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => 550_000,
            'currency' => 'PHP',
            'tuition_amount' => 5_000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Artisan::call('enrollments:recalculate-stale-financials');

        $enrollment->refresh();

        $this->assertSame(5_000, $enrollment->balance_tuition_due);
    }

    public function test_command_keeps_zero_balance_when_early_bird_fully_paid_before_deadline(): void
    {
        $program = Program::create([
            'name' => 'Command Full Early',
            'slug' => 'command-full-early',
            'category' => 'Individual Programs (Theoretical)',
            'price_full' => 43_000,
            'price_early' => 41_000,
            'early_deadline' => '2026-01-01',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $enrollment = Enrollment::create([
            'reference_number' => 'DMF-CMD-FULL-EARLY',
            'status' => EnrollmentStatus::CONFIRMED->value,
            'first_name' => 'Full',
            'surname' => 'Early',
            'email' => 'full-early@test.com',
            'phone' => '09000000000',
            'birthday' => '2000-01-01',
            'sex' => 'Male',
            'addr_street' => '123 Test St',
            'addr_city' => 'Quezon City',
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1100',
            'school' => 'Test University',
            'year_level' => 'Graduate',
            'taker_status' => 'First Taker',
            'payment_type' => 'downpayment',
            'base_amount' => 21_500,
            'convenience_fee' => 50,
            'total_amount' => 21_550,
            'purchasable_name_snapshot' => $program->name,
            'purchasable_slug_snapshot' => $program->slug,
            'purchasable_type' => Program::class,
            'purchasable_id' => $program->getKey(),
            'tuition_list_amount' => 43_000,
            'tuition_price_early' => 41_000,
            'tuition_early_deadline' => '2026-01-01',
            'amount_paid_tuition' => 41_000,
            'balance_tuition_due' => 2_000,
        ]);

        Payment::create([
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'card',
            'amount' => 2_155_000,
            'currency' => 'PHP',
            'tuition_amount' => 21_500,
            'status' => 'paid',
            'paid_at' => '2025-12-15 10:00:00',
        ]);

        Payment::create([
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => Payment::PURPOSE_BALANCE,
            'payment_method' => 'card',
            'amount' => 1_955_000,
            'currency' => 'PHP',
            'tuition_amount' => 19_500,
            'status' => 'paid',
            'paid_at' => '2025-12-24 10:00:00',
        ]);

        Artisan::call('enrollments:recalculate-stale-financials');

        $enrollment->refresh();

        $this->assertSame(41_000, $enrollment->amount_paid_tuition);
        $this->assertSame(0, $enrollment->balance_tuition_due);
        $this->assertSame(EnrollmentStatus::CONFIRMED->value, $enrollment->status->value);
    }
}
