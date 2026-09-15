<?php

namespace Tests\Feature\Import;

use App\Imports\AreaImport;
use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AreaImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_area_import_creates_new_areas(): void
    {
        $importer = new AreaImport();
        $importer->array([
            ['code' => 'FA', 'name' => 'Final Assembly', 'description' => 'Assembly line'],
            ['code' => 'SMT', 'name' => 'Surface Mount Technology', 'description' => 'SMT line'],
        ]);

        $this->assertEquals(2, $importer->successCount);
        $this->assertEquals(0, $importer->errorCount);
        $this->assertDatabaseHas('areas', ['code' => 'FA', 'name' => 'Final Assembly']);
        $this->assertDatabaseHas('areas', ['code' => 'SMT', 'name' => 'Surface Mount Technology']);
    }

    public function test_area_import_updates_existing_and_restores_soft_deleted_area(): void
    {
        $area = Area::factory()->create(['code' => 'FA', 'name' => 'Old Assembly']);
        $area->delete();
        $this->assertSoftDeleted('areas', ['id' => $area->id]);

        $importer = new AreaImport();
        $importer->array([
            ['code' => 'FA', 'name' => 'Final Assembly Restored', 'description' => 'Updated'],
        ]);

        $this->assertEquals(1, $importer->successCount);
        $this->assertEquals(1, $importer->updatedCount);
        $this->assertDatabaseHas('areas', [
            'id' => $area->id,
            'code' => 'FA',
            'name' => 'Final Assembly Restored',
            'deleted_at' => null,
        ]);
    }

    public function test_area_import_records_errors_when_required_fields_missing(): void
    {
        $importer = new AreaImport();
        $importer->array([
            ['code' => '', 'name' => 'Area without code'],
            ['code' => 'QA', 'name' => ''],
        ]);

        $this->assertEquals(0, $importer->successCount);
        $this->assertEquals(2, $importer->errorCount);
        $this->assertCount(2, $importer->errors);
        $this->assertEquals('code', $importer->errors[0]['field']);
        $this->assertEquals('name', $importer->errors[1]['field']);
    }

    public function test_area_import_controller_rejects_non_excel_files(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('areas.import'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_area_template_download(): void
    {
        $response = $this->actingAs($this->admin)->get(route('areas.template'));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }
}

