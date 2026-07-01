<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Program;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Tests\TestCase;

class SuccessPageDownpaymentInitialBankTransferTest extends TestCase
{
    public function test_success_page_renders_for_downpayment_with_submitted_initial_bank_transfer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01', 'Asia/Manila')->startOfDay());

        $program = Program::factory()->create([
            'price_full' => 10_000,
        ]);

        $enrollment = app(EnrollmentService::class)->createEnrollment([
            'program' => $program->slug,
            'schedule_id' => null,
            'first_name' => 'Test',
            'middle_name' => null,
            'surname' => 'Student',
            'birthday' => '2000-01-01',
            'sex' => 'Female',
            'phone' => '09171234567',
            'email' => 'test@example.com',
            'facebook_messenger_name' => 'Test Student',
            'facebook_messenger_url' => null,
            'addr_street' => '1 Main St',
            'addr_city' => 'Manila',
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1000',
            'deliv_street' => null,
            'deliv_city' => null,
            'deliv_province' => null,
            'deliv_zip' => null,
            'school' => 'University',
            'year_level' => 'Graduate',
            'year_graduated' => '2024',
            'taker_status' => 'First taker',
            'payment_type' => 'downpayment',
        ]);

        // Initial downpayment submitted via bank transfer with tuition_amount correctly set.
        Payment::query()->create([
            'enrollment_id' => $enrollment->id,
            'purpose' => Payment::PURPOSE_INITIAL,
            'payment_method' => 'bank_transfer',
            'amount' => 5_000 * 100,
            'currency' => 'PHP',
            'tuition_amount' => 5_000,
            'status' => 'submitted',
        ]);

        $response = $this->withSession([
            'latest_enrollment_ref' => $enrollment->reference_number,
        ])->get(route('enroll.success', ['ref' => $enrollment->reference_number]));

        $response->assertOk();
        $response->assertSee('₱5,000');

        Carbon::setTestNow();
    }
}
