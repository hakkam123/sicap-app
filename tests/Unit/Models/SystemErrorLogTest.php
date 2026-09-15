<?php

namespace Tests\Unit\Models;

use App\Models\SystemErrorLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemErrorLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_error_log_creation_and_attributes(): void
    {
        $log = SystemErrorLog::create([
            'error_type' => 'system',
            'status_code' => 500,
            'severity' => 'critical',
            'feature' => 'consume',
            'message' => 'Database timeout during calculation',
            'status' => 'unresolved',
        ]);

        $this->assertNotEmpty($log->id);
        $this->assertTrue(Str::isUlid($log->id));
        $this->assertSame(500, $log->status_code);
        $this->assertEquals('critical', $log->severity);
        $this->assertEquals('unresolved', $log->status);
    }

    public function test_system_error_log_feature_label_accessor(): void
    {
        $log = SystemErrorLog::factory()->make(['feature' => 'sync_api']);
        $this->assertEquals('Sync API Eksternal', $log->feature_label);

        $logAuth = SystemErrorLog::factory()->make(['feature' => 'auth']);
        $this->assertEquals('Autentikasi & Keamanan', $logAuth->feature_label);
    }

    public function test_system_error_log_scopes_filtering(): void
    {
        SystemErrorLog::factory()->unresolved()->create(['feature' => 'consume', 'severity' => 'critical']);
        SystemErrorLog::factory()->unresolved()->create(['feature' => 'consume', 'severity' => 'warning']);
        SystemErrorLog::factory()->resolved()->create(['feature' => 'user', 'severity' => 'critical']);

        $this->assertEquals(2, SystemErrorLog::unresolved()->count());
        $this->assertEquals(1, SystemErrorLog::resolved()->count());
        $this->assertEquals(2, SystemErrorLog::feature('consume')->count());
        $this->assertEquals(2, SystemErrorLog::severity('critical')->count());
    }

    public function test_system_error_log_status_transition_to_resolved(): void
    {
        $admin = User::factory()->admin()->create();
        $log = SystemErrorLog::factory()->unresolved()->create();

        $this->assertEquals('unresolved', $log->status);
        $this->assertNull($log->resolved_at);
        $this->assertNull($log->resolved_by);

        $log->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
            'resolution_notes' => 'Fixed database indexes to solve timeouts.',
        ]);

        $fresh = $log->fresh();
        $this->assertEquals('resolved', $fresh->status);
        $this->assertEquals($admin->id, $fresh->resolved_by);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertEquals('Fixed database indexes to solve timeouts.', $fresh->resolution_notes);
        $this->assertEquals($admin->name, $fresh->resolver->name);
    }

    public function test_system_error_log_user_and_resolver_relationships(): void
    {
        $user = User::factory()->create(['name' => 'Operator']);
        $admin = User::factory()->admin()->create(['name' => 'Lead Admin']);

        $log = SystemErrorLog::factory()->create([
            'user_id' => $user->id,
            'resolved_by' => $admin->id,
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $this->assertEquals('Operator', $log->user->name);
        $this->assertEquals('Lead Admin', $log->resolver->name);
    }
}

