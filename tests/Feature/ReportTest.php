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

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_view_reports_page_with_aggregates(): void
    {
        $part = PartNumber::factory()->create();
        Consume::factory()->create([
            'part_number_id' => $part->id,
            'quantity' => -10,
            'amount' => -200000.00,
        ]);
        Consume::factory()->create([
            'part_number_id' => $part->id,
            'quantity' => 5,
            'amount' => 100000.00,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('consumptions.data', 2)
            ->has('summary')
            ->where('summary.total_qty', 15) // ABS(-10) + ABS(5) = 15
            ->where('summary.total_amount', 300000)
        );
    }

    public function test_reports_filter_by_area_machine_and_date(): void
    {
        $area = Area::factory()->create();
        $machine = Machine::factory()->create(['area_id' => $area->id]);
        $part = PartNumber::factory()->create();

        Consume::factory()->create([
            'part_number_id' => $part->id,
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'quantity' => 10,
            'amount' => 50000,
            'consumed_at' => '2026-03-05',
        ]);
        Consume::factory()->create([
            'part_number_id' => $part->id,
            'quantity' => 20,
            'amount' => 100000,
            'consumed_at' => '2026-03-20',
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.index', [
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-10',
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('consumptions.data', 1)
            ->where('summary.total_qty', 10)
            ->where('summary.total_amount', 50000)
        );
    }

    public function test_reports_search_by_part_number(): void
    {
        $partTarget = PartNumber::factory()->create(['pn_baan' => 'PN-TARGET-123']);
        $partOther = PartNumber::factory()->create(['pn_baan' => 'PN-OTHER-999']);

        Consume::factory()->create(['part_number_id' => $partTarget->id]);
        Consume::factory()->create(['part_number_id' => $partOther->id]);

        $response = $this->actingAs($this->user)->get(route('reports.index', ['search' => 'TARGET']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('consumptions.data', 1)
            ->where('consumptions.data.0.part_number.pn_baan', 'PN-TARGET-123')
        );
    }

    public function test_reports_excel_export(): void
    {
        Consume::factory()->count(2)->create();

        $response = $this->actingAs($this->user)->get(route('reports.export', ['type' => 'excel']));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }

    public function test_reports_pdf_export(): void
    {
        Consume::factory()->count(2)->create();

        $response = $this->actingAs($this->user)->get(route('reports.export', ['type' => 'pdf']));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}

