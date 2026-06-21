<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Program;
use App\Support\Filament\CatalogOptionsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_landing_page_packages_returns_active_packages_with_programs_loaded(): void
    {
        $package = Package::factory()
            ->has(Program::factory()->count(2))
            ->create(['is_active' => true]);

        Package::factory()->create(['is_active' => false]);

        $result = CatalogOptionsCache::landingPagePackages();

        $this->assertCount(1, $result);
        $this->assertTrue($result->first()->relationLoaded('programs'));
        $this->assertCount(2, $result->first()->programs);
    }

    public function test_landing_page_packages_are_served_from_cache_on_second_call(): void
    {
        Package::factory()->create(['is_active' => true]);

        CatalogOptionsCache::landingPagePackages(); // warm cache

        // Delete from DB — if cache works, the second call still returns the record
        Package::query()->delete();

        $result = CatalogOptionsCache::landingPagePackages();

        $this->assertCount(1, $result);
    }

    public function test_cache_is_invalidated_when_package_is_saved(): void
    {
        $package = Package::factory()->create(['is_active' => true]);

        CatalogOptionsCache::landingPagePackages(); // warm cache

        // Touch triggers saved event → forgetAll()
        $package->touch();

        // Delete from DB after cache clear — fresh query should return empty
        Package::query()->delete();

        $result = CatalogOptionsCache::landingPagePackages();

        $this->assertCount(0, $result);
    }

    public function test_landing_page_uses_cached_packages(): void
    {
        Package::factory()->count(2)->create(['is_active' => true]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertViewHas('packages');
        $this->assertCount(2, $response->viewData('packages'));
    }
}
