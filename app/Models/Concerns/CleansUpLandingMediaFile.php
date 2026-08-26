<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\LandingMediaService;

trait CleansUpLandingMediaFile
{
    protected static function bootCleansUpLandingMediaFile(): void
    {
        static::deleting(function (self $model): void {
            app(LandingMediaService::class)->deleteAsset($model->image_path);
        });
    }
}
