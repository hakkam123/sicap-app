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
    /**
     * Capture and store an exception as a system error log.
     */
    public static function captureException(\Throwable $e, ?Request $request = null, array $additionalContext = []): ?SystemErrorLog
    {
        try {
            $req = $request ?: (app()->bound('request') ? request() : null);

            $statusCode = self::determineStatusCode($e);
            $severity = self::determineSeverity($statusCode, $e);
            $feature = self::determineFeature($req, $additionalContext);
            $errorType = self::determineErrorType($statusCode, $e);

            $payload = null;
            if ($req) {
                $allInputs = $req->except(['password', 'password_confirmation', '_token', 'token', 'secret', 'authorization']);
                $payload = json_encode([
                    'headers' => [
                        'user-agent' => $req->userAgent(),
                        'accept' => $req->header('Accept'),
                        'content-type' => $req->header('Content-Type'),
                    ],
                    'query' => $req->query(),
                    'body' => $allInputs,
                    'context' => $additionalContext,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }

            // Truncate stack trace to reasonable size (max 8000 chars)
            $stackTrace = Str::limit($e->getTraceAsString(), 8000);

            return SystemErrorLog::create([
                'error_type' => $errorType,
                'status_code' => $statusCode,
                'severity' => $severity,
                'feature' => $feature,
                'message' => Str::limit($e->getMessage() ?: (get_class($e) . ' occurred'), 1000),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $req ? $req->fullUrl() : null,
                'method' => $req ? $req->method() : (app()->runningInConsole() ? 'CLI' : null),
                'request_payload' => $payload,
                'stack_trace' => $stackTrace,
                'user_id' => Auth::id(),
                'user_ip' => $req ? $req->ip() : null,
                'user_agent' => $req ? $req->userAgent() : null,
                'status' => 'unresolved',
            ]);
        } catch (\Throwable $loggingException) {
            // Safety: Fallback to standard Laravel log if logging to DB fails
            Log::error("Failed to store SystemErrorLog in database: " . $loggingException->getMessage(), [
                'original_exception' => $e->getMessage(),
            ]);
            return null;
        }
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
