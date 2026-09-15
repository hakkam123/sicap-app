<?php

namespace Tests\Unit\Models;

use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_log_creation_and_attributes(): void
    {
        $user = User::factory()->create();
        $log = ImportLog::create([
            'feature' => 'consume',
            'filename' => 'consume_data_2026.xlsx',
            'status' => 'pending',
            'total_rows' => 100,
            'processed_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
            'user_id' => $user->id,
        ]);

        $this->assertNotEmpty($log->id);
        $this->assertTrue(Str::isUlid($log->id));
        $this->assertEquals('pending', $log->status);
        $this->assertSame(100, $log->total_rows);
        $this->assertEquals($user->id, $log->user_id);
    }

    public function test_import_log_feature_label_accessor(): void
    {
        $logConsume = ImportLog::factory()->make(['feature' => 'consume']);
        $logPart = ImportLog::factory()->make(['feature' => 'part_number']);
        $logMapping = ImportLog::factory()->make(['feature' => 'mapping']);
        $logArea = ImportLog::factory()->make(['feature' => 'area']);
        $logMachine = ImportLog::factory()->make(['feature' => 'machine']);

        $this->assertEquals('Consume', $logConsume->feature_label);
        $this->assertEquals('Part Number', $logPart->feature_label);
        $this->assertEquals('Mapping Part', $logMapping->feature_label);
        $this->assertEquals('Area', $logArea->feature_label);
        $this->assertEquals('Machine', $logMachine->feature_label);
    }

    public function test_import_log_progress_percentage_calculation(): void
    {
        $log = ImportLog::factory()->make([
            'total_rows' => 200,
            'processed_rows' => 100,
            'status' => 'processing',
        ]);
        $this->assertSame(50, $log->progress_percentage);

        $logComplete = ImportLog::factory()->make([
            'total_rows' => 50,
            'processed_rows' => 50,
            'status' => 'success',
        ]);
        $this->assertSame(100, $logComplete->progress_percentage);

        $logZero = ImportLog::factory()->make([
            'total_rows' => 0,
            'processed_rows' => 0,
            'status' => 'pending',
        ]);
        $this->assertSame(0, $logZero->progress_percentage);
    }

    public function test_import_log_status_transitions_and_error_details_array_cast(): void
    {
        $user = User::factory()->create();
        $log = ImportLog::factory()->pending()->create(['user_id' => $user->id]);
        $this->assertEquals('pending', $log->status);

        // Transition to processing
        $log->update([
            'status' => 'processing',
            'started_at' => now(),
            'total_rows' => 10,
            'processed_rows' => 5,
        ]);
        $this->assertEquals('processing', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->started_at);

        // Transition to failed
        $errors = [
            ['row' => 2, 'field' => 'part_number', 'message' => 'Not found in master'],
            ['row' => 3, 'field' => 'amount', 'message' => 'Invalid amount'],
        ];
        $log->update([
            'status' => 'failed',
            'error_message' => 'Import encountered errors',
            'error_details' => $errors,
            'failed_rows' => 2,
            'success_rows' => 3,
            'finished_at' => now(),
        ]);

        $fresh = $log->fresh();
        $this->assertEquals('failed', $fresh->status);
        $this->assertIsArray($fresh->error_details);
        $this->assertCount(2, $fresh->error_details);
        $this->assertEquals('Not found in master', $fresh->error_details[0]['message']);
    }

    public function test_import_log_belongs_to_user_including_soft_deleted(): void
    {
        $user = User::factory()->create(['name' => 'Operator']);
        $log = ImportLog::factory()->create(['user_id' => $user->id]);

        $user->delete();
        $log->refresh();

        $this->assertNotNull($log->user);
        $this->assertEquals('Operator', $log->user->name);
    }
}

