<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Package;
use App\Models\Program;
use Tests\TestCase;

class AssignsCatalogSortOrderTest extends TestCase
{
    public function test_new_category_gets_next_sort_order_automatically(): void
    {
        Category::query()->create([
            'name' => 'Existing Category',
            'sort_order' => 30,
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'New Category',
            'is_active' => true,
        ]);

        $this->assertSame(40, $category->sort_order);
    }

    public function test_explicit_sort_order_is_preserved_for_seeders(): void
    {
        $program = Program::query()->create([
            'name' => 'Seeded Program',
            'slug' => 'seeded-program-sort',
            'category' => 'Review Packages',
            'sort_order' => 100,
            'price_full' => 1000,
            'is_active' => true,
        ]);

        $this->assertSame(100, $program->sort_order);
    }

    public function test_package_syncs_program_order_from_selected_ids(): void
    {
        $first = Program::query()->create([
            'name' => 'Program A',
            'slug' => 'program-a-sort',
            'category' => 'Review Packages',
            'sort_order' => 10,
            'price_full' => 1000,
            'is_active' => true,
        ]);

        $second = Program::query()->create([
            'name' => 'Program B',
            'slug' => 'program-b-sort',
            'category' => 'Review Packages',
            'sort_order' => 20,
            'price_full' => 1000,
            'is_active' => true,
        ]);

        $package = Package::query()->create([
            'name' => 'Sort Package',
            'slug' => 'sort-package',
            'category' => 'Review Packages',
            'price_full' => 1000,
            'is_active' => true,
        ]);

        $package->syncProgramsSortOrder([$second->id, $first->id]);

        $this->assertSame(
            [$second->id, $first->id],
            $package->programs()->pluck('programs.id')->all(),
        );
        $this->assertSame(10, $package->programs()->whereKey($second->id)->first()->pivot->sort_order);
        $this->assertSame(20, $package->programs()->whereKey($first->id)->first()->pivot->sort_order);
    }
}
