<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeedbackImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackImage>
 */
class FeedbackImageFactory extends Factory
{
    protected $model = FeedbackImage::class;

    public function definition(): array
    {
        return [
            'image_path' => 'landing/feedback/'.$this->faker->uuid().'.jpg',
            'is_featured' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_featured' => true,
        ]);
    }
}
