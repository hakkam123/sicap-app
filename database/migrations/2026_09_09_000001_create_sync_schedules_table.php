<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('time', 5); // Format: HH:MM (e.g. 05:00)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert initial default schedule times
        $now = now();
        DB::table('sync_schedules')->insert([
            ['id' => (string) \Illuminate\Support\Str::ulid(), 'time' => '05:00', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => (string) \Illuminate\Support\Str::ulid(), 'time' => '11:00', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => (string) \Illuminate\Support\Str::ulid(), 'time' => '17:00', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_schedules');
    }
};

