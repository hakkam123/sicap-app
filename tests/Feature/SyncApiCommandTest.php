<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use App\Services\ConsumeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncApiCommandTest extends TestCase
{
    public function test_artisan_command_sync_api_runs_successfully(): void
    {
        $this->artisan('sicap:sync-api')
            ->assertExitCode(0);
    }

    public function test_consume_sync_service_processes_items_correctly(): void
    {
        $part = PartNumber::first();
        if (!$part) {
            $part = PartNumber::create([
                'pn_baan' => 'TEST-PN-001',
                'description' => 'Test Part',
                'price_per_unit' => 50000,
            ]);
        }

        $area = Area::first();
        if (!$area) {
            $area = Area::create([
                'code' => 'TEST-A1',
                'name' => 'Test Area',
            ]);
        }

        $machine = Machine::where('area_id', $area->id)->first();
        if (!$machine) {
            $machine = Machine::create([
                'area_id' => $area->id,
                'code' => 'TEST-M1',
                'name' => 'Test Machine',
            ]);
        }

        $service = app(ConsumeSyncService::class);
        $result = $service->sync([
            [
                'part_number' => $part->pn_baan,
                'area_code' => $area->code,
                'machine_code' => $machine->code,
                'quantity' => 5,
                'consumed_at' => now()->toDateTimeString(),
            ]
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['synced_count']);

        $this->assertDatabaseHas('consumes', [
            'part_number_id' => $part->id,
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'quantity' => 5,
            'source' => 'api',
        ]);
    }

    public function test_manual_sync_api_web_route(): void
    {
        $user = \App\Models\User::first();
        if (!$user) {
            $user = \App\Models\User::factory()->create();
        }

        $response = $this->actingAs($user)->post(route('consume.sync-api'));
        $response->assertRedirect(route('consume.index'));
    }
}
