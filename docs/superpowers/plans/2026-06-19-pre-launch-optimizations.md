# Pre-Launch Optimizations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all performance, database, and code quality issues identified before going live.

**Architecture:** Six targeted improvements — two new DB index migrations, one early bird pricing trait extraction to eliminate duplication, one catalog caching fix for public enrollment pages, one batch insert optimization for enrollment items, and one relation-awareness fix in the pricing service to avoid redundant DB queries on the success page.

**Tech Stack:** Laravel 11, PHP 8.4, MySQL, PHPUnit 11

---

## Audit Summary — Issues Found

| # | Issue | Severity | Task |
|---|-------|----------|------|
| 1 | `schedules` table has no composite index on `(program_id, is_active)` | HIGH | Task 1 |
| 2 | `programs` table has no composite index on `(is_active, sort_order)` | HIGH | Task 1 |
| 3 | Duplicate early bird pricing methods in `Package` and `Program` (100% identical code) | MEDIUM | Task 2 |
| 4 | `EnrollmentController::landing()` and `form()` run raw `Package::query()` on every page load — no caching | MEDIUM | Task 3 |
| 5 | `createEnrollmentItems()` creates each item with an individual `INSERT` inside a loop | MEDIUM | Task 4 |
| 6 | `paidPaymentsForSettlement()` always runs a new DB query even when `payments` relation is already loaded | MEDIUM | Task 5 |

---

## File Structure

**New files:**
- `database/migrations/2026_06_19_000001_add_schedules_programs_performance_indexes.php` — composite indexes
- `app/Models/Concerns/HasEarlyBirdPricing.php` — extracted trait

**Modified files:**
- `app/Models/Package.php` — use new trait, remove duplicated methods
- `app/Models/Program.php` — use new trait, remove duplicated methods
- `app/Http/Controllers/EnrollmentController.php` — use cached catalog query for landing/form pages
- `app/Support/Filament/CatalogOptionsCache.php` — add `landingPagePackages()` cached method
- `app/Services/EnrollmentService.php` — batch insert enrollment items
- `app/Services/EnrollmentPricingService.php` — use pre-loaded relation when available

---

## Task 1: Add Missing Database Indexes

**Files:**
- Create: `database/migrations/2026_06_19_000001_add_schedules_programs_performance_indexes.php`

The `packages` table already has `index(['is_active', 'sort_order'])` from its original migration. The `programs` table does not. The `schedules` table only has an implicit single-column index on `program_id` from the foreign key constraint — no composite with `is_active`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/PerformanceIndexesTest.php
php artisan make:test PerformanceIndexesTest --phpunit
```

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerformanceIndexesTest extends TestCase
{
    public function test_schedules_table_has_composite_index_on_program_id_and_is_active(): void
    {
        $indexes = collect(Schema::getIndexes('schedules'));

        $hasComposite = $indexes->contains(function (array $index) {
            return count($index['columns']) === 2
                && in_array('program_id', $index['columns'], true)
                && in_array('is_active', $index['columns'], true);
        });

        $this->assertTrue($hasComposite, 'schedules table is missing composite index on (program_id, is_active)');
    }

    public function test_programs_table_has_composite_index_on_is_active_and_sort_order(): void
    {
        $indexes = collect(Schema::getIndexes('programs'));

        $hasComposite = $indexes->contains(function (array $index) {
            return count($index['columns']) === 2
                && in_array('is_active', $index['columns'], true)
                && in_array('sort_order', $index['columns'], true);
        });

        $this->assertTrue($hasComposite, 'programs table is missing composite index on (is_active, sort_order)');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact tests/Feature/PerformanceIndexesTest.php
```

Expected: FAIL — both assertions fail because indexes don't exist yet.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration add_schedules_programs_performance_indexes --no-interaction
```

Edit the generated file with exactly this content:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->index(['program_id', 'is_active'], 'schedules_program_id_is_active_index');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->index(['is_active', 'sort_order'], 'programs_is_active_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_program_id_is_active_index');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropIndex('programs_is_active_sort_order_index');
        });
    }
};
```

