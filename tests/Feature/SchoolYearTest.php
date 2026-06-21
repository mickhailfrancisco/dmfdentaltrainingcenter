<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\SchoolYearResource\Pages\CreateSchoolYear;
use App\Filament\Resources\SchoolYearResource\Pages\ListSchoolYears;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolYearTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_list_school_years(): void
    {
        $admin = User::factory()->admin()->create();
        $schoolYear = SchoolYear::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListSchoolYears::class)
            ->assertCanSeeTableRecords([$schoolYear]);
    }

    public function test_admin_can_create_school_year(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateSchoolYear::class)
            ->fillForm([
                'label' => 'SY 2026–2027',
                'start_date' => '2026-06-01',
                'end_date' => '2027-05-31',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(SchoolYear::class, [
            'label' => 'SY 2026–2027',
            'is_active' => true,
        ]);
    }

    public function test_school_year_can_be_bound_to_schedule(): void
    {
        $schoolYear = SchoolYear::factory()->active()->create();
        $schedule = Schedule::factory()->create(['school_year_id' => $schoolYear->id]);

        $this->assertEquals($schoolYear->id, $schedule->fresh()->school_year_id);
        $this->assertTrue($schoolYear->schedules()->where('id', $schedule->id)->exists());
    }

    public function test_deleting_school_year_nullifies_schedule_foreign_key(): void
    {
        $schoolYear = SchoolYear::factory()->create();
        $schedule = Schedule::factory()->create(['school_year_id' => $schoolYear->id]);

        $schoolYear->delete();

        $this->assertNull($schedule->fresh()->school_year_id);
    }
}
