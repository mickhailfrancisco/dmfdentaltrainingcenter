<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

// This migration is intentionally a no-op. The composite unique constraint
// (enrollment_id, purpose) is now created directly in:
// 2026_03_26_120000_enrollment_pricing_snapshot_and_payment_purpose
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
