<?php

namespace App\Models;

use App\Support\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** SRS 5 - system users, each holding exactly one role (FR-011). */
#[Fillable(['name', 'email', 'password', 'role_id', 'owner_id', 'phone', 'is_active', 'last_activity_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory;

    /** Attribute changes never worth writing to the audit trail. */
    protected array $auditExclude = ['password', 'remember_token', 'last_activity_at'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Set for shop-owner portal accounts (SRS 3.2). */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    /** FR-011 - every authorisation check funnels through here. */
    public function hasPermission(string $permission): bool
    {
        return (bool) $this->role?->hasPermission($permission);
    }

    public function hasRole(string ...$slugs): bool
    {
        return in_array($this->role?->slug, $slugs, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    public function isShopOwner(): bool
    {
        return $this->hasRole(Role::SHOP_OWNER);
    }
}
