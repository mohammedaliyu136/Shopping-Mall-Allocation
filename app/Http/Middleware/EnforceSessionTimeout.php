<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SRS FR-003 - inactive sessions are terminated after a configurable period.
 */
class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        $timeoutMinutes = (int) SystemSetting::get('session.timeout_minutes', 30);
        $lastActivity = $user->last_activity_at;

        if ($timeoutMinutes > 0 && $lastActivity && $lastActivity->lt(Carbon::now()->subMinutes($timeoutMinutes))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your session expired after a period of inactivity. Please sign in again.']);
        }

        $user->forceFill(['last_activity_at' => Carbon::now()])->saveQuietly();

        return $next($request);
    }
}
