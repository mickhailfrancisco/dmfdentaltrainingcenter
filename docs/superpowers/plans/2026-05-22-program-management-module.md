# Program Management Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a second early-bird pricing tier (`price_early_2` / `early_deadline_2`) to Programs and Packages, propagate it through the enrollment snapshot and pricing service, and improve the Filament admin UI for the whole catalog module.

**Architecture:** New columns are added via dedicated migrations; pricing logic lives in the models (`getActivePriceAttribute`) and `EnrollmentPricingService` (balance calculation); Filament forms and tables are updated in-place; the `EnrollmentService` snapshots all three price tiers at enrollment creation time.

**Tech Stack:** Laravel 11, Filament v3, Livewire v3, PHPUnit v11, Tailwind CSS v3, PHP 8.4

---

## File Map

| Action | File |
|--------|------|
| Create | `database/migrations/2026_05_22_000001_add_second_early_pricing_to_programs_and_packages.php` |
| Create | `database/migrations/2026_05_22_000002_add_second_early_pricing_snapshot_to_enrollments.php` |
| Modify | `app/Models/Program.php` |
| Modify | `app/Models/Package.php` |
| Modify | `app/Models/Enrollment.php` |
| Modify | `app/Filament/Resources/ProgramResource.php` |
| Modify | `app/Filament/Resources/PackageResource.php` |
| Modify | `app/Services/EnrollmentService.php` |
| Modify | `app/Services/EnrollmentPricingService.php` |
| Create | `tests/Unit/ProgramPricingTest.php` |
| Create | `tests/Unit/PackagePricingTest.php` |
| Modify | `tests/Unit/EnrollmentPricingServiceTest.php` |

---

## Pricing Tier Logic (reference for all tasks)

| Condition | Active Price |
|-----------|-------------|
| today ≤ `early_deadline` AND `price_early` set | `price_early` (cheapest) |
| today ≤ `early_deadline_2` AND `price_early_2` set | `price_early_2` |
| otherwise | `price_full` |

---

### Task 1: Migration — second early pricing on programs & packages

**Files:**
- Create: `database/migrations/2026_05_22_000001_add_second_early_pricing_to_programs_and_packages.php`

- [ ] **Step 1: Create migration via Artisan**

```bash
php artisan make:migration add_second_early_pricing_to_programs_and_packages --no-interaction
```

- [ ] **Step 2: Write migration content**

Open the new file and replace its contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->unsignedInteger('price_early_2')->nullable()->after('early_deadline');
            $table->date('early_deadline_2')->nullable()->after('price_early_2');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedInteger('price_early_2')->nullable()->after('early_deadline');
            $table->date('early_deadline_2')->nullable()->after('price_early_2');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['price_early_2', 'early_deadline_2']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['price_early_2', 'early_deadline_2']);
        });
    }
};
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate --no-interaction
```

Expected output: `Migrating: 2026_05_22_000001_add_second_early_pricing_to_programs_and_packages`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_22_000001_add_second_early_pricing_to_programs_and_packages.php
git commit -m "feat(catalog): add price_early_2 and early_deadline_2 columns to programs and packages"
```

---

### Task 2: Update Program model — 3-tier pricing

**Files:**
- Modify: `app/Models/Program.php`
- Create: `tests/Unit/ProgramPricingTest.php`

- [ ] **Step 1: Write the failing tests**

```bash
php artisan make:test --phpunit --unit ProgramPricingTest --no-interaction
```

Open `tests/Unit/ProgramPricingTest.php` and replace its contents with:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Program;
use Carbon\Carbon;
use Tests\TestCase;

class ProgramPricingTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_full_price_when_no_early_pricing_set(): void
    {
        $program = new Program(['price_full' => 18_000]);

        $this->assertSame(18_000, $program->active_price);
        $this->assertFalse($program->isEarlyBirdActive());
    }

    public function test_first_early_price_when_within_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = new Program([
            'price_full' => 18_000,
            'price_early' => 15_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
        ]);

        $this->assertSame(15_000, $program->active_price);
        $this->assertTrue($program->isEarlyBirdActive());
        $this->assertTrue($program->isFirstEarlyBirdActive());
        $this->assertFalse($program->isSecondEarlyBirdActive());
    }

    public function test_second_early_price_when_past_first_deadline_but_within_second(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10', 'Asia/Manila')->startOfDay());

        $program = new Program([
            'price_full' => 18_000,
            'price_early' => 15_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
            'price_early_2' => 16_500,
            'early_deadline_2' => Carbon::parse('2026-08-01', 'Asia/Manila'),
        ]);

        $this->assertSame(16_500, $program->active_price);
        $this->assertTrue($program->isEarlyBirdActive());
        $this->assertFalse($program->isFirstEarlyBirdActive());
        $this->assertTrue($program->isSecondEarlyBirdActive());
    }

    public function test_full_price_when_both_deadlines_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01', 'Asia/Manila')->startOfDay());

        $program = new Program([
            'price_full' => 18_000,
            'price_early' => 15_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
            'price_early_2' => 16_500,
            'early_deadline_2' => Carbon::parse('2026-08-01', 'Asia/Manila'),
        ]);

        $this->assertSame(18_000, $program->active_price);
        $this->assertFalse($program->isEarlyBirdActive());
    }

    public function test_full_price_when_only_second_tier_set_and_deadline_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01', 'Asia/Manila')->startOfDay());

        $program = new Program([
            'price_full' => 18_000,
            'price_early_2' => 16_500,
            'early_deadline_2' => Carbon::parse('2026-08-01', 'Asia/Manila'),
        ]);

        $this->assertSame(18_000, $program->active_price);
        $this->assertFalse($program->isEarlyBirdActive());
    }

    public function test_second_early_price_when_only_second_tier_set_and_within_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15', 'Asia/Manila')->startOfDay());

        $program = new Program([
            'price_full' => 18_000,
            'price_early_2' => 16_500,
            'early_deadline_2' => Carbon::parse('2026-08-01', 'Asia/Manila'),
        ]);

        $this->assertSame(16_500, $program->active_price);
        $this->assertTrue($program->isEarlyBirdActive());
        $this->assertTrue($program->isSecondEarlyBirdActive());
    }

    public function test_downpayment_is_always_50_percent_of_full_price(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $program = new Program([
            'price_full' => 18_000,
            'price_early' => 15_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
        ]);

        $this->assertSame(9_000, $program->downpayment_amount);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact tests/Unit/ProgramPricingTest.php
```

Expected: FAIL — `isFirstEarlyBirdActive` and `isSecondEarlyBirdActive` do not exist yet.

- [ ] **Step 3: Update Program model**

Replace `app/Models/Program.php` with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'tag',
        'category_id',
        'price_full', 'price_early', 'early_deadline',
        'price_early_2', 'early_deadline_2',
        'early_bird_label',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'early_deadline' => 'date',
        'early_deadline_2' => 'date',
        'is_active' => 'boolean',
    ];

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_program', 'program_id', 'package_id')
            ->withPivot(['sort_order']);
    }

    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return $this->categoryModel?->name ?: (string) $this->category;
    }

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

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test --compact tests/Unit/ProgramPricingTest.php
```

Expected: All 7 tests PASS.

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Models/Program.php tests/Unit/ProgramPricingTest.php
git commit -m "feat(catalog): add 3-tier early-bird pricing methods to Program model"
```

---

### Task 3: Update Package model — 3-tier pricing

**Files:**
- Modify: `app/Models/Package.php`
- Create: `tests/Unit/PackagePricingTest.php`

- [ ] **Step 1: Write the failing tests**

```bash
php artisan make:test --phpunit --unit PackagePricingTest --no-interaction
```

Open `tests/Unit/PackagePricingTest.php` and replace its contents with:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Package;
use Carbon\Carbon;
use Tests\TestCase;

class PackagePricingTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_full_price_when_no_early_pricing_set(): void
    {
        $package = new Package(['price_full' => 40_000]);

        $this->assertSame(40_000, $package->active_price);
        $this->assertFalse($package->isEarlyBirdActive());
    }

    public function test_first_early_price_when_within_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        $package = new Package([
            'price_full' => 40_000,
            'price_early' => 36_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
        ]);

        $this->assertSame(36_000, $package->active_price);
        $this->assertTrue($package->isFirstEarlyBirdActive());
        $this->assertFalse($package->isSecondEarlyBirdActive());
    }

    public function test_second_early_price_when_past_first_deadline_but_within_second(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10', 'Asia/Manila')->startOfDay());

        $package = new Package([
            'price_full' => 40_000,
            'price_early' => 36_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
            'price_early_2' => 38_000,
            'early_deadline_2' => Carbon::parse('2026-08-01', 'Asia/Manila'),
        ]);

        $this->assertSame(38_000, $package->active_price);
        $this->assertTrue($package->isSecondEarlyBirdActive());
        $this->assertFalse($package->isFirstEarlyBirdActive());
    }

    public function test_full_price_when_both_deadlines_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01', 'Asia/Manila')->startOfDay());

        $package = new Package([
            'price_full' => 40_000,
            'price_early' => 36_000,
            'early_deadline' => Carbon::parse('2026-07-01', 'Asia/Manila'),
            'price_early_2' => 38_000,
            'early_deadline_2' => Carbon::parse('2026-08-01', 'Asia/Manila'),
        ]);

        $this->assertSame(40_000, $package->active_price);
        $this->assertFalse($package->isEarlyBirdActive());
    }

    public function test_downpayment_is_always_50_percent_of_full_price(): void
    {
        $package = new Package(['price_full' => 40_000]);

        $this->assertSame(20_000, $package->downpayment_amount);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact tests/Unit/PackagePricingTest.php
```

Expected: FAIL — `isFirstEarlyBirdActive` and `isSecondEarlyBirdActive` do not exist yet.

- [ ] **Step 3: Update Package model**

Replace `app/Models/Package.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Package extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'tag',
        'category_id',
        'category',
        'price_full',
        'price_early',
        'early_deadline',
        'price_early_2',
        'early_deadline_2',
        'early_bird_label',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'early_deadline' => 'date',
        'early_deadline_2' => 'date',
        'is_active' => 'boolean',
    ];

    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'package_program', 'package_id', 'program_id')
            ->withPivot(['sort_order'])
            ->orderBy('package_program.sort_order');
    }

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

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test --compact tests/Unit/PackagePricingTest.php
```

Expected: All 5 tests PASS.

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Models/Package.php tests/Unit/PackagePricingTest.php
git commit -m "feat(catalog): add 3-tier early-bird pricing methods to Package model"
```

---

### Task 4: Update Filament — ProgramResource form and table

**Files:**
- Modify: `app/Filament/Resources/ProgramResource.php`

- [ ] **Step 1: Update ProgramResource**

Replace the `Pricing` section in the `form()` method and add columns to `table()`. Open `app/Filament/Resources/ProgramResource.php` and make the following changes:

**Replace the entire `Pricing` section** (lines 77–98) with:

```php
            Forms\Components\Section::make('Pricing')
                ->schema([
                    Forms\Components\TextInput::make('price_full')
                        ->label('Full Price')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('₱'),

                    Forms\Components\Placeholder::make('pricing_spacer')
                        ->label('')
                        ->content(''),

                    Forms\Components\TextInput::make('price_early')
                        ->label('1st Early Bird Price')
                        ->numeric()
                        ->nullable()
                        ->minValue(0)
                        ->prefix('₱'),

                    Forms\Components\DatePicker::make('early_deadline')
                        ->label('1st Early Bird Deadline')
                        ->helperText('After this date, the 2nd early price (or full price) applies.')
                        ->nullable(),

                    Forms\Components\TextInput::make('price_early_2')
                        ->label('2nd Early Bird Price')
                        ->numeric()
                        ->nullable()
                        ->minValue(0)
                        ->prefix('₱'),

                    Forms\Components\DatePicker::make('early_deadline_2')
                        ->label('2nd Early Bird Deadline')
                        ->helperText('After this date, the full price applies.')
                        ->nullable(),

                    Forms\Components\Textarea::make('early_bird_label')
                        ->label('Early Bird Label')
                        ->rows(2)
                        ->nullable()
                        ->columnSpanFull(),
                ])->columns(2),
```

**Replace the `table()` columns array** with:

```php
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('category_label')
                    ->label('Category')
                    ->getStateUsing(fn (Program $record) => $record->category_label)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('category', $direction)->orderBy('name', 'asc');
                    }),

                Tables\Columns\TextColumn::make('price_full')
                    ->label('Full')
                    ->money('PHP')
                    ->sortable()
                    ->alignment(Alignment::End),

                Tables\Columns\TextColumn::make('price_early')
                    ->label('1st Early')
                    ->money('PHP')
                    ->sortable()
                    ->alignment(Alignment::End)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('price_early_2')
                    ->label('2nd Early')
                    ->money('PHP')
                    ->sortable()
                    ->alignment(Alignment::End)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
            ])
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint app/Filament/Resources/ProgramResource.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/ProgramResource.php
git commit -m "feat(catalog): add 2nd early-bird pricing fields to ProgramResource form and table"
```

---

### Task 5: Update Filament — PackageResource form and table

**Files:**
- Modify: `app/Filament/Resources/PackageResource.php`

- [ ] **Step 1: Update PackageResource**

Open `app/Filament/Resources/PackageResource.php` and make the following changes:

**Replace the entire `Pricing` section** in `form()` with:

```php
                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('price_full')
                            ->label('Full Price')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('₱'),

                        Forms\Components\Placeholder::make('pricing_spacer')
                            ->label('')
                            ->content(''),

                        Forms\Components\TextInput::make('price_early')
                            ->label('1st Early Bird Price')
                            ->numeric()
                            ->nullable()
                            ->minValue(0)
                            ->prefix('₱'),

                        Forms\Components\DatePicker::make('early_deadline')
                            ->label('1st Early Bird Deadline')
                            ->helperText('After this date, the 2nd early price (or full price) applies.')
                            ->nullable(),

                        Forms\Components\TextInput::make('price_early_2')
                            ->label('2nd Early Bird Price')
                            ->numeric()
                            ->nullable()
                            ->minValue(0)
                            ->prefix('₱'),

                        Forms\Components\DatePicker::make('early_deadline_2')
                            ->label('2nd Early Bird Deadline')
                            ->helperText('After this date, the full price applies.')
                            ->nullable(),

                        Forms\Components\Textarea::make('early_bird_label')
                            ->label('Early Bird Label')
                            ->rows(2)
                            ->nullable()
                            ->columnSpanFull(),
                    ])->columns(2),
