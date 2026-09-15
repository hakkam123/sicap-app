<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Machine;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MappingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_view_mapping_index_page(): void
    {
        $part = PartNumber::factory()->create();
        $area = Area::factory()->create();
        $part->areas()->attach($area->id);

        $response = $this->actingAs($this->admin)->get(route('mapping.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Mapping/Index')
            ->has('partNumbers.data')
            ->has('mappings.data')
            ->has('areas')
        );
    }

    public function test_admin_can_get_part_mapping_details_as_json(): void
    {
        $part = PartNumber::factory()->create();
        $area = Area::factory()->create();
        $machine = Machine::factory()->create(['area_id' => $area->id]);

        $part->areas()->attach($area->id);
        $part->machines()->attach($machine->id);

        $response = $this->actingAs($this->admin)->getJson(route('mapping.detail', $part));

        $response->assertStatus(200);
        $response->assertJson([
            'area_ids' => [$area->id],
            'machine_ids' => [$machine->id],
        ]);
    }

    public function test_admin_can_sync_mapping_relationships(): void
    {
        $part = PartNumber::factory()->create();
        $area = Area::factory()->create();
        $machine = Machine::factory()->create(['area_id' => $area->id]);

        $response = $this->actingAs($this->admin)
            ->from(route('mapping.index'))
            ->post(route('mapping.sync'), [
                'part_number_id' => $part->id,
                'area_ids' => [$area->id],
                'machine_ids' => [$machine->id],
            ]);

        $response->assertRedirect(route('mapping.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('area_part_number', [
            'area_id' => $area->id,
            'part_number_id' => $part->id,
        ]);
        $this->assertDatabaseHas('machine_part_number', [
            'machine_id' => $machine->id,
            'part_number_id' => $part->id,
        ]);
    }

    public function test_mapping_sync_validation_fails_when_area_is_missing(): void
    {
        $part = PartNumber::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('mapping.sync'), [
            'part_number_id' => $part->id,
            'area_ids' => [],
        ]);

        $response->assertSessionHasErrors('area_ids');
    }

    public function test_mapping_sync_validation_fails_with_invalid_part_number(): void
    {
        $area = Area::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('mapping.sync'), [
            'part_number_id' => '01JNONEXISTENTPART000000000',
            'area_ids' => [$area->id],
        ]);

        $response->assertSessionHasErrors('part_number_id');
    }
}