- [ ] **Step 4: Run the migration**

```bash
php artisan migrate --no-interaction
```

Expected output: `Running migrations... 2026_06_19_000001_add_schedules_programs_performance_indexes ... DONE`

- [ ] **Step 5: Run test to verify it passes**

```bash
php artisan test --compact tests/Feature/PerformanceIndexesTest.php
```

Expected: PASS

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_06_19_000001_add_schedules_programs_performance_indexes.php tests/Feature/PerformanceIndexesTest.php
git commit -m "perf(db): add composite indexes on schedules(program_id,is_active) and programs(is_active,sort_order)"
```

---

## Task 2: Extract Duplicate Early Bird Pricing to a Trait

**Files:**
- Create: `app/Models/Concerns/HasEarlyBirdPricing.php`
- Modify: `app/Models/Package.php:79-118`
- Modify: `app/Models/Program.php:65-104`

`Package` and `Program` have five identical methods: `isFirstEarlyBirdActive()`, `isSecondEarlyBirdActive()`, `isEarlyBirdActive()`, `getActivePriceAttribute()`, `getDownpaymentAmountAttribute()`. This is byte-for-byte duplication.

- [ ] **Step 1: Write the failing test**

This test verifies both models respond identically via the trait. Run it before creating the trait to confirm setup.

```bash
php artisan make:test EarlyBirdPricingTraitTest --phpunit
```

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarlyBirdPricingTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_and_program_share_identical_early_bird_behavior_with_no_early_bird(): void
    {
        $package = Package::factory()->create([
            'price_full' => 10000,
            'price_early' => null,
            'early_deadline' => null,
            'price_early_2' => null,
            'early_deadline_2' => null,
        ]);

        $program = Program::factory()->create([
            'price_full' => 10000,
            'price_early' => null,
            'early_deadline' => null,
            'price_early_2' => null,
            'early_deadline_2' => null,
        ]);

        $this->assertFalse($package->isFirstEarlyBirdActive());
        $this->assertFalse($package->isEarlyBirdActive());
        $this->assertSame(10000, $package->active_price);

        $this->assertFalse($program->isFirstEarlyBirdActive());
        $this->assertFalse($program->isEarlyBirdActive());
        $this->assertSame(10000, $program->active_price);
    }

    public function test_package_and_program_active_price_uses_early_bird_when_deadline_is_future(): void
    {
        $deadline = now('Asia/Manila')->addDays(3)->toDateString();

        $package = Package::factory()->create([
            'price_full' => 10000,
            'price_early' => 8000,
            'early_deadline' => $deadline,
            'price_early_2' => null,
            'early_deadline_2' => null,
        ]);

        $program = Program::factory()->create([
            'price_full' => 10000,
            'price_early' => 8000,
            'early_deadline' => $deadline,
            'price_early_2' => null,
            'early_deadline_2' => null,
        ]);

        $this->assertTrue($package->isFirstEarlyBirdActive());
        $this->assertSame(8000, $package->active_price);

        $this->assertTrue($program->isFirstEarlyBirdActive());
        $this->assertSame(8000, $program->active_price);
    }

    public function test_downpayment_amount_is_fifty_percent_of_list_price(): void
    {
        $package = Package::factory()->create(['price_full' => 10000]);
        $program = Program::factory()->create(['price_full' => 10000]);

        $this->assertSame(5000, $package->downpayment_amount);
        $this->assertSame(5000, $program->downpayment_amount);
    }
}
```

- [ ] **Step 2: Run test to verify it passes before refactor (baseline)**

```bash
php artisan test --compact tests/Feature/EarlyBirdPricingTraitTest.php
```

Expected: PASS — these methods exist on both models already, so tests should pass now.

- [ ] **Step 3: Create the trait**

```bash
php artisan make:class app/Models/Concerns/HasEarlyBirdPricing --no-interaction
```

Replace the generated content entirely with:

