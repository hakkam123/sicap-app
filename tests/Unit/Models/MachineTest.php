<?php

namespace Tests\Unit\Models;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_machine_creation_and_attributes(): void
    {
        $area = Area::factory()->create();
        $machine = Machine::factory()->create([
            'area_id' => $area->id,
            'code' => 'MC-01',
            'name' => 'SMT Machine 1',
            'description' => 'High Speed Mounter',
        ]);

        $this->assertNotEmpty($machine->id);
        $this->assertTrue(Str::isUlid($machine->id));
        $this->assertEquals('MC-01', $machine->code);
        $this->assertEquals('SMT Machine 1', $machine->name);
        $this->assertEquals($area->id, $machine->area_id);
    }

    public function test_machine_belongs_to_area(): void
    {
        $area = Area::factory()->create(['name' => 'Main Area']);
        $machine = Machine::factory()->create(['area_id' => $area->id]);

        $this->assertInstanceOf(Area::class, $machine->area);
        $this->assertEquals('Main Area', $machine->area->name);
    }

    public function test_machine_belongs_to_many_part_numbers(): void
    {
        $machine = Machine::factory()->create();
        $part1 = PartNumber::factory()->create();
        $part2 = PartNumber::factory()->create();

        $machine->partNumbers()->attach([$part1->id, $part2->id]);

        $this->assertCount(2, $machine->partNumbers);
        $this->assertTrue($machine->partNumbers->contains($part1));
        $this->assertTrue($machine->partNumbers->contains($part2));
    }

    public function test_machine_has_many_consumes(): void
    {
        $machine = Machine::factory()->create();
        $consume = Consume::factory()->create(['machine_id' => $machine->id]);

        $this->assertCount(1, $machine->consumes);
        $this->assertTrue($machine->consumes->contains($consume));
    }

    public function test_machine_soft_deletes_and_restores(): void
    {
        $machine = Machine::factory()->create();
        $machineId = $machine->id;

        $machine->delete();
        $this->assertSoftDeleted('machines', ['id' => $machineId]);
        $this->assertNull(Machine::find($machineId));

        $machine->restore();
        $this->assertNotNull(Machine::find($machineId));
    }
}

