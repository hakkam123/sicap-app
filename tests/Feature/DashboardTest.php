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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_dashboard_renders_with_summary_metrics_and_charts(): void
    {
        $part = PartNumber::factory()->create();
        Consume::factory()->create([
            'part_number_id' => $part->id,
            'quantity' => -10,
            'amount' => -150000.00,
            'consumed_at' => now(),
        ]);
        Consume::factory()->create([
            'part_number_id' => $part->id,
            'quantity' => 5,
            'amount' => 75000.00,
            'consumed_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('summary')
            ->where('summary.total_qty', 15) // ABS(-10) + ABS(5) = 15
            ->where('summary.total_amount', fn ($amount) => (float) $amount === 225000.0) // ABS(-150000) + ABS(75000) = 225000
            ->where('summary.total_transactions', 2)
            ->has('chartData')
            ->has('topConsumes')
            ->has('areaConsumption')
        );
    }

    public function test_dashboard_filters_by_area_and_date_range(): void
    {
        $area1 = Area::factory()->create(['name' => 'FA Area']);
        $area2 = Area::factory()->create(['name' => 'SMT Area']);

        $part = PartNumber::factory()->create();

        Consume::factory()->create([
            'part_number_id' => $part->id,
            'area_id' => $area1->id,
            'quantity' => 10,
            'amount' => 100000,
            'consumed_at' => '2026-03-01 10:00:00',
        ]);
        Consume::factory()->create([
            'part_number_id' => $part->id,
            'area_id' => $area2->id,
            'quantity' => 20,
            'amount' => 200000,
            'consumed_at' => '2026-03-15 10:00:00',
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard', [
            'area_id' => $area1->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-05',
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('summary.total_qty', 10)
            ->where('summary.total_amount', fn ($amount) => (float) $amount === 100000.0)
            ->where('summary.total_transactions', 1)
        );
    }

    public function test_dashboard_drill_down_by_part_number(): void
    {
        $part = PartNumber::factory()->create(['pn_baan' => 'PN-DRILL-01']);
        Consume::factory()->create([
            'part_number_id' => $part->id,
            'quantity' => 8,
            'amount' => 80000,
            'consumed_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/dashboard/drill-down?type=part&id=' . $part->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'title',
            'rows',
            'total_qty',
            'total_amount',
            'pagination',
        ]);
        $response->assertJsonFragment(['total_qty' => 8]);
    }

    public function test_dashboard_drill_down_by_area(): void
    {
        $area = Area::factory()->create(['name' => 'Fabrication Area']);
        $part = PartNumber::factory()->create();
        Consume::factory()->create([
            'area_id' => $area->id,
            'part_number_id' => $part->id,
            'quantity' => 12,
            'amount' => 120000,
        ]);

        $response = $this->actingAs($this->user)->getJson('/dashboard/drill-down?type=area&id=' . $area->id);

        $response->assertStatus(200);
        $response->assertJsonFragment(['total_qty' => 12]);
    }

    public function test_dashboard_drill_down_by_unassigned(): void
    {
        $part = PartNumber::factory()->create();
        Consume::factory()->create([
            'part_number_id' => $part->id,
            'area_id' => null,
            'quantity' => 7,
            'amount' => 70000,
            'consumed_at' => '2026-03-14 08:00:00',
        ]);

        $response = $this->actingAs($this->user)->getJson('/dashboard/drill-down?type=unassigned&id=all');

        $response->assertStatus(200);
        $response->assertJsonFragment(['total_qty' => 7]);
    }
}

