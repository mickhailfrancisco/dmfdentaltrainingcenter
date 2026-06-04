<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unsignedInteger('tuition_price_early_2')->nullable()->after('tuition_price_early');
            $table->date('tuition_early_deadline_2')->nullable()->after('tuition_early_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['tuition_price_early_2', 'tuition_early_deadline_2']);
        });
    }
};
