<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Category;
use App\Models\Package;
use App\Models\Program;
use Illuminate\Support\Facades\Cache;

/**
 * Cached catalog option lists for Filament filter and form dropdowns.
 */
final class CatalogOptionsCache
{
    private const TTL_SECONDS = 600;

    private const KEY_PURCHASED_ITEMS = 'filament.catalog.purchased_item_filter_options';

    private const KEY_PROGRAMS = 'filament.catalog.program_options';

    private const KEY_CATEGORIES = 'filament.catalog.category_options';

    /**
     * @return array<string, string>
     */
    public static function purchasedItemFilterOptions(): array
    {
        return Cache::remember(self::KEY_PURCHASED_ITEMS, self::TTL_SECONDS, function (): array {
            $packages = Package::query()
                ->orderBy('sort_order')
                ->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => ["package:{$id}" => "Package — {$name}"]);

            $programs = Program::query()
                ->orderBy('sort_order')
                ->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => ["program:{$id}" => "Program — {$name}"]);

            return $packages->merge($programs)->all();
        });
    }

    /**
     * @return array<int|string, string>
     */
    public static function programOptions(): array
    {
        return Cache::remember(self::KEY_PROGRAMS, self::TTL_SECONDS, function (): array {
            return Program::query()
                ->orderBy('sort_order')
                ->pluck('name', 'id')
                ->all();
        });
    }

    /**
     * @return array<int|string, string>
     */
    public static function categoryOptions(): array
    {
        return Cache::remember(self::KEY_CATEGORIES, self::TTL_SECONDS, function (): array {
            return Category::query()
                ->orderBy('sort_order')
                ->pluck('name', 'id')
                ->all();
        });
    }

    public static function forgetAll(): void
    {
        Cache::forget(self::KEY_PURCHASED_ITEMS);
        Cache::forget(self::KEY_PROGRAMS);
        Cache::forget(self::KEY_CATEGORIES);
    }
}
