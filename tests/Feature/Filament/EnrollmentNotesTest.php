<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\EnrollmentResource\Pages\ViewEnrollment;
use App\Models\Enrollment;
use App\Models\Permission;
use App\Models\User;
use App\Support\PermissionCodes;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

class EnrollmentNotesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (PermissionCodes::definitions() as $code => $label) {
            Permission::query()->firstOrCreate(
                ['code' => $code],
                ['label' => $label],
            );
        }
    }

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    private function makeEnrollment(array $overrides = []): Enrollment
    {
        return Enrollment::create(array_merge([
            'reference_number' => 'DMF-N'.substr(uniqid('', true), -7),
            'status' => EnrollmentStatus::CONFIRMED->value,
            'first_name' => 'Notes',
            'surname' => 'Student',
            'email' => 'notes-'.uniqid('', true).'@test.com',
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

    public function test_admin_can_save_and_view_enrollment_notes(): void
    {
        $admin = $this->makeAdmin();
        $enrollment = $this->makeEnrollment();

        $this->actingAs($admin);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertActionVisible('editNotes')
            ->assertSee('Staff notes')
            ->callAction('editNotes', data: [
                'notes' => 'Follow up on payment Monday.',
            ])
            ->assertNotified();

        $enrollment->refresh();

        $this->assertSame('Follow up on payment Monday.', $enrollment->notes);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertSee('Follow up on payment Monday.');
    }

    public function test_admin_clearing_notes_saves_null(): void
    {
        $admin = $this->makeAdmin();
        $enrollment = $this->makeEnrollment(['notes' => 'Existing note.']);

        $this->actingAs($admin);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->callAction('editNotes', data: [
                'notes' => '',
            ])
            ->assertNotified();

        $enrollment->refresh();

        $this->assertNull($enrollment->notes);
    }

    public function test_assistant_with_notes_permissions_can_save_notes(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::ENROLLMENT_DETAIL_NOTES,
            PermissionCodes::ENROLLMENT_ACTION_EDIT_NOTES,
        ]);
        $enrollment = $this->makeEnrollment();

        $this->actingAs($assistant);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertActionVisible('editNotes')
            ->assertSee('Staff notes')
            ->callAction('editNotes', data: [
                'notes' => 'Assistant note for student.',
            ])
            ->assertNotified();

        $this->assertSame('Assistant note for student.', $enrollment->refresh()->notes);
    }

    public function test_assistant_without_notes_permissions_cannot_see_notes_ui(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::ENROLLMENT_DETAIL_APPLICANT_PROFILE,
        ]);
        $enrollment = $this->makeEnrollment(['notes' => 'Hidden internal note.']);

        $this->actingAs($assistant);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertSuccessful()
            ->assertActionDoesNotExist('editNotes')
            ->assertDontSee('Staff notes')
            ->assertDontSee('Hidden internal note.');
    }
}
