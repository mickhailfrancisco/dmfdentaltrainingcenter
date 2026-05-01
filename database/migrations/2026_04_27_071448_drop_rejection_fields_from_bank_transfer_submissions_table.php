<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transfer_submissions', function (Blueprint $table) {
            // Be defensive: this migration may run in environments where the rejection fields were never added.
            if (Schema::hasColumn('bank_transfer_submissions', 'rejected_by')) {
                $table->dropIndex('bank_transfer_submissions_rejected_by_index');
                $table->dropConstrainedForeignId('rejected_by');
            }

            if (Schema::hasColumn('bank_transfer_submissions', 'rejected_at')) {
                $table->dropIndex('bank_transfer_submissions_rejected_at_index');
                $table->dropColumn('rejected_at');
            }

            if (Schema::hasColumn('bank_transfer_submissions', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_transfer_submissions', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('verified_by');
            $table->foreignId('rejected_by')
                ->nullable()
                ->after('rejected_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('rejected_by');

            $table->index('rejected_at');
            $table->index('rejected_by');
        });
    }
};
