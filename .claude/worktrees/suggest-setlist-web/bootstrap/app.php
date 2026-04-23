<?php

declare(strict_types=1);

use App\Exceptions\RepertoireLimitExceededException;
use App\Http\Middleware\EnsureBillingSetupComplete;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleIdempotency;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust proxies so X-Forwarded-Proto/For/Host headers are respected.
        // TRUSTED_PROXIES env var controls which IPs are trusted:
        //   - "*"         → trust all (safe for local dev behind Docker Desktop)
        //   - Comma-separated IPs/CIDRs for production (e.g. "10.0.0.0/8,172.16.0.0/12")
        // Default: known private ranges (Valet, Docker, DO load balancers, K8s pods)
        $trustedProxies = env('TRUSTED_PROXIES', '127.0.0.1,10.0.0.0/8,172.16.0.0/12,10.244.0.0/16');
        $middleware->trustProxies(
            at: $trustedProxies === '*' ? '*' : explode(',', $trustedProxies),
        );

        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);

        $middleware->alias([
            'billing.setup' => EnsureBillingSetupComplete::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);

        $middleware->appendToGroup('api', [
            HandleIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (RepertoireLimitExceededException $exception, $request) {
            $payload = [
                'code' => 'repertoire_limit_reached',
                'message' => $exception->getMessage(),
                'project_id' => $exception->projectId,
                'repertoire_song_limit' => $exception->limit,
            ];

            if ($request->expectsJson()) {
                return response()->json($payload, 422);
            }

            return response($payload['message'], 422);
        });

        Integration::handles($exceptions);
    })->create();
