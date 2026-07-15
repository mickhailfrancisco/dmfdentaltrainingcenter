<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\EnrollmentResource\Pages\ListEnrollments;
use App\Filament\Resources\EnrollmentResource\Pages\ViewEnrollment;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\Filament\CatalogOptionsCache;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class EnrollmentAdminPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    private function makeEnrollment(string $suffix): Enrollment
    {
        return Enrollment::create([
            'reference_number' => 'DMF-P'.substr($suffix, -7),
            'status' => EnrollmentStatus::CONFIRMED->value,
            'first_name' => 'Perf',
            'surname' => 'Test',
            'email' => "perf-{$suffix}@test.com",
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
            'base_amount' => 38500,
            'convenience_fee' => 500,
            'total_amount' => 39000,
            'purchasable_name_snapshot' => 'Package A',
            'purchasable_slug_snapshot' => 'package-a',
            'purchasable_type' => 'App\\Models\\Package',
            'purchasable_id' => 1,
            'tuition_list_amount' => 38500,
        ]);
    }

    public function test_view_enrollment_does_not_recalculate_ledger_on_mount(): void
    {
        $admin = $this->makeAdmin();
        $enrollment = $this->makeEnrollment(uniqid('', true));
        $updatedAt = $enrollment->updated_at;

        $this->actingAs($admin);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertSuccessful();

        $enrollment->refresh();

        $this->assertTrue($enrollment->updated_at->equalTo($updatedAt));
    }

    public function test_enrollment_list_uses_bounded_query_count(): void
    {
        $admin = $this->makeAdmin();
        $this->makeEnrollment(uniqid('a', true));
        $this->makeEnrollment(uniqid('b', true));
        $this->makeEnrollment(uniqid('c', true));

        $this->actingAs($admin);

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(ListEnrollments::class)
            ->loadTable()
            ->assertSuccessful();

        $queryCount = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(12, $queryCount, "Expected at most 12 queries, got {$queryCount}");
    }

    public function test_catalog_options_cache_serves_program_options(): void
    {
        CatalogOptionsCache::forgetAll();

        $first = CatalogOptionsCache::programOptions();
        $second = CatalogOptionsCache::programOptions();

        $this->assertSame($first, $second);
    }

    public function test_recalculate_ledger_action_does_not_lazy_load_enrollment_items(): void
    {
        $admin = $this->makeAdmin();
        $enrollment = $this->makeEnrollment(uniqid('', true));

        $this->actingAs($admin);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->callAction('refreshPaymentTotals')
            ->assertNotified()
            ->assertSuccessful();
    }
}
