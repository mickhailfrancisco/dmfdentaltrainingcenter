<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Program;
use App\Services\EnrollmentPricingService;
use App\Services\EnrollmentService;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PaymentMethodFeeDisplayTest extends TestCase
{
    private function createProgram(array $overrides = []): Program
    {
        return Program::create(array_merge([
            'name' => 'Fee Display Program',
            'slug' => 'fee-display-program-'.bin2hex(random_bytes(4)),
            'category' => 'Individual Programs (Theoretical)',
            'tag' => null,
            'price_full' => 10_000,
            'price_early' => null,
            'early_deadline' => null,
            'early_bird_label' => null,
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function baseEnrollmentPayload(string $programSlug): array
    {
        return [
            'program' => $programSlug,
            'schedule_id' => null,
            'first_name' => 'Ana',
            'middle_name' => null,
            'surname' => 'Santos',
            'birthday' => '2000-01-01',
            'sex' => 'Female',
            'phone' => '09171234567',
            'email' => 'ana@example.com',
            'facebook_messenger_name' => 'Ana Santos',
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
        ];
    }

    public function test_payment_page_loads_alpine_for_method_dependent_fees(): void
    {
        $program = $this->createProgram();
        $expectedCardFee = EnrollmentPricingService::convenienceFeeForPaymentMethod('card', 10_000);

        $this->post(route('enroll.store'), $this->baseEnrollmentPayload($program->slug))
            ->assertRedirect(route('enroll.payment'));

        $response = $this->get(route('enroll.payment'));

        $response->assertOk();
        $response->assertSee('alpinejs@3.14.9/dist/cdn.min.js', false);
        $response->assertSee('x-model="method"', false);
        $response->assertSee('bankTransferFee: 0', false);
        $response->assertSee('cardFee: '.$expectedCardFee, false);
        $response->assertSee('x-show="method === \'card\'"', false);
    }

    public function test_resume_checkout_page_includes_reactive_fee_summary(): void
    {
        $program = $this->createProgram();
        $enrollment = app(EnrollmentService::class)->createEnrollment($this->baseEnrollmentPayload($program->slug));
        $expectedCardFee = EnrollmentPricingService::convenienceFeeForPaymentMethod('card', (int) $enrollment->base_amount);

        $signedCheckoutUrl = URL::temporarySignedRoute(
            'enroll.checkout',
            now()->addMinutes(120),
            ['reference_number' => $enrollment->reference_number],
        );

        $response = $this->get($signedCheckoutUrl);

        $response->assertOk();
        $response->assertSee('alpinejs@3.14.9/dist/cdn.min.js', false);
        $response->assertSee('bankTransferFee: 0', false);
        $response->assertSee('cardFee: '.$expectedCardFee, false);
        $response->assertSee('x-show="method === \'card\'"', false);
        $response->assertSee('Tuition', false);
        $response->assertSee('₱'.number_format((int) $enrollment->base_amount), false);
    }
}
