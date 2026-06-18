<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\ScheduleResource\Pages\ListSchedules;
use App\Models\Enrollment;
use App\Models\EnrollmentItem;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Actions\DeleteAction;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_delete_action_hidden_when_schedule_has_enrollment_items(): void
    {
        $admin = User::factory()->admin()->create();

        $program = Program::create([
            'name' => 'Schedule Guard Program',
            'slug' => 'schedule-guard-program',
            'category' => 'Individual Programs (Theoretical)',
            'price_full' => 10_000,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $scheduleWithEnrollments = Schedule::create([
            'program_id' => $program->getKey(),
            'label' => 'Batch A',
            'mode' => 'Online',
            'is_active' => true,
        ]);

        $emptySchedule = Schedule::create([
            'program_id' => $program->getKey(),
            'label' => 'Batch B',
            'mode' => 'Online',
            'is_active' => true,
        ]);

        $enrollment = Enrollment::create([
            'reference_number' => 'DMF-SCHED-GUARD',
            'status' => EnrollmentStatus::PENDING->value,
            'first_name' => 'Sched',
            'surname' => 'Test',
            'email' => 'sched@test.com',
            'phone' => '09000000000',
            'birthday' => '2000-01-01',
            'sex' => 'Male',
            'addr_street' => '123 Test St',
            'addr_city' => 'Quezon City',
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1100',
            'school' => 'Test University',
            'year_level' => 'Graduate',
            'taker_status' => 'First Taker',
            'payment_type' => 'full',
            'base_amount' => 10_000,
            'convenience_fee' => 500,
            'total_amount' => 10_500,
            'purchasable_name_snapshot' => $program->name,
            'purchasable_slug_snapshot' => $program->slug,
            'purchasable_type' => Program::class,
            'purchasable_id' => $program->getKey(),
            'tuition_list_amount' => 10_000,
        ]);

        EnrollmentItem::create([
            'enrollment_id' => $enrollment->getKey(),
            'program_id' => $program->getKey(),
            'schedule_id' => $scheduleWithEnrollments->getKey(),
            'status' => 'pending',
            'program_name_snapshot' => $program->name,
            'program_slug_snapshot' => $program->slug,
            'schedule_label_snapshot' => $scheduleWithEnrollments->label,
            'schedule_mode_snapshot' => $scheduleWithEnrollments->mode,
        ]);

        $this->actingAs($admin);

        Livewire::test(ListSchedules::class)
            ->loadTable()
            ->assertTableActionHidden(DeleteAction::class, $scheduleWithEnrollments->loadCount('enrollmentItems'))
            ->assertTableActionVisible(DeleteAction::class, $emptySchedule->loadCount('enrollmentItems'));
    }
}
