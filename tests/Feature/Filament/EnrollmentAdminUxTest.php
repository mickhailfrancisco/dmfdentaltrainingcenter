<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\EnrollmentStatus;
use App\Filament\Pages\EnrollmentOverview;
use App\Filament\Resources\EnrollmentResource;
use App\Filament\Resources\EnrollmentResource\Pages\ListEnrollments;
use App\Filament\Resources\EnrollmentResource\Pages\ViewEnrollment;
use App\Filament\Resources\EnrollmentResource\RelationManagers\PaymentsRelationManager;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class EnrollmentAdminUxTest extends TestCase
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

    private function makeEnrollment(array $overrides = []): Enrollment
    {
        return Enrollment::create(array_merge([
            'reference_number' => 'DMF-U'.substr(uniqid('', true), -7),
            'status' => EnrollmentStatus::CONFIRMED->value,
            'first_name' => 'UX',
            'surname' => 'Student',
            'email' => 'ux-'.uniqid('', true).'@test.com',
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
            'amount_paid_tuition' => 38500,
            'balance_tuition_due' => 0,
        ], $overrides));
    }

    public function test_list_page_shows_program_enrolled_column(): void
    {
        $admin = $this->makeAdmin();
        $this->makeEnrollment(['purchasable_name_snapshot' => 'Board Review Bundle']);

        $this->actingAs($admin);

        Livewire::test(ListEnrollments::class)
            ->loadTable()
            ->assertSuccessful()
            ->assertSee('Program Enrolled')
            ->assertSee('Board Review Bundle');
    }

    public function test_enrolled_date_filter_limits_results(): void
    {
        $admin = $this->makeAdmin();

        Carbon::setTestNow('2026-05-10 12:00:00');
        $inRange = $this->makeEnrollment(['surname' => 'InMay']);

        Carbon::setTestNow('2026-04-01 12:00:00');
        $this->makeEnrollment(['surname' => 'InApril']);

        Carbon::setTestNow();

        $this->actingAs($admin);

        Livewire::test(ListEnrollments::class)
            ->filterTable('enrolled_between', [
                'from' => '2026-05-01',
                'until' => '2026-05-31',
            ])
            ->loadTable()
            ->assertCanSeeTableRecords([$inRange])
            ->assertSee('InMay')
            ->assertDontSee('InApril');
    }

    public function test_awaiting_payment_tab_excludes_confirmed_enrollments(): void
    {
        $admin = $this->makeAdmin();

        $pending = $this->makeEnrollment([
            'status' => EnrollmentStatus::PENDING->value,
            'amount_paid_tuition' => 0,
            'surname' => 'AwaitingPay',
        ]);

        $this->makeEnrollment([
            'status' => EnrollmentStatus::CONFIRMED->value,
            'surname' => 'AlreadyPaid',
        ]);

        $this->actingAs($admin);

        Livewire::test(ListEnrollments::class)
            ->set('activeTab', 'awaiting_payment')
            ->loadTable()
            ->assertCanSeeTableRecords([$pending])
            ->assertSee('AwaitingPay')
            ->assertDontSee('AlreadyPaid');
    }

    public function test_view_page_shows_breadcrumbs_and_summary_strip(): void
    {
        $admin = $this->makeAdmin();
        $enrollment = $this->makeEnrollment([
            'first_name' => 'Maria',
            'surname' => 'Santos',
            'reference_number' => 'DMF-BREADCRUMB',
        ]);

        $this->actingAs($admin);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertSuccessful()
            ->assertSee('Enrollments')
            ->assertSee('Maria Santos')
            ->assertSee('Payment & enrollment status')
            ->assertSee('DMF-BREADCRUMB');
    }

    public function test_export_action_is_available_on_list_page(): void
    {
        $admin = $this->makeAdmin();
        $this->makeEnrollment();

        $this->actingAs($admin);

        Livewire::test(ListEnrollments::class)
            ->assertActionExists('export')
            ->loadTable()
            ->assertSuccessful()
            ->assertSee('Export CSV');
    }

    public function test_operations_overview_page_loads_with_stat_links(): void
    {
        $admin = $this->makeAdmin();

        $this->makeEnrollment([
            'status' => EnrollmentStatus::PENDING->value,
            'amount_paid_tuition' => 0,
        ]);

        $this->actingAs($admin);

        Livewire::test(EnrollmentOverview::class)
            ->assertSuccessful()
            ->assertSee('Awaiting payment')
            ->assertSee('Pending verification')
            ->assertSee('Balance due')
            ->assertSee(EnrollmentResource::getUrl('index', ['activeTab' => 'awaiting_payment']));
    }

    public function test_pending_verification_tab_includes_submitted_bank_transfer(): void
    {
        $admin = $this->makeAdmin();

        $pendingVerification = $this->makeEnrollment([
            'status' => EnrollmentStatus::PENDING->value,
            'amount_paid_tuition' => 0,
            'surname' => 'VerifyMe',
        ]);

        Payment::create([
            'enrollment_id' => $pendingVerification->getKey(),
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'bank_transfer',
            'status' => 'submitted',
            'tuition_amount' => 38500,
            'amount' => 39000,
            'currency' => 'PHP',
        ]);

        $this->makeEnrollment([
            'status' => EnrollmentStatus::PENDING->value,
            'amount_paid_tuition' => 0,
            'surname' => 'NoTransfer',
        ]);

        $this->actingAs($admin);

        Livewire::test(ListEnrollments::class)
            ->set('activeTab', 'pending_verification')
            ->loadTable()
            ->assertCanSeeTableRecords([$pendingVerification])
            ->assertSee('VerifyMe')
            ->assertDontSee('NoTransfer');
    }

    public function test_topbar_shows_time_of_day_greeting_for_authenticated_admin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 09:30:00', config('app.display_timezone')));

        $admin = $this->makeAdmin();

        $this->actingAs($admin);

        $this->get(EnrollmentOverview::getUrl())
            ->assertOk()
            ->assertSee('dmf-topbar-greeting', false)
            ->assertSee('dmf-topbar-greeting__sky-scene', false)
            ->assertSee('dmf-topbar-greeting__celestial--sun', false)
            ->assertSee('Good Morning, Doc', false)
            ->assertSee('May 22, 2026 - 9:30:00 AM', false);

        Carbon::setTestNow();
    }

    public function test_topbar_greeting_uses_assistant_first_name(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 18:30:00', config('app.display_timezone')));

        $assistant = User::factory()->assistant()->create([
            'name' => 'Jane Assistant',
        ]);

        $this->actingAs($assistant);

        $this->get(EnrollmentOverview::getUrl())
            ->assertOk()
            ->assertSee('Good Evening, Jane', false)
            ->assertSee('dmf-topbar-greeting__celestial--sun', false);

        Carbon::setTestNow();
    }

    public function test_topbar_shows_good_evening_and_moon_at_early_morning(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-24 03:20:00', config('app.display_timezone')));

        $admin = $this->makeAdmin();

        $this->actingAs($admin);

        $this->get(EnrollmentOverview::getUrl())
            ->assertOk()
            ->assertSee('Good Evening, Doc', false)
            ->assertSee('dmf-topbar-greeting__celestial--moon', false);

        Carbon::setTestNow();
    }

    public function test_topbar_user_menu_is_hidden_and_logout_is_in_sidebar(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin);

        $this->get(EnrollmentOverview::getUrl())
            ->assertOk()
            ->assertSee('Sign out', false)
            ->assertSee('dmf-topbar-greeting', false)
            ->assertDontSee('fi-user-menu', false);
    }

    public function test_topbar_time_simulator_is_available_in_debug_mode(): void
    {
        config(['app.debug' => true]);

        Carbon::setTestNow(Carbon::parse('2026-05-22 09:30:00', config('app.display_timezone')));

        $admin = $this->makeAdmin();

        $this->actingAs($admin);

        $this->get(EnrollmentOverview::getUrl())
            ->assertOk()
            ->assertSee('dmf-topbar-greeting__sim-toggle', false)
            ->assertSee('Play day', false);

        $this->get(EnrollmentOverview::getUrl(['sky_sim' => '05:45']))
            ->assertOk()
            ->assertSee('Good Morning, Doc', false)
            ->assertSee('dmf-topbar-greeting__sky-scene', false);

        Carbon::setTestNow();
    }

    public function test_topbar_time_simulator_is_hidden_outside_debug_mode(): void
    {
        config(['app.debug' => false]);

        $admin = $this->makeAdmin();

        $this->actingAs($admin);

        $this->get(EnrollmentOverview::getUrl())
            ->assertOk()
            ->assertDontSee('dmf-topbar-greeting__sim-toggle', false)
            ->assertDontSee('Play day', false);
    }

    public function test_payments_tab_shows_all_columns_without_filters(): void
    {
        $admin = $this->makeAdmin();
        $enrollment = $this->makeEnrollment();

        Payment::create([
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'bank_transfer',
            'status' => 'paid',
            'tuition_amount' => 21500,
            'amount' => 22000,
            'currency' => 'PHP',
            'paid_at' => Carbon::parse('2026-05-24 02:23:00', config('app.display_timezone')),
        ]);

        $this->actingAs($admin);

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $enrollment,
            'pageClass' => ViewEnrollment::class,
        ])
            ->assertSuccessful()
            ->loadTable()
            ->assertTableColumnVisible('bankTransferSubmission.reference_number')
            ->assertTableColumnVisible('bankTransferSubmission.submitted_at')
            ->assertTableColumnVisible('bankTransferSubmission.verified_at')
            ->assertTableColumnVisible('paid_at')
            ->assertSee('May 24, 2026', false);
    }
}
