<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $schoolYearId = DB::table('school_years')->insertGetId([
            'label' => 'SY 2025–2026',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('schedules')
            ->whereNull('school_year_id')
            ->update(['school_year_id' => $schoolYearId]);
    }

    public function down(): void
    {
        DB::table('schedules')->update(['school_year_id' => null]);
        DB::table('school_years')->where('label', 'SY 2025–2026')->delete();
    }
};
