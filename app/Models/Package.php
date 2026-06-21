<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsCatalogSortOrder;
use App\Models\Concerns\HasEarlyBirdPricing;
use App\Models\Concerns\SyncsCatalogCategoryString;
use App\Support\Filament\CatalogOptionsCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Package extends Model
{
    use AssignsCatalogSortOrder;
    use HasEarlyBirdPricing;
    use HasFactory;
    use SyncsCatalogCategoryString;

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

    protected static function booted(): void
    {
        static::saved(fn () => CatalogOptionsCache::forgetAll());
        static::deleted(fn () => CatalogOptionsCache::forgetAll());
    }

    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function getCategoryLabelAttribute(): string
    {
        return $this->categoryModel?->name ?: (string) $this->category;
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'package_program', 'package_id', 'program_id')
            ->withPivot(['sort_order'])
            ->orderBy('package_program.sort_order')
            ->orderBy('programs.name');
    }

    /**
     * @param  list<int|string>  $programIds
     */
    public function syncProgramsSortOrder(array $programIds): void
    {
        $sync = [];

        foreach (array_values($programIds) as $index => $programId) {
            $sync[(int) $programId] = ['sort_order' => ($index + 1) * 10];
        }

        $this->programs()->sync($sync);
    }
}
