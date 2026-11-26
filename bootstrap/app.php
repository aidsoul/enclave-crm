<?php

use App\Http\Middleware\OrderAccess;
use App\Http\Middleware\OrderEdit;
use App\Http\Middleware\OrderStatusChange;
use App\Http\Middleware\PreventSearchIndexing;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SettingsEdit;
use App\Http\Middleware\SettingsView;
use App\Http\Middleware\ValidateTelegramWebhookSecret;
use App\Http\Middleware\ValidateTmaUser;
use App\Services\AntiFraud\FingerprintResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'))))
        );

        $middleware->append(PreventSearchIndexing::class);

        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        $middleware->alias([
            'tma.auth' => ValidateTmaUser::class,
            'role' => RequireRole::class,
            'order.access' => OrderAccess::class,
            'order.edit' => OrderEdit::class,
            'order.status.change' => OrderStatusChange::class,
            'settings.view' => SettingsView::class,
            'settings.edit' => SettingsEdit::class,
            'telegram.webhook.secret' => ValidateTelegramWebhookSecret::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->booted(function () {
        RateLimiter::for('tma-auth', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('invitation-accept', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('incoming-orders', function (Request $request) {
            $resolver = app(FingerprintResolver::class);
            $keys = $resolver->resolveRateLimitKeys($request);

            $limits = [
                Limit::perMinute(60)->by($keys['siteKey'])->response(fn () => response()->json([
                    'message' => 'Too Many Requests for this site token.',
                ], 429)),
                Limit::perMinute(60)->by('order_phys_'.$keys['physicalIpKey'])->response(fn () => response()->json([
                    'message' => 'Too Many Requests from this IP address.',
                ], 429)),
            ];

            if ($keys['hasExplicitClientIp'] && $keys['clientIpKey']) {
                $limits[] = Limit::perMinute(15)->by('order_'.$keys['clientIpKey'])->response(fn () => response()->json([
                    'message' => 'Too Many Requests from this IP address.',
                ], 429));
            }

            if ($keys['hasExplicitFingerprint']) {
                $limits[] = Limit::perMinute(10)->by('order_'.$keys['fingerprintKey'])->response(fn () => response()->json([
                    'message' => 'Too Many Requests from this device fingerprint.',
                ], 429));
            }

            return $limits;
        });

        RateLimiter::for('incoming-leads', function (Request $request) {
            $resolver = app(FingerprintResolver::class);
            $keys = $resolver->resolveRateLimitKeys($request);

            $ipKey = $keys['clientIpKey'] ?? $keys['physicalIpKey'];

            $limits = [
                Limit::perMinute(60)->by($keys['siteKey'])->response(fn () => response()->json([
                    'message' => 'Too Many Requests for this site token.',
                ], 429)),
                Limit::perMinute(60)->by('lead_phys_'.$keys['physicalIpKey'])->response(fn () => response()->json([
                    'message' => 'Too Many Requests from this IP address.',
                ], 429)),
                Limit::perMinute(15)->by('lead_'.$ipKey)->response(fn () => response()->json([
                    'message' => 'Too Many Requests from this IP address.',
                ], 429)),
                Limit::perMinute(10)->by('lead_'.$keys['fingerprintKey'])->response(fn () => response()->json([
                    'message' => 'Too Many Requests from this device fingerprint.',
                ], 429)),
            ];

            if ($keys['contactKey']) {
                $limits[] = Limit::perMinute(5)->by('lead_'.$keys['contactKey'])->response(fn () => response()->json([
                    'message' => 'Too Many Requests for this contact.',
                ], 429));
            }

            return $limits;
        });

        RateLimiter::for('catalog-sync', function (Request $request) {
            $token = $request->header('X-API-Token');
            $physicalIp = $request->ip() ?: '127.0.0.1';
            $siteKey = $token ? 'token:'.$token : 'ip:'.$physicalIp;

            return [
                Limit::perMinute(60)->by($siteKey)->response(fn () => response()->json([
                    'message' => 'Too Many Requests for this site token.',
                ], 429)),
                Limit::perMinute(60)->by('catalog_ip:'.$physicalIp)->response(fn () => response()->json([
                    'message' => 'Too Many Requests from this IP address.',
                ], 429)),
            ];
        });

        RateLimiter::for('emergency-wipe', function (Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return Limit::perMinute(10)->by($key);
        });

        RateLimiter::for('bulk-orders', function (Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return Limit::perMinute(60)->by($key);
        });

        RateLimiter::for('telegram-webhook', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('financial-export', function (Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return Limit::perMinute(10)->by($key);
        });

        RateLimiter::for('desktop-link', function (Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return Limit::perMinute(10)->by($key);
        });
    })
    ->create();
