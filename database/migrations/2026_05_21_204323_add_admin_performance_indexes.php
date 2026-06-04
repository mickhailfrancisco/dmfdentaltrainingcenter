<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['enrollment_id', 'purpose', 'payment_method', 'status'], 'payments_enrollment_bank_transfer_lookup_index');
            $table->index(['enrollment_id', 'status'], 'payments_enrollment_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_enrollment_bank_transfer_lookup_index');
            $table->dropIndex('payments_enrollment_status_index');
        });
    }
};
