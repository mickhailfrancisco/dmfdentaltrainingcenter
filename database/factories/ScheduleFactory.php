<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Program;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'label' => $this->faker->randomElement(['Morning', 'Afternoon', 'Evening']),
            'mode' => $this->faker->randomElement(['Online', 'Face-to-face']),
            'start_date' => now()->addDays(30)->toDateString(),
            'end_date' => now()->addDays(60)->toDateString(),
            'slots' => 30,
            'is_active' => true,
        ];
    }
}
