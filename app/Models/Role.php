<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** SRS FR-010 - configurable roles. */
#[Fillable(['name', 'slug', 'description', 'is_system'])]
class Role extends Model
{
    public const SUPER_ADMIN = 'super-administrator';

    public const MALL_ADMIN = 'mall-administrator';

    public const ALLOCATION_OFFICER = 'allocation-officer';

    public const FINANCE_OFFICER = 'finance-officer';

    public const SUPPORT_OFFICER = 'customer-support-officer';

    public const AUDITOR = 'auditor';

    public const SHOP_OWNER = 'shop-owner';

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->slug === self::SUPER_ADMIN) {
            return true;
        }

        return $this->permissions->contains('slug', $slug);
    }
}
