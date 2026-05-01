<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransferSubmission;
use App\Models\BankTransferSubmissionFile;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankTransferProofStreamTest extends TestCase
{
    public function test_admin_can_stream_proof_inline(): void
    {
        Storage::fake('local');

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@dmfdental.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $program = Program::query()->create([
            'name' => 'Program Stream',
            'slug' => 'program-stream',
            'category' => 'Individual Programs (Theoretical)',
            'tag' => null,
            'price_full' => 9_000,
            'price_dp' => 4_500,
            'price_early' => null,
            'early_deadline' => null,
            'early_bird_label' => null,
            'inclusions' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $enrollment = Enrollment::query()->create([
            'reference_number' => 'DMF-STREAMTEST',
            'status' => 'pending',
            'program_id' => $program->id,
            'purchasable_type' => Program::class,
            'purchasable_id' => $program->id,
            'first_name' => 'Test',
            'middle_name' => null,
            'surname' => 'User',
            'birthday' => '2000-01-01',
            'sex' => 'Female',
            'phone' => '09170000000',
            'email' => 'stream@test.com',
            'facebook_messenger_name' => 'Test',
            'facebook_messenger_url' => null,
            'addr_street' => '1 Main',
            'addr_city' => 'Manila',
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1000',
            'deliv_street' => null,
            'deliv_city' => null,
            'deliv_province' => null,
            'deliv_zip' => null,
            'school' => 'U',
            'year_level' => 'Graduate',
            'year_graduated' => '2024',
            'taker_status' => 'First taker',
            'payment_type' => 'full',
            'base_amount' => 9000,
            'convenience_fee' => 50,
            'total_amount' => 9050,
        ]);

        $payment = Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'bank_transfer',
            'amount' => 9050 * 100,
            'currency' => 'PHP',
            'tuition_amount' => 9000,
            'status' => 'submitted',
        ]);

        $path = 'bank-transfers/DMF-STREAMTEST/initial/proof.pdf';
        Storage::disk('local')->put($path, 'dummy');

        $submission = BankTransferSubmission::query()->create([
            'payment_id' => $payment->id,
            'reference_number' => 'REF-STREAM',
            'proof_path' => $path,
            'submitted_at' => now(),
        ]);

        BankTransferSubmissionFile::query()->create([
            'bank_transfer_submission_id' => $submission->getKey(),
            'slot' => BankTransferSubmissionFile::SLOT_PHOTO_1,
            'path' => $path,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.bank-transfer-submissions.proof', [
            'submission' => $submission->getKey(),
            'slot' => BankTransferSubmissionFile::SLOT_PHOTO_1,
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'inline');
    }
}
