<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * SRS FR-170 - records create, update and delete activity on a model into the
 * audit trail, capturing the user, the action, the timestamp and the values
 * before and after the change.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->writeAuditLog($model->auditAction('created'), [], $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changed = $model->auditableAttributes($model->getChanges());

            if ($changed === []) {
                return;
            }

            $before = [];
            foreach (array_keys($changed) as $key) {
                $before[$key] = $model->getOriginal($key);
            }

            $model->writeAuditLog($model->auditAction('updated'), $before, $changed);
        });

        static::deleted(function (Model $model) {
            $model->writeAuditLog($model->auditAction('deleted'), $model->auditableAttributes($model->getOriginal()), []);
        });
    }

    /** Names a lifecycle event after the model it happened to, e.g. "created.allocation". */
    public function auditAction(string $event): string
    {
        return $event.'.'.Str::snake(class_basename($this));
    }

    /**
     * Strips attributes that must never reach the audit trail, such as password hashes.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function auditableAttributes(array $attributes): array
    {
        $excluded = array_merge(
            ['created_at', 'updated_at', 'remember_token'],
            $this->auditExclude ?? [],
        );

        return array_diff_key($attributes, array_flip($excluded));
    }

    /**
     * Writes one entry to the audit trail. The action is stored exactly as
     * given, so callers recording a domain event (for instance "card.reissued")
     * name it themselves; lifecycle events come pre-named by auditAction().
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function writeAuditLog(string $action, array $old, array $new, ?string $description = null): AuditLog
    {
        $user = Auth::user();
        $request = request();

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 250, ''),
        ]);
    }
}
