<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FeedbackImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * One-time import of the bundled public/images/feedback/* screenshots into the
 * feedback_images table, so the landing page keeps showing them after the
 * feedback section became database + S3 driven.
 *
 * Not auto-run from DatabaseSeeder — run manually once per environment:
 * php artisan db:seed --class=FeedbackImageSeeder
 */
class FeedbackImageSeeder extends Seeder
{
    private const RELATIVE_DIRECTORY = 'images/feedback';

    /**
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function run(): void
    {
        $directory = public_path(self::RELATIVE_DIRECTORY);

        if (! is_dir($directory)) {
            return;
        }

        $files = collect(File::files($directory))
            ->filter(fn ($file): bool => in_array(strtolower($file->getExtension()), self::ALLOWED_EXTENSIONS, true))
            ->sortBy(fn ($file): string => strtolower($file->getFilename()))
            ->values();

        foreach ($files as $index => $file) {
            $imagePath = self::RELATIVE_DIRECTORY.'/'.$file->getFilename();

            if (FeedbackImage::query()->where('image_path', $imagePath)->exists()) {
                continue;
            }

            FeedbackImage::query()->create([
                'image_path' => $imagePath,
                'is_featured' => $index < 3,
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }
}
