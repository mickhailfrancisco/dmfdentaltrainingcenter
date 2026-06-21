<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug(),
            'tag' => null,
            'category_id' => null,
            'category' => 'Review Packages',
            'price_full' => 10000,
            'price_early' => null,
            'early_deadline' => null,
            'price_early_2' => null,
            'early_deadline_2' => null,
            'early_bird_label' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
