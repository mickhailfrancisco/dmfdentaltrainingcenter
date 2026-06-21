<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\EnrollmentItem;
use App\Models\Package;
use App\Models\Program;
use App\Models\Schedule;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnrollmentItemBatchInsertTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_enrollment_creates_all_items_with_a_single_insert(): void
    {
        $package = Package::factory()
            ->has(Program::factory()->count(5)->state(['is_active' => true]))
            ->create(['is_active' => true]);

        $enrollment = Enrollment::factory()->create([
            'purchasable_type' => Package::class,
            'purchasable_id' => $package->getKey(),
        ]);

        $insertCount = 0;
        DB::listen(function ($query) use (&$insertCount) {
            if (stripos($query->sql, 'insert into') !== false
                && stripos($query->sql, 'enrollment_items') !== false) {
                $insertCount++;
            }
        });

        app(EnrollmentService::class)->createEnrollmentItemsPublic($enrollment, $package, null);

        $this->assertSame(1, $insertCount, "Expected 1 batch INSERT, got {$insertCount}");
        $this->assertSame(5, EnrollmentItem::where('enrollment_id', $enrollment->getKey())->count());
    }

    public function test_single_program_enrollment_creates_one_item_correctly(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        $schedule = Schedule::factory()->create([
            'program_id' => $program->getKey(),
            'is_active' => true,
        ]);

        $enrollment = Enrollment::factory()->create([
            'purchasable_type' => Program::class,
            'purchasable_id' => $program->getKey(),
        ]);

        app(EnrollmentService::class)->createEnrollmentItemsPublic($enrollment, $program, $schedule->getKey());

        $item = EnrollmentItem::where('enrollment_id', $enrollment->getKey())->first();

        $this->assertNotNull($item);
        $this->assertSame($program->getKey(), $item->program_id);
        $this->assertSame($schedule->getKey(), $item->schedule_id);
        $this->assertSame($schedule->label, $item->schedule_label_snapshot);
    }

    public function test_package_with_no_active_programs_creates_no_items(): void
    {
        $package = Package::factory()
            ->has(Program::factory()->count(3)->state(['is_active' => false]))
            ->create(['is_active' => true]);

        $enrollment = Enrollment::factory()->create([
            'purchasable_type' => Package::class,
            'purchasable_id' => $package->getKey(),
        ]);

        app(EnrollmentService::class)->createEnrollmentItemsPublic($enrollment, $package, null);

        $this->assertSame(0, EnrollmentItem::where('enrollment_id', $enrollment->getKey())->count());
    }
}
