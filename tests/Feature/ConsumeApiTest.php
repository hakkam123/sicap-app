<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumeApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $apiUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiUser = User::factory()->create();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/api/consumes/sync', [
            'consumes' => [
                [
                    'date' => '2026-03-14',
                    'part_number' => 'PN-TEST-01',
                    'qty' => 5,
                    'amount' => 50000,
                ]
            ]
        ]);

        $response->assertStatus(401);
    }

    public function test_request_with_insufficient_token_ability_is_forbidden(): void
    {
        Sanctum::actingAs($this->apiUser, ['reports:view']); // missing 'consumes:sync'

        $response = $this->postJson('/api/consumes/sync', [
            'consumes' => [
                [
                    'date' => '2026-03-14',
                    'part_number' => 'PN-TEST-01',
                    'qty' => 5,
                    'amount' => 50000,
                ]
            ]
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Token tidak memiliki hak akses (ability) [consumes:sync].',
        ]);
    }

    public function test_successful_batch_sync_via_sanctum_token(): void
    {
        Sanctum::actingAs($this->apiUser, ['consumes:sync']);

        $area = Area::factory()->create(['code' => 'FA']);
        $machine = Machine::factory()->create(['area_id' => $area->id, 'code' => 'MC-01']);
        $part = PartNumber::factory()->create(['pn_baan' => 'PN-API-01']);
        $part->areas()->attach($area->id);
        $part->machines()->attach($machine->id);

        $response = $this->postJson('/api/consumes/sync', [
            'consumes' => [
                [
                    'date' => '2026-03-14',
                    'part_number' => 'PN-API-01',
                    'desc' => 'API test part',
                    'qty' => 10,
                    'amount' => '350.000,00',
                ]
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'total' => 1,
                'processed' => 1,
                'failed' => 0,
            ]
        ]);

        $this->assertDatabaseHas('consumes', [
            'part_number_id' => $part->id,
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'quantity' => 10,
            'amount' => 350000.00,
            'source' => 'api',
            'created_by' => $this->apiUser->id,
        ]);
    }

    public function test_batch_sync_validation_failure_returns_422(): void
    {
        Sanctum::actingAs($this->apiUser, ['consumes:sync']);

        $response = $this->postJson('/api/consumes/sync', [
            'consumes' => [
                [
                    'date' => 'invalid-date',
                    'part_number' => '',
                    'qty' => 0,
                    'amount' => 'not_a_number',
                ]
            ]
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'status',
            'message',
            'errors',
        ]);
    }

    public function test_user_can_create_sanctum_token(): void
    {
        Sanctum::actingAs($this->apiUser, ['*']);

        $response = $this->postJson('/api/tokens/create', [
            'token_name' => 'sap-sync-token',
            'abilities' => ['consumes:sync'],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'token',
            'abilities',
        ]);
        $response->assertJsonFragment(['status' => 'success']);
    }
}

