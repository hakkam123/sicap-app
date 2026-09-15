<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use App\Models\SyncSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConsumeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_view_consume_index_page(): void
    {
        Consume::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get(route('consume.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Consume/Index')
            ->has('consumes.data', 3)
            ->has('areas')
            ->has('partNumbers')
            ->has('syncSchedules')
        );
    }

    public function test_user_can_filter_consume_records_by_date_and_area(): void
    {
        $area1 = Area::factory()->create();
        $area2 = Area::factory()->create();

        $part = PartNumber::factory()->create();

        Consume::factory()->create([
            'part_number_id' => $part->id,
            'area_id' => $area1->id,
            'consumed_at' => '2026-03-10 10:00:00',
        ]);
        Consume::factory()->create([
            'part_number_id' => $part->id,
            'area_id' => $area2->id,
            'consumed_at' => '2026-03-12 10:00:00',
        ]);

        $response = $this->actingAs($this->user)->get(route('consume.index', [
            'area_id' => $area1->id,
            'date_from' => '2026-03-09',
            'date_to' => '2026-03-11',
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Consume/Index')
            ->has('consumes.data', 1)
        );
    }

    public function test_user_can_store_new_consume_record_manually(): void
    {
        $part = PartNumber::factory()->create();
        $area = Area::factory()->create();
        $machine = Machine::factory()->create(['area_id' => $area->id]);

        $response = $this->actingAs($this->user)->post(route('consume.store'), [
            'part_number_id' => $part->id,
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'quantity' => 15,
            'amount' => 225000.00,
            'consumed_at' => '2026-03-14',
        ]);

        $response->assertRedirect(route('consume.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('consumes', [
            'part_number_id' => $part->id,
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'quantity' => 15,
            'amount' => 225000.00,
            'source' => 'manual',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_user_can_update_existing_consume_record(): void
    {
        $consume = Consume::factory()->create(['quantity' => 5, 'amount' => 50000]);

        $response = $this->actingAs($this->user)->put(route('consume.update', $consume), [
            'part_number_id' => $consume->part_number_id,
            'quantity' => 8,
            'amount' => 80000.00,
            'consumed_at' => '2026-03-14',
        ]);

        $response->assertRedirect(route('consume.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('consumes', [
            'id' => $consume->id,
            'quantity' => 8,
            'amount' => 80000.00,
        ]);
    }

    public function test_user_can_delete_consume_record(): void
    {
        $consume = Consume::factory()->create();

        $response = $this->actingAs($this->user)->delete(route('consume.destroy', $consume));

        $response->assertRedirect(route('consume.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('consumes', ['id' => $consume->id]);
    }

    public function test_get_machines_by_area_ajax_endpoint(): void
    {
        $area = Area::factory()->create();
        $machine = Machine::factory()->create(['area_id' => $area->id, 'name' => 'Machine In Area']);

        $response = $this->actingAs($this->user)->getJson(route('consume.machines-by-area', ['area_id' => $area->id]));

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Machine In Area']);
    }

    public function test_update_sync_schedules(): void
    {
        $response = $this->actingAs($this->user)->post(route('consume.sync-schedules.update'), [
            'schedules' => [
                ['time' => '08:00', 'is_active' => true],
                ['time' => '17:00', 'is_active' => false],
            ]
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sync_schedules', ['time' => '08:00', 'is_active' => true]);
        $this->assertDatabaseHas('sync_schedules', ['time' => '17:00', 'is_active' => false]);
    }
}

