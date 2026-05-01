<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transfer_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')
                ->unique()
                ->constrained('payments')
                ->cascadeOnDelete();

            $table->string('reference_number', 60);
            $table->string('proof_path');
            $table->timestamp('submitted_at');

            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('reference_number');
            $table->index('submitted_at');
            $table->index('verified_at');
            $table->index('verified_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfer_submissions');
    }
};
