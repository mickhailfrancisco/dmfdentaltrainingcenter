<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait AssignsCatalogSortOrder
{
    protected static function bootAssignsCatalogSortOrder(): void
    {
        static::creating(function (self $model): void {
            if ($model->isDirty('sort_order') && (int) $model->sort_order !== 0) {
                return;
            }

            if ((int) ($model->sort_order ?? 0) !== 0) {
                return;
            }

            $model->sort_order = ((int) static::query()->max('sort_order')) + 10;
        });
    }
}
