<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'purpose' => Payment::PURPOSE_INITIAL,
            'paymongo_checkout_session_id' => null,
            'paymongo_payment_intent_id' => null,
            'paymongo_payment_id' => null,
            'payment_method' => $this->faker->randomElement(['card', 'gcash', 'bank_transfer']),
            'amount' => $this->faker->numberBetween(100_00, 500_00) * 100, // centavos
            'currency' => 'PHP',
            'tuition_amount' => 0,
            'status' => 'pending',
            'paid_at' => null,
            'paymongo_payload' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
            'paymongo_payment_id' => 'pay_'.$this->faker->regexify('[a-zA-Z0-9]{24}'),
        ]);
    }
}
