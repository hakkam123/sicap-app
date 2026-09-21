<?php

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
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Memastikan request API selalu merender response JSON
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Capture unhandled exceptions into system_error_logs table and detailed exception-trace.log
        $exceptions->report(function (\Throwable $e) {
            try {
                $req = app()->bound('request') ? request() : null;
                $method = $req ? $req->method() : (app()->runningInConsole() ? 'CLI' : 'UNKNOWN');

                $logDir = storage_path('logs');
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0777, true);
                }

                $traceFrames = array_slice($e->getTrace(), 0, 10);
                $formattedTrace = [];
                foreach ($traceFrames as $i => $frame) {
                    $file = $frame['file'] ?? 'unknown_file';
                    $line = $frame['line'] ?? 0;
                    $class = $frame['class'] ?? '';
                    $type = $frame['type'] ?? '';
                    $func = $frame['function'] ?? '';
                    $formattedTrace[] = "  #{$i} {$file}({$line}): {$class}{$type}{$func}()";
                }

                $entry = sprintf(
                    "================================================================================\n" .
                    "TIMESTAMP   : %s\n" .
                    "METHOD / URL: %s %s\n" .
                    "PHP RUNTIME : Version: %s (ID: %s) | SAPI: %s | PID: %s\n" .
                    "BINARY / INI: %s | %s\n" .
                    "EXCEPTION   : %s\n" .
                    "MESSAGE     : %s\n" .
                    "LOCATION    : %s (Line %d)\n" .
                    "STACK TRACE (First 10 frames):\n%s\n\n",
                    date('Y-m-d H:i:s T'),
                    $method,
                    $req ? $req->fullUrl() : 'N/A',
                    PHP_VERSION,
                    PHP_VERSION_ID,
                    PHP_SAPI,
                    getmypid(),
                    defined('PHP_BINARY') ? PHP_BINARY : 'N/A',
                    php_ini_loaded_file() ?: 'None',
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    implode("\n", $formattedTrace)
                );

                @file_put_contents($logDir . '/exception-trace.log', $entry, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $loggingErr) {
                // Ignore tracing failures to not disrupt error flow
            }

            \App\Services\SystemErrorLogService::captureException($e);
        });

        // Global handler untuk exception 429 Too Many Requests
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $headers = $e->getHeaders();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Terlalu banyak permintaan ke API. Silakan jeda beberapa saat.',
                    'retry_after_seconds' => (int) ($headers['Retry-After'] ?? 60),
                ], 429, $headers);
            }
        });
    })->create();