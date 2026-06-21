<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\EnrollmentResource;
use App\Filament\Resources\EnrollmentResource\Pages\ViewEnrollment;
use App\Filament\Resources\EnrollmentResource\RelationManagers\PaymentsRelationManager;
use App\Models\BankTransferSubmission;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use App\Models\User;
use App\Services\BankTransferService;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BankTransferVerifyAtomicityTest extends TestCase
{
    private function makeEnrollmentWithPendingBankTransfer(): array
    {
        $program = Program::create([
            'name' => 'Verify Test Program',
            'slug' => 'verify-test-program',
            'category' => 'Individual Programs (Theoretical)',
            'price_full' => 43_000,
            'price_early' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $enrollment = Enrollment::create([
            'reference_number' => 'DMF-VRF-'.Str::upper(Str::random(4)),
            'payment_type' => 'downpayment',
            'purchasable_type' => 'program',
            'purchasable_id' => $program->getKey(),
            'purchasable_name_snapshot' => $program->name,
            'program_id' => $program->getKey(),
            'first_name' => 'Test',
            'middle_name' => null,
            'surname' => 'Student',
            'birthday' => '2000-01-01',
            'sex' => 'Female',
            'phone' => '09171234567',
            'email' => 'verify@example.com',
            'facebook_messenger_name' => 'Test Student',
            'facebook_messenger_url' => null,
            'addr_street' => '1 Main',
            'addr_city' => 'Manila',
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1000',
            'school' => 'U',
            'year_level' => 'Graduate',
            'year_graduated' => '2024',
            'taker_status' => 'First taker',
            'status' => 'pending',
            'base_amount' => 21_500,
            'total_amount' => 21_550,
            'convenience_fee' => 50,
            'tuition_list_amount' => 43_000,
            'tuition_price_early' => null,
            'tuition_early_deadline' => null,
            'tuition_price_dp' => 21_500,
            'tuition_discount_amount' => 0,
            'amount_paid_tuition' => 0,
            'balance_tuition_due' => 43_000,
        ]);

        $payment = Payment::create([
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => 'initial',
            'payment_method' => 'bank_transfer',
            'amount' => 21_550 * 100,
            'currency' => 'PHP',
            'status' => 'submitted',
            'tuition_amount' => 21_500,
        ]);

        $submission = BankTransferSubmission::create([
            'payment_id' => $payment->getKey(),
            'reference_number' => 'BDO-12345',
            'proof_path' => 'bank-transfers/test/photo1.jpg',
            'submitted_at' => now(),
            'manual_method' => 'bank_transfer',
            'channel_code' => 'bdo',
        ]);

        return [$enrollment, $payment, $submission];
    }

    public function test_payments_relation_manager_has_no_tuition_description(): void
    {
        [$enrollment] = $this->makeEnrollmentWithPendingBankTransfer();

        $admin = User::factory()->admin()->create();

        $html = Livewire::actingAs($admin)
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $enrollment,
                'pageClass' => ViewEnrollment::class,
            ])
            ->assertSuccessful()
            ->html();

        $this->assertStringNotContainsString('Tuition paid', $html);
        $this->assertStringNotContainsString('Remaining', $html);
    }

    public function test_verify_payment_updates_enrollment_ledger_atomically(): void
    {
        [$enrollment, $payment] = $this->makeEnrollmentWithPendingBankTransfer();

        $admin = User::factory()->admin()->create();

        $this->assertSame(0, (int) $enrollment->fresh()->amount_paid_tuition);

        app(BankTransferService::class)->verifyPayment($payment, $admin);

        $fresh = $enrollment->fresh();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(21_500, (int) $fresh->amount_paid_tuition);
        $this->assertSame(21_500, (int) $fresh->balance_tuition_due); // 43000 - 21500 = 21500 remaining
        $this->assertSame('partially_paid', (string) $fresh->status->value);
    }

    public function test_verify_bank_transfer_action_redirects_to_enrollment_view_after_success(): void
    {
        [$enrollment, $payment] = $this->makeEnrollmentWithPendingBankTransfer();

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $enrollment,
                'pageClass' => ViewEnrollment::class,
            ])
            ->callTableAction('verifyBankTransfer', $payment)
            ->assertRedirect(EnrollmentResource::getUrl('view', ['record' => $enrollment]));
    }
}
