<?php

namespace Tests\Unit\Models;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_consume_creation_with_attributes_and_casts(): void
    {
        $part = PartNumber::factory()->create();
        $area = Area::factory()->create();
        $machine = Machine::factory()->create(['area_id' => $area->id]);
        $user = User::factory()->create();

        $consume = Consume::create([
            'part_number_id' => $part->id,
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'quantity' => 10,
            'amount' => 125000.50,
            'consumed_at' => '2026-03-14 10:00:00',
            'source' => 'manual',
            'created_by' => $user->id,
        ]);

        $this->assertNotEmpty($consume->id);
        $this->assertTrue(Str::isUlid($consume->id));
        $this->assertSame(10, $consume->quantity);
        $this->assertEquals('125000.50', $consume->amount);
        $this->assertEquals('manual', $consume->source);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $consume->consumed_at);
    }

    public function test_consume_belongs_to_relations(): void
    {
        $part = PartNumber::factory()->create();
        $area = Area::factory()->create();
        $machine = Machine::factory()->create(['area_id' => $area->id]);
        $user = User::factory()->create();

        $consume = Consume::factory()->create([
            'part_number_id' => $part->id,
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'created_by' => $user->id,
        ]);

        $this->assertEquals($part->id, $consume->partNumber->id);
        $this->assertEquals($area->id, $consume->area->id);
        $this->assertEquals($machine->id, $consume->machine->id);
        $this->assertEquals($user->id, $consume->creator->id);
    }

    public function test_consume_relations_resolve_even_when_parent_is_soft_deleted(): void
    {
        $area = Area::factory()->create(['name' => 'FA Area']);
        $machine = Machine::factory()->create(['area_id' => $area->id, 'name' => 'Machine A']);
        $user = User::factory()->create(['name' => 'Admin User']);

        $consume = Consume::factory()->create([
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'created_by' => $user->id,
        ]);

        $area->delete();
        $machine->delete();
        $user->delete();

        $consume->refresh();

        $this->assertNotNull($consume->area);
        $this->assertEquals('FA Area', $consume->area->name);
        $this->assertNotNull($consume->machine);
        $this->assertEquals('Machine A', $consume->machine->name);
        $this->assertNotNull($consume->creator);
        $this->assertEquals('Admin User', $consume->creator->name);
    }

    public function test_consume_supports_negative_quantity_and_negative_amount(): void
    {
        $consume = Consume::factory()->create([
            'quantity' => -5,
            'amount' => -60000.00,
            'source' => 'excel',
        ]);

        $this->assertSame(-5, $consume->quantity);
        $this->assertEquals('-60000.00', $consume->amount);
    }

    public function test_consume_sources_can_be_filtered(): void
    {
        Consume::factory()->excel()->create();
        Consume::factory()->api()->create();
        Consume::factory()->manual()->create();

        $this->assertEquals(1, Consume::where('source', 'excel')->count());
        $this->assertEquals(1, Consume::where('source', 'api')->count());
        $this->assertEquals(1, Consume::where('source', 'manual')->count());
    }
}

