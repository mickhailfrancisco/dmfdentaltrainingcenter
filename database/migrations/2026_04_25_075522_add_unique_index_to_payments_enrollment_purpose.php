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
            // FK must be dropped before dropping the index it depends on.
            $table->dropForeign(['enrollment_id']);
            $table->dropIndex(['enrollment_id', 'purpose']);
            $table->unique(['enrollment_id', 'purpose'], 'payments_enrollment_purpose_unique');
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['enrollment_id']);
            $table->dropUnique('payments_enrollment_purpose_unique');
            $table->index(['enrollment_id', 'purpose']);
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('cascade');
        });
    }
};
