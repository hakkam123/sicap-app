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
        Schema::create('machine_part_number', function (Blueprint $table) {
            $table->foreignUlid('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->foreignUlid('part_number_id')->constrained('part_numbers')->cascadeOnDelete();
            $table->primary(['machine_id', 'part_number_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machine_part_number');
    }
};

