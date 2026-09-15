<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MachineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_view_machines_list_with_inertia(): void
    {
        $area = Area::factory()->create();
        Machine::factory()->count(3)->create(['area_id' => $area->id]);

        $response = $this->actingAs($this->admin)->get(route('machines.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Machine/Index')
            ->has('machines.data', 3)
            ->has('areas')
            ->has('filters')
        );
    }

    public function test_admin_can_filter_machines_by_area(): void
    {
        $area1 = Area::factory()->create(['name' => 'Area 1']);
        $area2 = Area::factory()->create(['name' => 'Area 2']);

        Machine::factory()->create(['area_id' => $area1->id, 'name' => 'M1 in Area 1']);
        Machine::factory()->create(['area_id' => $area2->id, 'name' => 'M2 in Area 2']);

        $response = $this->actingAs($this->admin)->get(route('machines.index', ['area_id' => $area1->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Machine/Index')
            ->has('machines.data', 1)
            ->where('machines.data.0.name', 'M1 in Area 1')
        );
    }

    public function test_admin_can_create_new_machine(): void
    {
        $area = Area::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('machines.store'), [
            'area_id' => $area->id,
            'name' => 'Wave Solder 01',
            'code' => 'WS-01',
            'description' => 'Wave soldering unit',
        ]);

        $response->assertRedirect(route('machines.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('machines', [
            'area_id' => $area->id,
            'name' => 'Wave Solder 01',
            'code' => 'WS-01',
        ]);
    }

    public function test_admin_can_update_existing_machine(): void
    {
        $area1 = Area::factory()->create();
        $area2 = Area::factory()->create();
        $machine = Machine::factory()->create(['area_id' => $area1->id, 'code' => 'OLD-M']);

        $response = $this->actingAs($this->admin)->put(route('machines.update', $machine), [
            'area_id' => $area2->id,
            'name' => 'Updated Machine Name',
            'code' => 'NEW-M',
            'description' => 'Relocated machine',
        ]);

        $response->assertRedirect(route('machines.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('machines', [
            'id' => $machine->id,
            'area_id' => $area2->id,
            'name' => 'Updated Machine Name',
            'code' => 'NEW-M',
        ]);
    }

    public function test_admin_can_delete_unused_machine(): void
    {
        $machine = Machine::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('machines.destroy', $machine));

        $response->assertRedirect(route('machines.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('machines', ['id' => $machine->id]);
    }

    public function test_admin_cannot_delete_machine_with_linked_consumes(): void
    {
        $machine = Machine::factory()->create();
        Consume::factory()->create(['machine_id' => $machine->id]);

        $response = $this->actingAs($this->admin)
            ->from(route('machines.index'))
            ->delete(route('machines.destroy', $machine));

        $response->assertRedirect(route('machines.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('machines', ['id' => $machine->id, 'deleted_at' => null]);
    }
}

