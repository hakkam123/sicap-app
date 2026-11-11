<?php

namespace App\Services;

use App\Models\SystemErrorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SystemErrorLogService
{
    /** Batas ukuran agar tabel tidak membengkak. */
    private const MAX_MESSAGE = 300;
    private const MAX_TRACE_FRAMES = 5;
    private const MAX_PAYLOAD = 2000;
    private const DEDUP_HOURS = 24;

    /**
     * Capture and store an exception as a system error log.
     * - Hanya error server (5xx); 4xx adalah perilaku user biasa, bukan error aplikasi.
     * - Error yang sama (class + file + line) yang masih unresolved dalam 24 jam
     *   tidak dibuat row baru, hanya updated_at yang diperbarui.
     */
    public static function captureException(\Throwable $e, ?Request $request = null, array $additionalContext = []): ?SystemErrorLog
    {
        try {
            $statusCode = self::determineStatusCode($e);
            if ($statusCode < 500) {
                return null;
            }

            $file = self::relativePath($e->getFile());

            $existing = SystemErrorLog::query()
                ->where('status', 'unresolved')
                ->where('exception_class', get_class($e))
                ->where('file', $file)
                ->where('line', $e->getLine())
                ->where('created_at', '>=', now()->subHours(self::DEDUP_HOURS))
                ->first();

            if ($existing) {
                $existing->touch();
                return $existing;
            }

            $req = $request ?: (app()->bound('request') && !app()->runningInConsole() ? request() : null);

            $payload = null;
            if ($req) {
                $data = array_filter([
                    'query' => $req->query(),
                    'body' => $req->except(['password', 'password_confirmation', '_token', 'token', 'secret', 'authorization', 'file']),
                    'context' => $additionalContext,
                ]);
                $payload = $data ? Str::limit(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), self::MAX_PAYLOAD) : null;
            }

            return SystemErrorLog::create([
                'error_type' => self::determineErrorType($statusCode, $e),
                'status_code' => $statusCode,
                'severity' => self::determineSeverity($statusCode, $e),
                'feature' => self::determineFeature($req, $additionalContext),
                'message' => self::shortMessage($e),
                'exception_class' => get_class($e),
                'file' => $file,
                'line' => $e->getLine(),
                'url' => $req ? Str::limit($req->fullUrl(), 500) : null,
                'method' => $req ? $req->method() : (app()->runningInConsole() ? 'CLI' : null),
                'request_payload' => $payload,
                'stack_trace' => self::shortTrace($e),
                'user_id' => Auth::id(),
                'user_ip' => $req?->ip(),
                'user_agent' => $req ? Str::limit((string) $req->userAgent(), 255) : null,
                'status' => 'unresolved',
            ]);
        } catch (\Throwable $loggingException) {
            Log::error('Failed to store SystemErrorLog: ' . Str::limit($loggingException->getMessage(), 200));
            return null;
        }
    }

    /**
     * Pesan error ringkas: buang prefix driver ODBC & bagian "(Connection: ..., SQL: ...)".
     */
    public static function shortMessage(\Throwable $e): string
    {
        $msg = $e->getMessage() ?: (class_basename($e) . ' occurred');
        $msg = preg_replace('/(\[Microsoft\]|\[ODBC Driver [^\]]*\]|\[SQL Server\])/', '', $msg);
        $msg = preg_replace('/\s*\(Connection: .*$/s', '', $msg);
        $msg = preg_split('/\R/', trim($msg))[0];

        return Str::limit(trim(preg_replace('/\s+/', ' ', $msg)), self::MAX_MESSAGE);
    }

    /**
     * Stack trace ringkas: hanya beberapa frame teratas, path relatif.
     */
    public static function shortTrace(\Throwable $e): string
    {
        $lines = [];
        foreach (array_slice($e->getTrace(), 0, self::MAX_TRACE_FRAMES) as $i => $f) {
            $lines[] = sprintf(
                '#%d %s(%s): %s%s%s()',
                $i,
                self::relativePath($f['file'] ?? '?'),
                $f['line'] ?? '?',
                $f['class'] ?? '',
                $f['type'] ?? '',
                $f['function'] ?? ''
            );
        }

        return implode("\n", $lines);
    }

    public static function relativePath(string $path): string
    {
        return ltrim(str_replace([base_path(), '\\'], ['', '/'], $path), '/');
    }

    /**
     * Determine HTTP status code from exception.
     */
    protected static function determineStatusCode(\Throwable $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        if (method_exists($e, 'getStatusCode')) {
            return (int) $e->getStatusCode();
        }

        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return 422;
        }

        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return 401;
        }

        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return 403;
        }

        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException || 
            $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return 404;
        }

        return 500;
    }

    /**
     * Determine severity level based on status code and exception type.
     */
    protected static function determineSeverity(int $statusCode, \Throwable $e): string
    {
        if ($statusCode >= 500 || $e instanceof \PDOException || $e instanceof \Illuminate\Database\QueryException) {
            return 'critical';
        }

        if ($statusCode === 404 || $statusCode === 403 || $statusCode === 401) {
            return 'warning';
        }

        if ($statusCode === 422) {
            return 'info';
        }

        return 'error';
    }

    /**
     * Determine application feature/module from URL or context.
     */
    protected static function determineFeature(?Request $request, array $context = []): string
    {
        if (!empty($context['feature'])) {
            return (string) $context['feature'];
        }

        if (!$request) {
            return 'system';
        }

        $path = strtolower($request->path());

        if (str_contains($path, 'consume')) return 'consume';
        if (str_contains($path, 'part-number')) return 'part_number';
        if (str_contains($path, 'area')) return 'area';
        if (str_contains($path, 'machine')) return 'machine';
        if (str_contains($path, 'mapping')) return 'mapping';
        if (str_contains($path, 'user')) return 'user';
        if (str_contains($path, 'report')) return 'report';
        if (str_contains($path, 'sync-api') || str_contains($path, 'api/')) return 'sync_api';
        if (str_contains($path, 'login') || str_contains($path, 'logout') || str_contains($path, 'password')) return 'auth';

        return 'system';
    }

    /**
     * Human-friendly error type label.
     */
    protected static function determineErrorType(int $statusCode, \Throwable $e): string
    {
        if ($e instanceof \Illuminate\Database\QueryException || $e instanceof \PDOException) {
            return 'Database Query Exception';
        }

        if ($e instanceof \TypeError) {
            return 'PHP TypeError';
        }

        return match ($statusCode) {
            500 => 'HTTP 500 Internal Server Error',
            404 => 'HTTP 404 Not Found',
            403 => 'HTTP 403 Forbidden',
            401 => 'HTTP 401 Unauthorized',
            422 => 'HTTP 422 Unprocessable Content',
            429 => 'HTTP 429 Too Many Requests',
            default => "HTTP {$statusCode} Error",
        };
    }
}

