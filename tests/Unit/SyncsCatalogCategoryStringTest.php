<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Package;
use App\Models\Program;
use Tests\TestCase;

class SyncsCatalogCategoryStringTest extends TestCase
{
    public function test_package_syncs_category_string_from_selected_category(): void
    {
        $category = Category::query()->create([
            'name' => 'Review Packages',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $package = Package::query()->create([
            'name' => 'Package Test',
            'slug' => 'package-test-sync',
            'category_id' => $category->id,
            'category' => 'Stale value',
            'price_full' => 1000,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->assertSame('Review Packages', $package->fresh()->category);
        $this->assertSame('Review Packages', $package->fresh()->category_label);
    }

    public function test_program_syncs_category_string_from_selected_category(): void
    {
        $category = Category::query()->create([
            'name' => 'Individual Programs (Theoretical)',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $program = Program::query()->create([
            'name' => 'Program Test',
            'slug' => 'program-test-sync',
            'category_id' => $category->id,
            'category' => 'Stale value',
            'price_full' => 1000,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->assertSame('Individual Programs (Theoretical)', $program->fresh()->category);
        $this->assertSame('Individual Programs (Theoretical)', $program->fresh()->category_label);
    }
}
