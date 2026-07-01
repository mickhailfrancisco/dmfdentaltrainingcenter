<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use App\Services\BankTransferService;
use Tests\TestCase;

class BankTransferZeroFeeTest extends TestCase
{
    public function test_bank_transfer_payment_has_zero_fee(): void
    {
        $program = Program::factory()->create([
            'price_full' => 10_000,
        ]);

        $enrollment = Enrollment::factory()->create([
            'purchasable_type' => Program::class,
            'purchasable_id' => $program->getKey(),
            'payment_type' => 'full',
            'base_amount' => 10_000,
            'convenience_fee' => 50,
            'total_amount' => 10_050,
            'tuition_list_amount' => 10_000,
            'reference_number' => 'DMF-BTZERO-TEST01',
        ]);

        $service = app(BankTransferService::class);
        $service->startInitialBankTransfer($enrollment);

        $payment = Payment::where('enrollment_id', $enrollment->getKey())->first();

        $this->assertSame(10_000 * 100, $payment->amount);
        $this->assertSame(10_000, $payment->tuition_amount);

        $enrollment->refresh();
        $this->assertSame(0, (int) $enrollment->convenience_fee);
        $this->assertSame(10_000, (int) $enrollment->total_amount);
    }
}
