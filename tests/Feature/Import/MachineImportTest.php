<?php

namespace Tests\Feature\Import;

use App\Imports\MachineImport;
use App\Models\Area;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MachineImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_machine_import_creates_new_machines(): void
    {
        $area = Area::factory()->create(['code' => 'FA']);

        $importer = new MachineImport();
        $importer->array([
            ['area_code' => 'FA', 'code' => 'M-01', 'name' => 'Assembly Machine 1', 'description' => 'Desc 1'],
            ['area_code' => 'FA', 'code' => 'M-02', 'name' => 'Assembly Machine 2', 'description' => 'Desc 2'],
        ]);

        $this->assertEquals(2, $importer->successCount);
        $this->assertEquals(0, $importer->errorCount);
        $this->assertDatabaseHas('machines', ['area_id' => $area->id, 'code' => 'M-01']);
        $this->assertDatabaseHas('machines', ['area_id' => $area->id, 'code' => 'M-02']);
    }

    public function test_machine_import_updates_existing_and_restores_soft_deleted(): void
    {
        $area = Area::factory()->create(['code' => 'FA']);
        $machine = Machine::factory()->create(['area_id' => $area->id, 'code' => 'M-01', 'name' => 'Old Name']);
        $machine->delete();
        $this->assertSoftDeleted('machines', ['id' => $machine->id]);

        $importer = new MachineImport();
        $importer->array([
            ['area_code' => 'FA', 'code' => 'M-01', 'name' => 'New Machine Name', 'description' => 'Updated'],
        ]);

        $this->assertEquals(1, $importer->successCount);
        $this->assertEquals(1, $importer->updatedCount);
        $this->assertDatabaseHas('machines', [
            'id' => $machine->id,
            'name' => 'New Machine Name',
            'deleted_at' => null,
        ]);
    }

    public function test_machine_import_records_error_when_area_code_not_found(): void
    {
        $importer = new MachineImport();
        $importer->array([
            ['area_code' => 'UNKNOWN_AREA', 'code' => 'M-01', 'name' => 'Machine 1'],
        ]);

        $this->assertEquals(0, $importer->successCount);
        $this->assertEquals(1, $importer->errorCount);
        $this->assertStringContainsString("tidak ditemukan di database", $importer->errors[0]['message']);
    }

    public function test_machine_import_records_errors_when_required_fields_missing(): void
    {
        $area = Area::factory()->create(['code' => 'FA']);

        $importer = new MachineImport();
        $importer->array([
            ['area_code' => '', 'code' => 'M-01', 'name' => 'Machine 1'],
            ['area_code' => 'FA', 'code' => '', 'name' => 'Machine 2'],
            ['area_code' => 'FA', 'code' => 'M-03', 'name' => ''],
        ]);

        $this->assertEquals(0, $importer->successCount);
        $this->assertEquals(3, $importer->errorCount);
    }

    public function test_machine_template_download(): void
    {
        $response = $this->actingAs($this->admin)->get(route('machines.template'));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }
}

