<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Program;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CardOnlyPaymentMethodsTest extends TestCase
{
    private function createProgram(array $overrides = []): Program
    {
        return Program::create(array_merge([
            'name' => 'Card Only Program',
            'slug' => 'card-only-program',
            'category' => 'Individual Programs (Theoretical)',
            'tag' => null,
            'price_full' => 10_000,
            'price_early' => 8_000,
            'early_deadline' => '2026-07-15',
            'early_bird_label' => 'Early',
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function baseEnrollmentPayload(string $programSlug, array $overrides = []): array
    {
        return array_merge([
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
            'payment_type' => 'downpayment',
        ], $overrides);
    }

    public function test_balance_pay_rejects_legacy_paymongo_methods(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram(['slug' => 'card-only-balance']);
        $enrollment = app(EnrollmentService::class)->createEnrollment($this->baseEnrollmentPayload($program->slug, [
            'program' => $program->slug,
        ]));

        $signedPayUrl = URL::temporarySignedRoute(
            'enroll.balance.pay',
            now()->addMinutes(120),
            ['reference_number' => $enrollment->reference_number],
        );

        $this->post($signedPayUrl, ['payment_method' => 'gcash'])
            ->assertSessionHasErrors('payment_method');

        Carbon::setTestNow();
    }

    public function test_resume_checkout_pay_rejects_legacy_paymongo_methods(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram(['slug' => 'card-only-resume']);
        $enrollment = app(EnrollmentService::class)->createEnrollment($this->baseEnrollmentPayload($program->slug, [
            'program' => $program->slug,
        ]));

        $signedPayUrl = URL::temporarySignedRoute(
            'enroll.checkout.pay',
            now()->addMinutes(120),
            ['reference_number' => $enrollment->reference_number],
        );

        $this->post($signedPayUrl, ['payment_method' => 'gcash'])
            ->assertSessionHasErrors('payment_method');

        Carbon::setTestNow();
    }

    public function test_resume_checkout_pay_accepts_card_and_redirects_to_paymongo(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram(['slug' => 'card-only-resume-redirect']);
        $enrollment = app(EnrollmentService::class)->createEnrollment($this->baseEnrollmentPayload($program->slug, [
            'program' => $program->slug,
        ]));

        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_card_only',
                    'attributes' => [
                        'checkout_url' => 'https://paymongo.test/checkout/cs_test_card_only',
                    ],
                ],
            ], 200),
        ]);

        $signedPayUrl = URL::temporarySignedRoute(
            'enroll.checkout.pay',
            now()->addMinutes(120),
            ['reference_number' => $enrollment->reference_number],
        );

        $this->post($signedPayUrl, ['payment_method' => 'card'])
            ->assertRedirect('https://paymongo.test/checkout/cs_test_card_only');

        Carbon::setTestNow();
    }

    public function test_checkout_session_includes_billing_address_from_enrollment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram(['slug' => 'billing-addr-prefill']);
        $enrollment = app(EnrollmentService::class)->createEnrollment($this->baseEnrollmentPayload($program->slug, [
            'program' => $program->slug,
            'addr_street' => '45 Billing Street, Brgy. Test',
            'addr_city' => 'Davao City',
            'addr_province' => 'Davao del Sur',
            'addr_zip' => '8000',
        ]));

        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_billing_test',
                    'attributes' => [
                        'checkout_url' => 'https://paymongo.test/checkout/cs_billing_test',
                    ],
                ],
            ], 200),
        ]);

        $signedPayUrl = URL::temporarySignedRoute(
            'enroll.checkout.pay',
            now()->addMinutes(120),
            ['reference_number' => $enrollment->reference_number],
        );

        $this->post($signedPayUrl, ['payment_method' => 'card'])
            ->assertRedirect('https://paymongo.test/checkout/cs_billing_test');

        Http::assertSent(function (Request $request) use ($enrollment): bool {
            if ($request->url() !== 'https://api.paymongo.com/v1/checkout_sessions') {
                return false;
            }

            $payload = $request->data();
            $billing = $payload['data']['attributes']['billing'] ?? null;
            if (! is_array($billing)) {
                return false;
            }

            $address = $billing['address'] ?? null;
            if (! is_array($address)) {
                return false;
            }

            return $billing['name'] === $enrollment->full_name
                && $billing['email'] === $enrollment->email
                && $billing['phone'] === $enrollment->phone
                && $address['line1'] === '45 Billing Street, Brgy. Test'
                && $address['city'] === 'Davao City'
                && $address['state'] === 'Davao del Sur'
                && $address['postal_code'] === '8000'
                && $address['country'] === 'PH';
        });

        Carbon::setTestNow();
    }

    public function test_signed_pay_link_with_legacy_method_segment_still_opens_card_checkout(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = $this->createProgram(['slug' => 'pay-link-legacy-segment']);
        $enrollment = app(EnrollmentService::class)->createEnrollment($this->baseEnrollmentPayload($program->slug, [
            'program' => $program->slug,
        ]));

        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_pay_link_legacy',
                    'attributes' => [
                        'checkout_url' => 'https://paymongo.test/checkout/cs_pay_link_legacy',
                    ],
                ],
            ], 200),
        ]);

        $signedPayLink = URL::temporarySignedRoute(
            'enroll.pay-link',
            now()->addMinutes(120),
            [
                'reference_number' => $enrollment->reference_number,
                'purpose' => 'initial',
                'payment_method' => 'gcash',
            ],
        );

        $this->get($signedPayLink)
            ->assertRedirect('https://paymongo.test/checkout/cs_pay_link_legacy');

        Carbon::setTestNow();
    }
}
