<?php

namespace Tests\Unit\Services;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use App\Models\User;
use App\Services\ConsumeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsumeSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_successfully_inserts_consumes_with_auto_mapped_area_and_machine(): void
    {
        $area = Area::factory()->create(['code' => 'FA']);
        $machine = Machine::factory()->create(['area_id' => $area->id, 'code' => 'FA-M01']);
        $part = PartNumber::factory()->create(['pn_baan' => 'PN-AUTO-01']);

        // Attach mapping
        $part->areas()->attach($area->id);
        $part->machines()->attach($machine->id);

        $user = User::factory()->create();
        $service = app(ConsumeSyncService::class);

        $payload = [
            [
                'part_number' => 'PN-AUTO-01',
                'date' => '2026-03-14',
                'qty' => 5,
                'amount' => '125.000,00',
            ]
        ];

        $result = $service->sync($payload, $user->id);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['synced_count']);
        $this->assertEmpty($result['errors']);

        $this->assertDatabaseHas('consumes', [
            'part_number_id' => $part->id,
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'quantity' => 5,
            'amount' => 125000.00,
            'source' => 'api',
            'created_by' => $user->id,
        ]);
    }

    public function test_sync_records_errors_when_part_number_not_found(): void
    {
        $service = app(ConsumeSyncService::class);
        $payload = [
            [
                'part_number' => 'PN-NONEXISTENT',
                'date' => '2026-03-14',
                'qty' => 2,
                'amount' => 50000,
            ]
        ];

        $result = $service->sync($payload);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(0, $result['synced_count']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString("tidak ditemukan di master data", $result['errors'][0]['message']);
        $this->assertEquals(0, Consume::count());
    }

    public function test_sync_records_errors_when_date_or_qty_is_invalid(): void
    {
        $part = PartNumber::factory()->create(['pn_baan' => 'PN-VALID-01']);
        $service = app(ConsumeSyncService::class);

        $payload = [
            [
                'part_number' => 'PN-VALID-01',
                'date' => 'invalid-date-string',
                'qty' => 0,
                'amount' => 50000,
            ]
        ];

        $result = $service->sync($payload);

        $this->assertEquals(0, $result['synced_count']);
        $this->assertNotEmpty($result['errors']);
        $this->assertEquals(0, Consume::count());
    }

    public function test_sync_via_mocked_external_api_call(): void
    {
        Config::set('services.external_api.url', 'https://api.visteon.com/consumes');
        Config::set('services.external_api.token', 'test-bearer-token');

        $part = PartNumber::factory()->create(['pn_baan' => 'PN-EXT-01']);

        Http::fake([
            'https://api.visteon.com/consumes' => Http::response([
                'status' => 'ok',
                'data' => [
                    [
                        'part_number' => 'PN-EXT-01',
                        'date' => '2026-03-14',
                        'qty' => 10,
                        'amount' => 250000,
                    ]
                ]
            ], 200),
        ]);

        $service = app(ConsumeSyncService::class);
        $result = $service->sync();

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['synced_count']);
        $this->assertDatabaseHas('consumes', [
            'part_number_id' => $part->id,
            'quantity' => 10,
            'amount' => 250000.00,
            'source' => 'api',
        ]);
    }

    public function test_sync_returns_warning_when_api_url_not_configured(): void
    {
        Config::set('services.external_api.url', null);

        $service = app(ConsumeSyncService::class);
        $result = $service->sync();

        $this->assertEquals('warning', $result['status']);
        $this->assertEquals(0, $result['synced_count']);
    }
}

