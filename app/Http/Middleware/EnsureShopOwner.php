<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** SRS 3.2 - the owner portal serves shop-owner accounts linked to an owner record. */
class EnsureShopOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->isShopOwner() || ! $user->owner_id) {
            abort(403, 'This area is reserved for registered shop owners.');
        }

        return $next($request);
    }
}
