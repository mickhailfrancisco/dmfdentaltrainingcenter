<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Replace the non-unique index with a composite unique constraint.
            $table->dropIndex(['enrollment_id', 'purpose']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['enrollment_id', 'purpose'], 'payments_enrollment_purpose_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_enrollment_purpose_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['enrollment_id', 'purpose']);
        });
    }
};
