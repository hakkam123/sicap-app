<?php

namespace Tests\Feature\Import;

use App\Imports\PartNumberImport;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartNumberImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_part_number_import_creates_new_records(): void
    {
        $importer = new PartNumberImport();
        $importer->array([
            ['pn_baan' => 'PN-IMP-01', 'description' => 'First Part'],
            ['pn_baan' => 'PN-IMP-02', 'description' => 'Second Part'],
        ]);

        $this->assertEquals(2, $importer->successCount);
        $this->assertEquals(0, $importer->errorCount);
        $this->assertDatabaseHas('part_numbers', ['pn_baan' => 'PN-IMP-01', 'description' => 'First Part']);
        $this->assertDatabaseHas('part_numbers', ['pn_baan' => 'PN-IMP-02', 'description' => 'Second Part']);
    }

    public function test_part_number_import_updates_and_restores_soft_deleted(): void
    {
        $part = PartNumber::factory()->create(['pn_baan' => 'PN-IMP-01', 'description' => 'Original Desc']);
        $part->delete();
        $this->assertSoftDeleted('part_numbers', ['id' => $part->id]);

        $importer = new PartNumberImport();
        $importer->array([
            ['pn_baan' => 'PN-IMP-01', 'description' => 'Updated Desc'],
        ]);

        $this->assertEquals(1, $importer->successCount);
        $this->assertEquals(1, $importer->updatedCount);
        $this->assertDatabaseHas('part_numbers', [
            'id' => $part->id,
            'description' => 'Updated Desc',
            'deleted_at' => null,
        ]);
    }

    public function test_part_number_import_records_error_when_pn_baan_is_empty(): void
    {
        $importer = new PartNumberImport();
        $importer->array([
            ['pn_baan' => '', 'description' => 'Part without code'],
        ]);

        $this->assertEquals(0, $importer->successCount);
        $this->assertEquals(1, $importer->errorCount);
        $this->assertStringContainsString("Kolom 'pn_baan' wajib diisi", $importer->errors[0]['message']);
    }

    public function test_part_number_template_download(): void
    {
        $response = $this->actingAs($this->admin)->get(route('part-numbers.template'));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }

    public function test_part_number_import_rejects_empty_upload(): void
    {
        $response = $this->actingAs($this->admin)->post(route('part-numbers.import'), []);

        $response->assertSessionHasErrors('file');
    }
}

