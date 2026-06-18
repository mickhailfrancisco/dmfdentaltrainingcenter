<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Services\EnrollmentFinancialService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Recomputes enrollment ledgers when early-bird deadlines have passed.
 *
 * Keeps stored balance_tuition_due aligned with computed early-bird pricing
 * so admin tabs and filters remain accurate after deadline transitions.
 *
 * @author CKD
 *
 * @created 2026-06-04
 */
class RecalculateStaleEnrollmentFinancials extends Command
{
    protected $signature = 'enrollments:recalculate-stale-financials';

    protected $description = 'Recalculate enrollment financials after early-bird deadlines have passed';

    public function handle(EnrollmentFinancialService $financialService): int
    {
        $today = Carbon::now(config('app.display_timezone'))->startOfDay()->toDateString();
        $processed = 0;

        Enrollment::query()
            ->where('status', '!=', EnrollmentStatus::CANCELLED->value)
            ->where(function ($query) use ($today): void {
                $query
                    ->where(function ($deadlineQuery) use ($today): void {
                        $deadlineQuery
                            ->whereNotNull('tuition_early_deadline')
                            ->whereDate('tuition_early_deadline', '<', $today);
                    })
                    ->orWhere(function ($deadlineQuery) use ($today): void {
                        $deadlineQuery
                            ->whereNotNull('tuition_early_deadline_2')
                            ->whereDate('tuition_early_deadline_2', '<', $today);
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function ($enrollments) use ($financialService, &$processed): void {
                foreach ($enrollments as $enrollment) {
                    $financialService->recalculateEnrollmentFinancials($enrollment);
                    $processed++;
                }
            });

        $this->info("Recalculated {$processed} enrollment(s).");

        return self::SUCCESS;
    }
}
