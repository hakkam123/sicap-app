<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Consume;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardAreaConsumptionTest extends TestCase
{
    public function test_dashboard_returns_area_consumption_prop(): void
    {
        $user = User::first();
        if (!$user) {
            $user = User::factory()->create();
        }

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('areaConsumption')
            ->has('areaConsumption.fa')
            ->has('areaConsumption.smt')
            ->has('areaConsumption.common')
        );
    }

    public function test_drill_down_by_category_fa(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson('/dashboard/drill-down?type=category&id=fa');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'title',
            'rows',
            'total_qty',
            'total_amount',
            'pagination',
        ]);
        $response->assertJsonFragment(['title' => 'Konsumsi Area — FA (Fabrication)']);
    }

    public function test_drill_down_by_category_common(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson('/dashboard/drill-down?type=category&id=common');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'title',
            'rows',
            'total_qty',
            'total_amount',
            'pagination',
        ]);
        $response->assertJsonFragment(['title' => 'Konsumsi Area — Common (FA & SMT)']);
    }
}

