<?php

use App\Http\Middleware\AddRequestId;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnforceTrustedHosts;
use App\Http\Middleware\EnsureAccountIsNotSuspended;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(
            at: function (): array {
                if (! app()->isProduction()) {
                    return [
                        '^localhost$',
                        '^127\.0\.0\.1$',
                        '^lutweb\.test$',
                        '^.+\.lutweb\.test$',
                    ];
                }

                $hosts = config('security.trusted_hosts', []);

                if (! is_array($hosts)) {
                    return [];
                }

                return collect($hosts)
                    ->filter(fn (mixed $host): bool => is_string($host) && $host !== '')
                    ->map(fn (string $host): string => '^'.preg_quote($host, '#').'$')
                    ->values()
                    ->all();
            },
            subdomains: false,
        );

        $middleware->prepend([
            EnforceTrustedHosts::class,
            AddRequestId::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            AddSecurityHeaders::class,
        ]);

        $middleware->alias([
            'account.active' => EnsureAccountIsNotSuspended::class,
            'not_suspended' => EnsureAccountIsNotSuspended::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/paypal',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (SymfonyResponse $response): SymfonyResponse {
            $request = request();
            $status = $response->getStatusCode();

            if (! in_array($status, [403, 404, 419, 429, 500, 503], true)
                || $request->is('api/*')
                || $request->expectsJson()) {
                return $response;
            }

            return Inertia::render('Errors/Error', [
                'status' => $status,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
