<?php

namespace Tests\Unit\Models;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_area_creation_and_attributes(): void
    {
        $area = Area::factory()->create([
            'code' => 'FA',
            'name' => 'Final Assembly',
            'description' => 'Assembly Area',
        ]);

        $this->assertNotEmpty($area->id);
        $this->assertTrue(Str::isUlid($area->id));
        $this->assertEquals('FA', $area->code);
        $this->assertEquals('Final Assembly', $area->name);
        $this->assertEquals('Assembly Area', $area->description);
    }

    public function test_area_has_many_machines(): void
    {
        $area = Area::factory()->create();
        $machine1 = Machine::factory()->create(['area_id' => $area->id]);
        $machine2 = Machine::factory()->create(['area_id' => $area->id]);

        $this->assertCount(2, $area->machines);
        $this->assertTrue($area->machines->contains($machine1));
        $this->assertTrue($area->machines->contains($machine2));
    }

    public function test_area_belongs_to_many_part_numbers(): void
    {
        $area = Area::factory()->create();
        $part1 = PartNumber::factory()->create();
        $part2 = PartNumber::factory()->create();

        $area->partNumbers()->attach([$part1->id, $part2->id]);

        $this->assertCount(2, $area->partNumbers);
        $this->assertTrue($area->partNumbers->contains($part1));
        $this->assertTrue($area->partNumbers->contains($part2));
    }

    public function test_area_has_many_consumes(): void
    {
        $area = Area::factory()->create();
        $consume = Consume::factory()->create(['area_id' => $area->id]);

        $this->assertCount(1, $area->consumes);
        $this->assertTrue($area->consumes->contains($consume));
    }

    public function test_area_soft_deletes_and_restores(): void
    {
        $area = Area::factory()->create();
        $areaId = $area->id;

        $area->delete();
        $this->assertSoftDeleted('areas', ['id' => $areaId]);
        $this->assertNull(Area::find($areaId));

        $area->restore();
        $this->assertNotNull(Area::find($areaId));
    }
}

