<?php

use App\Http\Middleware\LimitRequestBody;
use App\Http\Middleware\RequireCurrentConsent;
use App\Http\Middleware\RequireStaffRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->trustHosts(at: function (): array {
            if (app()->environment('local')) {
                // Physical devices reach the local API through the computer's LAN IP.
                return ['.*'];
            }

            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

            return ['^'.preg_quote($host, '/').'$'];
        });
        $middleware->append(LimitRequestBody::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'consent.current' => RequireCurrentConsent::class,
            'staff' => RequireStaffRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (
                $request->expectsJson()
                && app()->environment('production')
                && ! $e instanceof HttpExceptionInterface
                && ! $e instanceof ValidationException
                && ! $e instanceof AuthenticationException
            ) {
                report($e);

                return response()->json(['message' => 'The request could not be completed.'], 500);
            }
        });
    })->create();