```php
<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Carbon\Carbon;

/**
 * Shared early bird pricing methods for Program and Package models.
 * Both models have identical price_full, price_early, early_deadline,
 * price_early_2, early_deadline_2, and downpayment_amount fields.
 */
trait HasEarlyBirdPricing
{
    public function isFirstEarlyBirdActive(): bool
    {
        return $this->price_early !== null
            && $this->early_deadline !== null
            && now()->timezone('Asia/Manila')->startOfDay()->lte($this->early_deadline);
    }

    public function isSecondEarlyBirdActive(): bool
    {
        if ($this->isFirstEarlyBirdActive()) {
            return false;
        }

        return $this->price_early_2 !== null
            && $this->early_deadline_2 !== null
            && now()->timezone('Asia/Manila')->startOfDay()->lte($this->early_deadline_2);
    }

    public function isEarlyBirdActive(): bool
    {
        return $this->isFirstEarlyBirdActive() || $this->isSecondEarlyBirdActive();
    }

    public function getActivePriceAttribute(): int
    {
        if ($this->isFirstEarlyBirdActive()) {
            return (int) $this->price_early;
        }

        if ($this->isSecondEarlyBirdActive()) {
            return (int) $this->price_early_2;
        }

        return (int) $this->price_full;
    }

    public function getDownpaymentAmountAttribute(): int
    {
        return (int) round(((int) $this->price_full) * 0.5);
    }
}
```

- [ ] **Step 4: Update Package to use the trait — remove the 5 duplicated methods**

In `app/Models/Package.php`:

Replace the `use` block at the top from:

```php
use App\Models\Concerns\AssignsCatalogSortOrder;
use App\Models\Concerns\SyncsCatalogCategoryString;
```

to:

```php
use App\Models\Concerns\AssignsCatalogSortOrder;
use App\Models\Concerns\HasEarlyBirdPricing;
use App\Models\Concerns\SyncsCatalogCategoryString;
```

Replace the `use` trait line from:

```php
    use AssignsCatalogSortOrder;
    use SyncsCatalogCategoryString;
```

to:

```php
    use AssignsCatalogSortOrder;
    use HasEarlyBirdPricing;
    use SyncsCatalogCategoryString;
```

Delete lines 79–118 (the five methods: `isFirstEarlyBirdActive`, `isSecondEarlyBirdActive`, `isEarlyBirdActive`, `getActivePriceAttribute`, `getDownpaymentAmountAttribute`).

- [ ] **Step 5: Update Program to use the trait — remove the 5 duplicated methods**

In `app/Models/Program.php`:

Replace the `use` block at the top from:

```php
use App\Models\Concerns\AssignsCatalogSortOrder;
use App\Models\Concerns\SyncsCatalogCategoryString;
```

to:

```php
use App\Models\Concerns\AssignsCatalogSortOrder;
use App\Models\Concerns\HasEarlyBirdPricing;
use App\Models\Concerns\SyncsCatalogCategoryString;
```

Replace the `use` trait line from:

```php
    use AssignsCatalogSortOrder;
    use SyncsCatalogCategoryString;
```

to:

```php
    use AssignsCatalogSortOrder;
    use HasEarlyBirdPricing;
    use SyncsCatalogCategoryString;
```

Delete lines 65–104 (the five methods: `isFirstEarlyBirdActive`, `isSecondEarlyBirdActive`, `isEarlyBirdActive`, `getActivePriceAttribute`, `getDownpaymentAmountAttribute`).

- [ ] **Step 6: Run test again to verify refactor didn't break anything**

```bash
php artisan test --compact tests/Feature/EarlyBirdPricingTraitTest.php
```

Expected: PASS — identical behavior via the shared trait.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Concerns/HasEarlyBirdPricing.php app/Models/Package.php app/Models/Program.php tests/Feature/EarlyBirdPricingTraitTest.php
git commit -m "refactor(models): extract duplicate early bird pricing methods into HasEarlyBirdPricing trait"
```

---

## Task 3: Cache Catalog Queries on Public Enrollment Pages

**Files:**
- Modify: `app/Support/Filament/CatalogOptionsCache.php`
- Modify: `app/Http/Controllers/EnrollmentController.php:31-55`

`EnrollmentController::landing()` and `form()` both run:
```php
Package::query()->where('is_active', true)->with(['programs:id,name,slug'])->orderBy('sort_order')->get()
```
on every page load. `CatalogOptionsCache` exists but only caches Filament dropdown options, not this shape. We need a cached version that includes the programs eager load.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test CatalogCacheTest --phpunit
```

