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
            $table->foreignId('bank_transfer_submission_id')
                ->constrained('bank_transfer_submissions')
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
