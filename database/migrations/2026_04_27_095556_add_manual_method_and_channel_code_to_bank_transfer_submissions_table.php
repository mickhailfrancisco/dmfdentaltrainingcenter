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
            $table->string('manual_method', 20)->nullable()->after('submitted_at');
            $table->string('channel_code', 30)->nullable()->after('manual_method');

            $table->index('manual_method');
            $table->index('channel_code');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transfer_submissions', function (Blueprint $table) {
            $table->dropIndex(['manual_method']);
            $table->dropIndex(['channel_code']);
            $table->dropColumn(['manual_method', 'channel_code']);
        });
    }
};
