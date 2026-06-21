<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('schedules', 'school_year_id')) {
            $this->dropSchedulesSchoolYearForeignKeyIfExists();

            Schema::table('schedules', function (Blueprint $table) {
                $table->dropColumn('school_year_id');
            });
        }

        Schema::dropIfExists('school_years');
    }

    public function down(): void
    {
        // School years were removed from the product; do not recreate on rollback.
    }

    private function dropSchedulesSchoolYearForeignKeyIfExists(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $constraints = DB::select(
                "SELECT CONSTRAINT_NAME AS name
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'schedules'
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                  AND CONSTRAINT_NAME IN (
                      SELECT CONSTRAINT_NAME
                      FROM information_schema.KEY_COLUMN_USAGE
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'schedules'
                        AND COLUMN_NAME = 'school_year_id'
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                  )"
            );

            foreach ($constraints as $constraint) {
                DB::statement(sprintf(
                    'ALTER TABLE `schedules` DROP FOREIGN KEY `%s`',
                    str_replace('`', '``', (string) $constraint->name),
                ));
            }

            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS schedules_school_year_id_foreign');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            // SQLite tests rebuild schema without this FK in most cases.
            try {
                Schema::table('schedules', function (Blueprint $table) {
                    $table->dropForeign(['school_year_id']);
                });
            } catch (Throwable) {
                // Column-only legacy state; dropColumn below is sufficient.
            }
        }
    }
};