```

**Replace the `table()` columns array** with:

```php
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('categoryModel.name')
                    ->label('Category')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('price_full')
                    ->label('Full')
                    ->money('PHP')
                    ->sortable()
                    ->alignment(Alignment::End),

                Tables\Columns\TextColumn::make('price_early')
                    ->label('1st Early')
                    ->money('PHP')
                    ->sortable()
                    ->alignment(Alignment::End)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('price_early_2')
                    ->label('2nd Early')
                    ->money('PHP')
                    ->sortable()
                    ->alignment(Alignment::End)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
            ])
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint app/Filament/Resources/PackageResource.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/PackageResource.php
git commit -m "feat(catalog): add 2nd early-bird pricing fields to PackageResource form and table"
```

---

### Task 6: Migration — second early pricing snapshot on enrollments

**Files:**
- Create: `database/migrations/2026_05_22_000002_add_second_early_pricing_snapshot_to_enrollments.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration add_second_early_pricing_snapshot_to_enrollments --no-interaction
```

- [ ] **Step 2: Write migration content**

Open the new migration file and replace its contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unsignedInteger('tuition_price_early_2')->nullable()->after('tuition_price_early');
            $table->date('tuition_early_deadline_2')->nullable()->after('tuition_early_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['tuition_price_early_2', 'tuition_early_deadline_2']);
        });
    }
};
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate --no-interaction
```

