<?php

namespace Tests\Unit\Models;

use App\Models\SyncSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_schedule_creation_with_attributes(): void
    {
        $schedule = SyncSchedule::create([
            'time' => '07:30',
            'is_active' => true,
        ]);

        $this->assertNotEmpty($schedule->id);
        $this->assertTrue(Str::isUlid($schedule->id));
        $this->assertEquals('07:30', $schedule->time);
        $this->assertTrue($schedule->is_active);
    }

    public function test_sync_schedule_is_active_boolean_cast(): void
    {
        $schedule = SyncSchedule::create([
            'time' => '12:00',
            'is_active' => 1,
        ]);

        $this->assertIsBool($schedule->is_active);
        $this->assertTrue($schedule->is_active);

        $schedule->update(['is_active' => 0]);
        $this->assertFalse($schedule->fresh()->is_active);
    }

    public function test_sync_schedule_factory_states(): void
    {
        $active = SyncSchedule::factory()->active()->create();
        $inactive = SyncSchedule::factory()->inactive()->create();

        $this->assertTrue($active->is_active);
        $this->assertFalse($inactive->is_active);
    }

    public function test_sync_schedule_query_filtering_active(): void
    {
        SyncSchedule::truncate();

        SyncSchedule::factory()->active()->create(['time' => '08:00']);
        SyncSchedule::factory()->active()->create(['time' => '13:00']);
        SyncSchedule::factory()->inactive()->create(['time' => '18:00']);

        $activeSchedules = SyncSchedule::where('is_active', true)->get();
        $this->assertCount(2, $activeSchedules);
    }

    public function test_sync_schedule_update_time(): void
    {
        $schedule = SyncSchedule::factory()->create(['time' => '06:00']);
        $schedule->update(['time' => '06:30']);

        $this->assertEquals('06:30', $schedule->fresh()->time);
    }
}
