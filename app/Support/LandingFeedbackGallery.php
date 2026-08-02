<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Lists Facebook feedback screenshot images for the landing gallery.
 *
 * Place image files in `public/images/feedback/` (jpg, jpeg, png, webp, gif).
 *
 * @author CKD
 *
 * @created 2026-08-02
 *
 * @modified 2026-08-02 CKD
 */
final class LandingFeedbackGallery
{
    private const RELATIVE_DIRECTORY = 'images/feedback';

    /**
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * Absolute public URLs for feedback screenshots, sorted by filename.
     *
     * @return list<string>
     */
    public static function imageUrls(?string $absoluteDirectory = null): array
    {
        $directory = $absoluteDirectory ?? public_path(self::RELATIVE_DIRECTORY);

        if (! is_dir($directory)) {
            return [];
        }

        $usePublicAssetUrls = $absoluteDirectory === null
            || realpath($directory) === realpath(public_path(self::RELATIVE_DIRECTORY));

        return collect(File::files($directory))
            ->filter(function ($file): bool {
                return in_array(strtolower($file->getExtension()), self::ALLOWED_EXTENSIONS, true);
            })
            ->sortBy(fn ($file): string => strtolower($file->getFilename()))
            ->values()
            ->map(function ($file) use ($usePublicAssetUrls): string {
                if ($usePublicAssetUrls) {
                    return asset(self::RELATIVE_DIRECTORY.'/'.$file->getFilename());
                }

                return $file->getFilename();
            })
            ->all();
    }
}
