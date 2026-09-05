<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** SRS 8 - configurable system settings. */
class SettingController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'grouped' => SystemSetting::query()->orderBy('key')->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach ($validated['settings'] as $key => $value) {
            $setting = SystemSetting::query()->where('key', $key)->first();

            if (! $setting) {
                continue;
            }

            if ($setting->type === 'integer' && ! is_numeric($value)) {
                return back()->withErrors(["settings.{$key}" => "\"{$setting->key}\" must be a number."]);
            }

            if ($setting->type === 'json' && json_decode((string) $value) === null) {
                return back()->withErrors(["settings.{$key}" => "\"{$setting->key}\" must be valid JSON."]);
            }

            $setting->update(['value' => $value]);
        }

        return redirect()->route('admin.settings.index')->with('status', 'Settings saved.');
    }
}
