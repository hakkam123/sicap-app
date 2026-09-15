<?php

namespace Tests\Unit\Services;

use App\Models\SystemErrorLog;
use App\Models\User;
use App\Services\SystemErrorLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class SystemErrorLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_exception_creates_database_record(): void
    {
        $exception = new \RuntimeException('Database connection lost unexpectedly');

        $log = SystemErrorLogService::captureException($exception);

        $this->assertNotNull($log);
        $this->assertInstanceOf(SystemErrorLog::class, $log);
        $this->assertEquals('Database connection lost unexpectedly', $log->message);
        $this->assertEquals(\RuntimeException::class, $log->exception_class);
        $this->assertEquals('unresolved', $log->status);
        $this->assertEquals(500, $log->status_code);
        $this->assertEquals('critical', $log->severity);
    }

    public function test_capture_exception_sanitizes_sensitive_request_payload(): void
    {
        $request = Request::create('/api/login', 'POST', [
            'email' => 'admin@visteon.com',
            'password' => 'super_secret_password',
            '_token' => 'csrf_token_value',
            'secret' => 'some_secret',
            'extra_info' => 'harmless_data',
        ]);

        $exception = new \Exception('Authentication failure');
        $log = SystemErrorLogService::captureException($exception, $request);

        $this->assertNotNull($log);
        $this->assertStringNotContainsString('super_secret_password', (string) $log->request_payload);
        $this->assertStringNotContainsString('csrf_token_value', (string) $log->request_payload);
        $this->assertStringContainsString('admin@visteon.com', (string) $log->request_payload);
        $this->assertStringContainsString('harmless_data', (string) $log->request_payload);
    }

    public function test_capture_exception_determines_correct_status_code_and_severity(): void
    {
        $notFoundException = new NotFoundHttpException('Resource not found');
        $log404 = SystemErrorLogService::captureException($notFoundException);

        $this->assertEquals(404, $log404->status_code);
        $this->assertEquals('warning', $log404->severity);

        $generalException = new \Exception('General internal error');
        $log500 = SystemErrorLogService::captureException($generalException);

        $this->assertEquals(500, $log500->status_code);
        $this->assertEquals('critical', $log500->severity);
    }

    public function test_capture_exception_associates_logged_in_user(): void
    {
        $user = User::factory()->create(['name' => 'John Operator']);
        Auth::login($user);

        $exception = new \Exception('Operation failed');
        $log = SystemErrorLogService::captureException($exception);

        $this->assertEquals($user->id, $log->user_id);
    }

    public function test_capture_exception_handles_additional_context(): void
    {
        $exception = new \Exception('Import failure');
        $log = SystemErrorLogService::captureException($exception, null, ['batch_id' => 'B-1234']);

        $this->assertNotNull($log);
        $this->assertDatabaseHas('system_error_logs', [
            'id' => $log->id,
            'message' => 'Import failure',
        ]);
    }
}

