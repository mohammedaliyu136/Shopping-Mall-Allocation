<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** SRS 8 - configurable system settings (reference formats, billing dates, session timeout). */
#[Fillable(['key', 'value', 'type', 'group', 'description'])]
class SystemSetting extends Model
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'integer' => (int) $setting->value,
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $setting->value, true),
            default => $setting->value,
        };
    }

    public static function set(string $key, mixed $value, string $type = 'string', string $group = 'general'): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $type === 'json' ? json_encode($value) : (string) $value,
                'type' => $type,
                'group' => $group,
            ],
        );
    }
}
