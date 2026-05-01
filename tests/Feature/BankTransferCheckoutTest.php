<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransferSubmission;
use App\Models\Payment;
use App\Models\Program;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankTransferCheckoutTest extends TestCase
{
    public function test_student_can_choose_bank_transfer_and_submit_proof(): void
    {
        Storage::fake('local');

        $program = Program::create([
            'name' => 'Program BT',
            'slug' => 'program-bt',
            'category' => 'Individual Programs (Theoretical)',
            'tag' => null,
            'price_full' => 30_000,
            'price_dp' => 15_000,
            'price_early' => null,
            'early_deadline' => null,
            'early_bird_label' => null,
            'inclusions' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $storeResponse = $this->post(route('enroll.store'), [
            'program' => $program->slug,
            'schedule_id' => null,
            'first_name' => 'Test',
            'middle_name' => null,
            'surname' => 'User',
            'birthday' => '2000-01-01',
            'sex' => 'Female',
            'phone' => '09171234567',
            'email' => 'test@example.com',
            'facebook_messenger_name' => 'Test User',
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
            'data_accuracy_ack' => '1',
        ]);

        $storeResponse->assertRedirect(route('enroll.payment'));

        $payResponse = $this->post(route('enroll.pay'), [
            'payment_method' => 'bank_transfer',
        ]);

        $payResponse->assertRedirect();

        $showUrl = (string) $payResponse->headers->get('Location');
        $this->assertStringContainsString('/enroll/bank-transfer/', $showUrl);

        $payment = Payment::query()
            ->where('payment_method', 'bank_transfer')
            ->where('purpose', Payment::PURPOSE_INITIAL)
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame('pending', $payment->status);

        $showResponse = $this->get($showUrl);
        $showResponse->assertOk();

        $submitUrl = (string) $showResponse->viewData('submit_url');
        $this->assertNotSame('', $submitUrl);

        $submitResponse = $this->post($submitUrl, [
            'transfer_reference' => 'REF-12345',
            'manual_method' => 'bank_transfer',
            'channel_code' => 'bdo',
            'photo_1' => UploadedFile::fake()->image('photo-1.jpg'),
            'photo_2' => UploadedFile::fake()->image('photo-2.png'),
        ]);

        $submitResponse->assertRedirect(route('enroll.success', ['ref' => $payment->enrollment->reference_number]));

        $payment->refresh();
        $this->assertSame('submitted', $payment->status);

        $submission = BankTransferSubmission::query()->where('payment_id', $payment->id)->first();
        $this->assertNotNull($submission);
        $this->assertSame('REF-12345', $submission->reference_number);
        $this->assertSame('bank_transfer', $submission->manual_method);
        $this->assertSame('bdo', $submission->channel_code);

        Storage::disk('local')->assertExists($submission->proof_path);
    }

    public function test_bank_transfer_proof_submission_requires_signed_url(): void
    {
        $unsignedUrl = route('enroll.bank-transfer.submit', [
            'reference_number' => 'DMF-TEST',
            'purpose' => Payment::PURPOSE_INITIAL,
        ]);

        $response = $this->post($unsignedUrl, [
            'transfer_reference' => 'REF-1',
            'photo_1' => UploadedFile::fake()->image('photo-1.jpg'),
        ]);

        $response->assertStatus(403);
    }
}
