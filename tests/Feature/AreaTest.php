<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AreaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_view_areas_list_with_inertia(): void
    {
        Area::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('areas.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Area/Index')
            ->has('areas.data', 3)
            ->has('filters')
        );
    }

    public function test_admin_can_search_areas(): void
    {
        Area::factory()->create(['name' => 'Fabrication Unit', 'code' => 'FAB']);
        Area::factory()->create(['name' => 'Surface Mount', 'code' => 'SMT']);

        $response = $this->actingAs($this->admin)->get(route('areas.index', ['search' => 'Fabrication']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Area/Index')
            ->has('areas.data', 1)
            ->where('areas.data.0.code', 'FAB')
        );
    }

    public function test_admin_can_create_new_area(): void
    {
        $response = $this->actingAs($this->admin)->post(route('areas.store'), [
            'name' => 'Quality Assurance',
            'code' => 'QA',
            'description' => 'Inspection line',
        ]);

        $response->assertRedirect(route('areas.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('areas', [
            'name' => 'Quality Assurance',
            'code' => 'QA',
        ]);
    }

    public function test_admin_can_update_existing_area(): void
    {
        $area = Area::factory()->create(['name' => 'Old Name', 'code' => 'OLD']);

        $response = $this->actingAs($this->admin)->put(route('areas.update', $area), [
            'name' => 'New Name',
            'code' => 'NEW',
            'description' => 'Updated Description',
        ]);

        $response->assertRedirect(route('areas.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('areas', [
            'id' => $area->id,
            'name' => 'New Name',
            'code' => 'NEW',
        ]);
    }

    public function test_admin_can_delete_unused_area(): void
    {
        $area = Area::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('areas.destroy', $area));

        $response->assertRedirect(route('areas.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('areas', ['id' => $area->id]);
    }

    public function test_admin_cannot_delete_area_with_linked_machines(): void
    {
        $area = Area::factory()->create();
        Machine::factory()->create(['area_id' => $area->id]);

        $response = $this->actingAs($this->admin)
            ->from(route('areas.index'))
            ->delete(route('areas.destroy', $area));

        $response->assertRedirect(route('areas.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('areas', ['id' => $area->id, 'deleted_at' => null]);
    }
}

