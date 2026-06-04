<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Category;

trait SyncsCatalogCategoryString
{
    protected static function bootSyncsCatalogCategoryString(): void
    {
        static::saving(function (self $model): void {
            if (! $model->category_id) {
                return;
            }

            $categoryName = Category::query()
                ->whereKey($model->category_id)
                ->value('name');

            if (filled($categoryName)) {
                $model->category = $categoryName;
            }
        });
    }
}
