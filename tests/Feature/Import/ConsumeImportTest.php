<?php

namespace Tests\Feature\Import;

use App\Imports\ConsumeImport;
use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ConsumeImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Auth::login($this->user);
    }

    public function test_consume_import_successfully_inserts_records_with_mapped_area_machine(): void
    {
        $area = Area::factory()->create(['code' => 'FA']);
        $machine = Machine::factory()->create(['area_id' => $area->id, 'code' => 'FA-M1']);
        $part = PartNumber::factory()->create(['pn_baan' => 'PN-IMPORT-01']);

        $part->areas()->attach($area->id);
        $part->machines()->attach($machine->id);

        $importer = new ConsumeImport();
        $importer->array([
            [
                'part_number' => 'PN-IMPORT-01',
                'date' => '2026-03-14',
                'qty' => 5,
                'amount' => '125.000,00',
            ],
        ]);

        $this->assertEquals(1, $importer->successCount);
        $this->assertEquals(0, $importer->errorCount);

        $this->assertDatabaseHas('consumes', [
            'part_number_id' => $part->id,
            'area_id' => $area->id,
            'machine_id' => $machine->id,
            'quantity' => 5,
            'amount' => 125000.00,
            'source' => 'import_excel',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_consume_import_supports_negative_quantity_and_amounts(): void
    {
        $part = PartNumber::factory()->create(['pn_baan' => 'PN-RETURN-01']);

        $importer = new ConsumeImport();
        $importer->array([
            [
                'part_number' => 'PN-RETURN-01',
                'date' => '14/03/2026',
                'qty' => -3,
                'amount' => '-75.000,00',
            ],
        ]);

        $this->assertEquals(1, $importer->successCount);
        $this->assertDatabaseHas('consumes', [
            'part_number_id' => $part->id,
            'quantity' => -3,
            'amount' => -75000.00,
        ]);
    }

    public function test_consume_import_records_error_when_part_not_in_master(): void
    {
        $importer = new ConsumeImport();
        $importer->array([
            [
                'part_number' => 'PN-UNKNOWN',
                'date' => '2026-03-14',
                'qty' => 1,
                'amount' => 10000,
            ],
        ]);

        $this->assertEquals(0, $importer->successCount);
        $this->assertEquals(1, $importer->errorCount);
        $this->assertStringContainsString("tidak ditemukan di master data", $importer->errors[0]['message']);
    }

    public function test_consume_import_records_errors_on_invalid_date_or_zero_qty(): void
    {
        $part = PartNumber::factory()->create(['pn_baan' => 'PN-VALID-01']);

        $importer = new ConsumeImport();
        $importer->array([
            [
                'part_number' => 'PN-VALID-01',
                'date' => 'invalid-date',
                'qty' => 5,
                'amount' => 10000,
            ],
            [
                'part_number' => 'PN-VALID-01',
                'date' => '2026-03-14',
                'qty' => 0,
                'amount' => 10000,
            ],
        ]);

        $this->assertEquals(0, $importer->successCount);
        $this->assertEquals(2, $importer->errorCount);
    }

    public function test_consume_template_download(): void
    {
        $response = $this->actingAs($this->user)->get(route('consume.template'));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }
}

