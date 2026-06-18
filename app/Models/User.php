<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Support\PermissionCodes;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /**
     * In-request cache of permission codes for assistants (avoids hundreds of
     * exists() queries on Filament tables — admins skip DB entirely in hasPermission).
     *
     * @var list<string>|null
     */
    private ?array $assistantPermissionCodesCache = null;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Determines if the user may access the Filament admin panel.
     *
     * Only users with an explicit admin or assistant role may enter.
     *
     * @author CKD
     *
     * @created 2026-04-24
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Use tryFrom against the raw DB string so an unrecognised role value
        // never throws a ValueError — it simply returns null (access denied).
        $role = UserRole::tryFrom($this->getRawOriginal('role') ?? '');

        return $role === UserRole::Admin || $role === UserRole::Assistant;
    }

    /**
     * Returns true when this user is the primary administrator.
     *
     * @author CKD
     *
     * @created 2026-04-24
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Returns true when this user holds the assistant role.
     *
     * @author CKD
     *
     * @created 2026-04-24
     */
    public function isAssistant(): bool
    {
        return $this->role === UserRole::Assistant && ! $this->isAdmin();
    }

    /**
     * Permissions explicitly granted to this user (assistants). Admins implicitly have all access.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    /**
     * Whether this user holds a given permission code. Admins always return true.
     *
     * @author CKD
     *
     * @created 2026-04-25
     */
    public function hasPermission(string $code): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isAssistant()) {
            return false;
        }

        if ($this->assistantPermissionCodesCache === null) {
            $this->assistantPermissionCodesCache = $this->permissions()->pluck('code')->all();
        }

        return in_array($code, $this->assistantPermissionCodesCache, true);
    }

    /**
     * Replace assigned permissions using human-readable codes.
     *
     * @param  list<string>  $codes
     *
     * @author CKD
     *
     * @created 2026-04-25
     */
    public function syncPermissionsByCode(array $codes): void
    {
        $codes = array_values(array_unique(array_filter($codes)));

        if ($codes === []) {
            $this->permissions()->sync([]);
            $this->flushAssistantPermissionCodesCache();

            return;
        }

        $definitions = PermissionCodes::definitions();
        $ids = [];

        foreach ($codes as $code) {
            if (! is_string($code) || $code === '') {
                continue;
            }

            $label = $definitions[$code] ?? $code;

            $ids[] = Permission::query()->firstOrCreate(
                ['code' => $code],
                ['label' => $label],
            )->id;
        }

        $this->permissions()->sync($ids);
        $this->flushAssistantPermissionCodesCache();
    }

    private function flushAssistantPermissionCodesCache(): void
    {
        $this->assistantPermissionCodesCache = null;
    }
}