```php
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

    public function test_landing_page_packages_are_cached_on_second_call(): void
    {
        Package::factory()
            ->has(Program::factory()->count(2))
            ->count(2)
            ->create(['is_active' => true]);

        // First call — warms the cache
        $first = CatalogOptionsCache::landingPagePackages();

        // Manually invalidate DB to prove second call uses cache
        Package::query()->delete();

        $second = CatalogOptionsCache::landingPagePackages();

        $this->assertCount(2, $second);
        $this->assertTrue($second->first()->relationLoaded('programs'));
    }

    public function test_landing_page_cache_is_invalidated_when_package_is_saved(): void
    {
        $package = Package::factory()->create(['is_active' => true]);

        CatalogOptionsCache::landingPagePackages(); // warm cache

        $package->touch(); // triggers saved event → forgetAll()

        Package::query()->delete();

        $result = CatalogOptionsCache::landingPagePackages();

        $this->assertCount(0, $result); // cache was cleared, fresh query returned empty
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact tests/Feature/CatalogCacheTest.php
```

Expected: FAIL — `CatalogOptionsCache::landingPagePackages()` method doesn't exist yet.

- [ ] **Step 3: Add `landingPagePackages()` to CatalogOptionsCache**

In `app/Support/Filament/CatalogOptionsCache.php`, add a new constant and method, and add the new key to `forgetAll()`:

Add this constant below the existing ones:
```php
    private const KEY_LANDING_PACKAGES = 'catalog.landing_page_packages';
```

Add this method after `categoryOptions()`:
```php
    /**
     * Cached active packages with their programs for the public enrollment landing and form pages.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Package>
     */
    public static function landingPagePackages(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember(self::KEY_LANDING_PACKAGES, self::TTL_SECONDS, function (): \Illuminate\Database\Eloquent\Collection {
            return Package::query()
                ->where('is_active', true)
                ->with(['programs:id,name,slug'])
                ->orderBy('sort_order')
                ->get();
        });
    }
```

Update `forgetAll()` to also clear the new key:
```php
    public static function forgetAll(): void
    {
        Cache::forget(self::KEY_PURCHASED_ITEMS);
        Cache::forget(self::KEY_PROGRAMS);
        Cache::forget(self::KEY_CATEGORIES);
        Cache::forget(self::KEY_LANDING_PACKAGES);
    }
```

- [ ] **Step 4: Update EnrollmentController to use the cached method**

In `app/Http/Controllers/EnrollmentController.php`, add the import at the top:
```php
use App\Support\Filament\CatalogOptionsCache;
```

Replace `landing()` method body at lines 32–39:
```php
    public function landing()
    {
        $packages = CatalogOptionsCache::landingPagePackages();

        return view('enrollment.landing', compact('packages'));
    }
```

Replace the `Package::query()` block in `form()` at lines 51–55:
```php
        $packages = CatalogOptionsCache::landingPagePackages();
```

- [ ] **Step 5: Run test to verify it passes**

```bash
php artisan test --compact tests/Feature/CatalogCacheTest.php
```

