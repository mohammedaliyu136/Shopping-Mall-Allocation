<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** SRS FR-001 - user login. */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // FR-170 - failed sign-in attempts are recorded.
            AuditLog::create([
                'action' => 'auth.login_failed',
                'description' => 'Failed sign-in for '.$credentials['email'],
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 250),
            ]);

            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated.',
            ]);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_activity_at' => Carbon::now()])->saveQuietly();

        AuditLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => 'auth.login',
            'description' => 'Signed in',
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 250),
        ]);

        return redirect()->intended($user->isShopOwner() ? route('portal.dashboard') : route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => 'auth.logout',
            'description' => 'Signed out',
            'ip_address' => $request->ip(),
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
