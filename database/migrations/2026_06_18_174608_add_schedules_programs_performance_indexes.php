<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->index(['program_id', 'is_active'], 'schedules_program_id_is_active_index');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->index(['is_active', 'sort_order'], 'programs_is_active_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_program_id_is_active_index');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropIndex('programs_is_active_sort_order_index');
        });
    }
};
