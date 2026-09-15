<?php

namespace Tests\Feature;

use App\Models\ImportLog;
use App\Models\SystemErrorLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_view_error_monitoring_dashboard(): void
    {
        SystemErrorLog::factory()->unresolved()->create(['status_code' => 500, 'severity' => 'critical']);
        SystemErrorLog::factory()->unresolved()->create(['status_code' => 404, 'severity' => 'warning']);

        $response = $this->actingAs($this->admin)->get(route('error-monitoring.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ErrorMonitoring/Index')
            ->has('logs.data', 2)
            ->has('stats')
            ->where('stats.total_errors', 2)
            ->where('stats.critical_errors', 1)
            ->where('stats.not_found_errors', 1)
            ->where('stats.unresolved_errors', 2)
        );
    }

    public function test_admin_can_view_single_error_log_detail_as_json(): void
    {
        $log = SystemErrorLog::factory()->create([
            'message' => 'Specific fatal error in queue worker',
            'status_code' => 500,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('error-monitoring.show', $log));

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $log->id,
            'message' => 'Specific fatal error in queue worker',
            'status_code' => 500,
        ]);
    }

    public function test_admin_can_toggle_resolve_status_of_an_error(): void
    {
        $log = SystemErrorLog::factory()->unresolved()->create();

        // 1. Resolve error
        $response = $this->actingAs($this->admin)
            ->from(route('error-monitoring.index'))
            ->post(route('error-monitoring.resolve', $log), [
                'notes' => 'Fixed bug in controller.',
            ]);

        $response->assertRedirect(route('error-monitoring.index'));
        $this->assertEquals('resolved', $log->fresh()->status);
        $this->assertEquals($this->admin->id, $log->fresh()->resolved_by);
        $this->assertEquals('Fixed bug in controller.', $log->fresh()->resolution_notes);

        // 2. Unresolve (toggle back)
        $responseToggle = $this->actingAs($this->admin)
            ->from(route('error-monitoring.index'))
            ->post(route('error-monitoring.resolve', $log));

        $responseToggle->assertRedirect(route('error-monitoring.index'));
        $this->assertEquals('unresolved', $log->fresh()->status);
        $this->assertNull($log->fresh()->resolved_by);
    }

    public function test_admin_can_resolve_all_unresolved_errors(): void
    {
        SystemErrorLog::factory()->count(3)->unresolved()->create();

        $response = $this->actingAs($this->admin)
            ->from(route('error-monitoring.index'))
            ->post(route('error-monitoring.resolve-all'));

        $response->assertRedirect(route('error-monitoring.index'));
        $this->assertEquals(0, SystemErrorLog::unresolved()->count());
        $this->assertEquals(3, SystemErrorLog::resolved()->count());
    }

    public function test_admin_can_delete_single_error_log(): void
    {
        $log = SystemErrorLog::factory()->create();

        $response = $this->actingAs($this->admin)
            ->from(route('error-monitoring.index'))
            ->delete(route('error-monitoring.destroy', $log));

        $response->assertRedirect(route('error-monitoring.index'));
        $this->assertDatabaseMissing('system_error_logs', ['id' => $log->id]);
    }

    public function test_import_status_polling_endpoint(): void
    {
        $importLog = ImportLog::factory()->processing()->create([
            'total_rows' => 100,
            'processed_rows' => 45,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('imports.status', $importLog));

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $importLog->id,
            'status' => 'processing',
            'progress_percentage' => 45,
            'total_rows' => 100,
            'processed_rows' => 45,
        ]);
    }
}

