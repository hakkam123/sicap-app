<?php

namespace Tests\Feature\Security;

use App\Models\Area;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XssTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_xss_script_in_area_name_is_stored_literally_without_execution(): void
    {
        $xssPayload = '<script>alert("XSS")</script>';

        $response = $this->actingAs($this->admin)->post(route('areas.store'), [
            'name' => $xssPayload,
            'code' => 'XSS_AREA',
            'description' => '<img src=x onerror=alert(1)>',
        ]);

        $response->assertRedirect(route('areas.index'));

        $this->assertDatabaseHas('areas', [
            'code' => 'XSS_AREA',
            'name' => $xssPayload,
        ]);
    }

    public function test_xss_script_in_part_number_description(): void
    {
        $xssPayload = '<svg onload=alert("XSS")>';

        $response = $this->actingAs($this->admin)->post(route('part-numbers.store'), [
            'pn_baan' => 'PN-XSS-TEST',
            'description' => $xssPayload,
        ]);

        $response->assertRedirect(route('part-numbers.index'));

        $this->assertDatabaseHas('part_numbers', [
            'pn_baan' => 'PN-XSS-TEST',
            'description' => $xssPayload,
        ]);
    }

    public function test_xss_script_in_error_monitoring_resolve_notes(): void
    {
        $errorLog = \App\Models\SystemErrorLog::factory()->unresolved()->create();
        $xssNotes = '<iframe src="javascript:alert(1)"></iframe>';

        $response = $this->actingAs($this->admin)->post(route('error-monitoring.resolve', $errorLog), [
            'notes' => $xssNotes,
        ]);

        $response->assertRedirect();
        $this->assertEquals($xssNotes, $errorLog->fresh()->resolution_notes);
    }
}

