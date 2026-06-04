<?php

namespace App\Models;

use App\Models\Concerns\AssignsCatalogSortOrder;
use App\Support\Filament\CatalogOptionsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use AssignsCatalogSortOrder;

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => CatalogOptionsCache::forgetAll());
        static::deleted(fn () => CatalogOptionsCache::forgetAll());
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }
}
