<?php

use App\Http\Middleware\EnsureLicenseAllowsChanges;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetWorkspace;
use App\Services\Audit\SystemMonitor;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
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
        $middleware->append(SecurityHeaders::class);
        // Outside local development only the APP_URL domain is answered.
        $middleware->trustHosts();
        $middleware->web(append: [SetLocale::class]);
        $middleware->alias(['active' => EnsureUserIsActive::class, 'license' => EnsureLicenseAllowsChanges::class, 'workspace' => SetWorkspace::class]);
        // The workspace is set before route bindings, so that a record of another organisation is not found.
        $middleware->appendToPriorityList(AuthenticatesSessions::class, SetWorkspace::class);
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Errors are counted for the "System status" page and alerts.
        $exceptions->report(fn (Throwable $e) => SystemMonitor::recordError($e));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
