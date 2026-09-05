<?php

use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureShopOwner;
use App\Http\Middleware\EnsureStaffUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => EnsurePermission::class,      // FR-011
            'staff' => EnsureStaffUser::class,            // SRS 3.1
            'shop-owner' => EnsureShopOwner::class,       // SRS 3.2
        ]);

        // FR-003 session timeout and account suspension apply to every web request.
        $middleware->web(append: [
            EnsureAccountIsActive::class,
            EnforceSessionTimeout::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
