<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds pricing snapshot columns to enrollments and purpose/tuition columns to payments.
 * Backfills existing rows using the DB query builder only — no Eloquent models or
 * service classes, so this migration remains safe across future model/service changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add columns to payments ──────────────────────────────────────
        // Guard against partial previous run (MySQL DDL is non-transactional).
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'purpose')) {
                $table->string('purpose', 20)->default('initial')->after('enrollment_id');
            }
            if (! Schema::hasColumn('payments', 'tuition_amount')) {
                $table->unsignedInteger('tuition_amount')->default(0)->after('currency');
            }
        });

        // Backfill: purpose = 'initial', tuition_amount = enrollments.base_amount
        DB::table('payments')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $baseAmount = (int) (DB::table('enrollments')
                        ->where('id', $row->enrollment_id)
                        ->value('base_amount') ?? 0);

                    DB::table('payments')->where('id', $row->id)->update([
                        'purpose' => 'initial',
                        'tuition_amount' => $baseAmount,
                    ]);
                }
            });

        // Drop legacy unique constraint so one enrollment can have multiple payment rows.
        // MySQL requires dropping the FK that references the unique index before the index
        // can be dropped, then the FK is re-added.
        if (DB::getDriverName() === 'mysql') {
            $hasLegacyUnique = collect(DB::select("SHOW INDEX FROM payments WHERE Key_name = 'payments_enrollment_id_unique'"))->isNotEmpty();

            if ($hasLegacyUnique) {
                Schema::table('payments', function (Blueprint $table) {
                    $table->dropForeign(['enrollment_id']);
                });
                Schema::table('payments', function (Blueprint $table) {
                    $table->dropUnique(['enrollment_id']);
                });
                Schema::table('payments', function (Blueprint $table) {
                    $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('cascade');
                });
            }
        } else {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropUnique(['enrollment_id']);
            });
        }

        // Create composite unique constraint (enrollment + purpose) if not already present.
        if (DB::getDriverName() === 'mysql') {
            $hasCompositeUnique = collect(DB::select("SHOW INDEX FROM payments WHERE Key_name = 'payments_enrollment_purpose_unique'"))->isNotEmpty();

            if (! $hasCompositeUnique) {
                Schema::table('payments', function (Blueprint $table) {
                    $table->unique(['enrollment_id', 'purpose'], 'payments_enrollment_purpose_unique');
                });
            }
        } else {
            Schema::table('payments', function (Blueprint $table) {
                $table->unique(['enrollment_id', 'purpose'], 'payments_enrollment_purpose_unique');
            });
        }

        // ── 2. Add snapshot + ledger columns to enrollments ─────────────────
        Schema::table('enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('enrollments', 'tuition_list_amount')) {
                $table->unsignedInteger('tuition_list_amount')->nullable()->after('total_amount');
            }
            if (! Schema::hasColumn('enrollments', 'tuition_price_early')) {
                $table->unsignedInteger('tuition_price_early')->nullable()->after('tuition_list_amount');
            }
            if (! Schema::hasColumn('enrollments', 'tuition_early_deadline')) {
                $table->date('tuition_early_deadline')->nullable()->after('tuition_price_early');
            }
            if (! Schema::hasColumn('enrollments', 'tuition_price_dp')) {
                $table->unsignedInteger('tuition_price_dp')->nullable()->after('tuition_early_deadline');
            }
            if (! Schema::hasColumn('enrollments', 'tuition_discount_amount')) {
                $table->unsignedInteger('tuition_discount_amount')->default(0)->after('tuition_price_dp');
            }
            if (! Schema::hasColumn('enrollments', 'tuition_discount_label')) {
                $table->string('tuition_discount_label')->nullable()->after('tuition_discount_amount');
            }
            if (! Schema::hasColumn('enrollments', 'amount_paid_tuition')) {
                $table->unsignedInteger('amount_paid_tuition')->default(0)->after('tuition_discount_label');
            }
            if (! Schema::hasColumn('enrollments', 'balance_tuition_due')) {
                $table->unsignedInteger('balance_tuition_due')->default(0)->after('amount_paid_tuition');
            }
        });

        // Backfill: copy pricing snapshot from programs
        DB::table('enrollments')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $program = DB::table('programs')->where('id', $row->program_id)->first();
                    if (! $program) {
                        continue;
                    }

                    $list = (int) $program->price_full;
                    $early = $program->price_early !== null ? (int) $program->price_early : null;
                    $deadline = $program->early_deadline;
                    $dp = (int) $program->price_dp;

                    DB::table('enrollments')->where('id', $row->id)->update([
                        'tuition_list_amount' => $list,
                        'tuition_price_early' => $early,
                        'tuition_early_deadline' => $deadline,
                        'tuition_price_dp' => $dp,
                        'tuition_discount_amount' => 0,
                        'tuition_discount_label' => null,
                    ]);
                }
            });

        // ── 3. Recalculate ledger (amount_paid_tuition, balance_tuition_due, status) ──
        // Uses the same logic as EnrollmentFinancialService without depending on it.
        $nowManila = Carbon::now()->timezone('Asia/Manila')->startOfDay();

        DB::table('enrollments')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($nowManila): void {
                foreach ($rows as $row) {
                    // Sum paid tuition per payment row (mirrors EnrollmentFinancialService logic)
                    $payments = DB::table('payments')
                        ->where('enrollment_id', $row->id)
                        ->where('status', 'paid')
                        ->get(['tuition_amount', 'amount']);

                    $paid = 0;
                    foreach ($payments as $payment) {
                        $fromColumn = (int) $payment->tuition_amount;
                        if ($fromColumn > 0) {
                            $paid += $fromColumn;
                        } else {
                            $chargedPesos = (int) round((int) $payment->amount / 100);
                            $paid += max(0, $chargedPesos - 50); // 50 = CONVENIENCE_FEE_PESOS
                        }
                    }

                    // Determine balance
                    if ($row->payment_type === 'full') {
                        $balance = 0;
                    } else {
                        $early = $row->tuition_price_early;
                        $deadline = $row->tuition_early_deadline;
                        $list = (int) ($row->tuition_list_amount ?? 0);

                        if ($early !== null && $deadline !== null) {
                            $target = $nowManila->lte(Carbon::parse($deadline, 'Asia/Manila'))
                                ? (int) $early
                                : $list;
                        } else {
                            $target = $list;
                        }

                        $balance = max(0, $target - $paid);
                    }

                    // Resolve status (mirrors EnrollmentFinancialService::resolveStatusFromLedger)
                    if ($paid === 0) {
                        $status = $row->status === 'cancelled' ? 'cancelled' : 'pending';
                    } elseif ($row->payment_type === 'full') {
                        $status = 'confirmed';
                    } else {
                        $status = $balance > 0 ? 'partially_paid' : 'confirmed';
                    }

                    DB::table('enrollments')->where('id', $row->id)->update([
                        'amount_paid_tuition' => $paid,
                        'balance_tuition_due' => $balance,
                        'status' => $status,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn([
                'tuition_list_amount',
                'tuition_price_early',
                'tuition_early_deadline',
                'tuition_price_dp',
                'tuition_discount_amount',
                'tuition_discount_label',
                'amount_paid_tuition',
                'balance_tuition_due',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_enrollment_purpose_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('enrollment_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['purpose', 'tuition_amount']);
        });
    }
};