Expected: PASS

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Filament/CatalogOptionsCache.php app/Http/Controllers/EnrollmentController.php tests/Feature/CatalogCacheTest.php
git commit -m "perf(cache): cache landing/form page package catalog query via CatalogOptionsCache"
```

---

## Task 4: Batch Insert Enrollment Items

**Files:**
- Modify: `app/Services/EnrollmentService.php:158-191`

`createEnrollmentItems()` issues one `INSERT` per program in a package. A package with 20 programs fires 20 round-trips. Replace with a single `EnrollmentItem::insert()`.

Note: `EnrollmentItem::insert()` bypasses Eloquent events (created, etc.) — verify no listeners are registered on `EnrollmentItem` before this task. If there are, use `upsert` or keep the loop.

- [ ] **Step 1: Verify no EnrollmentItem model observers exist**

```bash
grep -rn "EnrollmentItem\|enrollment_items" /Users/ctrlkarldel/dmf-dental/app/Providers/ /Users/ctrlkarldel/dmf-dental/app/Observers/ 2>/dev/null || echo "No observers found"
grep -rn "EnrollmentItem::observe\|EnrollmentItemObserver" /Users/ctrlkarldel/dmf-dental/app/ 2>/dev/null || echo "No observers"
```

Expected: no observers. If observers ARE found, skip this task and leave a comment explaining why batch insert was intentionally not used.

- [ ] **Step 2: Write the failing test**

```bash
php artisan make:test EnrollmentItemBatchInsertTest --phpunit
```

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\EnrollmentItem;
use App\Models\Package;
use App\Models\Program;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnrollmentItemBatchInsertTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_enrollment_creates_all_items_in_one_query(): void
    {
        $package = Package::factory()
            ->has(Program::factory()->count(5)->state(['is_active' => true]))
            ->create(['is_active' => true]);

        $enrollment = Enrollment::factory()->create([
            'purchasable_type' => Package::class,
            'purchasable_id' => $package->getKey(),
        ]);

        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            if (stripos($query->sql, 'insert into `enrollment_items`') !== false) {
                $queryCount++;
            }
        });

        app(EnrollmentService::class)->createEnrollmentItemsForTest($enrollment, $package, null);

        $this->assertSame(1, $queryCount, "Expected 1 batch INSERT but got {$queryCount}");
        $this->assertSame(5, EnrollmentItem::query()->where('enrollment_id', $enrollment->getKey())->count());
    }
}
```

> Note: We expose `createEnrollmentItemsForTest()` as a `public` proxy for testing; the real `createEnrollmentItems()` stays `private`. Alternatively, make `createEnrollmentItems` protected and override in a test subclass. The simplest approach here is to make it `public` on the service. See Step 4.

- [ ] **Step 3: Run test to verify it fails**

```bash
php artisan test --compact tests/Feature/EnrollmentItemBatchInsertTest.php
```

Expected: FAIL — `createEnrollmentItemsForTest` doesn't exist yet.

- [ ] **Step 4: Refactor `createEnrollmentItems()` to use batch insert**

In `app/Services/EnrollmentService.php`, replace the `createEnrollmentItems()` method (lines 158–191):

```php
    public function createEnrollmentItemsForTest(Enrollment $enrollment, Program|Package $purchased, ?int $scheduleId): void
    {
        $this->createEnrollmentItems($enrollment, $purchased, $scheduleId);
    }

    private function createEnrollmentItems(Enrollment $enrollment, Program|Package $purchased, ?int $scheduleId): void
    {
        if ($purchased instanceof Package) {
            $includedPrograms = $purchased->programs()->where('is_active', true)->get();

            $rows = $includedPrograms->map(fn (Program $included) => [
                'enrollment_id' => $enrollment->getKey(),
                'program_id' => $included->getKey(),
                'schedule_id' => null,
                'status' => (string) $enrollment->status->value,
                'program_name_snapshot' => (string) $included->name,
                'program_slug_snapshot' => (string) $included->slug,
                'schedule_label_snapshot' => null,
                'schedule_mode_snapshot' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if (! empty($rows)) {
                EnrollmentItem::insert($rows);
            }

            return;
        }

        $schedule = $scheduleId ? Schedule::query()->find($scheduleId) : null;

        EnrollmentItem::query()->create([
            'enrollment_id' => $enrollment->getKey(),
            'program_id' => $purchased->getKey(),
            'schedule_id' => $scheduleId,
            'status' => (string) $enrollment->status->value,
            'program_name_snapshot' => (string) $purchased->name,
            'program_slug_snapshot' => (string) $purchased->slug,
            'schedule_label_snapshot' => $schedule?->label,
            'schedule_mode_snapshot' => $schedule?->mode,
        ]);
    }
```

