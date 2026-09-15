<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartNumberTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_view_part_numbers_list_with_inertia(): void
    {
        PartNumber::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('part-numbers.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('PartNumber/Index')
            ->has('partNumbers.data', 3)
            ->has('areas')
            ->has('filters')
        );
    }

    public function test_admin_can_search_part_numbers(): void
    {
        PartNumber::factory()->create(['pn_baan' => 'PN-CAP-100UF']);
        PartNumber::factory()->create(['pn_baan' => 'PN-RES-10K']);

        $response = $this->actingAs($this->admin)->get(route('part-numbers.index', ['search' => 'CAP-100']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('PartNumber/Index')
            ->has('partNumbers.data', 1)
            ->where('partNumbers.data.0.pn_baan', 'PN-CAP-100UF')
        );
    }

    public function test_admin_can_create_new_part_number_with_mapping(): void
    {
        $area = Area::factory()->create();
        $machine = Machine::factory()->create(['area_id' => $area->id]);

        $response = $this->actingAs($this->admin)->post(route('part-numbers.store'), [
            'pn_baan' => 'PN-DIODE-1N4148',
            'description' => 'Fast switching diode',
            'area_ids' => [$area->id],
            'machine_ids' => [$machine->id],
        ]);

        $response->assertRedirect(route('part-numbers.index'));
        $response->assertSessionHas('success');

        $part = PartNumber::where('pn_baan', 'PN-DIODE-1N4148')->first();
        $this->assertNotNull($part);
        $this->assertTrue($part->areas->contains($area));
        $this->assertTrue($part->machines->contains($machine));
    }

    public function test_admin_can_update_existing_part_number_and_mappings(): void
    {
        $part = PartNumber::factory()->create(['pn_baan' => 'PN-OLD-01']);
        $area = Area::factory()->create();

        $response = $this->actingAs($this->admin)->put(route('part-numbers.update', $part), [
            'pn_baan' => 'PN-UPDATED-01',
            'description' => 'New description',
            'area_ids' => [$area->id],
            'machine_ids' => [],
        ]);

        $response->assertRedirect(route('part-numbers.index'));
        $response->assertSessionHas('success');

        $part->refresh();
        $this->assertEquals('PN-UPDATED-01', $part->pn_baan);
        $this->assertTrue($part->areas->contains($area));
        $this->assertCount(0, $part->machines);
    }

    public function test_admin_can_delete_unused_part_number(): void
    {
        $part = PartNumber::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('part-numbers.destroy', $part));

        $response->assertRedirect(route('part-numbers.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('part_numbers', ['id' => $part->id]);
    }

    public function test_admin_cannot_delete_part_number_with_linked_consumes(): void
    {
        $part = PartNumber::factory()->create();
        Consume::factory()->create(['part_number_id' => $part->id]);

        $response = $this->actingAs($this->admin)
            ->from(route('part-numbers.index'))
            ->delete(route('part-numbers.destroy', $part));

        $response->assertRedirect(route('part-numbers.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('part_numbers', ['id' => $part->id, 'deleted_at' => null]);
    }
}

