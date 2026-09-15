<?php

namespace Tests\Unit\Models;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_part_number_creation_and_attributes(): void
    {
        $part = PartNumber::factory()->create([
            'pn_baan' => 'PN-100-TEST',
            'description' => 'Electronic Resistor',
        ]);

        $this->assertNotEmpty($part->id);
        $this->assertTrue(Str::isUlid($part->id));
        $this->assertEquals('PN-100-TEST', $part->pn_baan);
        $this->assertEquals('Electronic Resistor', $part->description);
    }

    public function test_part_number_belongs_to_many_areas(): void
    {
        $part = PartNumber::factory()->create();
        $area1 = Area::factory()->create();
        $area2 = Area::factory()->create();

        $part->areas()->attach([$area1->id, $area2->id]);

        $this->assertCount(2, $part->areas);
        $this->assertTrue($part->areas->contains($area1));
        $this->assertTrue($part->areas->contains($area2));
    }

    public function test_part_number_belongs_to_many_machines(): void
    {
        $part = PartNumber::factory()->create();
        $machine1 = Machine::factory()->create();
        $machine2 = Machine::factory()->create();

        $part->machines()->attach([$machine1->id, $machine2->id]);

        $this->assertCount(2, $part->machines);
        $this->assertTrue($part->machines->contains($machine1));
        $this->assertTrue($part->machines->contains($machine2));
    }

    public function test_part_number_has_many_consumes(): void
    {
        $part = PartNumber::factory()->create();
        $consume = Consume::factory()->create(['part_number_id' => $part->id]);

        $this->assertCount(1, $part->consumes);
        $this->assertTrue($part->consumes->contains($consume));
    }

    public function test_part_number_soft_deletes_and_restores(): void
    {
        $part = PartNumber::factory()->create();
        $partId = $part->id;

        $part->delete();
        $this->assertSoftDeleted('part_numbers', ['id' => $partId]);
        $this->assertNull(PartNumber::find($partId));

        $part->restore();
        $this->assertNotNull(PartNumber::find($partId));
    }
}