Expected: `Migrating: 2026_05_22_000002_add_second_early_pricing_snapshot_to_enrollments`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_22_000002_add_second_early_pricing_snapshot_to_enrollments.php
git commit -m "feat(enrollment): add tuition_price_early_2 and tuition_early_deadline_2 snapshot columns"
```

---

### Task 7: Update Enrollment model

**Files:**
- Modify: `app/Models/Enrollment.php`

- [ ] **Step 1: Add new fields to Enrollment**

In `app/Models/Enrollment.php`, add the two new fields to `$fillable` (after `tuition_price_early`) and to `$casts`:

In `$fillable`, after `'tuition_price_early',` add:
```php
        'tuition_price_early_2',
        'tuition_early_deadline_2',
```

In `$casts`, after `'tuition_early_deadline' => 'date',` add:
```php
        'tuition_early_deadline_2' => 'date',
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint app/Models/Enrollment.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Models/Enrollment.php
git commit -m "feat(enrollment): add tuition_price_early_2 and tuition_early_deadline_2 to Enrollment model"
```

---

### Task 8: Update EnrollmentPricingService — 3-tier balance logic

**Files:**
- Modify: `app/Services/EnrollmentPricingService.php`
- Modify: `tests/Unit/EnrollmentPricingServiceTest.php`

- [ ] **Step 1: Write failing tests**

Open `tests/Unit/EnrollmentPricingServiceTest.php` and add these test methods after the existing three tests:

```php
    public function test_balance_uses_second_early_price_when_past_first_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-01', 'Asia/Manila')->startOfDay());

        $enrollment = new Enrollment([
            'payment_type' => 'downpayment',
            'tuition_list_amount' => 10_000,
            'tuition_price_early' => 8_000,
            'tuition_price_early_2' => 9_000,
            'amount_paid_tuition' => 5_000,
        ]);
        $enrollment->tuition_early_deadline = Carbon::parse('2026-07-15', 'Asia/Manila');
        $enrollment->tuition_early_deadline_2 = Carbon::parse('2026-08-15', 'Asia/Manila');

        $this->assertSame(4_000, EnrollmentPricingService::balanceTuitionDue($enrollment));
    }

    public function test_balance_uses_list_price_when_both_deadlines_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01', 'Asia/Manila')->startOfDay());

        $enrollment = new Enrollment([
            'payment_type' => 'downpayment',
            'tuition_list_amount' => 10_000,
            'tuition_price_early' => 8_000,
            'tuition_price_early_2' => 9_000,
            'amount_paid_tuition' => 5_000,
        ]);
        $enrollment->tuition_early_deadline = Carbon::parse('2026-07-15', 'Asia/Manila');
        $enrollment->tuition_early_deadline_2 = Carbon::parse('2026-08-15', 'Asia/Manila');

        $this->assertSame(5_000, EnrollmentPricingService::balanceTuitionDue($enrollment));
    }

    public function test_balance_uses_second_early_price_when_only_second_tier_snapshot_present(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01', 'Asia/Manila')->startOfDay());

        $enrollment = new Enrollment([
            'payment_type' => 'downpayment',
            'tuition_list_amount' => 10_000,
            'tuition_price_early_2' => 9_000,
            'amount_paid_tuition' => 5_000,
        ]);
        $enrollment->tuition_early_deadline_2 = Carbon::parse('2026-08-15', 'Asia/Manila');

        $this->assertSame(4_000, EnrollmentPricingService::balanceTuitionDue($enrollment));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact tests/Unit/EnrollmentPricingServiceTest.php
```

Expected: The 3 new tests FAIL (old 3 still pass).

- [ ] **Step 3: Update EnrollmentPricingService**

Replace the `applicableTuitionTotal()` method in `app/Services/EnrollmentPricingService.php`:

```php
    public static function applicableTuitionTotal(Enrollment $enrollment): int
    {
        if ($enrollment->payment_type === 'full') {
            return (int) $enrollment->amount_paid_tuition;
        }

        $early = $enrollment->tuition_price_early;
        $deadline = $enrollment->tuition_early_deadline;

        if ($early !== null && $deadline !== null && self::isEarlyBirdWindowOpen($deadline)) {
            return (int) $early;
        }

        $early2 = $enrollment->tuition_price_early_2;
        $deadline2 = $enrollment->tuition_early_deadline_2;

        if ($early2 !== null && $deadline2 !== null && self::isEarlyBirdWindowOpen($deadline2)) {
            return (int) $early2;
        }

        return (int) ($enrollment->tuition_list_amount ?? 0);
    }
```

Also update the PHPDoc block at the top of the class to document the new 3-tier rule:

```php
 * - Remaining balance after DP: `applicable_tuition_total - amount_paid_tuition`, where
 *   `applicable_tuition_total` is `tuition_price_early` if still within `tuition_early_deadline`,
 *   else `tuition_price_early_2` if still within `tuition_early_deadline_2`,
 *   otherwise `tuition_list_amount` (`price_full` snapshot).
```

- [ ] **Step 4: Run all pricing service tests**

```bash
php artisan test --compact tests/Unit/EnrollmentPricingServiceTest.php
```

Expected: All 6 tests PASS.

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint app/Services/EnrollmentPricingService.php --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Services/EnrollmentPricingService.php tests/Unit/EnrollmentPricingServiceTest.php
git commit -m "feat(enrollment): update EnrollmentPricingService for 3-tier early-bird balance logic"
```

---

### Task 9: Update EnrollmentService — snapshot 2nd early tier

**Files:**
- Modify: `app/Services/EnrollmentService.php`

- [ ] **Step 1: Update `createEnrollment` to snapshot 2nd tier fields**

In `app/Services/EnrollmentService.php`, locate the `createEnrollment` method.

Find the block where early bird discount is calculated (around line 64–76):

```php
        $list = (int) $purchasable->price_full;
        $early = $purchasable->price_early !== null ? (int) $purchasable->price_early : null;
        $deadline = $purchasable->early_deadline;
        $dpSnapshot = (int) $purchasable->downpayment_amount;

        $discountAmount = 0;
        $discountLabel = null;
        if ($early !== null && $deadline !== null && $purchasable->isEarlyBirdActive()) {
            $discountAmount = max(0, $list - $early);
            $discountLabel = 'Early bird';
        }
```

Replace it with:

```php
        $list = (int) $purchasable->price_full;
        $early = $purchasable->price_early !== null ? (int) $purchasable->price_early : null;
        $deadline = $purchasable->early_deadline;
        $early2 = $purchasable->price_early_2 !== null ? (int) $purchasable->price_early_2 : null;
        $deadline2 = $purchasable->early_deadline_2;
        $dpSnapshot = (int) $purchasable->downpayment_amount;

        $activePrice = (int) $purchasable->active_price;
        $discountAmount = max(0, $list - $activePrice);
        $discountLabel = null;
        if ($discountAmount > 0) {
            $discountLabel = $purchasable->isFirstEarlyBirdActive() ? 'Early bird' : 'Early bird (2nd)';
        }
```

Also find the `Enrollment::create([...])` call and add the two new snapshot fields after `'tuition_early_deadline' => $deadline,`:

```php
            'tuition_price_early_2' => $early2,
            'tuition_early_deadline_2' => $deadline2,
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint app/Services/EnrollmentService.php --format agent
```

- [ ] **Step 3: Run all existing enrollment-related tests to ensure nothing broke**

```bash
php artisan test --compact tests/Feature/EnrollmentFinancialLedgerTest.php tests/Feature/EnrollmentScheduleValidationTest.php tests/Feature/EnrollmentPackageEnrollmentItemsTest.php tests/Unit/EnrollmentPricingServiceTest.php
```

Expected: All tests PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Services/EnrollmentService.php
git commit -m "feat(enrollment): snapshot price_early_2 and early_deadline_2 when creating enrollment"
```

---

### Task 10: Full test suite verification

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test --compact
```

Expected: All tests PASS. If any fail, read the error and fix before proceeding.

- [ ] **Step 2: Run Pint over all modified files**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Final commit (if any Pint fixes)**

```bash
git add -p
git commit -m "style: apply pint formatting to program management module"
```

---

## Self-Review Checklist

- [x] **Migration**: `price_early_2` + `early_deadline_2` added to `programs` and `packages` — Task 1
- [x] **Migration**: `tuition_price_early_2` + `tuition_early_deadline_2` added to `enrollments` — Task 6
- [x] **Program model**: 3-tier pricing methods (`isFirstEarlyBirdActive`, `isSecondEarlyBirdActive`, `isEarlyBirdActive`, `getActivePriceAttribute`) — Task 2
- [x] **Package model**: Same 3-tier pricing methods — Task 3
- [x] **Enrollment model**: New snapshot fields in `$fillable` and `$casts` — Task 7
- [x] **ProgramResource**: Form has `price_early_2` + `early_deadline_2` fields; table shows toggleable early price columns — Task 4
- [x] **PackageResource**: Same form/table updates + category column added — Task 5
- [x] **EnrollmentService**: Snapshots all 3 tiers; discount label differentiates tier 1 vs tier 2 — Task 9
- [x] **EnrollmentPricingService**: `applicableTuitionTotal` checks tier 1 → tier 2 → full — Task 8
- [x] **Tests**: ProgramPricingTest covers 7 scenarios; PackagePricingTest covers 5; EnrollmentPricingServiceTest has 6 including 3 new ones — Tasks 2, 3, 8
- [x] **Backward compatibility**: `isEarlyBirdActive()` still returns `true` when either tier is active — Tasks 2 & 3
