<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_number' => Enrollment::generateReference(),
            'status' => 'pending',

            'first_name' => $this->faker->firstName(),
            'middle_name' => null,
            'surname' => $this->faker->lastName(),
            'birthday' => $this->faker->date('Y-m-d', '-20 years'),
            'sex' => $this->faker->randomElement(['Male', 'Female']),

            'phone' => '09171234567',
            'email' => $this->faker->unique()->safeEmail(),
            'facebook_messenger_name' => null,
            'facebook_messenger_url' => null,

            'addr_street' => $this->faker->streetAddress(),
            'addr_city' => $this->faker->city(),
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1000',

            'deliv_street' => null,
            'deliv_city' => null,
            'deliv_province' => null,
            'deliv_zip' => null,

            'school' => $this->faker->company().' University',
            'year_level' => 'Graduate',
            'year_graduated' => '2024',
            'taker_status' => 'First taker',

            'purchasable_type' => Program::class,
            'purchasable_id' => Program::factory(),
            'purchasable_name_snapshot' => $this->faker->words(3, true),
            'purchasable_slug_snapshot' => $this->faker->unique()->slug(),

            'payment_type' => 'full',
            'base_amount' => 10000,
            'convenience_fee' => 50,
            'total_amount' => 10050,

            'tuition_list_amount' => 10000,
            'tuition_price_early' => null,
            'tuition_early_deadline' => null,
            'tuition_price_early_2' => null,
            'tuition_early_deadline_2' => null,
            'tuition_price_dp' => null,
            'tuition_discount_amount' => 0,
            'tuition_discount_label' => null,
            'amount_paid_tuition' => 0,
            'balance_tuition_due' => 0,
        ];
    }
}
