<?php

namespace Tests\Feature\Import;

use App\Imports\MappingImport;
use App\Models\Area;
use App\Models\Machine;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MappingImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_mapping_import_associates_part_with_area_and_machine(): void
    {
        $area = Area::factory()->create(['code' => 'FA']);
        $machine = Machine::factory()->create(['area_id' => $area->id, 'code' => 'FA-M1']);
        $part = PartNumber::factory()->create(['pn_baan' => 'PN-MAP-01']);

        $importer = new MappingImport();
        $importer->array([
            ['pn_baan' => 'PN-MAP-01', 'area_code' => 'FA', 'machine_code' => 'FA-M1'],
        ]);

        $this->assertEquals(1, $importer->successCount);
        $this->assertEquals(0, $importer->errorCount);

        $this->assertDatabaseHas('area_part_number', [
            'area_id' => $area->id,
            'part_number_id' => $part->id,
        ]);
        $this->assertDatabaseHas('machine_part_number', [
            'machine_id' => $machine->id,
            'part_number_id' => $part->id,
        ]);
    }

    public function test_mapping_import_fails_when_part_number_not_found(): void
    {
        $area = Area::factory()->create(['code' => 'FA']);

        $importer = new MappingImport();
        $importer->array([
            ['pn_baan' => 'PN-NONEXISTENT', 'area_code' => 'FA'],
        ]);

        $this->assertEquals(0, $importer->successCount);
        $this->assertEquals(1, $importer->errorCount);
        $this->assertStringContainsString("tidak ditemukan di database", $importer->errors[0]['message']);
    }

    public function test_mapping_import_fails_when_area_not_found(): void
    {
        $part = PartNumber::factory()->create(['pn_baan' => 'PN-MAP-02']);

        $importer = new MappingImport();
        $importer->array([
            ['pn_baan' => 'PN-MAP-02', 'area_code' => 'UNKNOWN_AREA'],
        ]);

        $this->assertEquals(0, $importer->successCount);
        $this->assertEquals(1, $importer->errorCount);
        $this->assertStringContainsString("tidak ditemukan di database", $importer->errors[0]['message']);
    }

    public function test_mapping_import_fails_when_machine_not_found(): void
    {
        $area = Area::factory()->create(['code' => 'FA']);
        $part = PartNumber::factory()->create(['pn_baan' => 'PN-MAP-03']);

        $importer = new MappingImport();
        $importer->array([
            ['pn_baan' => 'PN-MAP-03', 'area_code' => 'FA', 'machine_code' => 'UNKNOWN_MACHINE'],
        ]);

        $this->assertEquals(0, $importer->successCount);
        $this->assertEquals(1, $importer->errorCount);
        $this->assertStringContainsString("tidak ditemukan di area", $importer->errors[0]['message']);
    }

    public function test_mapping_template_download(): void
    {
        $response = $this->actingAs($this->admin)->get(route('mapping.template'));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }
}

