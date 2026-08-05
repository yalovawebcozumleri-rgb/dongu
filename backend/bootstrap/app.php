<?php

use App\Http\Middleware\EnsureAccountAllowed;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSupporter;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->alias(['admin' => EnsureAdmin::class, 'supporter' => EnsureSupporter::class, 'account.allowed' => EnsureAccountAllowed::class]);
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : ($request->is('destekci/*') ? '/destekci/giris' : '/admin/login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $error) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ThrottleRequestsException $error, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $headers = $error->getHeaders();
            $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));
            $wait = $seconds >= 60
                ? (int) ceil($seconds / 60).' dakika'
                : $seconds.' saniye';
            $message = $request->is('api/v1/auth/code/*')
                ? "Kısa sürede çok fazla kod istedin. Güvenliğin ve e-posta kutunun korunması için {$wait} sonra tekrar deneyebilirsin."
                : "Kısa sürede çok fazla işlem yaptın. {$wait} sonra tekrar deneyebilirsin.";

            return response()->json([
                'message' => $message,
                'retry_after' => $seconds,
            ], 429, $headers);
        });
    })->create();
