<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\EnrollmentResource;
use App\Filament\Resources\EnrollmentResource\Pages\ListEnrollments;
use App\Filament\Resources\EnrollmentResource\Pages\ViewEnrollment;
use App\Models\BankTransferSubmission;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Program;
use App\Models\User;
use App\Services\EnrollmentDeletionService;
use App\Support\PermissionCodes;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class EnrollmentDeleteTest extends TestCase
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

    private function makeProgram(): Program
    {
        return Program::factory()->create([
            'name' => 'Delete Test Program',
            'slug' => 'delete-test-program-'.uniqid('', true),
            'category' => 'Individual Programs (Theoretical)',
            'price_full' => 43_000,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeEnrollment(Program $program, array $overrides = []): Enrollment
    {
        return Enrollment::create(array_merge([
            'reference_number' => 'DMF-DEL-'.strtoupper(substr(uniqid('', true), -6)),
            'status' => EnrollmentStatus::PENDING->value,
            'payment_type' => 'full',
            'purchasable_type' => 'program',
            'purchasable_id' => $program->getKey(),
            'purchasable_name_snapshot' => $program->name,
            'program_id' => $program->getKey(),
            'first_name' => 'Abandoned',
            'middle_name' => null,
            'surname' => 'Student',
            'birthday' => '2000-01-01',
            'sex' => 'Female',
            'phone' => '09171234567',
            'email' => 'delete-'.uniqid('', true).'@example.com',
            'facebook_messenger_name' => 'Abandoned Student',
            'addr_street' => '1 Main',
            'addr_city' => 'Manila',
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1000',
            'school' => 'U',
            'year_level' => 'Graduate',
            'year_graduated' => '2024',
            'taker_status' => 'First taker',
            'base_amount' => 43_000,
            'total_amount' => 43_050,
            'convenience_fee' => 50,
            'tuition_list_amount' => 43_000,
            'amount_paid_tuition' => 0,
            'balance_tuition_due' => 43_000,
        ], $overrides));
    }

    public function test_admin_can_delete_abandoned_enrollment_from_list(): void
    {
        $program = $this->makeProgram();
        $enrollment = $this->makeEnrollment($program);
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ListEnrollments::class)
            ->callTableAction('delete', $enrollment);

        $this->assertDatabaseMissing('enrollments', ['id' => $enrollment->getKey()]);
    }

    public function test_admin_can_delete_abandoned_enrollment_from_view_page(): void
    {
        $program = $this->makeProgram();
        $enrollment = $this->makeEnrollment($program);
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertActionVisible('deleteEnrollment')
            ->callAction('deleteEnrollment');

        $this->assertDatabaseMissing('enrollments', ['id' => $enrollment->getKey()]);
    }

    public function test_assistant_without_delete_permission_cannot_delete(): void
    {
        $program = $this->makeProgram();
        $enrollment = $this->makeEnrollment($program);

        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode(PermissionCodes::legacyAssistantPreset());

        $this->assertFalse(EnrollmentResource::canDelete($enrollment));

        Livewire::actingAs($assistant)
            ->test(ListEnrollments::class)
            ->assertTableActionHidden('delete', $enrollment);
    }

    public function test_assistant_with_delete_permission_can_delete_abandoned_enrollment(): void
    {
        $program = $this->makeProgram();
        $enrollment = $this->makeEnrollment($program);

        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode(array_merge(
            PermissionCodes::legacyAssistantPreset(),
            [PermissionCodes::ENROLLMENT_ACTION_DELETE],
        ));

        $this->actingAs($assistant);
        $this->assertTrue(EnrollmentResource::canDelete($enrollment));

        Livewire::actingAs($assistant)
            ->test(ListEnrollments::class)
            ->callTableAction('delete', $enrollment);

        $this->assertDatabaseMissing('enrollments', ['id' => $enrollment->getKey()]);
    }

    public function test_cannot_delete_enrollment_with_tuition_paid(): void
    {
        $program = $this->makeProgram();
        $enrollment = $this->makeEnrollment($program, [
            'status' => EnrollmentStatus::PARTIALLY_PAID->value,
            'amount_paid_tuition' => 21_500,
            'balance_tuition_due' => 21_500,
        ]);

        $admin = User::factory()->admin()->create();

        $this->assertFalse(EnrollmentResource::canDelete($enrollment));

        Livewire::actingAs($admin)
            ->test(ListEnrollments::class)
            ->assertTableActionHidden('delete', $enrollment);
    }

    public function test_cannot_delete_enrollment_with_submitted_bank_transfer(): void
    {
        $program = $this->makeProgram();
        $enrollment = $this->makeEnrollment($program);

        $payment = Payment::create([
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'bank_transfer',
            'amount' => 43_050 * 100,
            'currency' => 'PHP',
            'status' => 'submitted',
            'tuition_amount' => 43_000,
        ]);

        BankTransferSubmission::create([
            'payment_id' => $payment->getKey(),
            'reference_number' => 'BDO-99999',
            'proof_path' => 'bank-transfers/test/photo1.jpg',
            'submitted_at' => now(),
            'manual_method' => 'bank_transfer',
            'channel_code' => 'bdo',
        ]);

        $admin = User::factory()->admin()->create();

        $this->assertFalse(EnrollmentResource::canDelete($enrollment));

        Livewire::actingAs($admin)
            ->test(ListEnrollments::class)
            ->assertTableActionHidden('delete', $enrollment);
    }

    public function test_deletion_service_removes_enrollment_and_proof_files(): void
    {
        Storage::fake('local');

        $program = $this->makeProgram();
        $enrollment = $this->makeEnrollment($program);

        $proofPath = 'bank-transfers/'.$enrollment->reference_number.'/initial/proof.jpg';
        Storage::disk('local')->put($proofPath, 'proof');

        $payment = Payment::create([
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'bank_transfer',
            'amount' => 43_050 * 100,
            'currency' => 'PHP',
            'status' => 'pending',
            'tuition_amount' => 43_000,
        ]);

        BankTransferSubmission::create([
            'payment_id' => $payment->getKey(),
            'reference_number' => 'BDO-PENDING',
            'proof_path' => $proofPath,
            'submitted_at' => now(),
            'manual_method' => 'bank_transfer',
            'channel_code' => 'bdo',
        ]);

        $admin = User::factory()->admin()->create();

        app(EnrollmentDeletionService::class)->delete($enrollment, $admin);

        $this->assertDatabaseMissing('enrollments', ['id' => $enrollment->getKey()]);
        $this->assertDatabaseMissing('payments', ['id' => $payment->getKey()]);
        Storage::disk('local')->assertMissing($proofPath);
    }

    public function test_deletion_service_rejects_ineligible_enrollment(): void
    {
        $program = $this->makeProgram();
        $enrollment = $this->makeEnrollment($program, [
            'amount_paid_tuition' => 43_000,
            'status' => EnrollmentStatus::CONFIRMED->value,
        ]);

        $admin = User::factory()->admin()->create();

        $this->expectException(RuntimeException::class);

        app(EnrollmentDeletionService::class)->delete($enrollment, $admin);
    }
}
