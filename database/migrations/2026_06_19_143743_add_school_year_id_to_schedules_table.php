<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('schedules', 'school_year_id')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->foreignId('school_year_id')
                    ->nullable()
                    ->after('program_id')
                    ->constrained('school_years')
                    ->nullOnDelete();

                $table->index('school_year_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_year_id');
        });
    }
};
