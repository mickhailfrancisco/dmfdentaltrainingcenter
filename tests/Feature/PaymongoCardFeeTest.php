<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use App\Services\EnrollmentPricingService;
use App\Services\PaymongoService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymongoCardFeeTest extends TestCase
{
    public function test_card_checkout_applies_dynamic_fee(): void
    {
        $program = Program::factory()->create([
            'price_full' => 10_000,
            'price_early' => null,
        ]);

        $enrollment = Enrollment::factory()->create([
            'purchasable_type' => Program::class,
            'purchasable_id' => $program->getKey(),
            'payment_type' => 'full',
            'base_amount' => 10_000,
            'convenience_fee' => 50,
            'total_amount' => 10_050,
            'tuition_list_amount' => 10_000,
        ]);

        $expectedFee = EnrollmentPricingService::convenienceFeeForPaymentMethod('card', 10_000);
        $expectedTotal = 10_000 + $expectedFee;

        Http::fake([
            'https://api.paymongo.com/*' => Http::response([
                'data' => [
                    'id' => 'cs_test_123',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.com/cs_test_123',
                    ],
                ],
            ], 200),
        ]);

        $service = app(PaymongoService::class);
        $service->createCheckoutSession($enrollment);

        $payment = Payment::where('enrollment_id', $enrollment->getKey())->first();

        $this->assertSame($expectedTotal * 100, $payment->amount);
        $this->assertSame(10_000, $payment->tuition_amount);

        $enrollment->refresh();
        $this->assertSame($expectedFee, (int) $enrollment->convenience_fee);
        $this->assertSame($expectedTotal, (int) $enrollment->total_amount);
    }
}
