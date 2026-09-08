<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // 1. Rate limiter khusus sinkronisasi batch consume (60 request/menit per user/IP)
        RateLimiter::for('api-consume-sync', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Terlalu banyak permintaan sinkronisasi data. Silakan coba lagi nanti.',
                        'retry_after_seconds' => (int) ($headers['Retry-After'] ?? 60),
                    ], 429, $headers);
                });
        });

        // 2. Rate limiter untuk endpoint generate token API (5 request/menit per IP)
        RateLimiter::for('api-token-create', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}