<?php

namespace Tests\Feature;

use App\Imports\AreaImport;
use App\Imports\ConsumeImport;
use App\Imports\MachineImport;
use App\Imports\MappingImport;
use App\Imports\PartNumberImport;
use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ExcelImportAndTemplateTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::first() ?? User::factory()->create([
            'role' => 'admin',
        ]);
        if ($this->admin->role !== 'admin') {
            $this->admin->role = 'admin';
            $this->admin->save();
        }
    }

    public function test_template_download_routes(): void
    {
        // 1. Area template
        $response = $this->actingAs($this->admin)->get(route('areas.template'));
        $response->assertStatus(200);

        // 2. Machine template
        $response = $this->actingAs($this->admin)->get(route('machines.template'));
        $response->assertStatus(200);

        // 3. Part number template
        $response = $this->actingAs($this->admin)->get(route('part-numbers.template'));
        $response->assertStatus(200);

        // 4. Mapping template
        $response = $this->actingAs($this->admin)->get(route('mapping.template'));
        $response->assertStatus(200);

        // 5. Consume template
        $response = $this->actingAs($this->admin)->get(route('consume.template'));
        $response->assertStatus(200);
    }

    public function test_area_import_logic(): void
    {
        $importer = new AreaImport();
        $importer->array([
            [
                'code' => 'TEST_AREA_IMP',
                'name' => 'Test Area Import Name',
                'description' => 'Test description',
            ],
        ]);

        $this->assertEquals(1, $importer->successCount);
        $this->assertDatabaseHas('areas', [
            'code' => 'TEST_AREA_IMP',
            'name' => 'Test Area Import Name',
        ]);
    }

    public function test_machine_import_logic(): void
    {
        $area = Area::firstOrCreate(['code' => 'FA'], ['name' => 'Final Assembly']);

        $importer = new MachineImport();
        $importer->array([
            [
                'area_code' => 'FA',
                'code' => 'TEST_MC_IMP',
                'name' => 'Test Machine Import',
                'description' => 'Machine Desc',
            ],
        ]);

        $this->assertEquals(1, $importer->successCount);
        $this->assertDatabaseHas('machines', [
            'area_id' => $area->id,
            'code' => 'TEST_MC_IMP',
            'name' => 'Test Machine Import',
        ]);
    }

    public function test_part_number_import_logic(): void
    {
        $importer = new PartNumberImport();
        $importer->array([
            [
                'pn_baan' => 'TEST-PN-IMP-001',
                'description' => 'Test Part Desc',
                'price_per_unit' => 75000,
            ],
        ]);

        $this->assertEquals(1, $importer->successCount);
        $this->assertDatabaseHas('part_numbers', [
            'pn_baan' => 'TEST-PN-IMP-001',
            'description' => 'Test Part Desc',
            'price_per_unit' => 75000,
        ]);
    }

    public function test_mapping_import_logic(): void
    {
        $area = Area::firstOrCreate(['code' => 'FA'], ['name' => 'Final Assembly']);
        $machine = Machine::firstOrCreate(
            ['area_id' => $area->id, 'code' => 'MC_FA_01'],
            ['name' => 'Machine 1']
        );
        $part = PartNumber::firstOrCreate(
            ['pn_baan' => 'TEST-PN-MAP-001'],
            ['description' => 'Part for Mapping']
        );

        $importer = new MappingImport();
        $importer->array([
            [
                'pn_baan' => 'TEST-PN-MAP-001',
                'area_code' => 'FA',
                'machine_code' => 'MC_FA_01',
            ],
        ]);

        $this->assertEquals(1, $importer->successCount);
        $this->assertDatabaseHas('area_part_number', [
            'area_id' => $area->id,
            'part_number_id' => $part->id,
        ]);
        $this->assertDatabaseHas('machine_part_number', [
            'machine_id' => $machine->id,
            'part_number_id' => $part->id,
        ]);
    }

    public function test_consume_import_logic(): void
    {
        $area = Area::firstOrCreate(['code' => 'FA'], ['name' => 'Final Assembly']);
        $machine = Machine::firstOrCreate(
            ['area_id' => $area->id, 'code' => 'MC_FA_01'],
            ['name' => 'Machine 1']
        );
        $part = PartNumber::firstOrCreate(
            ['pn_baan' => 'TEST-PN-CONS-001'],
            ['description' => 'Part for Consume', 'price_per_unit' => 20000]
        );

        Auth::login($this->admin);

        $importer = new ConsumeImport();
        $importer->array([
            [
                'pn_baan' => 'TEST-PN-CONS-001',
                'area_code' => 'FA',
                'machine_code' => 'MC_FA_01',
                'qty' => 3,
                'consumed_at' => now()->format('Y-m-d'),
            ],
        ]);

        $this->assertEquals(1, $importer->successCount);
        $this->assertDatabaseHas('consumes', [
            'part_number_id' => $part->id,
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'quantity' => 3,
            'amount' => 60000,
            'source' => 'import_excel',
        ]);
    }

    public function test_consume_update_route(): void
    {
        $area = Area::firstOrCreate(['code' => 'FA'], ['name' => 'Final Assembly']);
        $machine = Machine::firstOrCreate(
            ['area_id' => $area->id, 'code' => 'MC_FA_01'],
            ['name' => 'Machine 1']
        );
        $part = PartNumber::firstOrCreate(
            ['pn_baan' => 'TEST-PN-CONS-002'],
            ['description' => 'Part 2', 'price_per_unit' => 15000]
        );

        $consume = Consume::create([
            'part_number_id' => $part->id,
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'quantity' => 2,
            'amount' => 30000,
            'consumed_at' => now()->format('Y-m-d'),
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('consume.update', $consume->id), [
            'part_number_id' => $part->id,
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'quantity' => 5,
            'amount' => 75000,
            'consumed_at' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('consume.index'));
        $this->assertDatabaseHas('consumes', [
            'id' => $consume->id,
            'quantity' => 5,
            'amount' => 75000,
        ]);
    }
}

