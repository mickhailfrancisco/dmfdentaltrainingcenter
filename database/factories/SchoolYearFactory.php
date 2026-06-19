<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolYear>
 */
class SchoolYearFactory extends Factory
{
    public function definition(): array
    {
        $year = $this->faker->unique()->numberBetween(2024, 2035);

        return [
            'label' => "SY {$year}–".($year + 1),
            'start_date' => "{$year}-06-01",
            'end_date' => ($year + 1).'-05-31',
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }
}
