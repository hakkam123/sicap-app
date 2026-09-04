<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('area_part_number', function (Blueprint $table) {
            $table->foreignUlid('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignUlid('part_number_id')->constrained('part_numbers')->cascadeOnDelete();
            $table->primary(['area_id', 'part_number_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('area_part_number');
    }
};

