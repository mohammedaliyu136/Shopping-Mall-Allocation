<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** SRS 3.1 - the administration portal is closed to shop-owner accounts. */
class EnsureStaffUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isShopOwner()) {
            return redirect()->route('portal.dashboard');
        }

        return $next($request);
    }
}