- [ ] **Step 5: Run test to verify it passes**

```bash
php artisan test --compact tests/Feature/EnrollmentItemBatchInsertTest.php
```

Expected: PASS

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/EnrollmentService.php tests/Feature/EnrollmentItemBatchInsertTest.php
git commit -m "perf(enrollment): batch insert enrollment items for package enrollments"
```

---

## Task 5: Avoid Redundant DB Query in `paidPaymentsForSettlement()`

**Files:**
- Modify: `app/Services/EnrollmentPricingService.php:128-137`

`paidPaymentsForSettlement()` always runs `Payment::query()->...->get()` even when the success page has already loaded `$enrollment->payments` with `with('payments.bankTransferSubmission')`. This is a redundant round-trip on the most-viewed post-payment page.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test PricingServiceRelationAwarenessTest --phpunit
```

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\EnrollmentPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PricingServiceRelationAwarenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_tuition_due_does_not_query_db_when_payments_relation_is_loaded(): void
    {
        $enrollment = Enrollment::factory()->create([
            'payment_type' => 'dp',
            'tuition_list_amount' => 10000,
            'tuition_price_early' => null,
            'tuition_early_deadline' => null,
            'tuition_price_early_2' => null,
            'tuition_early_deadline_2' => null,
            'amount_paid_tuition' => 3000,
        ]);

        Payment::factory()->count(2)->create([
            'enrollment_id' => $enrollment->getKey(),
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Reload with relation pre-loaded (mimics what EnrollmentSuccessService does)
        $enrollment = $enrollment->fresh(['payments.bankTransferSubmission']);

        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            if (stripos($query->sql, 'select') !== false && stripos($query->sql, 'payments') !== false) {
                $queryCount++;
            }
        });

        EnrollmentPricingService::balanceTuitionDue($enrollment);

        $this->assertSame(0, $queryCount, "Expected 0 payment queries when relation is pre-loaded, but got {$queryCount}");
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact tests/Feature/PricingServiceRelationAwarenessTest.php
```

Expected: FAIL — currently always queries DB regardless of loaded relations.

- [ ] **Step 3: Update `paidPaymentsForSettlement()` to use loaded relation when available**

In `app/Services/EnrollmentPricingService.php`, replace the `paidPaymentsForSettlement()` method (lines 128–137):

```php
    /**
     * @return Collection<int, Payment>
     */
    private static function paidPaymentsForSettlement(Enrollment $enrollment): Collection
    {
        if ($enrollment->relationLoaded('payments')) {
            return $enrollment->payments
                ->where('status', 'paid')
                ->sortBy([['paid_at', 'asc'], ['id', 'asc']])
                ->values();
        }

        return Payment::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->where('status', 'paid')
            ->with('bankTransferSubmission')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();
    }
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --compact tests/Feature/PricingServiceRelationAwarenessTest.php
```

Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/EnrollmentPricingService.php tests/Feature/PricingServiceRelationAwarenessTest.php
git commit -m "perf(pricing): skip payment DB query when payments relation is already loaded on enrollment"
```

---

## Task 6: Run Full Test Suite Verification

After all tasks are complete, run the full suite to ensure no regressions.

- [ ] **Step 1: Run all tests**

```bash
php artisan test --compact
```

Expected: All tests pass. If failures appear, fix them before marking the plan complete.

- [ ] **Step 2: Verify migrations are clean**

```bash
php artisan migrate:status
```

Expected: All migrations show `Ran` status. No pending migrations.

---

## Self-Review Checklist

- [x] Task 1 covers both missing indexes (schedules composite + programs composite)
- [x] Task 2 covers the full 5-method duplication in Package and Program
- [x] Task 3 covers both `landing()` and `form()` controller methods, plus cache invalidation
- [x] Task 4 covers the package-enrollment batch insert case (single-program enrollment intentionally left as individual create — batch of 1 is no improvement)
- [x] Task 5 covers the success page DB redundancy
- [x] No placeholder steps — every code block is complete and copy-pasteable
- [x] Types and method signatures are consistent across all tasks
