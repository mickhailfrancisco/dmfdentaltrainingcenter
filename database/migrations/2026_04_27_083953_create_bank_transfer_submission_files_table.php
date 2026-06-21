<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transfer_submission_files', function (Blueprint $table) {
            $table->id();
            // Explicit FK name: auto-generated name exceeds MySQL's 64-char identifier limit.
            $table->foreignId('bank_transfer_submission_id')
                ->constrained('bank_transfer_submissions', 'id', 'bt_files_submission_fk')
                ->cascadeOnDelete();

            $table->string('slot', 20);
            $table->string('path');

            $table->timestamps();

            $table->unique(['bank_transfer_submission_id', 'slot'], 'bt_submission_files_slot_unique');
            $table->index('slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfer_submission_files');
    }
};
