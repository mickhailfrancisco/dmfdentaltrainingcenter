<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Support\PermissionCodes;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (PermissionCodes::definitions() as $code => $label) {
            Permission::query()->firstOrCreate(
                ['code' => $code],
                ['label' => $label],
            );
        }
    }

    public function down(): void
    {
        // Permission rows may already be assigned; do not delete on rollback.
    }
};
