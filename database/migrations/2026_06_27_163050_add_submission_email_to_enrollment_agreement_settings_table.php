<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_agreement_settings', function (Blueprint $table) {
            $table->string('submission_email')->nullable()->after('download_filename');
        });
    }

    public function down(): void
    {
        Schema::table('enrollment_agreement_settings', function (Blueprint $table) {
            $table->dropColumn('submission_email');
        });
    }
};
