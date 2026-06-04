<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->unsignedInteger('price_early_2')->nullable()->after('early_deadline');
            $table->date('early_deadline_2')->nullable()->after('price_early_2');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedInteger('price_early_2')->nullable()->after('early_deadline');
            $table->date('early_deadline_2')->nullable()->after('price_early_2');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['price_early_2', 'early_deadline_2']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['price_early_2', 'early_deadline_2']);
        });
    }
};
